<?php
/**
 * Вызовы Telegram Bot API. Совместимость: PHP 5.6+
 */
class Telegram
{
    private $base = 'https://api.telegram.org/bot';
    private $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    private function request($method, $params = array(), $get = false)
    {
        $url = $this->base . $this->token . '/' . $method;
        if ($get && $params !== array()) {
            $url .= '?' . http_build_query($params);
        }
        $ch = curl_init($url);
        $opts = array(CURLOPT_RETURNTRANSFER => true);
        if ($get) {
            $opts[CURLOPT_HTTPGET] = true;
        } else {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($params);
            $opts[CURLOPT_HTTPHEADER] = array('Content-Type: application/json');
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200) {
            throw new RuntimeException("Telegram API error: $code $body");
        }
        $data = json_decode($body, true);
        if (!$data['ok']) {
            $desc = isset($data['description']) ? $data['description'] : $body;
            throw new RuntimeException("Telegram API: " . $desc);
        }
        return $data;
    }

    public function getUpdates($offset = null, $timeout = 30)
    {
        $params = array('timeout' => $timeout);
        if ($offset !== null) {
            $params['offset'] = $offset;
        }
        $result = $this->request('getUpdates', $params, true);
        return isset($result['result']) ? $result['result'] : array();
    }

    public function sendMessage($chatId, $text, $parseMode = '')
    {
        $params = array('chat_id' => $chatId, 'text' => $text);
        if ($parseMode !== '') {
            $params['parse_mode'] = $parseMode;
        }
        return $this->request('sendMessage', $params);
    }
}
