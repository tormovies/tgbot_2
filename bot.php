<?php
/**
 * Книга перемен — бот для толкования по И-Цзин.
 * Запуск: php bot.php
 * Команды: /gadat, /vopros, /nomer, /tolkovanie, /spravka
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
            // Обработка ожидаемого ввода
            if (Db::isWaiting($userId, $chatId)) {
                $waitKey = Db::getWaitingCommandKey($userId, $chatId);
                if ($text === '') {
                    $msgMap = array(
                        'vopros' => 'BOT_MSG_SEND_VOPROS',
                        'tolkovanie' => 'BOT_MSG_SEND_TOLKOVANIE',
                        'nomer' => 'BOT_MSG_SEND_NOMER',
                    );
                    $key = isset($msgMap[$waitKey]) ? $msgMap[$waitKey] : 'BOT_MSG_SEND_VOPROS';
                    $tg->sendMessage($chatId, defined($key) ? constant($key) : 'Пришли текст одним сообщением.');
                    continue;
                }

                $commandKey = Db::getWaitingCommandKey($userId, $chatId);
                Db::clearWaiting($userId, $chatId);

                if ($commandKey === 'nomer') {
                    $num = (int) $text;
                    if ($num < 1 || $num > 64) {
                        $tg->sendMessage($chatId, defined('BOT_MSG_NOMER_INVALID') ? BOT_MSG_NOMER_INVALID : 'Номер должен быть от 1 до 64.');
                        continue;
                    }
                    $userContent = "Гексаграмма №{$num}";
                    $dreamText = "nomer {$num}";
                } elseif ($commandKey === 'vopros') {
                    $hexNum = mt_rand(1, 64);
                    $userContent = "Вопрос: {$text}\n\nВыпала гексаграмма №{$hexNum}.";
                    $dreamText = "[вопрос] {$text} (гексаграмма {$hexNum})";
                } else {
                    $userContent = $text;
                    $dreamText = $text;
                }

                echo date('Y-m-d H:i:s') . " [OK] user_id=$userId (команда=$commandKey), DeepSeek...\n";
                $interpretation = $deepseek->ask($userContent, $commandKey);
                Db::addLog($userId, $username, $chatId, $chatType, $dreamText, $interpretation);
                $tg->sendMessage($userId, $interpretation);
                if ($chatId !== $userId) {
                    $tg->sendMessage($chatId, defined('BOT_MSG_SENT_TO_DM') ? BOT_MSG_SENT_TO_DM : 'Толкование отправлено в личные сообщения.');
                }
                continue;
            }

            // Команды
            if ($text === '/start') {
                $tg->sendMessage($chatId, defined('BOT_MSG_START') ? BOT_MSG_START : 'Привет. /spravka — список команд.');
                continue;
            }
            if ($text === '/spravka') {
                $tg->sendMessage($chatId, defined('BOT_MSG_SPRAVKA') ? BOT_MSG_SPRAVKA : '/gadat, /vopros, /nomer, /tolkovanie');
                continue;
            }

            if ($text === '/gadat') {
                $hexNum = mt_rand(1, 64);
                echo date('Y-m-d H:i:s') . " [OK] /gadat от user_id=$userId, выпало №{$hexNum}\n";
                $userContent = "Выпала гексаграмма №{$hexNum}.";
                $interpretation = $deepseek->ask($userContent, 'gadat');
                Db::addLog($userId, $username, $chatId, $chatType, "gadat (гексаграмма {$hexNum})", $interpretation);
                $tg->sendMessage($userId, $interpretation);
                if ($chatId !== $userId) {
                    $tg->sendMessage($chatId, defined('BOT_MSG_SENT_TO_DM') ? BOT_MSG_SENT_TO_DM : 'Толкование отправлено в личные сообщения.');
                }
                continue;
            }

            if (preg_match('/^\/nomer\s+(\d+)$/i', $text, $m)) {
                $num = (int) $m[1];
                if ($num < 1 || $num > 64) {
                    $tg->sendMessage($chatId, defined('BOT_MSG_NOMER_INVALID') ? BOT_MSG_NOMER_INVALID : 'Номер должен быть от 1 до 64.');
                    continue;
                }
                echo date('Y-m-d H:i:s') . " [OK] /nomer {$num} от user_id=$userId\n";
                $userContent = "Гексаграмма №{$num}";
                $interpretation = $deepseek->ask($userContent, 'nomer');
                Db::addLog($userId, $username, $chatId, $chatType, "nomer {$num}", $interpretation);
                $tg->sendMessage($userId, $interpretation);
                if ($chatId !== $userId) {
                    $tg->sendMessage($chatId, defined('BOT_MSG_SENT_TO_DM') ? BOT_MSG_SENT_TO_DM : 'Толкование отправлено в личные сообщения.');
                }
                continue;
            }

            if ($text === '/nomer') {
                Db::setWaiting($userId, $chatId, 'nomer');
                $tg->sendMessage($chatId, defined('BOT_MSG_AFTER_NOMER') ? BOT_MSG_AFTER_NOMER : 'Напиши номер гексаграммы (1–64).');
                continue;
            }

            if ($text === '/vopros') {
                Db::setWaiting($userId, $chatId, 'vopros');
                $tg->sendMessage($chatId, defined('BOT_MSG_AFTER_VOPROS') ? BOT_MSG_AFTER_VOPROS : 'Напиши свой вопрос в следующем сообщении.');
                continue;
            }

            if ($text === '/tolkovanie') {
                Db::setWaiting($userId, $chatId, 'tolkovanie');
                $tg->sendMessage($chatId, defined('BOT_MSG_AFTER_TOLKOVANIE') ? BOT_MSG_AFTER_TOLKOVANIE : 'Опиши сон, ситуацию или символы в следующем сообщении.');
                continue;
            }

        } catch (Exception $e) {
            echo date('Y-m-d H:i:s') . " [ОШИБКА] " . $e->getMessage() . "\n";
            $tg->sendMessage($chatId, defined('BOT_MSG_ERROR') ? BOT_MSG_ERROR : 'Произошла ошибка, попробуй позже.');
        }
    }
}
