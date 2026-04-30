<?php
/**
 * ai_helper.php — Multi-provider AI abstraction layer
 * Supports: OpenAI, Anthropic, Google Gemini
 * Active provider/model stored in ai_config table; falls back to .env values.
 *
 * .env keys:
 *   OPENAI_API_KEY      — OpenAI secret key
 *   ANTHROPIC_API_KEY   — Anthropic secret key
 *   GOOGLE_AI_KEY       — Google AI / Gemini key
 *   AI_PROVIDER         — default provider if DB not set (openai|anthropic|google)
 *   AI_MODEL            — default model   if DB not set
 */

require_once __DIR__ . '/env_loader.php';
load_env();

// ── Model registry ───────────────────────────────────────────────────────────
// Prices are per 1 million tokens (USD), approximate as of April 2026.

function ai_get_models(): array {
    return [
        'openai' => [
            'label' => 'OpenAI',
            'models' => [
                'gpt-4o-mini'   => ['label' => 'GPT-4o Mini',   'input' => 0.150, 'output' => 0.600, 'recommended' => true,  'notes' => 'Best value'],
                'gpt-4.1-mini'  => ['label' => 'GPT-4.1 Mini',  'input' => 0.400, 'output' => 1.600, 'recommended' => false, 'notes' => 'Balanced'],
                'gpt-4o'        => ['label' => 'GPT-4o',         'input' => 2.500, 'output' => 10.00, 'recommended' => false, 'notes' => 'High quality'],
                'gpt-4.1'       => ['label' => 'GPT-4.1',        'input' => 2.000, 'output' => 8.000, 'recommended' => false, 'notes' => 'Latest'],
            ],
        ],
        'anthropic' => [
            'label' => 'Anthropic',
            'models' => [
                'claude-3-5-haiku-20241022'  => ['label' => 'Claude 3.5 Haiku',  'input' => 0.800, 'output' => 4.000,  'recommended' => true,  'notes' => 'Fast & cheap'],
                'claude-sonnet-4-5-20250514' => ['label' => 'Claude Sonnet 4.5', 'input' => 3.000, 'output' => 15.000, 'recommended' => false, 'notes' => 'High quality'],
            ],
        ],
        'google' => [
            'label' => 'Google Gemini',
            'models' => [
                'gemini-1.5-flash' => ['label' => 'Gemini 1.5 Flash', 'input' => 0.075, 'output' => 0.300, 'recommended' => false, 'notes' => 'Cheapest'],
                'gemini-2.0-flash' => ['label' => 'Gemini 2.0 Flash', 'input' => 0.100, 'output' => 0.400, 'recommended' => true,  'notes' => 'Latest fast'],
                'gemini-1.5-pro'   => ['label' => 'Gemini 1.5 Pro',   'input' => 1.250, 'output' => 5.000, 'recommended' => false, 'notes' => 'Quality'],
            ],
        ],
    ];
}

// ── Config table ─────────────────────────────────────────────────────────────

function ai_ensure_config_table(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS ai_config (
        config_key   VARCHAR(100) NOT NULL PRIMARY KEY,
        config_value TEXT         NOT NULL,
        updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ai_get_config(mysqli $conn): array {
    // Start with env / hardcoded defaults
    $cfg = [
        'provider' => getenv('AI_PROVIDER') ?: 'openai',
        'model'    => getenv('AI_MODEL')    ?: 'gpt-4o-mini',
        'selection_mode' => getenv('AI_SELECTION_MODE') ?: 'manual',
        'enabled' => strtolower((string) (getenv('AI_ENABLED') ?: '0')),
    ];

    // DB values override env
    $result = @$conn->query(
        "SELECT config_key, config_value FROM ai_config
         WHERE config_key IN ('ai_provider','ai_model','ai_selection_mode')"
    );
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['config_key'] === 'ai_provider') $cfg['provider'] = $row['config_value'];
            if ($row['config_key'] === 'ai_model')    $cfg['model']    = $row['config_value'];
            if ($row['config_key'] === 'ai_selection_mode') $cfg['selection_mode'] = $row['config_value'];
        }
        $result->free();
    }
    if ($cfg['selection_mode'] !== 'manual' && $cfg['selection_mode'] !== 'cheapest') {
        $cfg['selection_mode'] = 'manual';
    }
    $cfg['enabled'] = in_array($cfg['enabled'], ['1', 'true', 'yes', 'on'], true);
    return $cfg;
}

function ai_save_config(mysqli $conn, string $provider, string $model, string $selectionMode = 'manual'): bool {
    ai_ensure_config_table($conn);
    $stmt = $conn->prepare(
        "INSERT INTO ai_config (config_key, config_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()"
    );
    if (!$stmt) return false;
    $pairs = [['ai_provider', $provider], ['ai_model', $model], ['ai_selection_mode', $selectionMode]];
    foreach ($pairs as [$k, $v]) {
        $stmt->bind_param('ss', $k, $v);
        $stmt->execute();
    }
    $stmt->close();
    return true;
}

function ai_get_cheapest_available_model(): ?array {
    $registry = ai_get_models();
    $candidates = [];

    foreach ($registry as $providerKey => $providerData) {
        if (!ai_key_configured($providerKey)) {
            continue;
        }

        foreach ($providerData['models'] as $modelKey => $modelData) {
            $candidates[] = [
                'provider' => $providerKey,
                'provider_label' => $providerData['label'],
                'model' => $modelKey,
                'label' => $modelData['label'],
                'input' => (float) ($modelData['input'] ?? 0),
                'output' => (float) ($modelData['output'] ?? 0),
            ];
        }
    }

    if (empty($candidates)) {
        return null;
    }

    usort($candidates, static function (array $left, array $right): int {
        $costCompare = $left['input'] <=> $right['input'];
        if ($costCompare !== 0) {
            return $costCompare;
        }
        return $left['output'] <=> $right['output'];
    });

    return $candidates[0];
}

function ai_ensure_activity_log_table(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS ai_activity_log (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        provider VARCHAR(50) NOT NULL,
        model VARCHAR(120) NOT NULL,
        selection_mode VARCHAR(20) NOT NULL DEFAULT 'manual',
        status VARCHAR(20) NOT NULL,
        request_kind VARCHAR(100) NOT NULL DEFAULT 'generic',
        user_identifier VARCHAR(190) DEFAULT NULL,
        prompt_chars INT NOT NULL DEFAULT 0,
        response_chars INT NOT NULL DEFAULT 0,
        input_tokens INT NOT NULL DEFAULT 0,
        output_tokens INT NOT NULL DEFAULT 0,
        total_tokens INT NOT NULL DEFAULT 0,
        error_message TEXT DEFAULT NULL,
        metadata_json LONGTEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ai_activity_created_at (created_at),
        KEY idx_ai_activity_status (status),
        KEY idx_ai_activity_kind (request_kind)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ai_log_activity(mysqli $conn, array $entry): void {
    ai_ensure_activity_log_table($conn);

    $provider = (string) ($entry['provider'] ?? 'unknown');
    $model = (string) ($entry['model'] ?? 'unknown');
    $selectionMode = (string) ($entry['selection_mode'] ?? 'manual');
    $status = (string) ($entry['status'] ?? 'unknown');
    $requestKind = (string) ($entry['request_kind'] ?? 'generic');
    $userIdentifier = isset($entry['user_identifier']) ? (string) $entry['user_identifier'] : null;
    $promptChars = (int) ($entry['prompt_chars'] ?? 0);
    $responseChars = (int) ($entry['response_chars'] ?? 0);
    $inputTokens = (int) ($entry['input_tokens'] ?? 0);
    $outputTokens = (int) ($entry['output_tokens'] ?? 0);
    $totalTokens = (int) ($entry['total_tokens'] ?? 0);
    $errorMessage = isset($entry['error_message']) ? (string) $entry['error_message'] : null;
    $metadataJson = !empty($entry['metadata']) ? json_encode($entry['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

    $stmt = $conn->prepare(
        "INSERT INTO ai_activity_log (
            provider, model, selection_mode, status, request_kind, user_identifier,
            prompt_chars, response_chars, input_tokens, output_tokens, total_tokens,
            error_message, metadata_json
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        return;
    }

    $stmt->bind_param(
        'ssssssiiiiiss',
        $provider,
        $model,
        $selectionMode,
        $status,
        $requestKind,
        $userIdentifier,
        $promptChars,
        $responseChars,
        $inputTokens,
        $outputTokens,
        $totalTokens,
        $errorMessage,
        $metadataJson
    );
    $stmt->execute();
    $stmt->close();
}

// ── API key helpers ───────────────────────────────────────────────────────────

function ai_key_configured(string $provider): bool {
    $key = ai_get_api_key($provider);
    return $key !== '';
}

function ai_get_api_key(string $provider): string {
    $map = [
        'openai'    => getenv('OPENAI_API_KEY')    ?: '',
        'anthropic' => getenv('ANTHROPIC_API_KEY') ?: '',
        'google'    => getenv('GOOGLE_AI_KEY')     ?: '',
    ];
    return $map[$provider] ?? '';
}

// ── Main completion function ──────────────────────────────────────────────────

/**
 * Call the active AI provider.
 *
 * Returns on success:
 *   ['text' => string, 'usage' => array, 'provider' => string, 'model' => string]
 * Returns on failure:
 *   ['error' => string]
 *
 * @param mysqli $conn         Active DB connection (needed to read config).
 * @param string $userPrompt   The user-facing prompt / question.
 * @param string $systemPrompt Optional system/persona instruction.
 * @param int    $maxTokens    Max tokens in the response.
 * @param array  $options      Optional request context for logging/routing.
 */
function ai_complete(mysqli $conn, string $userPrompt, string $systemPrompt = '', int $maxTokens = 600, array $options = []): array {
    $cfg      = ai_get_config($conn);
    $provider = $cfg['provider'];
    $model    = $cfg['model'];
    $selectionMode = $cfg['selection_mode'] ?? 'manual';
    $requestKind = trim((string) ($options['request_kind'] ?? 'generic'));
    if ($requestKind === '') {
        $requestKind = 'generic';
    }
    $metadata = is_array($options['metadata'] ?? null) ? $options['metadata'] : [];

    if ($selectionMode === 'cheapest') {
        $cheapest = ai_get_cheapest_available_model();
        if ($cheapest === null) {
            ai_log_activity($conn, [
                'provider' => $provider,
                'model' => $model,
                'selection_mode' => $selectionMode,
                'status' => 'blocked',
                'request_kind' => $requestKind,
                'user_identifier' => $_SESSION['username'] ?? null,
                'prompt_chars' => strlen($systemPrompt . $userPrompt),
                'error_message' => 'No configured AI providers available for cheapest-cost mode.',
            ]);
            return ['error' => 'No configured AI providers are available for cheapest-cost mode. Add at least one API key in .env.'];
        }
        $provider = $cheapest['provider'];
        $model = $cheapest['model'];
        $metadata['resolved_provider_label'] = $cheapest['provider_label'] ?? $provider;
        $metadata['resolved_model_label'] = $cheapest['label'] ?? $model;
    }

    if (empty($cfg['enabled'])) {
        ai_log_activity($conn, [
            'provider' => $provider,
            'model' => $model,
            'selection_mode' => $selectionMode,
            'status' => 'blocked',
            'request_kind' => $requestKind,
            'user_identifier' => $_SESSION['username'] ?? null,
            'prompt_chars' => strlen($systemPrompt . $userPrompt),
            'error_message' => 'AI spending guard is enabled. No external request was sent.',
            'metadata' => $metadata,
        ]);

        return [
            'text' => 'AI is currently in no-spend mode. The request was logged, but no external model call was made.',
            'usage' => [],
            'provider' => $provider,
            'model' => $model,
            'selection_mode' => $selectionMode,
            'spend_blocked' => true,
        ];
    }

    $apiKey   = ai_get_api_key($provider);

    if ($apiKey === '') {
        ai_log_activity($conn, [
            'provider' => $provider,
            'model' => $model,
            'selection_mode' => $selectionMode,
            'status' => 'error',
            'request_kind' => $requestKind,
            'user_identifier' => $_SESSION['username'] ?? null,
            'prompt_chars' => strlen($systemPrompt . $userPrompt),
            'error_message' => "No API key configured for provider \"{$provider}\".",
            'metadata' => $metadata,
        ]);
        return ['error' => "No API key configured for provider \"{$provider}\". Add it to your .env file (OPENAI_API_KEY / ANTHROPIC_API_KEY / GOOGLE_AI_KEY)."];
    }

    // Validate model still exists in registry
    $registry = ai_get_models();
    if (!isset($registry[$provider]['models'][$model])) {
        ai_log_activity($conn, [
            'provider' => $provider,
            'model' => $model,
            'selection_mode' => $selectionMode,
            'status' => 'error',
            'request_kind' => $requestKind,
            'user_identifier' => $_SESSION['username'] ?? null,
            'prompt_chars' => strlen($systemPrompt . $userPrompt),
            'error_message' => "Model \"{$model}\" is not in the registry for provider \"{$provider}\".",
            'metadata' => $metadata,
        ]);
        return ['error' => "Model \"{$model}\" is not in the registry for provider \"{$provider}\". Update AI Settings."];
    }

    try {
        $response = null;
        switch ($provider) {
            case 'openai':
                $response = ai_call_openai($apiKey, $model, $systemPrompt, $userPrompt, $maxTokens);
                break;
            case 'anthropic':
                $response = ai_call_anthropic($apiKey, $model, $systemPrompt, $userPrompt, $maxTokens);
                break;
            case 'google':
                $response = ai_call_google($apiKey, $model, $systemPrompt, $userPrompt, $maxTokens);
                break;
            default:
                return ['error' => "Unknown provider: {$provider}"];
        }

        if (!isset($response['error'])) {
            $response['selection_mode'] = $selectionMode;
            ai_log_activity($conn, [
                'provider' => $provider,
                'model' => $model,
                'selection_mode' => $selectionMode,
                'status' => 'success',
                'request_kind' => $requestKind,
                'user_identifier' => $_SESSION['username'] ?? null,
                'prompt_chars' => strlen($systemPrompt . $userPrompt),
                'response_chars' => strlen((string) ($response['text'] ?? '')),
                'input_tokens' => (int) (($response['usage']['prompt_tokens'] ?? $response['usage']['input_tokens'] ?? 0)),
                'output_tokens' => (int) (($response['usage']['completion_tokens'] ?? $response['usage']['output_tokens'] ?? 0)),
                'total_tokens' => (int) (($response['usage']['total_tokens'] ?? 0)),
                'metadata' => $metadata,
            ]);
        } else {
            ai_log_activity($conn, [
                'provider' => $provider,
                'model' => $model,
                'selection_mode' => $selectionMode,
                'status' => 'error',
                'request_kind' => $requestKind,
                'user_identifier' => $_SESSION['username'] ?? null,
                'prompt_chars' => strlen($systemPrompt . $userPrompt),
                'error_message' => (string) ($response['error'] ?? 'Unknown AI error'),
                'metadata' => $metadata,
            ]);
        }

        return $response;
    } catch (Exception $e) {
        ai_log_activity($conn, [
            'provider' => $provider,
            'model' => $model,
            'selection_mode' => $selectionMode,
            'status' => 'error',
            'request_kind' => $requestKind,
            'user_identifier' => $_SESSION['username'] ?? null,
            'prompt_chars' => strlen($systemPrompt . $userPrompt),
            'error_message' => $e->getMessage(),
            'metadata' => $metadata,
        ]);
        return ['error' => $e->getMessage()];
    }
}

// ── Provider call implementations ─────────────────────────────────────────────

function ai_call_openai(string $key, string $model, string $system, string $user, int $maxTokens): array {
    $messages = [];
    if ($system !== '') $messages[] = ['role' => 'system', 'content' => $system];
    $messages[] = ['role' => 'user', 'content' => $user];

    $raw  = ai_curl_post(
        'https://api.openai.com/v1/chat/completions',
        ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
        json_encode(['model' => $model, 'messages' => $messages, 'max_tokens' => $maxTokens])
    );
    $data = json_decode($raw, true);
    if (isset($data['error'])) return ['error' => $data['error']['message'] ?? 'OpenAI error'];
    return [
        'text'     => trim($data['choices'][0]['message']['content'] ?? ''),
        'usage'    => $data['usage'] ?? [],
        'provider' => 'openai',
        'model'    => $model,
    ];
}

function ai_call_anthropic(string $key, string $model, string $system, string $user, int $maxTokens): array {
    $payload = [
        'model'      => $model,
        'max_tokens' => $maxTokens,
        'messages'   => [['role' => 'user', 'content' => $user]],
    ];
    if ($system !== '') $payload['system'] = $system;

    $raw  = ai_curl_post(
        'https://api.anthropic.com/v1/messages',
        ['x-api-key: ' . $key, 'anthropic-version: 2023-06-01', 'Content-Type: application/json'],
        json_encode($payload)
    );
    $data = json_decode($raw, true);
    if (isset($data['error'])) return ['error' => $data['error']['message'] ?? 'Anthropic error'];
    return [
        'text'     => trim($data['content'][0]['text'] ?? ''),
        'usage'    => $data['usage'] ?? [],
        'provider' => 'anthropic',
        'model'    => $model,
    ];
}

function ai_call_google(string $key, string $model, string $system, string $user, int $maxTokens): array {
    $text    = $system !== '' ? $system . "\n\n" . $user : $user;
    $payload = [
        'contents'         => [['parts' => [['text' => $text]]]],
        'generationConfig' => ['maxOutputTokens' => $maxTokens],
    ];

    $raw  = ai_curl_post(
        "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}",
        ['Content-Type: application/json'],
        json_encode($payload)
    );
    $data = json_decode($raw, true);
    if (isset($data['error'])) return ['error' => $data['error']['message'] ?? 'Google AI error'];
    return [
        'text'     => trim($data['candidates'][0]['content']['parts'][0]['text'] ?? ''),
        'usage'    => $data['usageMetadata'] ?? [],
        'provider' => 'google',
        'model'    => $model,
    ];
}

// ── Shared cURL helper ────────────────────────────────────────────────────────

function ai_curl_post(string $url, array $headers, string $body): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception('cURL error: ' . $err);
    }
    curl_close($ch);
    return (string) $response;
}
