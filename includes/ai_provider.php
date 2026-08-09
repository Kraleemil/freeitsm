<?php
/**
 * Reusable, storage-agnostic AI provider client.
 *
 * One place that knows how to send a chat/completion request to any of the
 * supported providers and normalise the response. It takes a plain config
 * array (provider/model/key/...) — it does NOT read settings itself, so it
 * can be reused by any module. The companion `ai_settings.php` loads config
 * from system_settings and hands it here.
 *
 * Providers:
 *   - anthropic   → POST https://api.anthropic.com/v1/messages
 *   - openai      → POST https://api.openai.com/v1/chat/completions
 *   - openrouter  → POST https://openrouter.ai/api/v1/chat/completions
 *                   (OpenAI-wire-compatible; one key reaches hundreds of
 *                    models, model ids are namespaced e.g. "anthropic/claude-3.5-sonnet")
 *
 * Request shapes mirror the proven ones in includes/rfp_ai.php; this file is
 * intentionally independent so the RFP builder isn't affected.
 */

require_once __DIR__ . '/encryption.php';

const AI_PROVIDER_RETRY_MAX        = 3;
const AI_PROVIDER_RETRY_BACKOFF_MS = 2000;
const AI_PROVIDER_HTTP_TIMEOUT     = 120;
const AI_PROVIDER_VALID            = ['anthropic', 'openai', 'openrouter'];

const AI_OPENROUTER_BASE  = 'https://openrouter.ai/api/v1';
const AI_OPENAI_BASE      = 'https://api.openai.com/v1';
const AI_ANTHROPIC_URL    = 'https://api.anthropic.com/v1/messages';
const AI_OPENROUTER_MODELS_URL = 'https://openrouter.ai/api/v1/models';
const AI_OPENROUTER_MODELS_TTL = 86400; // 24h

/**
 * Send a one-shot chat request and return a normalised result.
 *
 * @param array $cfg  ['provider','model','api_key','verify_ssl'(bool),'base_url'?]
 * @param array $opts ['system','user','max_tokens'?=1024,'temperature'?=0.0,
 *                     'referer'?,'title'?]  (referer/title attribute the call on
 *                     OpenRouter's dashboard — defaults to FreeITSM)
 * @return array ['content','tokens_in','tokens_out','provider','model','duration_ms']
 * @throws RuntimeException on misconfiguration or API/network failure.
 */
function aiProviderChat(array $cfg, array $opts): array
{
    $provider = $cfg['provider'] ?? 'anthropic';
    $model    = trim((string)($cfg['model'] ?? ''));
    $apiKey   = (string)($cfg['api_key'] ?? '');
    $verify   = !empty($cfg['verify_ssl']);

    if (!in_array($provider, AI_PROVIDER_VALID, true)) {
        throw new RuntimeException('Unknown AI provider: ' . $provider);
    }
    if ($apiKey === '') {
        throw new RuntimeException('No API key configured.');
    }
    if ($model === '') {
        throw new RuntimeException('No model configured.');
    }

    $opts['max_tokens']  = $opts['max_tokens']  ?? 1024;
    $opts['temperature'] = $opts['temperature'] ?? 0.0;

    $start = microtime(true);

    if ($provider === 'anthropic') {
        $result = aiProviderCallAnthropic($model, $apiKey, $verify, $opts);
    } else {
        // openai + openrouter share the OpenAI-compatible chat-completions wire format
        $base = $provider === 'openrouter'
            ? ($cfg['base_url'] ?? AI_OPENROUTER_BASE)
            : ($cfg['base_url'] ?? AI_OPENAI_BASE);
        $extraHeaders = [];
        if ($provider === 'openrouter') {
            // Optional attribution headers — surface the app on the OpenRouter dashboard.
            $extraHeaders[] = 'HTTP-Referer: ' . ($opts['referer'] ?? 'https://freeitsm.co.uk');
            $extraHeaders[] = 'X-Title: ' . ($opts['title'] ?? 'FreeITSM');
        }
        $result = aiProviderCallOpenAICompatible($base, $model, $apiKey, $verify, $opts, $extraHeaders);
    }

    $result['provider']    = $provider;
    $result['model']       = $model;
    $result['duration_ms'] = (int)((microtime(true) - $start) * 1000);
    return $result;
}

function aiProviderCallAnthropic(string $model, string $apiKey, bool $verify, array $opts): array
{
    $body = json_encode([
        'model'       => $model,
        'max_tokens'  => $opts['max_tokens'],
        'temperature' => $opts['temperature'],
        'system'      => (string)($opts['system'] ?? ''),
        'messages'    => [['role' => 'user', 'content' => (string)($opts['user'] ?? '')]],
    ]);

    $headers = [
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
        'content-type: application/json',
    ];

    $resp = aiProviderHttpPost(AI_ANTHROPIC_URL, $headers, $body, $verify);
    $data = $resp['data'];

    $text = '';
    foreach (($data['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') {
            $text .= $block['text'];
        }
    }

    return [
        'content'    => trim($text),
        'tokens_in'  => $data['usage']['input_tokens']  ?? null,
        'tokens_out' => $data['usage']['output_tokens'] ?? null,
    ];
}

function aiProviderCallOpenAICompatible(string $base, string $model, string $apiKey, bool $verify, array $opts, array $extraHeaders = []): array
{
    $body = json_encode([
        'model'       => $model,
        'max_tokens'  => $opts['max_tokens'],
        'temperature' => $opts['temperature'],
        'messages'    => [
            ['role' => 'system', 'content' => (string)($opts['system'] ?? '')],
            ['role' => 'user',   'content' => (string)($opts['user']   ?? '')],
        ],
    ]);

    $headers = array_merge([
        'Authorization: Bearer ' . $apiKey,
        'content-type: application/json',
    ], $extraHeaders);

    $resp = aiProviderHttpPost(rtrim($base, '/') . '/chat/completions', $headers, $body, $verify);
    $data = $resp['data'];

    $text = $data['choices'][0]['message']['content'] ?? '';

    return [
        'content'    => trim((string)$text),
        'tokens_in'  => $data['usage']['prompt_tokens']     ?? null,
        'tokens_out' => $data['usage']['completion_tokens'] ?? null,
    ];
}

/**
 * ─── Tool calling ────────────────────────────────────────────────────────────
 *
 * A conversation where the model may ask us to run something and then answer
 * using the result. Everything above is single-turn; this is the loop.
 *
 * Deliberately ADDITIVE — aiProviderChat() and its two callees are untouched, so
 * the eight existing AI features cannot be affected by anything here.
 *
 * The two wire formats differ more than they look:
 *
 *   Anthropic  tools:[{name,description,input_schema}]; the reply carries
 *              content blocks of type tool_use; the result goes back as a USER
 *              message containing tool_result blocks, and the assistant's own
 *              turn must be echoed back verbatim first.
 *   OpenAI     tools:[{type:'function',function:{...,parameters}}]; the reply
 *              carries message.tool_calls with arguments as a JSON STRING; each
 *              result goes back as its own message with role:'tool'.
 *
 * $runTool receives (string $name, array $args) and returns a string — whatever
 * the model should see. It must NEVER throw: a tool that fails should say so in
 * words, because "the CMDB lookup failed" is a useful thing for the model to
 * tell the reader, and an exception here would lose the whole conversation.
 *
 * @param array    $tools    [['name'=>…,'description'=>…,'schema'=>[JSON Schema]], …]
 * @param callable $runTool  fn(string $name, array $args): string
 * @return array ['content','calls'=>[['name','args','result'],…],'tokens_in','tokens_out','provider','model','duration_ms']
 */
function aiProviderChatTools(array $cfg, array $opts, array $tools, callable $runTool): array
{
    $provider = $cfg['provider'] ?? 'anthropic';
    $model    = trim((string)($cfg['model'] ?? ''));
    $apiKey   = (string)($cfg['api_key'] ?? '');
    $verify   = !empty($cfg['verify_ssl']);

    if (!in_array($provider, AI_PROVIDER_VALID, true)) throw new RuntimeException('Unknown AI provider: ' . $provider);
    if ($apiKey === '') throw new RuntimeException('No API key configured.');
    if ($model  === '') throw new RuntimeException('No model configured.');

    // A hard ceiling on round trips. A model that keeps asking for tools would
    // otherwise loop until the request times out — during an incident, on the
    // box everyone is relying on.
    $maxRounds  = max(1, min(6, (int)($opts['max_rounds'] ?? 4)));
    $maxTokens  = $opts['max_tokens']  ?? 1024;
    $temperature = $opts['temperature'] ?? 0.0;

    $start = microtime(true);
    $calls = [];
    $tokIn = 0;
    $tokOut = 0;

    if ($provider === 'anthropic') {
        $messages = [['role' => 'user', 'content' => (string)($opts['user'] ?? '')]];
        $wire = array_map(function ($t) {
            return ['name' => $t['name'], 'description' => $t['description'], 'input_schema' => $t['schema']];
        }, $tools);

        for ($round = 0; $round < $maxRounds; $round++) {
            $body = json_encode([
                'model'       => $model,
                'max_tokens'  => $maxTokens,
                'temperature' => $temperature,
                'system'      => (string)($opts['system'] ?? ''),
                'tools'       => $wire,
                'messages'    => $messages,
            ]);
            $resp = aiProviderHttpPost(AI_ANTHROPIC_URL, [
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ], $body, $verify);
            $data = $resp['data'];

            $tokIn  += (int)($data['usage']['input_tokens']  ?? 0);
            $tokOut += (int)($data['usage']['output_tokens'] ?? 0);

            $text = '';
            $toolUses = [];
            foreach (($data['content'] ?? []) as $block) {
                if (($block['type'] ?? '') === 'text')     $text .= $block['text'];
                if (($block['type'] ?? '') === 'tool_use') $toolUses[] = $block;
            }

            if (!$toolUses) {
                return aiToolsResult(trim($text), $calls, $tokIn, $tokOut, $provider, $model, $start);
            }

            // The assistant's turn goes back exactly as received, or the API
            // rejects the tool_result that follows it.
            //
            // ⚠️ EXCEPT FOR ONE THING, AND IT IS A PHP TRAP RATHER THAN AN API ONE.
            // A tool that takes no arguments arrives as "input": {}. json_decode
            // with assoc=true turns that into an EMPTY PHP ARRAY, and re-encoding
            // an empty array produces [] — a JSON array. Anthropic then rejects
            // the echoed turn with:
            //   messages.N.content.M.tool_use.input: Input should be an object
            // The failure is intermittent in the worst way: it only happens once
            // the model reaches for a parameterless tool, so it looks like a flaky
            // provider rather than a bug. Cast every tool_use input back to an
            // object before sending it home.
            $echo = $data['content'];
            foreach ($echo as $i => $block) {
                if (($block['type'] ?? '') === 'tool_use') {
                    $echo[$i]['input'] = (object) ($block['input'] ?? []);
                }
            }
            $messages[] = ['role' => 'assistant', 'content' => $echo];

            $results = [];
            foreach ($toolUses as $u) {
                $out = (string) $runTool((string)$u['name'], (array)($u['input'] ?? []));
                $calls[] = ['name' => $u['name'], 'args' => $u['input'] ?? [], 'result' => $out];
                $results[] = ['type' => 'tool_result', 'tool_use_id' => $u['id'], 'content' => $out];
            }
            $messages[] = ['role' => 'user', 'content' => $results];
        }

        // Out of rounds. Return what we have rather than nothing.
        return aiToolsResult('', $calls, $tokIn, $tokOut, $provider, $model, $start);
    }

    // ── OpenAI-compatible (openai, openrouter) ──
    $base = $provider === 'openrouter'
        ? ($cfg['base_url'] ?? AI_OPENROUTER_BASE)
        : ($cfg['base_url'] ?? AI_OPENAI_BASE);
    $extraHeaders = [];
    if ($provider === 'openrouter') {
        $extraHeaders[] = 'HTTP-Referer: ' . ($opts['referer'] ?? 'https://freeitsm.co.uk');
        $extraHeaders[] = 'X-Title: ' . ($opts['title'] ?? 'FreeITSM');
    }

    $messages = [
        ['role' => 'system', 'content' => (string)($opts['system'] ?? '')],
        ['role' => 'user',   'content' => (string)($opts['user']   ?? '')],
    ];
    $wire = array_map(function ($t) {
        return ['type' => 'function', 'function' => [
            'name' => $t['name'], 'description' => $t['description'], 'parameters' => $t['schema'],
        ]];
    }, $tools);

    for ($round = 0; $round < $maxRounds; $round++) {
        $body = json_encode([
            'model'       => $model,
            'max_tokens'  => $maxTokens,
            'temperature' => $temperature,
            'tools'       => $wire,
            'messages'    => $messages,
        ]);
        $resp = aiProviderHttpPost(rtrim($base, '/') . '/chat/completions', array_merge([
            'Authorization: Bearer ' . $apiKey,
            'content-type: application/json',
        ], $extraHeaders), $body, $verify);
        $data = $resp['data'];

        $tokIn  += (int)($data['usage']['prompt_tokens']     ?? 0);
        $tokOut += (int)($data['usage']['completion_tokens'] ?? 0);

        $msg   = $data['choices'][0]['message'] ?? [];
        $tcs   = $msg['tool_calls'] ?? [];

        if (!$tcs) {
            return aiToolsResult(trim((string)($msg['content'] ?? '')), $calls, $tokIn, $tokOut, $provider, $model, $start);
        }

        $messages[] = $msg;
        foreach ($tcs as $tc) {
            $name = (string)($tc['function']['name'] ?? '');
            // ⚠️ arguments arrive as a JSON STRING here, not an object — decoding
            // it is not optional, and a model occasionally sends '' for a tool
            // that takes none.
            $args = json_decode((string)($tc['function']['arguments'] ?? '{}'), true);
            if (!is_array($args)) $args = [];
            $out = (string) $runTool($name, $args);
            $calls[] = ['name' => $name, 'args' => $args, 'result' => $out];
            $messages[] = ['role' => 'tool', 'tool_call_id' => $tc['id'] ?? '', 'content' => $out];
        }
    }

    return aiToolsResult('', $calls, $tokIn, $tokOut, $provider, $model, $start);
}

/** Shared return shape for aiProviderChatTools. */
function aiToolsResult(string $content, array $calls, int $tokIn, int $tokOut, string $provider, string $model, float $start): array
{
    return [
        'content'     => $content,
        'calls'       => $calls,
        'tokens_in'   => $tokIn,
        'tokens_out'  => $tokOut,
        'provider'    => $provider,
        'model'       => $model,
        'duration_ms' => (int)((microtime(true) - $start) * 1000),
    ];
}

/**
 * POST with retry/backoff on 429 / 5xx / network errors. Ported from
 * rfpAiHttpPostWithRetry so this file stands alone.
 */
function aiProviderHttpPost(string $url, array $headers, string $body, bool $verifySsl): array
{
    $attempt = 0;
    $lastErr = '';

    while ($attempt < AI_PROVIDER_RETRY_MAX) {
        $attempt++;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => AI_PROVIDER_HTTP_TIMEOUT,
        ]);
        sslApplyCurl($ch);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            $lastErr = 'Network error: ' . $err;
            if ($attempt < AI_PROVIDER_RETRY_MAX) {
                usleep(AI_PROVIDER_RETRY_BACKOFF_MS * 1000 * (2 ** ($attempt - 1)));
                continue;
            }
            throw new RuntimeException($lastErr);
        }

        $data = json_decode($resp, true);

        if ($code >= 200 && $code < 300) {
            return ['code' => $code, 'data' => $data];
        }

        $errMsg  = $data['error']['message'] ?? ('HTTP ' . $code);
        $lastErr = "$errMsg (HTTP $code)";

        $retryable = ($code === 429 || ($code >= 500 && $code < 600));
        if ($retryable && $attempt < AI_PROVIDER_RETRY_MAX) {
            usleep(AI_PROVIDER_RETRY_BACKOFF_MS * 1000 * (2 ** ($attempt - 1)));
            continue;
        }
        throw new RuntimeException($lastErr);
    }

    throw new RuntimeException('Failed after ' . AI_PROVIDER_RETRY_MAX . ' attempts: ' . $lastErr);
}

/**
 * Fetch (and cache) the OpenRouter model catalogue. No API key required.
 * Cached in system_settings as JSON for 24h to keep the model picker snappy.
 * Falls back to a stale cache if a refresh fetch fails.
 *
 * @return array{models: array<int,array>, cached_at: int, stale: bool}
 *   models: [{id,name,context_length,prompt_price,completion_price}]
 */
function aiProviderListOpenRouterModels(PDO $conn, bool $force = false): array
{
    $readSetting = function (string $key) use ($conn) {
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : $v;
    };

    $cachedAt = (int)($readSetting('openrouter_models_cached_at') ?? 0);
    $cacheRaw = $readSetting('openrouter_models_cache');
    $fresh    = $cacheRaw !== null && (time() - $cachedAt) < AI_OPENROUTER_MODELS_TTL;

    if ($fresh && !$force) {
        $decoded = json_decode($cacheRaw, true);
        if (is_array($decoded)) {
            return ['models' => $decoded, 'cached_at' => $cachedAt, 'stale' => false];
        }
    }

    // Fetch fresh
    try {
        $ch = curl_init(AI_OPENROUTER_MODELS_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        sslApplyCurl($ch);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $code < 200 || $code >= 300) {
            throw new RuntimeException('OpenRouter models fetch failed (HTTP ' . $code . ')');
        }

        $json = json_decode($resp, true);
        $raw  = $json['data'] ?? [];
        $models = [];
        foreach ($raw as $m) {
            if (empty($m['id'])) continue;
            $models[] = [
                'id'              => $m['id'],
                'name'            => $m['name'] ?? $m['id'],
                'context_length'  => $m['context_length'] ?? ($m['top_provider']['context_length'] ?? null),
                'prompt_price'    => isset($m['pricing']['prompt'])     ? (float)$m['pricing']['prompt']     : null,
                'completion_price'=> isset($m['pricing']['completion']) ? (float)$m['pricing']['completion'] : null,
            ];
        }

        // Persist cache (plain JSON, no secrets)
        $now = time();
        $upsert = function (string $key, string $value) use ($conn) {
            $stmt = $conn->prepare(
                "INSERT INTO system_settings (setting_key, setting_value) VALUES (:k, :v)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            );
            $stmt->execute([':k' => $key, ':v' => $value]);
        };
        $upsert('openrouter_models_cache', json_encode($models));
        $upsert('openrouter_models_cached_at', (string)$now);

        return ['models' => $models, 'cached_at' => $now, 'stale' => false];
    } catch (Throwable $e) {
        // Fall back to whatever stale cache we have rather than failing the picker.
        if ($cacheRaw !== null) {
            $decoded = json_decode($cacheRaw, true);
            if (is_array($decoded)) {
                return ['models' => $decoded, 'cached_at' => $cachedAt, 'stale' => true];
            }
        }
        throw new RuntimeException('Could not load the OpenRouter model list: ' . $e->getMessage());
    }
}
