<?php
/**
 * Запуск: php bot.php
 * Держит long polling, обрабатывает /son и текст сна, ответ в личку.
 * Совместимость: PHP 5.6+
 */

$config = __DIR__ . '/config.php';
if (!is_file($config)) {
    echo "Создайте config.php из config.sample.php и заполните BOT_TOKEN и DEEPSEEK_API_KEY.\n";
    exit(1);
}
require $config;
require __DIR__ . '/lib/Db.php';
require __DIR__ . '/lib/Telegram.php';
require __DIR__ . '/lib/DeepSeek.php';

$tg = new Telegram(BOT_TOKEN);
$deepseek = new DeepSeek(DEEPSEEK_API_KEY);

$offset = null;
echo "Бот запущен. Ожидание сообщений...\n";

while (true) {
    try {
        $updates = $tg->getUpdates($offset, 30);
    } catch (Exception $e) {
        echo date('Y-m-d H:i:s') . " getUpdates error: " . $e->getMessage() . "\n";
        sleep(5);
        continue;
    }

    foreach ($updates as $u) {
        $offset = (isset($u['update_id']) ? $u['update_id'] : 0) + 1;
        $msg = isset($u['message']) ? $u['message'] : null;
        if (!$msg) {
            continue;
        }

        $chatId = (int) $msg['chat']['id'];
        $chatType = isset($msg['chat']['type']) ? $msg['chat']['type'] : 'private';
        $userId = (int) (isset($msg['from']['id']) ? $msg['from']['id'] : 0);
        $username = isset($msg['from']['username']) ? $msg['from']['username'] : null;
        $text = trim((string) (isset($msg['text']) ? $msg['text'] : ''));

        try {
            if (Db::isWaiting($userId, $chatId)) {
                // Следующее сообщение после /son — текст сна
                if ($text === '') {
                    $tg->sendMessage($chatId, defined('BOT_MSG_SEND_DREAM') ? BOT_MSG_SEND_DREAM : 'Пришли текст сна одним сообщением.');
                    continue;
                }
                Db::clearWaiting($userId, $chatId);
                $dreamText = $text;
                echo date('Y-m-d H:i:s') . " [OK] Текст сна от user_id=$userId, отправка в DeepSeek...\n";
                $interpretation = $deepseek->interpretDream($dreamText);
                Db::addLog($userId, $username, $chatId, $chatType, $dreamText, $interpretation);
                // Ответ только в личку
                $tg->sendMessage($userId, $interpretation);
                // В группе — короткое уведомление
                if ($chatId !== $userId) {
                    $tg->sendMessage($chatId, defined('BOT_MSG_SENT_TO_DM') ? BOT_MSG_SENT_TO_DM : 'Расшифровка отправлена тебе в личные сообщения.');
                }
                continue;
            }

            if ($text === '/son' || $text === '/start') {
                if ($text === '/son') {
                    echo date('Y-m-d H:i:s') . " [OK] Команда /son от user_id=$userId\n";
                    Db::setWaiting($userId, $chatId);
                    $tg->sendMessage($chatId, defined('BOT_MSG_AFTER_SON') ? BOT_MSG_AFTER_SON : 'Опиши сон в следующем сообщении.');
                } else {
                    $tg->sendMessage($chatId, defined('BOT_MSG_START') ? BOT_MSG_START : 'Привет. Чтобы расшифровать сон, отправь команду /son и затем опиши сон следующим сообщением.');
                }
            }
        } catch (Exception $e) {
            echo date('Y-m-d H:i:s') . " [ОШИБКА] " . $e->getMessage() . "\n";
            $tg->sendMessage($chatId, defined('BOT_MSG_ERROR') ? BOT_MSG_ERROR : 'Произошла ошибка, попробуй позже.');
        }
    }
}
