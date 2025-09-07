<?php

namespace OVAC\IDoc\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * iDoc AI Chat Controller
 *
 * Purpose
 * - Provides a provider-agnostic chat endpoint that can answer questions about
 *   your API using the generated OpenAPI document (and optional extra context),
 *   and returns structured metadata used by the docs UI.
 *
 * Key capabilities
 * - Providers: DeepSeek, OpenAI, Google Gemini, Groq, Hugging Face
 *   Inference API, Together, and OpenAI-compatible servers.
 * - Streaming: Supports server-sent events (SSE) for OpenAI-compatible providers.
 * - Attachments: Accepts text/json/image/url attachments. Text/JSON are injected
 *   into the system context (first 4000 chars). Images are accepted when the
 *   selected provider/model supports vision and are attached to the user turn on
 *   OpenAI-compatible providers. Else they are dropped with meta warnings.
 * - Edit & Resend: When `replaces_message_id` is present, the replaced user
 *   message and its immediate assistant reply are pruned from prompt history.
 * - Action hints: When the assistant content contains a heading such as
 *   "### METHOD /path", an `actionHints` object is returned under `data.meta`:
 *     { endpoint: { method, path, anchor? },
 *       tryIt: { method, path, prefill: { pathParams?, query?, headers?, body? }, autoExecute: true } }
 *   The UI uses these hints to render “Open endpoint” and “Try it with this data”.
 *
 * Configuration (config/idoc.php)
 * - idoc.chat.enabled   bool   Enable/disable the endpoint (default: false)
 * - idoc.chat.provider  string Provider id
 * - idoc.chat.model     string Model id
 * - idoc.chat.base_url  string Base URL override (for OpenAI-compatible servers)
 * - idoc.chat.info_view string Optional Blade view rendered into system context
 * - idoc.chat.api_key_env string Optional env var name for provider key
 *
 * Route
 * - POST /{idoc.path}/chat (name: idoc.chat). Loaded when enabled.
 *
 * Responses (JSON or SSE)
 * - 403: Feature disabled
 * - 500: Chat provider error (misconfig, network, etc.)
 * - 200: { status: 'success', message: 'ok', data: { reply: string, meta: { warnings?: string[], actionHints?: object } } }
 */
class ChatController extends Controller
{
    /**
     * Accepts a user prompt and returns a model-generated reply.
     *
     * Flow:
     * - Validates input (message), checks feature flag and API key.
     * - Builds assistant context (OpenAPI JSON + relevant ops + optional view).
     * - Calls OpenAI Chat Completions and relays the assistant's content.
     *
     * @param  Request  $request JSON body: { message: string }
     * @return \Illuminate\Http\JsonResponse
     */
    public function chat(Request $request)
    {
        if (!config('idoc.chat.enabled', false)) {
            return response()->json([
                'status' => 'error',
                'message' => 'iDoc chat is disabled.',
            ], 403);
        }

        $data = $request->validate([
            'message' => 'required|string',
            'history' => 'sometimes|array',
            'history.*.role' => 'required_with:history|string|in:user,assistant',
            'history.*.content' => 'required_with:history|string',
            'stream' => 'sometimes|boolean',
            'history.*.id' => 'sometimes|string',
            'replaces_message_id' => 'sometimes|string',
            'attachments' => 'sometimes|array',
            'attachments.*.name' => 'sometimes|string',
            'attachments.*.type' => 'sometimes|string|in:text,json,image,url',
            'attachments.*.mime' => 'sometimes|string',
            'attachments.*.content' => 'sometimes|string',
            'attachments.*.url' => 'sometimes|string',
            'attachments.*.bytes' => 'sometimes|integer',
            'temperature' => 'sometimes|numeric|min:0|max:1',
            'max_tokens' => 'sometimes|integer|min:1|max:4096',
            'system_override' => 'sometimes|string',
        ]);

        $apiKey = $this->resolveApiKey();
        if (!$apiKey) {
            return response()->json($this->missingKeyPayload(), 200);
        }

        $ctx = $this->buildContext();

        // System prompt (loaded from configurable Markdown file with a safe fallback)
        $system = $this->getSystemPrompt();
        if (!empty($data['system_override'])) {
            $system = (string) $data['system_override'];
        }
        if (!empty($data['system_override'])) {
            $system = (string) $data['system_override'];
        }

        // Compose messages in Chat Completions format
        $messages = [ ['role' => 'system', 'content' => $system] ];

        if (!empty($ctx['openapi_json'])) {
            $messages[] = ['role' => 'system', 'content' => "OpenAPI JSON:\n".$ctx['openapi_json']];
        }

        if (!empty($ctx['spec'])) {
            if ($ops = $this->selectRelevantOperations($ctx['spec'], $data['message'])) {
                $messages[] = ['role' => 'system', 'content' => "Matched operations (from OpenAPI):\n".$ops];
            }
            // Provide explicit headings the model should include at the top
            if ($cands = $this->headingCandidatesForQuery($ctx['spec'], (string) ($data['message'] ?? ''), 3)) {
                $lines = implode("\n", $cands);
                $messages[] = ['role' => 'system', 'content' => "Begin your reply with one or more of these headings, exactly as written (one per relevant endpoint):\n".$lines];
            }
        }

        if (!empty($ctx['info_full'])) {
            $messages[] = ['role' => 'system', 'content' => "Docs info:\n".$ctx['info_full']];
        }

        // Append prior conversation (optional), with edit replace support
        $history = [];
        if (!empty($data['history']) && is_array($data['history'])) {
            // Keep the most recent 12 turns to control prompt size
            $history = array_slice($data['history'], -12);
            if (!empty($data['replaces_message_id'])) {
                $history = $this->pruneEditedHistory($history, (string) $data['replaces_message_id']);
                try {
                    Log::info('IdocChat: edit-resend applied', ['replaces_message_id' => (string) $data['replaces_message_id']]);
                } catch (\Throwable $e) {
                }
            }
            foreach ($history as $m) {
                $r = $m['role'] ?? null;
                $c = $m['content'] ?? '';
                if (($r === 'user' || $r === 'assistant') && $c !== '') {
                    $messages[] = [ 'role' => $r, 'content' => (string) $c ];
                }
            }
        }
        // Attachment system messages will be inserted before the new user message

        try {
            $provider = strtolower((string) (config('idoc.chat.provider', env('IDOC_CHAT_PROVIDER', 'openai'))));
            $model = (string) (config('idoc.chat.model', env('IDOC_CHAT_MODEL', 'gpt-4o-mini')));
            $temperature = is_numeric($data['temperature'] ?? null) ? (float) $data['temperature'] : 0.2;
            $maxTokens = is_numeric($data['max_tokens'] ?? null) ? (int) $data['max_tokens'] : 600;
            $wantStream = (bool) ($data['stream'] ?? true);

            $baseUrl = rtrim((string) (config('idoc.chat.base_url') ?: $this->defaultBaseUrl($provider)), '/').'/';
            $headers = [ 'Content-Type: application/json' ];
            if (!in_array($provider, ['google'], true)) {
                $headers[] = 'Authorization: Bearer '.$apiKey;
            }

            $meta = [ 'warnings' => [] ];
            if (!empty($data['attachments']) && is_array($data['attachments'])) {
                $vision = $this->supportsVision($provider, $model);
                $images = [];
                foreach ($data['attachments'] as $att) {
                    $type = strtolower((string) ($att['type'] ?? 'text'));
                    $name = (string) ($att['name'] ?? $type);
                    if (in_array($type, ['text','json'], true)) {
                        $content = (string) ($att['content'] ?? '');
                        if ($content !== '') {
                            $snippet = mb_substr($content, 0, 4000);
                            $messages[] = ['role' => 'system', 'content' => "[Attachment: {$name}] First 4000 chars below:

".$snippet];
                        }
                    } elseif ($type === 'image') {
                        if ($vision) {
                            $images[] = $att;
                        } else {
                            $meta['warnings'][] = "Dropped image attachment '".$name."' because the current model does not support images.";
                        }
                    } elseif ($type === 'url') {
                        $url = (string) ($att['url'] ?? '');
                        if ($url) {
                            $messages[] = ['role' => 'system', 'content' => "Attachment URL ({$name}): 
".$url];
                        }
                    }
                }
            }
            $userIndex = count($messages);
            $messages[] = ['role' => 'user', 'content' => $data['message']];
            if (!empty($images)) {
                if ($provider !== 'huggingface' && $provider !== 'google') {
                    $parts = [['type' => 'text', 'text' => (string) $messages[$userIndex]['content'] ]];
                    foreach ($images as $img) {
                        $url = (string) ($img['url'] ?? '');
                        if ($url) {
                            $parts[] = ['type' => 'input_image', 'image_url' => ['url' => $url]];
                        }
                    }
                    $messages[$userIndex]['content'] = $parts;
                }
            }
            $endpoint = '';
            $bodyArr = [];

            if (in_array($provider, ['openai','groq','openai_compat','deepseek'], true)) {
                $endpoint = $baseUrl.'chat/completions';
                $bodyArr = [ 'model' => $model, 'messages' => $messages, 'temperature' => $temperature, 'max_tokens' => $maxTokens, 'stream' => $wantStream ];
            } elseif ($provider === 'huggingface') {
                $endpoint = rtrim($baseUrl, '/').'/models/'.rawurlencode($model);
                $prompt = $this->messagesToPrompt($messages);
                $bodyArr = [ 'inputs' => $prompt, 'parameters' => [ 'max_new_tokens' => $maxTokens, 'temperature' => $temperature, 'return_full_text' => false ] ];
            } elseif ($provider === 'google') {
                // Google Gemini GenerateContent API
                $base = rtrim($baseUrl, '/');
                $endpoint = $base.'/models/'.rawurlencode($model).':generateContent?key='.urlencode($apiKey);
                [$systemInstruction, $contents] = $this->toGoogleContents($messages);
                $bodyArr = [ 'contents' => $contents, 'generationConfig' => [ 'temperature' => $temperature, 'maxOutputTokens' => $maxTokens ] ];
                if ($systemInstruction) {
                    $bodyArr['systemInstruction'] = [ 'parts' => [ ['text' => $systemInstruction] ] ];
                }
            } else {
                throw new \RuntimeException('Unsupported provider: '.$provider);
            }



            // Stream for OpenAI‑compatible when requested
            if ($wantStream && in_array($provider, ['openai','groq','openai_compat','deepseek'], true)) {
                $headersOut = [
                    'Content-Type' => 'text/event-stream',
                    'Cache-Control' => 'no-cache',
                    'Connection' => 'keep-alive',
                ];
                return response()->stream(function () use ($endpoint, $headers, $payload) {
                    $ch = curl_init($endpoint);
                    curl_setopt_array($ch, [
                        CURLOPT_POST => true,
                        CURLOPT_HTTPHEADER => $headers,
                        CURLOPT_POSTFIELDS => $payload,
                        CURLOPT_WRITEFUNCTION => function ($ch, $chunk) {
                            echo $chunk;
                            @ob_flush();
                            flush();
                            return strlen($chunk);
                        },
                        CURLOPT_TIMEOUT => 0,
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                }, 200, $headersOut);
            }
            $payload = json_encode($bodyArr, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // Stream for OpenAI‑compatible when requested
            if ($wantStream && in_array($provider, ['openai','groq','openai_compat','deepseek'], true)) {
                $headersOut = [
                    'Content-Type' => 'text/event-stream',
                    'Cache-Control' => 'no-cache',
                    'Connection' => 'keep-alive',
                ];
                return response()->stream(function () use ($endpoint, $headers, $payload) {
                    $ch = curl_init($endpoint);
                    curl_setopt_array($ch, [
                        CURLOPT_POST => true,
                        CURLOPT_HTTPHEADER => $headers,
                        CURLOPT_POSTFIELDS => $payload,
                        CURLOPT_WRITEFUNCTION => function ($ch, $chunk) {
                            // Pass-through provider SSE to client
                            echo $chunk;
                            @ob_flush();
                            flush();
                            return strlen($chunk);
                        },
                        CURLOPT_TIMEOUT => 0,
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                }, 200, $headersOut);
            }

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 45,
            ]);
            $respBody = curl_exec($ch);
            $err = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($err) {
                throw new \RuntimeException($err);
            }

            $json = json_decode((string) $respBody, true);
            if ($status >= 400) {
                $msg = $json['error']['message'] ?? ($json['error'] ?? ('HTTP '.$status));
                throw new \RuntimeException($msg);
            }

            $answer = '';
            if (in_array($provider, ['openai','groq','openai_compat','deepseek'], true)) {
                $answer = trim($json['choices'][0]['message']['content'] ?? '');
            } elseif ($provider === 'huggingface') {
                if (is_array($json)) {
                    $first = $json[0] ?? [];
                    $answer = trim((string) ($first['generated_text'] ?? ''));
                    if (!$answer && isset($json['generated_text'])) {
                        $answer = trim((string) $json['generated_text']);
                    }
                }
            } elseif ($provider === 'google') {
                // Extract from candidates[0].content.parts[].text
                $cand = $json['candidates'][0]['content']['parts'] ?? [];
                $texts = [];
                foreach ($cand as $part) {
                    if (!empty($part['text'])) {
                        $texts[] = (string) $part['text'];
                    }
                }
                $answer = trim(implode("\n", $texts));
            }
            // Build actionHints from assistant answer and last user input
            $actionHints = $this->buildActionHints($answer, $data['message'] ?? '', $data['attachments'] ?? [], $ctx['spec'] ?? null);
            if (!empty($actionHints)) {
                $meta['actionHints'] = $actionHints;
            }

            return response()->json([
                'status' => 'success',
                'message' => 'ok',
                'data' => [ 'reply' => $answer, 'meta' => $meta ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('IdocChat error', ['e' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Chat provider error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build assistant context from available local artifacts.
     *
     * Sources:
     * - OpenAPI JSON at public_path(config('idoc.output'))/openapi.json
     * - Optional Blade view (idoc.chat.info_view) rendered to plain text
     *
     * @return array{openapi_json:(string|null),spec:(array|null),info_full:(string|null)}
     */
    protected function buildContext(): array
    {
        $out = [ 'openapi_json' => null, 'spec' => null, 'info_full' => null ];

        // Load the single OpenAPI JSON that iDoc already generates
        try {
            $output = trim(config('idoc.output', '/docs'), '/');
            $rel = $output ? '/'.$output : '';
            $path = public_path($rel.'/openapi.json');
            if (is_file($path)) {
                $json = (string) file_get_contents($path);
                $spec = json_decode($json, true);
                if (is_array($spec)) {
                    $out['spec'] = $spec;
                    $out['openapi_json'] = $json;
                }
            }
        } catch (\Throwable $e) {
        }

        // Optional: render an info view into text for the model
        try {
            $view = config('idoc.chat.info_view'); // eg. 'idoc.info'
            if ($view && view()->exists($view)) {
                $out['info_full'] = strip_tags((string) view($view)->render());
            }
        } catch (\Throwable $e) {
        }

        return $out;
    }

    /**
     * Pick a few operations that loosely match the question.
     *
     * Strategy:
     * - Token-overlap scoring on method/path/tags/summary/description.
     * - Return a Markdown snippet for the top 3 operations.
     *
     * @param  array  $spec   Decoded OpenAPI document
     * @param  string $query  User's question
     * @return string|null    Markdown or null when no matches found
     */
    protected function selectRelevantOperations(array $spec, string $query): ?string
    {
        if (!$query) {
            return null;
        } $ops = [];
        foreach (($spec['paths'] ?? []) as $path => $methods) {
            foreach ($methods as $method => $op) {
                if (!is_array($op)) {
                    continue;
                }
                $summary = (string) ($op['summary'] ?? '');
                $desc    = (string) ($op['description'] ?? '');
                $tags    = array_map('strval', $op['tags'] ?? []);
                $params  = $op['parameters'] ?? [];
                $rb      = $op['requestBody']['content']['application/json']['schema'] ?? null;
                $hay = strtoupper($method.' '.$path.' '.implode(' ', $tags).' '.$summary.' '.$desc);
                $score = $this->scoreQuery($hay, $query);
                if ($score <= 0) {
                    continue;
                }
                $ops[] = [ 'score' => $score,'method' => strtoupper($method),'path' => $path,'summary' => $summary,'tags' => $tags,'params' => $params,'body' => $rb ];
            }
        }
        if (!$ops) {
            return null;
        } usort($ops, fn ($a, $b) => $b['score'] <=> $a['score']);
        $ops = array_slice($ops, 0, 3);
        $md = [];
        foreach ($ops as $op) {
            $md[] = $this->renderOperation($op);
        }
        return implode("\n\n---\n\n", $md);
    }

    /**
     * Tiny token-overlap relevance score for a haystack string.
     *
     * Heuristic:
     * - +2 if token appears in UPPERCASE hay (bias on method/path/tags)
     * - +1 if token appears in lowercase hay
     *
     * @param  string $hay Normalized operation text
     * @param  string $q   User query
     * @return int         Non-negative score
     */
    protected function scoreQuery(string $hay, string $q): int
    {
        $q = strtolower($q);
        $tok = preg_split('/\W+/', $q, -1, PREG_SPLIT_NO_EMPTY);
        $score = 0;
        foreach ($tok as $t) {
            if (str_contains($hay, strtoupper($t))) {
                $score += 2;
            } if (str_contains(strtolower($hay), $t)) {
                $score += 1;
            }
        }
        return $score;
    }

    /**
     * Render a compact Markdown description for an operation.
     *
     * Sections:
     * - H3 title with METHOD and path
     * - Optional summary and tags
     * - Path/query/header parameters
     * - JSON body fields (when defined)
     *
     * @param  array $op  Operation fields (method, path, summary, tags, params, body)
     * @return string
     */
    protected function renderOperation(array $op): string
    {
        $out = [];
        $out[] = sprintf('### %s %s', $op['method'], $op['path']);
        if (!empty($op['summary'])) {
            $out[] = '_'.$op['summary'].'_';
        }
        if (!empty($op['tags'])) {
            $out[] = 'Tags: `'.implode('`, `', $op['tags']).'`';
        }
        if (!empty($op['params'])) {
            $byIn = [];
            foreach ($op['params'] as $p) {
                $byIn[$p['in'] ?? 'other'][] = $p;
            }
            foreach (['path','query','header'] as $group) {
                if (empty($byIn[$group])) {
                    continue;
                } $out[] = '**'.ucfirst($group).' Params:**';
                foreach ($byIn[$group] as $p) {
                    $req = !empty($p['required']) ? 'required' : 'optional';
                    $name = $p['name'] ?? '';
                    $schemaType = $p['schema']['type'] ?? '';
                    $desc = trim((string) ($p['description'] ?? ''));
                    $out[] = sprintf('- `%s` (%s, %s) %s%s', $name, $group, $req, $schemaType ? "type: $schemaType" : '', $desc ? " - $desc" : '');
                }
            }
        }
        if (!empty($op['body'])) {
            $schema = $op['body'];
            $fields = [];
            $required = $schema['required'] ?? [];
            $props = $schema['properties'] ?? [];
            foreach ($props as $name => $def) {
                $t = $def['type'] ?? '';
                $d = $def['description'] ?? '';
                $req = in_array($name, $required, true) ? 'required' : 'optional';
                $fields[] = sprintf('- `%s` (%s) %s%s', $name, $req, $t ? "type: $t" : '', $d ? " - $d" : '');
            }
            if ($fields) {
                $out[] = '**Body Fields:**';
                $out = array_merge($out, $fields);
            }
        }
        return implode("\n", $out);
    }

    /**
     * Build '### METHOD /path' heading candidates for the current query.
     * Returns up to $max lines the model should include at the start.
     */
    protected function headingCandidatesForQuery(array $spec, string $query, int $max = 3): array
    {
        if (!$query) {
            return [];
        }
        $ops = [];
        foreach (($spec['paths'] ?? []) as $path => $methods) {
            foreach ($methods as $method => $op) {
                if (!is_array($op)) {
                    continue;
                }
                $summary = (string) ($op['summary'] ?? '');
                $desc    = (string) ($op['description'] ?? '');
                $tags    = array_map('strval', $op['tags'] ?? []);
                $hay = strtoupper($method.' '.$path.' '.implode(' ', $tags).' '.$summary.' '.$desc);
                $score = $this->scoreQuery($hay, $query);
                if ($score <= 0) {
                    continue;
                }
                $ops[] = [ 'score' => $score, 'method' => strtoupper($method), 'path' => $path ];
            }
        }
        if (!$ops) {
            return [];
        }
        usort($ops, fn ($a, $b) => $b['score'] <=> $a['score']);
        $ops = array_slice($ops, 0, max(1, $max));
        $out = [];
        foreach ($ops as $op) {
            $out[] = sprintf('### %s %s', $op['method'], $op['path']);
        }
        return $out;
    }

    protected function missingKeyPayload(): array
    {
        $current = (string) config('idoc.chat.provider', env('IDOC_CHAT_PROVIDER', 'openai'));
        $providers = [
            [
                'id' => 'google',
                'title' => 'Google Gemini',
                'keys_url' => 'https://aistudio.google.com/app/apikey',
                'docs_url' => 'https://ai.google.dev/gemini-api/docs',
                'default_model' => 'gemini-1.5-flash',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'env_lines' => [
                    'IDOC_CHAT_ENABLED=true',
                    'IDOC_CHAT_PROVIDER=google',
                    'IDOC_CHAT_MODEL=gemini-1.5-flash',
                    'IDOC_CHAT_BASE_URL=https://generativelanguage.googleapis.com/v1beta',
                    'GOOGLE_API_KEY=your_google_api_key',
                ],
            ],
            [
                'id' => 'deepseek',
                'title' => 'DeepSeek (OpenAI‑compatible)',
                'keys_url' => 'https://platform.deepseek.com',
                'docs_url' => 'https://api.deepseek.com',
                'default_model' => 'deepseek-chat',
                'base_url' => 'https://api.deepseek.com/v1',
                'env_lines' => [
                    'IDOC_CHAT_ENABLED=true',
                    'IDOC_CHAT_PROVIDER=deepseek',
                    'IDOC_CHAT_MODEL=deepseek-chat',
                    'IDOC_CHAT_BASE_URL=https://api.deepseek.com/v1',
                    'DEEPSEEK_API_KEY=your_deepseek_key',
                ],
            ],
            [
                'id' => 'oss_local',
                'title' => 'Free/Open Source (Self‑hosted)',
                'keys_url' => 'https://github.com/abetlen/llama-cpp-python#server',
                'docs_url' => 'https://github.com/abetlen/llama-cpp-python#server',
                'default_model' => 'qwen2.5-7b-instruct',
                'base_url' => 'http://127.0.0.1:8000/v1',
                'env_lines' => [
                    'IDOC_CHAT_ENABLED=true',
                    'IDOC_CHAT_PROVIDER=openai',
                    'IDOC_CHAT_BASE_URL=http://127.0.0.1:8000/v1',
                    'IDOC_CHAT_MODEL=qwen2.5-7b-instruct',
                    'IDOC_CHAT_API_KEY=localdev',
                ],
            ],
            [
                'id' => 'openai',
                'title' => 'OpenAI (ChatGPT)',
                'keys_url' => 'https://platform.openai.com/account/api-keys',
                'docs_url' => 'https://platform.openai.com/docs/api-reference/chat',
                'default_model' => 'gpt-4o-mini',
                'base_url' => 'https://api.openai.com/v1',
                'env_lines' => [
                    'IDOC_CHAT_ENABLED=true',
                    'IDOC_CHAT_PROVIDER=openai',
                    'IDOC_CHAT_MODEL=gpt-4o-mini',
                    'IDOC_CHAT_BASE_URL=https://api.openai.com/v1',
                    'OPENAI_API_KEY=your_openai_key',
                ],
            ],
            [
                'id' => 'groq',
                'title' => 'Groq',
                'keys_url' => 'https://console.groq.com/keys',
                'docs_url' => 'https://console.groq.com/docs',
                'default_model' => 'mixtral-8x7b-32768',
                'base_url' => 'https://api.groq.com/openai/v1',
                'env_lines' => [
                    'IDOC_CHAT_ENABLED=true',
                    'IDOC_CHAT_PROVIDER=groq',
                    'IDOC_CHAT_MODEL=mixtral-8x7b-32768',
                    'IDOC_CHAT_BASE_URL=https://api.groq.com/openai/v1',
                    'GROQ_API_KEY=your_groq_key',
                ],
            ],
            [
                'id' => 'huggingface',
                'title' => 'Hugging Face Inference API',
                'keys_url' => 'https://huggingface.co/settings/tokens',
                'docs_url' => 'https://huggingface.co/docs/api-inference/index',
                'default_model' => 'Qwen/Qwen2.5-7B-Instruct',
                'base_url' => 'https://api-inference.huggingface.co',
                'env_lines' => [
                    'IDOC_CHAT_ENABLED=true',
                    'IDOC_CHAT_PROVIDER=huggingface',
                    'IDOC_CHAT_MODEL=Qwen/Qwen2.5-7B-Instruct',
                    'IDOC_CHAT_BASE_URL=https://api-inference.huggingface.co',
                    'HF_API_TOKEN=your_hf_token',
                ],
            ],
            [
                'id' => 'together',
                'title' => 'Together AI',
                'keys_url' => 'https://api.together.ai',
                'docs_url' => 'https://docs.together.ai/docs/intro',
                'default_model' => 'Qwen/Qwen2.5-7B-Instruct',
                'base_url' => 'https://api.together.xyz/v1',
                'env_lines' => [
                    'IDOC_CHAT_ENABLED=true',
                    'IDOC_CHAT_PROVIDER=openai',
                    'IDOC_CHAT_BASE_URL=https://api.together.xyz/v1',
                    'IDOC_CHAT_MODEL=Qwen/Qwen2.5-7B-Instruct',
                    'TOGETHER_API_KEY=your_together_key',
                ],
            ],
        ];

        return [
            'status' => 'error',
            'reason' => 'missing_api_key',
            'message' => 'Chat API key not configured in environment. Choose a provider to view setup instructions.',
            'data' => [
                'current_provider' => $current,
                'providers' => $providers,
                'post_setup_note' => 'Run: php artisan config:clear && php artisan cache:clear',
            ],
        ];
    }

    /** Resolve API key using config/env fallbacks. */
    protected function resolveApiKey(): ?string
    {
        $provider = strtolower((string) (config('idoc.chat.provider', env('IDOC_CHAT_PROVIDER', 'openai'))));
        $envKey = config('idoc.chat.api_key_env');
        if ($envKey && ($v = env($envKey))) {
            return $v;
        }
        if ($v = env('IDOC_CHAT_API_KEY')) {
            return $v;
        }
        if ($provider === 'groq') {
            return env('GROQ_API_KEY') ?: env('OPENAI_API_KEY');
        }
        if ($provider === 'deepseek') {
            return env('DEEPSEEK_API_KEY') ?: env('OPENAI_API_KEY');
        }
        if ($provider === 'google') {
            return env('GOOGLE_API_KEY') ?: env('GEMINI_API_KEY');
        }
        if ($provider === 'huggingface') {
            return env('HUGGINGFACE_API_KEY') ?: env('HF_API_TOKEN');
        }
        return env('OPENAI_API_KEY');
    }

    /** Default base URL per provider. */
    protected function defaultBaseUrl(string $provider): string
    {
        return match (strtolower($provider)) {
            'groq' => 'https://api.groq.com/openai/v1',
            'deepseek' => 'https://api.deepseek.com/v1',
            'google' => 'https://generativelanguage.googleapis.com/v1beta',
            'huggingface' => 'https://api-inference.huggingface.co',
            default => 'https://api.openai.com/v1',
        };
    }

    /** Convert OpenAI messages into Google Gemini contents and systemInstruction. */
    protected function toGoogleContents(array $messages): array
    {
        $system = null;
        $contents = [];
        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';
            $text = (string) ($m['content'] ?? '');
            if ($text === '') {
                continue;
            }
            if ($role === 'system') {
                $system = trim(($system ? $system."\n\n" : '').$text);
                continue;
            }
            $gRole = $role === 'assistant' ? 'model' : 'user';
            $contents[] = [ 'role' => $gRole, 'parts' => [ ['text' => $text] ] ];
        }
        return [$system, $contents];
    }

    /** Convert Chat Completions messages to a flat prompt string for HF. */
    protected function messagesToPrompt(array $messages): string
    {
        $lines = [];
        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';
            $content = trim((string) ($m['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            if ($role === 'system') {
                $lines[] = "System: ".$content;
            } elseif ($role === 'assistant') {
                $lines[] = "Assistant: ".$content;
            } else {
                $lines[] = "User: ".$content;
            }
        }
        $lines[] = 'Assistant:';
        return implode("\n\n", $lines);
    }

    /**
     * Load the default system prompt from a Markdown file.
     * Order: configured path → bundled default → hardcoded fallback.
     */
    protected function getSystemPrompt(): string
    {
        $maxBytes = 200 * 1024; // 200 KiB safety limit
        $path = (string) (config('idoc.chat.system_prompt_md') ?: '');
        $contents = '';
        if ($path) {
            try {
                $real = realpath($path);
                if ($real && is_file($real) && is_readable($real)) {
                    $raw = file_get_contents($real, false, null, 0, $maxBytes);
                    if (is_string($raw)) {
                        $contents = $this->sanitizePrompt($raw);
                    }
                }
            } catch (\Throwable $e) { /* ignore and fall back */
            }
        }
        if ($contents === '') {
            // Prefer a published prompt if present: resources/vendor/idoc/prompts/chat-system.md
            try {
                $published = realpath(base_path('resources/vendor/idoc/prompts/chat-system.md')) ?: base_path('resources/vendor/idoc/prompts/chat-system.md');
                if ($published && is_file($published) && is_readable($published)) {
                    $raw = file_get_contents($published, false, null, 0, $maxBytes);
                    if (is_string($raw)) {
                        $contents = $this->sanitizePrompt($raw);
                    }
                }
            } catch (\Throwable $e) { /* ignore and fall back to package default */
            }
        }
        if ($contents === '') {
            try {
                $default = realpath(__DIR__.'/../../../../resources/prompts/chat-system.md');
                if ($default && is_file($default) && is_readable($default)) {
                    $raw = file_get_contents($default, false, null, 0, $maxBytes);
                    if (is_string($raw)) {
                        $contents = $this->sanitizePrompt($raw);
                    }
                }
            } catch (\Throwable $e) { /* ignore */
            }
        }
        if ($contents === '') {
            $contents = "You are an API assistant for this project. Answer concisely and only with information supported by the current documentation. If unsure, say so briefly.\n\nStrict formatting rules:\n- Start your answer with one or more markdown H3 headings in the exact form: '### METHOD /path'.\n- Do not put any text before the first '### METHOD /path' heading.\n- For POST/PUT/PATCH, include a minimal JSON body example in a fenced code block:```json\n{ }\n```\n- For GET, show the relevant query parameters or a sample URL.\n- Use regular markdown headings and do not escape the hashes.\n";
        }
        return $contents;
    }

    protected function sanitizePrompt(string $raw): string
    {
        $raw = str_replace("\0", '', $raw);
        return trim($raw);
    }

    /** Extract action hints from assistant content and the last user message. */
    protected function buildActionHints(string $assistant, string $userText, array $attachments, ?array $spec): array
    {
        $hint = [];
        // Detect first ###/#### METHOD /path heading
        $method = null;
        $path = null;
        if (preg_match('/^#{3,4}\s+(GET|POST|PUT|PATCH|DELETE)\s+(\/[^\s#]+)/mi', $assistant, $m)) {
            $method = strtoupper($m[1]);
            $path = $m[2];
        }
        if ($method && $path) {
            $endpoint = [ 'method' => $method, 'path' => $path ];
            $anchor = $this->resolveDocsAnchor($spec, $method, $path);
            if ($anchor) {
                $endpoint['anchor'] = $anchor;
            }
            $hint['endpoint'] = $endpoint;

            // Build Try-it prefill from user text and attachments
            $prefill = $this->buildTryItPrefill($method, $path, $userText, $attachments);
            $hint['tryIt'] = [
                'method' => $method,
                'path' => $path,
                'prefill' => $prefill,
                'autoExecute' => true,
            ];
        }
        return $hint;
    }

    /** Resolve a docs anchor (operation/ID) from spec if possible. */
    protected function resolveDocsAnchor(?array $spec, string $method, string $path): ?string
    {
        try {
            if (!$spec || empty($spec['paths'])) {
                return null;
            }
            $m = strtolower($method);
            if (!isset($spec['paths'][$path][$m])) {
                return null;
            }
            $op = $spec['paths'][$path][$m];
            $opId = $op['operationId'] ?? null;
            return $opId ? ('operation/'.$opId) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Build a best-effort prefill object from user text and attachments. */
    protected function buildTryItPrefill(string $method, string $path, string $userText, array $attachments): array
    {
        $prefill = [ 'pathParams' => [], 'query' => [], 'headers' => [], 'body' => null ];

        // 1) fenced ```json ... ```
        $body = null;
        if (preg_match('/```\s*json\s*([\s\S]*?)```/i', $userText, $m)) {
            $body = $this->tryJsonDecode(trim($m[1]));
        }
        // 2) any JSON object
        if ($body === null) {
            $first = $this->extractFirstJsonObject($userText);
            if ($first !== null) {
                $body = $first;
            }
        }
        // 3) key:value pairs lines
        $kv = [];
        if ($body === null) {
            $kv = $this->parseKeyValueLines($userText);
            if (!empty($kv)) {
                $body = $kv;
            }
        }
        // 5) attachments text/json as body
        if ($body === null && !empty($attachments)) {
            foreach ($attachments as $att) {
                $t = strtolower((string) ($att['type'] ?? ''));
                if ($t === 'json') {
                    $try = $this->tryJsonDecode((string) ($att['content'] ?? ''));
                    if ($try !== null) {
                        $body = $try;
                        break;
                    }
                }
                if ($t === 'text') {
                    $txt = (string) ($att['content'] ?? '');
                    $first = $this->extractFirstJsonObject($txt);
                    if ($first !== null) {
                        $body = $first;
                        break;
                    }
                    $body = $txt !== '' ? mb_substr($txt, 0, 4000) : null;
                    if ($body !== null) {
                        break;
                    }
                }
            }
        }

        // 4) path params from {param}
        if (preg_match_all('/\{([^}]+)\}/', $path, $pm)) {
            $candidates = is_array($body) ? $body : (is_array($kv) ? $kv : []);
            foreach ($pm[1] as $p) {
                $v = $candidates[$p] ?? null;
                if ($v !== null && !is_array($v)) {
                    $prefill['pathParams'][$p] = (string) $v;
                }
            }
        }

        // 6) GET: move scalars into query
        if (strtoupper($method) === 'GET') {
            if (is_array($body)) {
                foreach ($body as $k => $v) {
                    if (!is_array($v)) {
                        $prefill['query'][$k] = (string) $v;
                    }
                }
                $body = null;
            }
        }

        if ($body !== null) {
            $prefill['body'] = $body;
        }
        return $prefill;
    }

    protected function tryJsonDecode(string $text)
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $val = json_decode($text, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $val : null;
    }

    protected function extractFirstJsonObject(string $text)
    {
        $text = trim($text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $raw = substr($text, $start, $end - $start + 1);
        $val = json_decode($raw, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $val : null;
    }

    protected function parseKeyValueLines(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $text) as $line) {
            if (preg_match('/^\s*([^:\n]+?)\s*:\s*(.+)\s*$/', $line, $m)) {
                $out[trim($m[1])] = trim($m[2]);
            }
        }
        return $out;
    }

    /**
     * Determine whether the current provider/model likely supports image inputs.
     * Conservative defaults: disable for Google/HF until a proper parts mapping is implemented.
     */
    protected function supportsVision(string $provider, string $model): bool
    {
        $p = strtolower($provider);
        $m = strtolower($model);
        if (in_array($p, ['google','huggingface'], true)) {
            return false; // not wired in current request shaping
        }
        if ($p === 'deepseek') {
            return str_contains($m, 'vl'); // e.g., deepseek-vl
        }
        if ($p === 'groq') {
            return str_contains($m, 'vision') || str_contains($m, 'llama-3.2');
        }
        // openai/openai-compatible
        return str_contains($m, 'gpt-4o') || str_contains($m, 'o3') || str_contains($m, 'o4') || str_contains($m, 'gpt-4.1');
    }

    /**
     * Prune history for Edit & Resend.
     * Drops the original user message (by id) and the immediate assistant reply following it.
     */
    protected function pruneEditedHistory(array $history, string $replacesId): array
    {
        if ($replacesId === '' || empty($history)) {
            return $history;
        }
        $out = [];
        $skipNextAssistant = false;
        foreach ($history as $i => $item) {
            $id = (string) ($item['id'] ?? '');
            $role = (string) ($item['role'] ?? '');
            if ($id !== '' && $id === $replacesId && $role === 'user') {
                // Skip this user entry and mark to skip the immediate assistant
                $skipNextAssistant = true;
                continue;
            }
            if ($skipNextAssistant && $role === 'assistant') {
                // Drop the first assistant right after the replaced user
                $skipNextAssistant = false; // only skip one assistant
                continue;
            }
            $out[] = $item;
        }
        return $out;
    }
}
