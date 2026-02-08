<?php
/**
 * Запрос к DeepSeek Chat API. Совместимость: PHP 5.6+
 */
class DeepSeek
{
    const URL = 'https://api.deepseek.com/v1/chat/completions';
    private $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Запрос к DeepSeek. $userContent — текст пользователя, $promptKey — ключ команды (gadat, vopros, nomer, tolkovanie).
     */
    /**
     * Гадание по гексаграмме. $lines — массив ["yin"|"yang"] от линии 1 (нижней) до 6 (верхней).
     */
    public function askHexagram(array $lines, $castingTime = null)
    {
        if ($castingTime === null) {
            $castingTime = date('c');
        }
        $ichingRequest = array(
            'iching_request' => array(
                'hexagram_data' => array('lines' => $lines),
                'response_format' => array('language' => 'ru'),
            ),
        );
        $userContent = json_encode($ichingRequest, JSON_UNESCAPED_UNICODE);
        return $this->ask($userContent, 'gadat');
    }

    public function ask($userContent, $promptKey = 'tolkovanie')
    {
        foreach (array(0, 1) as $attempt) {
            $content = $this->doRequest($userContent, $promptKey);
            if ($content !== null && trim($content) !== '') {
                return trim($content);
            }
            if ($attempt === 1) {
                throw new RuntimeException("DeepSeek: no content in response");
            }
            sleep(2);
        }
    }

    private function doRequest($userContent, $promptKey)
    {
        $systemPrompt = $this->getSystemPrompt($promptKey);
        $body = array(
            'model' => 'deepseek-chat',
            'messages' => array(
                array('role' => 'system', 'content' => $systemPrompt),
                array('role' => 'user', 'content' => $userContent),
            ),
        );
        if (defined('DEEPSEEK_MAX_TOKENS') && DEEPSEEK_MAX_TOKENS > 0) {
            $body['max_tokens'] = (int) DEEPSEEK_MAX_TOKENS;
        }

        $ch = curl_init(self::URL);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ),
            CURLOPT_TIMEOUT => 360,  // DeepSeek может отвечать 3–5 минут
        ));
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            throw new RuntimeException("DeepSeek API error: $code " . substr($response, 0, 500));
        }

        $data = json_decode($response, true);
        return isset($data['choices'][0]['message']['content']) ? $data['choices'][0]['message']['content'] : null;
    }

    /** Промпт по ключу: DEEPSEEK_PROMPTS[$key], иначе дефолт для И-Цзин. */
    private function getSystemPrompt($promptKey)
    {
        $prompts = isset($GLOBALS['DEEPSEEK_PROMPTS']) && is_array($GLOBALS['DEEPSEEK_PROMPTS'])
            ? $GLOBALS['DEEPSEEK_PROMPTS']
            : array();
        if (isset($prompts[$promptKey]) && $prompts[$promptKey] !== '') {
            return $prompts[$promptKey];
        }
        return 'Ты толкователь по «Книге перемен» (И-Цзин). Дай развёрнутое толкование на русском, в духе классического И-Цзин.';
    }
}
