<?php

/**
 * Endpoint JSON del cliente LLM.
 *
 * Soporta:
 * - POST con prompt directo
 * - POST con messages o messages_json
 * - POST con prompt_preset para cargar una plantilla maestra
 * - GET informativo para descubrir proveedores y presets disponibles
 */
require_once dirname(__FILE__) . '/lib/LlmClient.php';
require_once dirname(__FILE__) . '/provider_catalog.php';
require_once dirname(__FILE__) . '/prompt_presets.php';
require_once dirname(__FILE__) . '/project_meta.php';

$configPath = dirname(__FILE__) . '/config.php';
$config = file_exists($configPath) ? require $configPath : require dirname(__FILE__) . '/config.example.php';
$projectMeta = php_llm_get_project_meta();
$providerCatalog = php_llm_get_provider_catalog();
$promptPresets = php_llm_get_prompt_presets();
$config = php_llm_api_merge_config_with_catalog($config, $providerCatalog);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_status_code(200);
    echo encode_json(
        array(
            'ok' => true,
            'message' => 'Usa POST para consultar un modelo.',
            'project' => $projectMeta,
            'providers' => $providerCatalog,
            'prompt_presets' => php_llm_get_prompt_preset_summaries($promptPresets),
            'example' => array(
                'provider' => 'openai',
                'model' => 'gpt-4.1-mini',
                'prompt' => 'Hola',
                'system' => 'Eres un asistente util.',
                'prompt_preset' => 'daily_short_master',
            ),
        )
    );
    exit;
}

$payload = read_request_payload();

try {
    $payload = apply_prompt_preset_if_needed($payload, $promptPresets);
    $client = new LlmClient($config, null);
    $result = $client->chat($payload);

    echo encode_json(
        array(
            'ok' => true,
            'result' => $result,
        )
    );
} catch (Exception $e) {
    set_status_code(500);
    echo encode_json(
        array(
            'ok' => false,
            'error' => $e->getMessage(),
        )
    );
}

/**
 * Lee JSON crudo o POST tradicional.
 */
function read_request_payload()
{
    $raw = file_get_contents('php://input');

    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    if (!empty($_POST)) {
        return $_POST;
    }

    return array();
}

/**
 * Si el cliente manda un prompt_preset y no manda prompt manual,
 * inyecta automaticamente el texto de la plantilla.
 */
function apply_prompt_preset_if_needed($payload, $promptPresets)
{
    if (!is_array($payload)) {
        return array();
    }

    if (
        isset($payload['prompt_preset']) &&
        trim((string) $payload['prompt_preset']) !== '' &&
        (!isset($payload['prompt']) || trim((string) $payload['prompt']) === '')
    ) {
        $presetKey = trim((string) $payload['prompt_preset']);

        if (isset($promptPresets[$presetKey]['prompt'])) {
            $payload['prompt'] = $promptPresets[$presetKey]['prompt'];
        }
    }

    return $payload;
}

/**
 * Asegura que el backend siempre tenga los proveedores oficiales basicos.
 */
function php_llm_api_merge_config_with_catalog($config, $providerCatalog)
{
    if (!isset($config['providers']) || !is_array($config['providers'])) {
        $config['providers'] = array();
    }

    foreach ($providerCatalog as $providerName => $providerMeta) {
        if (!isset($config['providers'][$providerName])) {
            $config['providers'][$providerName] = array();
        }

        if (!isset($config['providers'][$providerName]['type']) && isset($providerMeta['type'])) {
            $config['providers'][$providerName]['type'] = $providerMeta['type'];
        }

        if (!isset($config['providers'][$providerName]['base_url']) && isset($providerMeta['official_base_url'])) {
            $config['providers'][$providerName]['base_url'] = $providerMeta['official_base_url'];
        }
    }

    if (!isset($config['default_provider']) || trim((string) $config['default_provider']) === '') {
        $config['default_provider'] = 'openai';
    }

    return $config;
}

/**
 * Serializa JSON con banderas utiles cuando estan disponibles.
 */
function encode_json($value)
{
    $flags = 0;

    if (defined('JSON_PRETTY_PRINT')) {
        $flags |= JSON_PRETTY_PRINT;
    }

    if (defined('JSON_UNESCAPED_UNICODE')) {
        $flags |= JSON_UNESCAPED_UNICODE;
    }

    if (defined('JSON_UNESCAPED_SLASHES')) {
        $flags |= JSON_UNESCAPED_SLASHES;
    }

    return json_encode($value, $flags);
}

/**
 * Compatibilidad con PHP 5.4, donde http_response_code puede no existir.
 */
function set_status_code($statusCode)
{
    if (function_exists('http_response_code')) {
        http_response_code($statusCode);
        return;
    }

    $texts = array(
        200 => 'OK',
        500 => 'Internal Server Error',
    );

    $text = isset($texts[$statusCode]) ? $texts[$statusCode] : 'OK';
    header('HTTP/1.1 ' . $statusCode . ' ' . $text);
}
