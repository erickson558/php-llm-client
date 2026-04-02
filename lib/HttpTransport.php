<?php

class HttpTransport
{
    protected $curlExeAvailable = null;

    public function requestJson($method, $url, $headers, $payload, $options)
    {
        $body = $this->jsonEncode($payload);

        if ($body === false) {
            throw new Exception('No se pudo serializar el payload a JSON.');
        }

        $headers = $this->normalizeHeaders($headers);
        $headers[] = 'Content-Type: application/json';

        return $this->request($method, $url, $headers, $body, $options);
    }

    public function request($method, $url, $headers, $body, $options)
    {
        $headers = $this->normalizeHeaders($headers);
        $timeout = isset($options['timeout']) ? (int) $options['timeout'] : 120;
        $connectTimeout = isset($options['connect_timeout']) ? (int) $options['connect_timeout'] : 30;
        $curlSslNoRevoke = isset($options['curl_ssl_no_revoke'])
            ? (bool) $options['curl_ssl_no_revoke']
            : $this->shouldUseCurlSslNoRevoke();
        $insecure = isset($options['insecure']) ? (bool) $options['insecure'] : false;

        if (function_exists('curl_init')) {
            return $this->requestWithCurlExtension($method, $url, $headers, $body, $timeout, $connectTimeout);
        }

        if ($this->canUseCurlExe()) {
            return $this->requestWithCurlExe(
                $method,
                $url,
                $headers,
                $body,
                $timeout,
                $connectTimeout,
                $curlSslNoRevoke,
                $insecure
            );
        }

        if ($this->canUseStreamTransport($url)) {
            return $this->requestWithStream($method, $url, $headers, $body, $timeout);
        }

        throw new Exception(
            'No hay transporte HTTP disponible. Activa la extension curl de PHP, habilita HTTPS en PHP o usa curl.exe del sistema.'
        );
    }

    protected function requestWithCurlExtension($method, $url, $headers, $body, $timeout, $connectTimeout)
    {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new Exception('No se pudo inicializar la extension curl de PHP.');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);

        $rawResponse = curl_exec($ch);

        if ($rawResponse === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('curl extension fallo: ' . $error);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr($rawResponse, 0, $headerSize);
        $responseBody = substr($rawResponse, $headerSize);

        return $this->buildResponse($statusCode, $rawHeaders, $responseBody, 'curl_extension');
    }

    protected function requestWithCurlExe($method, $url, $headers, $body, $timeout, $connectTimeout, $curlSslNoRevoke, $insecure)
    {
        $tempFiles = array(
            tempnam(sys_get_temp_dir(), 'llm_cfg_'),
            tempnam(sys_get_temp_dir(), 'llm_req_'),
            tempnam(sys_get_temp_dir(), 'llm_res_'),
            tempnam(sys_get_temp_dir(), 'llm_hdr_'),
        );

        $configFile = $tempFiles[0];
        $requestFile = $tempFiles[1];
        $responseFile = $tempFiles[2];
        $headerFile = $tempFiles[3];

        try {
            file_put_contents($requestFile, $body);

            $configLines = array();
            $configLines[] = 'request = ' . $this->quoteCurlConfigValue(strtoupper($method));
            $configLines[] = 'url = ' . $this->quoteCurlConfigValue($url);
            $configLines[] = 'connect-timeout = ' . (int) $connectTimeout;
            $configLines[] = 'max-time = ' . (int) $timeout;
            $configLines[] = 'dump-header = ' . $this->quoteCurlConfigValue($this->toCurlPath($headerFile));
            $configLines[] = 'output = ' . $this->quoteCurlConfigValue($this->toCurlPath($responseFile));
            $configLines[] = 'write-out = "%{http_code}"';

            if ($curlSslNoRevoke) {
                $configLines[] = 'ssl-no-revoke';
            }

            if ($insecure) {
                $configLines[] = 'insecure';
            }

            foreach ($headers as $header) {
                $configLines[] = 'header = ' . $this->quoteCurlConfigValue($header);
            }

            $configLines[] = 'data-binary = ' . $this->quoteCurlConfigValue('@' . $this->toCurlPath($requestFile));

            file_put_contents($configFile, implode(PHP_EOL, $configLines));

            $command = 'curl.exe -sS -K ' . $this->quoteWindowsArgument($configFile) . ' 2>&1';
            $output = array();
            $exitCode = 0;
            exec($command, $output, $exitCode);

            $stdout = trim(implode("\n", $output));
            $responseBody = file_exists($responseFile) ? file_get_contents($responseFile) : '';
            $rawHeaders = file_exists($headerFile) ? file_get_contents($headerFile) : '';

            if ($exitCode !== 0) {
                throw new Exception('curl.exe fallo: ' . $stdout);
            }

            if (!preg_match('/(\d{3})\s*$/', $stdout, $matches)) {
                throw new Exception('No se pudo obtener el codigo HTTP desde curl.exe.');
            }

            $statusCode = (int) $matches[1];

            $this->cleanupFiles($tempFiles);

            return $this->buildResponse($statusCode, $rawHeaders, $responseBody, 'curl_exe');
        } catch (Exception $e) {
            $this->cleanupFiles($tempFiles);
            throw $e;
        }
    }

    protected function requestWithStream($method, $url, $headers, $body, $timeout)
    {
        $context = stream_context_create(
            array(
                'http' => array(
                    'method' => strtoupper($method),
                    'header' => implode("\r\n", $headers),
                    'content' => $body,
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                ),
            )
        );

        $responseBody = @file_get_contents($url, false, $context);
        $rawHeaders = '';
        $statusCode = 0;

        if (isset($http_response_header) && is_array($http_response_header)) {
            $rawHeaders = implode("\r\n", $http_response_header);
            $statusCode = $this->extractStatusCode($rawHeaders);
        }

        if ($responseBody === false && $statusCode === 0) {
            throw new Exception('El transporte stream de PHP no pudo completar la solicitud.');
        }

        return $this->buildResponse($statusCode, $rawHeaders, $responseBody, 'stream');
    }

    protected function buildResponse($statusCode, $rawHeaders, $body, $transport)
    {
        return array(
            'status_code' => (int) $statusCode,
            'headers' => $this->parseHeaders($rawHeaders),
            'raw_headers' => $rawHeaders,
            'body' => $body,
            'json' => $this->decodeJson($body),
            'transport' => $transport,
        );
    }

    protected function normalizeHeaders($headers)
    {
        $normalized = array();

        if (!is_array($headers)) {
            return $normalized;
        }

        foreach ($headers as $key => $value) {
            if (is_int($key)) {
                $normalized[] = $value;
            } else {
                $normalized[] = $key . ': ' . $value;
            }
        }

        return $normalized;
    }

    protected function canUseCurlExe()
    {
        if ($this->curlExeAvailable !== null) {
            return $this->curlExeAvailable;
        }

        if (!function_exists('exec')) {
            $this->curlExeAvailable = false;
            return false;
        }

        $output = array();
        $exitCode = 0;
        @exec('curl.exe --version 2>NUL', $output, $exitCode);

        $this->curlExeAvailable = ($exitCode === 0);

        return $this->curlExeAvailable;
    }

    protected function canUseStreamTransport($url)
    {
        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME));
        $wrappers = stream_get_wrappers();

        if ($scheme === 'https') {
            return in_array('https', $wrappers);
        }

        return in_array('http', $wrappers);
    }

    protected function shouldUseCurlSslNoRevoke()
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    protected function quoteCurlConfigValue($value)
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace('"', '\\"', $value);

        return '"' . $value . '"';
    }

    protected function quoteWindowsArgument($value)
    {
        $value = str_replace('"', '""', $value);
        return '"' . $value . '"';
    }

    protected function toCurlPath($path)
    {
        return str_replace('\\', '/', $path);
    }

    protected function extractStatusCode($rawHeaders)
    {
        $statusCode = 0;

        if (preg_match_all('/^HTTP\/[0-9.]+\s+(\d{3})/mi', $rawHeaders, $matches) && !empty($matches[1])) {
            $statusCode = (int) $matches[1][count($matches[1]) - 1];
        }

        return $statusCode;
    }

    protected function parseHeaders($rawHeaders)
    {
        $headers = array();
        $lines = preg_split("/\r\n|\n|\r/", (string) $rawHeaders);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || stripos($line, 'HTTP/') === 0) {
                continue;
            }

            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $name = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            if (!isset($headers[$name])) {
                $headers[$name] = $value;
            } else {
                $headers[$name] .= ', ' . $value;
            }
        }

        return $headers;
    }

    protected function decodeJson($body)
    {
        if (!is_string($body) || trim($body) === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    protected function jsonEncode($value)
    {
        $flags = 0;

        if (defined('JSON_UNESCAPED_UNICODE')) {
            $flags |= JSON_UNESCAPED_UNICODE;
        }

        if (defined('JSON_UNESCAPED_SLASHES')) {
            $flags |= JSON_UNESCAPED_SLASHES;
        }

        return json_encode($value, $flags);
    }

    protected function cleanupFiles($files)
    {
        foreach ($files as $file) {
            if (is_string($file) && $file !== '' && file_exists($file)) {
                @unlink($file);
            }
        }
    }
}
