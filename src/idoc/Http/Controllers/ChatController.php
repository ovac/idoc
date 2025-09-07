<?php

namespace OVAC\IDoc\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * iDoc AI Chat Controller
 *
 * Purpose
 * - Exposes a minimal chat endpoint that answers questions about your API
 *   using the generated OpenAPI document (and optionally, extra context from a
 *   Blade view) via OpenAI's Chat Completions API.
 *
 * Configuration (config/idoc.php)
 * - idoc.chat.enabled   bool   Enable/disable the endpoint (default: false)
 * - idoc.chat.model     string Model id (default: gpt-4o-mini; override with IDOC_CHAT_MODEL)
 * - idoc.chat.info_view string Optional view name (eg. 'idoc.info') to render as extra context
 * - Required env var:   OPENAI_API_KEY
 *
 * Route
 * - POST /{idoc.path}/chat (name: idoc.chat). Loaded conditionally when enabled.
 *
 * Responses
 * - 403: Feature disabled
 * - 500: Missing key or provider error
 * - 200: { status: 'success', data: { reply: string } }
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
        ]);

        $apiKey = $this->resolveApiKey();
        if (!$apiKey) {
            return response()->json($this->missingKeyPayload(), 200);
        }

        $ctx = $this->buildContext();

        // System prompt to keep answers concise and aligned to the spec
        $system = "You are an API assistant for this project. Answer concisely, referencing endpoint paths, methods, and required parameters. Prefer examples that match the current docs. If unsure, say so briefly.";

        // Compose messages in Chat Completions format
        $messages = [ ['role' => 'system', 'content' => $system] ];

        if (!empty($ctx['openapi_json'])) {
            $messages[] = ['role' => 'system', 'content' => "OpenAPI JSON:\n".$ctx['openapi_json']];
        }

        if (!empty($ctx['spec'])) {
            if ($ops = $this->selectRelevantOperations($ctx['spec'], $data['message'])) {
                $messages[] = ['role' => 'system', 'content' => "Matched operations (from OpenAPI):\n".$ops];
            }
        }

        if (!empty($ctx['info_full'])) {
            $messages[] = ['role' => 'system', 'content' => "Docs info:\n".$ctx['info_full']];
        }

        $messages[] = ['role' => 'user', 'content' => $data['message']];

        try {
            $provider = strtolower((string) config('idoc.chat.provider', env('IDOC_CHAT_PROVIDER', 'openai')));
            $model = (string) config('idoc.chat.model', env('IDOC_CHAT_MODEL', 'gpt-4o-mini'));
            $temperature = 0.2;
            $maxTokens = 600;

            $baseUrl = rtrim((string) (config('idoc.chat.base_url') ?: $this->defaultBaseUrl($provider)), '/').'/';
            $headers = [ 'Content-Type: application/json' ];
            if (!in_array($provider, ['google'], true)) {
                $headers[] = 'Authorization: Bearer '.$apiKey;
            }
            $endpoint = '';
            $bodyArr = [];

            if (in_array($provider, ['openai','groq','openai_compat','deepseek'], true)) {
                $endpoint = $baseUrl.'chat/completions';
                $bodyArr = [ 'model' => $model, 'messages' => $messages, 'temperature' => $temperature, 'max_tokens' => $maxTokens ];
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

            $payload = json_encode($bodyArr, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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
            return response()->json([
                'status' => 'success',
                'message' => 'ok',
                'data' => [ 'reply' => $answer ],
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
                    $out[] = sprintf('- `%s` (%s, %s) %s%s', $name, $group, $req, $schemaType ? "type: $schemaType" : '', $desc ? " — $desc" : '');
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
                $fields[] = sprintf('- `%s` (%s) %s%s', $name, $req, $t ? "type: $t" : '', $d ? " — $d" : '');
            }
            if ($fields) {
                $out[] = '**Body Fields:**';
                $out = array_merge($out, $fields);
            }
        }
        return implode("\n", $out);
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
        $provider = strtolower((string) config('idoc.chat.provider', env('IDOC_CHAT_PROVIDER', 'openai')));
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
}
