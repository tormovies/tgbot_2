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

    public function interpretDream($dreamText)
    {
        $systemPrompt = defined('DEEPSEEK_SYSTEM_PROMPT') ? DEEPSEEK_SYSTEM_PROMPT : 'Ты толкователь снов. Дай краткую и понятную расшифровку сна на русском языке. Пиши по делу, без лишних вступлений.';
        $body = array(
            'model' => 'deepseek-chat',
            'messages' => array(
                array('role' => 'system', 'content' => $systemPrompt),
                array('role' => 'user', 'content' => $dreamText),
            ),
        );

        $ch = curl_init(self::URL);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ),
            CURLOPT_TIMEOUT => 60,
        ));
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            throw new RuntimeException("DeepSeek API error: $code " . substr($response, 0, 500));
        }

        $data = json_decode($response, true);
        $content = isset($data['choices'][0]['message']['content']) ? $data['choices'][0]['message']['content'] : null;
        if ($content === null) {
            throw new RuntimeException("DeepSeek: no content in response");
        }
        return trim($content);
    }
}
