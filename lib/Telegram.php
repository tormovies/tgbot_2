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

    const MAX_MESSAGE_LENGTH = 4000;

    private function logSend($line)
    {
        $dir = defined('DATA_DIR') ? DATA_DIR : (__DIR__ . '/../data');
        @file_put_contents($dir . '/send_debug.log', date('Y-m-d H:i:s') . ' ' . $line . "\n", FILE_APPEND | LOCK_EX);
    }

    public function sendMessage($chatId, $text, $parseMode = '', $inlineKeyboard = null, $replyKeyboard = null, $disableLinkPreview = false)
    {
        $text = str_replace("\\n", "\n", $text);
        $chunks = $this->splitText($text, self::MAX_MESSAGE_LENGTH);
        $numChunks = count($chunks);
        if ($numChunks > 1) {
            $this->logSend("sendMessage chat_id={$chatId} chunks={$numChunks} total_len=" . mb_strlen($text));
        }
        $last = null;
        $isFirst = true;
        $idx = 0;
        foreach ($chunks as $chunk) {
            $idx++;
            if (!$isFirst) {
                sleep(1);
            }
            $params = array('chat_id' => $chatId, 'text' => $chunk);
            if ($parseMode !== '') {
                $params['parse_mode'] = $parseMode;
            }
            if ($disableLinkPreview) {
                $params['disable_web_page_preview'] = true;
            }
            if ($inlineKeyboard !== null && $isFirst && $numChunks === 1) {
                $params['reply_markup'] = json_encode(array('inline_keyboard' => $inlineKeyboard));
            } elseif ($replyKeyboard !== null && $isFirst && $numChunks === 1) {
                $params['reply_markup'] = json_encode(array(
                    'keyboard' => $replyKeyboard,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ));
            }
            try {
                $last = $this->request('sendMessage', $params);
                if ($numChunks > 1) {
                    $this->logSend("chunk {$idx}/{$numChunks} ok chat_id={$chatId}");
                }
            } catch (Exception $e) {
                if ($numChunks > 1) {
                    $this->logSend("chunk {$idx}/{$numChunks} FAIL chat_id={$chatId} " . $e->getMessage());
                    echo date('Y-m-d H:i:s') . " [ОШИБКА] sendMessage chunk: " . $e->getMessage() . "\n";
                } else {
                    throw $e;
                }
            }
            $isFirst = false;
        }
        return $last;
    }

    public function sendPhoto($chatId, $photoUrl, $caption = '', $parseMode = '', $inlineKeyboard = null, $disableLinkPreview = false)
    {
        $params = array('chat_id' => $chatId, 'photo' => $photoUrl);
        if ($caption !== '') {
            $params['caption'] = $caption;
        }
        if ($parseMode !== '') {
            $params['parse_mode'] = $parseMode;
        }
        if ($disableLinkPreview) {
            $params['disable_web_page_preview'] = true;
        }
        if ($inlineKeyboard !== null) {
            $params['reply_markup'] = json_encode(array('inline_keyboard' => $inlineKeyboard));
        }
        return $this->request('sendPhoto', $params);
    }

    public function editMessageText($chatId, $messageId, $text, $inlineKeyboard = null)
    {
        $text = str_replace("\\n", "\n", $text);
        $params = array('chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text);
        $params['reply_markup'] = json_encode(array('inline_keyboard' => $inlineKeyboard !== null ? $inlineKeyboard : array()));
        return $this->request('editMessageText', $params);
    }

    public function answerCallbackQuery($callbackQueryId)
    {
        return $this->request('answerCallbackQuery', array('callback_query_id' => $callbackQueryId));
    }

    /** Устанавливает меню команд (видны при нажатии / или меню). */
    public function setMyCommands(array $commands)
    {
        $list = array();
        foreach ($commands as $cmd => $desc) {
            $list[] = array('command' => $cmd, 'description' => $desc);
        }
        return $this->request('setMyCommands', array('commands' => $list));
    }

    private function splitText($text, $maxLen)
    {
        if (mb_strlen($text) <= $maxLen) {
            return array($text);
        }
        $chunks = array();
        $rest = $text;
        while (mb_strlen($rest) > 0) {
            if (mb_strlen($rest) <= $maxLen) {
                $chunks[] = $rest;
                break;
            }
            $part = mb_substr($rest, 0, $maxLen);
            $lastNewline = mb_strrpos($part, "\n");
            if ($lastNewline !== false && $lastNewline > $maxLen / 2) {
                $part = mb_substr($part, 0, $lastNewline + 1);
            }
            $chunks[] = $part;
            $rest = mb_substr($rest, mb_strlen($part));
        }
        return $chunks;
    }
}
