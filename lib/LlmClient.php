<?php

require_once dirname(__FILE__) . '/HttpTransport.php';
require_once dirname(dirname(__FILE__)) . '/provider_catalog.php';

class LlmClient
{
    protected $config;
    protected $transport;

    public function __construct($config = array(), $transport = null)
    {
        $this->config = is_array($config) ? $config : array();
        $this->transport = $transport instanceof HttpTransport ? $transport : new HttpTransport();
    }

    public function chat($request)
    {
        // Punto de entrada unico: valida la solicitud y decide que adaptador usar.
        if (!is_array($request)) {
            throw new Exception('La solicitud debe ser un arreglo.');
        }

        $provider = $this->resolveProviderConfig($request);

        if ($provider['type'] === 'anthropic') {
            return $this->chatAnthropic($provider, $request);
        }

        return $this->chatOpenAiCompatible($provider, $request);
    }

    protected function chatOpenAiCompatible($provider, $request)
    {
        // Convierte la solicitud al formato Chat Completions usado por OpenAI y proveedores compatibles.
        $messages = $this->resolveMessages($request);

        if (empty($messages)) {
            throw new Exception('Debes enviar "prompt", "messages" o "messages_json".');
        }

        $payload = array(
            'model' => $provider['model'],
            'messages' => $messages,
            'stream' => false,
        );

        if ($this->hasValue($request, 'temperature')) {
            $payload['temperature'] = (float) $request['temperature'];
        }

        if ($this->hasValue($request, 'top_p')) {
            $payload['top_p'] = (float) $request['top_p'];
        }

        if ($this->hasValue($request, 'max_completion_tokens')) {
            $payload['max_completion_tokens'] = (int) $request['max_completion_tokens'];
        } elseif ($this->hasValue($request, 'max_tokens')) {
            if ($this->shouldUseMaxCompletionTokens($provider['provider'], $provider['model'])) {
                $payload['max_completion_tokens'] = (int) $request['max_tokens'];
            } else {
                $payload['max_tokens'] = (int) $request['max_tokens'];
            }
        }

        $headers = array(
            'Authorization: Bearer ' . $provider['api_key'],
        );

        if ($this->hasValue($provider, 'organization')) {
            $headers[] = 'OpenAI-Organization: ' . $provider['organization'];
        }

        if ($this->hasValue($provider, 'project')) {
            $headers[] = 'OpenAI-Project: ' . $provider['project'];
        }

        $headers = array_merge($headers, $this->normalizeCustomHeaders($provider));

        $endpoint = $this->buildOpenAiEndpoint($provider['base_url']);
        $response = $this->transport->requestJson('POST', $endpoint, $headers, $payload, $this->buildTransportOptions($request));

        $this->assertSuccessResponse($response, $provider['provider']);

        return array(
            'ok' => true,
            'provider' => $provider['provider'],
            'provider_type' => $provider['type'],
            'model' => $provider['model'],
            'endpoint' => $endpoint,
            'transport' => $response['transport'],
            'status_code' => $response['status_code'],
            'text' => $this->extractOpenAiText($response['json']),
            'reasoning_text' => $this->extractOpenAiReasoningText($response['json']),
            'usage' => isset($response['json']['usage']) ? $response['json']['usage'] : null,
            'raw' => $response['json'] !== null ? $response['json'] : $response['body'],
        );
    }

    protected function chatAnthropic($provider, $request)
    {
        // Convierte la solicitud al formato Messages API nativo de Anthropic.
        $messages = $this->resolveMessages($request);
        $system = $this->hasValue($request, 'system') ? trim($request['system']) : '';

        $normalized = $this->splitAnthropicSystemFromMessages($messages);

        if ($system === '') {
            $system = $normalized['system'];
        } elseif ($normalized['system'] !== '') {
            $system .= "\n\n" . $normalized['system'];
        }

        $messages = $normalized['messages'];

        if (empty($messages)) {
            throw new Exception('Anthropic necesita al menos un mensaje user o assistant.');
        }

        $payload = array(
            'model' => $provider['model'],
            'messages' => $messages,
        );

        if ($system !== '') {
            $payload['system'] = $system;
        }

        if ($this->hasValue($request, 'temperature')) {
            $payload['temperature'] = (float) $request['temperature'];
        }

        if ($this->hasValue($request, 'max_tokens')) {
            $payload['max_tokens'] = (int) $request['max_tokens'];
        } else {
            $payload['max_tokens'] = 1024;
        }

        $headers = array(
            'x-api-key: ' . $provider['api_key'],
            'anthropic-version: ' . $provider['anthropic_version'],
        );

        $headers = array_merge($headers, $this->normalizeCustomHeaders($provider));

        $endpoint = $this->buildAnthropicEndpoint($provider['base_url']);
        $response = $this->transport->requestJson('POST', $endpoint, $headers, $payload, $this->buildTransportOptions($request));

        $this->assertSuccessResponse($response, $provider['provider']);

        return array(
            'ok' => true,
            'provider' => $provider['provider'],
            'provider_type' => $provider['type'],
            'model' => $provider['model'],
            'endpoint' => $endpoint,
            'transport' => $response['transport'],
            'status_code' => $response['status_code'],
            'text' => $this->extractAnthropicText($response['json']),
            'usage' => isset($response['json']['usage']) ? $response['json']['usage'] : null,
            'raw' => $response['json'] !== null ? $response['json'] : $response['body'],
        );
    }

    protected function resolveProviderConfig($request)
    {
        // Une configuracion del archivo, defaults oficiales y overrides enviados por formulario o API.
        $providerName = $this->hasValue($request, 'provider')
            ? trim($request['provider'])
            : (isset($this->config['default_provider']) ? $this->config['default_provider'] : 'openai');

        $provider = array();
        if (isset($this->config['providers'][$providerName]) && is_array($this->config['providers'][$providerName])) {
            $provider = $this->config['providers'][$providerName];
        }

        $provider['provider'] = $providerName;
        $provider['type'] = isset($provider['type']) ? $provider['type'] : $this->inferProviderType($providerName);

        $overrideKeys = array(
            'api_key',
            'base_url',
            'model',
            'organization',
            'project',
            'anthropic_version',
        );

        foreach ($overrideKeys as $key) {
            if ($this->hasValue($request, $key)) {
                $provider[$key] = trim($request[$key]);
            }
        }

        if (!$this->hasValue($provider, 'base_url')) {
            $provider['base_url'] = $this->defaultBaseUrlFor($providerName, $provider['type']);
        }

        if (!$this->hasValue($provider, 'model') && $this->hasValue($provider, 'default_model')) {
            $provider['model'] = $provider['default_model'];
        }

        if (!$this->hasValue($provider, 'anthropic_version')) {
            $provider['anthropic_version'] = '2023-06-01';
        }

        if (!$this->hasValue($provider, 'api_key')) {
            throw new Exception('Falta la API key del proveedor "' . $providerName . '".');
        }

        if (!$this->hasValue($provider, 'model')) {
            throw new Exception('Falta el modelo para el proveedor "' . $providerName . '".');
        }

        if (!$this->hasValue($provider, 'base_url')) {
            throw new Exception('Falta el base_url para el proveedor "' . $providerName . '".');
        }

        return $provider;
    }

    protected function resolveMessages($request)
    {
        // Prioridad:
        // 1. messages como arreglo nativo
        // 2. messages_json como texto JSON
        // 3. system + prompt construidos desde la GUI
        if (isset($request['messages']) && is_array($request['messages'])) {
            return $request['messages'];
        }

        if ($this->hasValue($request, 'messages_json')) {
            $messages = json_decode($request['messages_json'], true);
            if (!is_array($messages)) {
                throw new Exception('messages_json no contiene un JSON valido.');
            }
            return $messages;
        }

        $messages = array();

        if ($this->hasValue($request, 'system')) {
            $messages[] = array(
                'role' => 'system',
                'content' => (string) $request['system'],
            );
        }

        if ($this->hasValue($request, 'prompt')) {
            $messages[] = array(
                'role' => 'user',
                'content' => (string) $request['prompt'],
            );
        }

        return $messages;
    }

    protected function splitAnthropicSystemFromMessages($messages)
    {
        // Anthropic maneja system separado del historial user/assistant.
        $systemParts = array();
        $normalizedMessages = array();

        if (!is_array($messages)) {
            $messages = array();
        }

        foreach ($messages as $message) {
            if (!is_array($message) || !isset($message['role'])) {
                continue;
            }

            $role = strtolower($message['role']);

            if ($role === 'system') {
                if (isset($message['content']) && !is_array($message['content'])) {
                    $systemParts[] = trim($message['content']);
                }
                continue;
            }

            if ($role !== 'user' && $role !== 'assistant') {
                throw new Exception('Anthropic solo soporta mensajes user/assistant en esta implementacion minima.');
            }

            $normalizedMessages[] = $message;
        }

        return array(
            'system' => trim(implode("\n\n", array_filter($systemParts))),
            'messages' => $normalizedMessages,
        );
    }

    protected function buildTransportOptions($request)
    {
        // Centraliza opciones de transporte para no duplicarlas por proveedor.
        $options = array();

        if ($this->hasValue($request, 'timeout')) {
            $options['timeout'] = (int) $request['timeout'];
        }

        if ($this->hasValue($request, 'connect_timeout')) {
            $options['connect_timeout'] = (int) $request['connect_timeout'];
        }

        if (isset($request['curl_ssl_no_revoke'])) {
            $options['curl_ssl_no_revoke'] = $this->toBoolean($request['curl_ssl_no_revoke']);
        }

        if (isset($request['insecure'])) {
            $options['insecure'] = $this->toBoolean($request['insecure']);
        }

        return $options;
    }

    protected function normalizeCustomHeaders($provider)
    {
        $headers = array();

        if (!isset($provider['headers']) || !is_array($provider['headers'])) {
            return $headers;
        }

        foreach ($provider['headers'] as $key => $value) {
            if (is_int($key)) {
                $headers[] = $value;
            } else {
                $headers[] = $key . ': ' . $value;
            }
        }

        return $headers;
    }

    protected function assertSuccessResponse($response, $providerName)
    {
        if (!isset($response['status_code']) || $response['status_code'] < 200 || $response['status_code'] >= 300) {
            $message = $this->extractProviderError($response);
            throw new Exception(
                'El proveedor "' . $providerName . '" devolvio HTTP ' . (int) $response['status_code'] . ': ' . $message
            );
        }
    }

    protected function extractProviderError($response)
    {
        if (isset($response['json']['error']['message']) && is_string($response['json']['error']['message'])) {
            return $response['json']['error']['message'];
        }

        if (isset($response['json']['error']['type']) && is_string($response['json']['error']['type'])) {
            return $response['json']['error']['type'];
        }

        if (isset($response['body']) && is_string($response['body']) && trim($response['body']) !== '') {
            return trim($response['body']);
        }

        return 'Error desconocido.';
    }

    protected function buildOpenAiEndpoint($baseUrl)
    {
        // Permite pasar solo base_url o el endpoint completo.
        $baseUrl = rtrim(trim($baseUrl), '/');

        if (preg_match('#/chat/completions$#i', $baseUrl)) {
            return $baseUrl;
        }

        return $baseUrl . '/chat/completions';
    }

    protected function buildAnthropicEndpoint($baseUrl)
    {
        // Permite pasar solo base_url o el endpoint completo.
        $baseUrl = rtrim(trim($baseUrl), '/');

        if (preg_match('#/messages$#i', $baseUrl)) {
            return $baseUrl;
        }

        return $baseUrl . '/messages';
    }

    protected function inferProviderType($providerName)
    {
        $providerName = strtolower((string) $providerName);

        if ($providerName === 'anthropic' || $providerName === 'claude') {
            return 'anthropic';
        }

        return 'openai_compatible';
    }

    protected function defaultBaseUrlFor($providerName, $providerType)
    {
        // Primero intenta usar el catalogo oficial compartido con la interfaz.
        $providerName = strtolower((string) $providerName);
        $catalog = php_llm_get_provider_catalog();

        if (isset($catalog[$providerName]['official_base_url']) && trim($catalog[$providerName]['official_base_url']) !== '') {
            return $catalog[$providerName]['official_base_url'];
        }

        // Fallbacks utiles para proveedores compatibles que el usuario agregue manualmente.
        if ($providerType === 'anthropic') {
            return 'https://api.anthropic.com/v1';
        }

        $compatibilityMap = array(
            'openrouter' => 'https://openrouter.ai/api/v1',
            'groq' => 'https://api.groq.com/openai/v1',
            'together' => 'https://api.together.xyz/v1',
            'ollama' => 'http://localhost:11434/v1',
        );

        if (isset($compatibilityMap[$providerName])) {
            return $compatibilityMap[$providerName];
        }

        return '';
    }

    protected function shouldUseMaxCompletionTokens($providerName, $model)
    {
        // Algunos modelos recientes de OpenAI usan max_completion_tokens en lugar de max_tokens.
        $providerName = strtolower((string) $providerName);
        $model = strtolower((string) $model);

        if ($providerName !== 'openai') {
            return false;
        }

        return (
            strpos($model, 'o1') === 0 ||
            strpos($model, 'o3') === 0 ||
            strpos($model, 'o4') === 0 ||
            strpos($model, 'gpt-5') === 0
        );
    }

    protected function extractOpenAiText($json)
    {
        if (!is_array($json) || !isset($json['choices'][0]['message']['content'])) {
            return '';
        }

        return $this->normalizeTextContent($json['choices'][0]['message']['content']);
    }

    protected function extractOpenAiReasoningText($json)
    {
        if (!is_array($json) || !isset($json['choices'][0]['message']['reasoning_content'])) {
            return '';
        }

        return $this->normalizeTextContent($json['choices'][0]['message']['reasoning_content']);
    }

    protected function extractAnthropicText($json)
    {
        if (!is_array($json) || !isset($json['content']) || !is_array($json['content'])) {
            return '';
        }

        $parts = array();
        foreach ($json['content'] as $block) {
            if (is_array($block) && isset($block['type']) && $block['type'] === 'text' && isset($block['text'])) {
                $parts[] = $block['text'];
            }
        }

        return trim(implode("\n", $parts));
    }

    protected function normalizeTextContent($content)
    {
        if (is_string($content)) {
            return $content;
        }

        if (!is_array($content)) {
            return '';
        }

        $parts = array();

        foreach ($content as $block) {
            if (is_string($block)) {
                $parts[] = $block;
                continue;
            }

            if (!is_array($block)) {
                continue;
            }

            if (isset($block['text']) && is_string($block['text'])) {
                $parts[] = $block['text'];
            } elseif (isset($block['content']) && is_string($block['content'])) {
                $parts[] = $block['content'];
            }
        }

        return trim(implode("\n", $parts));
    }

    protected function hasValue($array, $key)
    {
        return isset($array[$key]) && trim((string) $array[$key]) !== '';
    }

    protected function toBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, array('1', 'true', 'yes', 'si', 'on'));
    }
}
