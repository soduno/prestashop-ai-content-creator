<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class SodunoAiPromptClient
{
    const PROVIDER_GREMINI = 'gremini';
    const PROVIDER_CHATGPT = 'chatgpt';
    const PROVIDER_FREELLM = 'freellm';
    const PROVIDER_CLAUDE = 'claude';
    const PROVIDER_CUSTMO = 'custmo';

    /**
     * @var string
     */
    private $provider;

    /**
     * @var string
     */
    private $apiKey;

    public function __construct($provider, $apiKey)
    {
        $this->provider = (string) $provider;
        $this->apiKey = trim((string) $apiKey);
    }

    public static function fromConfiguration()
    {
        $provider = (string) Configuration::get(Soduno_Aicreator::CONFIG_PROVIDER);
        $apiKey = (string) Configuration::get(Soduno_Aicreator::CONFIG_API_KEY);

        return new self($provider, $apiKey);
    }

    public function isConfigured()
    {
        return $this->provider !== '' && $this->apiKey !== '';
    }

    public function prompt($prompt, array $options = array())
    {
        if (!$this->isConfigured()) {
            return array(
                'success' => false,
                'provider' => $this->provider,
                'error' => 'AI credentials are not configured.',
            );
        }

        $prompt = trim((string) $prompt);
        if ($prompt === '') {
            return array(
                'success' => false,
                'provider' => $this->provider,
                'error' => 'Prompt cannot be empty.',
            );
        }

        switch ($this->provider) {
            case self::PROVIDER_CHATGPT:
                return $this->callChatGpt($prompt, $options);
            case self::PROVIDER_CLAUDE:
                return $this->callClaude($prompt, $options);
            case self::PROVIDER_GREMINI:
                return $this->callGremini($prompt, $options);
            case self::PROVIDER_FREELLM:
            case self::PROVIDER_CUSTMO:
                return $this->callCustomEndpoint($prompt, $options);
            default:
                return array(
                    'success' => false,
                    'provider' => $this->provider,
                    'error' => 'Unsupported provider: ' . $this->provider,
                );
        }
    }

    public function promptMany(array $prompts, array $options = array())
    {
        $results = array();

        foreach ($prompts as $key => $prompt) {
            $results[$key] = $this->prompt($prompt, $options);
        }

        return $results;
    }

    private function callChatGpt($prompt, array $options)
    {
        $model = isset($options['model']) ? (string) $options['model'] : 'gpt-4o-mini';
        $payload = array(
            'model' => $model,
            'input' => $prompt,
        );

        $response = $this->httpJsonRequest(
            'https://api.openai.com/v1/responses',
            array(
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ),
            $payload
        );

        if (!$response['success']) {
            return $response;
        }

        $text = '';
        if (isset($response['data']['output_text'])) {
            $text = (string) $response['data']['output_text'];
        }

        return array(
            'success' => true,
            'provider' => $this->provider,
            'text' => $text,
            'raw' => $response['data'],
        );
    }

    private function callClaude($prompt, array $options)
    {
        $model = isset($options['model']) ? (string) $options['model'] : 'claude-3-5-haiku-latest';
        $maxTokens = isset($options['max_tokens']) ? (int) $options['max_tokens'] : 512;

        $payload = array(
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt,
                ),
            ),
        );

        $response = $this->httpJsonRequest(
            'https://api.anthropic.com/v1/messages',
            array(
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
                'Content-Type: application/json',
            ),
            $payload
        );

        if (!$response['success']) {
            return $response;
        }

        $text = '';
        if (!empty($response['data']['content'][0]['text'])) {
            $text = (string) $response['data']['content'][0]['text'];
        }

        return array(
            'success' => true,
            'provider' => $this->provider,
            'text' => $text,
            'raw' => $response['data'],
        );
    }

    private function callGremini($prompt, array $options)
    {
        $model = isset($options['model']) ? (string) $options['model'] : 'gemini-1.5-flash';
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($model)
            . ':generateContent?key='
            . rawurlencode($this->apiKey);

        $payload = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt),
                    ),
                ),
            ),
        );

        $response = $this->httpJsonRequest(
            $url,
            array('Content-Type: application/json'),
            $payload
        );

        if (!$response['success']) {
            return $response;
        }

        $text = '';
        if (!empty($response['data']['candidates'][0]['content']['parts'][0]['text'])) {
            $text = (string) $response['data']['candidates'][0]['content']['parts'][0]['text'];
        }

        return array(
            'success' => true,
            'provider' => $this->provider,
            'text' => $text,
            'raw' => $response['data'],
        );
    }

    private function callCustomEndpoint($prompt, array $options)
    {
        $endpoint = isset($options['endpoint']) ? trim((string) $options['endpoint']) : '';
        if ($endpoint === '') {
            return array(
                'success' => false,
                'provider' => $this->provider,
                'error' => 'Custom provider requires an endpoint option.',
            );
        }

        $model = isset($options['model']) ? (string) $options['model'] : '';
        $payload = array(
            'prompt' => $prompt,
            'api_key' => $this->apiKey,
        );
        if ($model !== '') {
            $payload['model'] = $model;
        }

        $headers = array('Content-Type: application/json');
        if (!empty($options['headers']) && is_array($options['headers'])) {
            $headers = array_merge($headers, $options['headers']);
        }

        $response = $this->httpJsonRequest($endpoint, $headers, $payload);

        if (!$response['success']) {
            return $response;
        }

        $text = '';
        if (isset($response['data']['text'])) {
            $text = (string) $response['data']['text'];
        } elseif (isset($response['data']['output'])) {
            $text = (string) $response['data']['output'];
        }

        return array(
            'success' => true,
            'provider' => $this->provider,
            'text' => $text,
            'raw' => $response['data'],
        );
    }

    private function httpJsonRequest($url, array $headers, array $payload)
    {
        if (!function_exists('curl_init')) {
            return array(
                'success' => false,
                'provider' => $this->provider,
                'error' => 'cURL is not available in PHP environment.',
            );
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return array(
                'success' => false,
                'provider' => $this->provider,
                'error' => 'Unable to initialize cURL request.',
            );
        }

        $jsonPayload = json_encode($payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $rawResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($rawResponse === false) {
            return array(
                'success' => false,
                'provider' => $this->provider,
                'error' => 'HTTP request failed: ' . $curlError,
            );
        }

        $decoded = json_decode($rawResponse, true);
        if (!is_array($decoded)) {
            return array(
                'success' => false,
                'provider' => $this->provider,
                'status_code' => $statusCode,
                'error' => 'Invalid JSON response from provider.',
                'raw' => $rawResponse,
            );
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            return array(
                'success' => false,
                'provider' => $this->provider,
                'status_code' => $statusCode,
                'error' => isset($decoded['error']['message'])
                    ? (string) $decoded['error']['message']
                    : 'Provider returned non-success HTTP status.',
                'raw' => $decoded,
            );
        }

        return array(
            'success' => true,
            'provider' => $this->provider,
            'status_code' => $statusCode,
            'data' => $decoded,
        );
    }
}
