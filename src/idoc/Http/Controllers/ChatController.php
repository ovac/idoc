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

        $apiKey = env('OPENAI_API_KEY');
        if (!$apiKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'OPENAI_API_KEY not configured in environment.',
            ], 500);
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
            $model = config('idoc.chat.model', env('IDOC_CHAT_MODEL', 'gpt-4o-mini'));

            $payload = json_encode([
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.2,
                'max_tokens' => 600,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // Use cURL to avoid adding an extra dependency (e.g., Guzzle)
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer '.$apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 30,
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
                $msg = $json['error']['message'] ?? ('HTTP '.$status);
                throw new \RuntimeException('OpenAI error: '.$msg);
            }
            $answer = trim($json['choices'][0]['message']['content'] ?? '');
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
                if (is_array($spec)) { $out['spec'] = $spec; $out['openapi_json'] = $json; }
            }
        } catch (\Throwable $e) {}

        // Optional: render an info view into text for the model
        try {
            $view = config('idoc.chat.info_view'); // eg. 'idoc.info'
            if ($view && view()->exists($view)) {
                $out['info_full'] = strip_tags((string) view($view)->render());
            }
        } catch (\Throwable $e) {}

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
        if (!$query) return null; $ops = [];
        foreach (($spec['paths'] ?? []) as $path => $methods) {
            foreach ($methods as $method => $op) {
                if (!is_array($op)) continue;
                $summary = (string) ($op['summary'] ?? '');
                $desc    = (string) ($op['description'] ?? '');
                $tags    = array_map('strval', $op['tags'] ?? []);
                $params  = $op['parameters'] ?? [];
                $rb      = $op['requestBody']['content']['application/json']['schema'] ?? null;
                $hay = strtoupper($method.' '.$path.' '.implode(' ', $tags).' '.$summary.' '.$desc);
                $score = $this->scoreQuery($hay, $query); if ($score <= 0) continue;
                $ops[] = [ 'score'=>$score,'method'=>strtoupper($method),'path'=>$path,'summary'=>$summary,'tags'=>$tags,'params'=>$params,'body'=>$rb ];
            }
        }
        if (!$ops) return null; usort($ops, fn($a,$b) => $b['score'] <=> $a['score']);
        $ops = array_slice($ops, 0, 3);
        $md = [];
        foreach ($ops as $op) { $md[] = $this->renderOperation($op); }
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
        $q = strtolower($q); $tok = preg_split('/\W+/', $q, -1, PREG_SPLIT_NO_EMPTY); $score = 0;
        foreach ($tok as $t) { if (str_contains($hay, strtoupper($t))) $score += 2; if (str_contains(strtolower($hay), $t)) $score += 1; }
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
        $out = []; $out[] = sprintf('### %s %s', $op['method'], $op['path']);
        if (!empty($op['summary'])) $out[] = '_'.$op['summary'].'_';
        if (!empty($op['tags'])) $out[] = 'Tags: `'.implode('`, `', $op['tags']).'`';
        if (!empty($op['params'])) {
            $byIn = [];
            foreach ($op['params'] as $p) { $byIn[$p['in'] ?? 'other'][] = $p; }
            foreach (['path','query','header'] as $group) {
                if (empty($byIn[$group])) continue; $out[] = '**'.ucfirst($group).' Params:**';
                foreach ($byIn[$group] as $p) {
                    $req = !empty($p['required']) ? 'required' : 'optional';
                    $name = $p['name'] ?? ''; $schemaType = $p['schema']['type'] ?? '';
                    $desc = trim((string) ($p['description'] ?? ''));
                    $out[] = sprintf('- `%s` (%s, %s) %s%s', $name, $group, $req, $schemaType ? "type: $schemaType" : '', $desc ? " — $desc" : '');
                }
            }
        }
        if (!empty($op['body'])) {
            $schema = $op['body']; $fields = []; $required = $schema['required'] ?? []; $props = $schema['properties'] ?? [];
            foreach ($props as $name => $def) {
                $t = $def['type'] ?? ''; $d = $def['description'] ?? '';
                $req = in_array($name, $required, true) ? 'required' : 'optional';
                $fields[] = sprintf('- `%s` (%s) %s%s', $name, $req, $t ? "type: $t" : '', $d ? " — $d" : '');
            }
            if ($fields) { $out[] = '**Body Fields:**'; $out = array_merge($out, $fields); }
        }
        return implode("\n", $out);
    }
}

