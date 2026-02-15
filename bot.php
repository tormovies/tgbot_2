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
require __DIR__ . '/texts.php';
require __DIR__ . '/lib/Db.php';
require __DIR__ . '/lib/Telegram.php';
require __DIR__ . '/lib/DeepSeek.php';
require __DIR__ . '/lib/Iching.php';

$tg = new Telegram(BOT_TOKEN);
$deepseek = new DeepSeek(DEEPSEEK_API_KEY);

/* Меню команд — настраивается через BotFather. setMyCommands перезаписывает и ломает отображение. */

$offset = null;
echo "Бот запущен. Ожидание сообщений...\n";

function formatLine($yangOrYin)
{
    return $yangOrYin === 'yang' 
        ? (defined('BOT_MSG_GADAT_LINE_YANG') ? BOT_MSG_GADAT_LINE_YANG : '———')
        : (defined('BOT_MSG_GADAT_LINE_YIN') ? BOT_MSG_GADAT_LINE_YIN : '— —');
}

function formatGadatLines(array $lines)
{
    $out = '';
    foreach ($lines as $i => $line) {
        $n = $i + 1;
        $label = $line === 'yang' ? 'ян' : 'инь';
        $out .= "{$n}) " . formatLine($line) . "  {$label}\n";
    }
    return trim($out);
}

function formatHexagram(array $lines)
{
    $out = '';
    for ($i = 5; $i >= 0; $i--) {
        $n = $i + 1;
        $label = $lines[$i] === 'yang' ? 'ян' : 'инь';
        $out .= "{$n}) " . formatLine($lines[$i]) . "  {$label}\n";
    }
    return trim($out);
}

$heartbeatFile = (defined('DATA_DIR') ? DATA_DIR : __DIR__ . '/data') . '/heartbeat';
while (true) {
    @file_put_contents($heartbeatFile, (string) time());
    try {
        $updates = $tg->getUpdates($offset, 30);
    } catch (Exception $e) {
        echo date('Y-m-d H:i:s') . " getUpdates error: " . $e->getMessage() . "\n";
        sleep(5);
        continue;
    }

    foreach ($updates as $u) {
        $offset = (isset($u['update_id']) ? $u['update_id'] : 0) + 1;

        // Обработка callback (inline-кнопки)
        $callback = isset($u['callback_query']) ? $u['callback_query'] : null;
        if ($callback) {
            $chatId = (int) $callback['message']['chat']['id'];
            $messageId = (int) $callback['message']['message_id'];
            $userId = (int) $callback['from']['id'];
            $username = isset($callback['from']['username']) ? $callback['from']['username'] : null;
            $data = isset($callback['data']) ? $callback['data'] : '';
            $callbackId = isset($callback['id']) ? $callback['id'] : '';

            try {
                $tg->answerCallbackQuery($callbackId);
            } catch (Exception $e) {
                /* игнор */
            }

            if (in_array($data, array('menu_gadat', 'menu_tolkovanie', 'menu_nomer'))) {
                $dmChatId = $userId;
                if ($data === 'menu_gadat') {
                    Db::setWaiting($userId, $dmChatId, 'gadat', array('lines' => array()));
                    $startText = defined('BOT_MSG_GADAT_START') ? BOT_MSG_GADAT_START : 'Гексаграмма состоит из 6 линий.';
                    $btnText = defined('BOT_MSG_GADAT_BTN_THROW') ? BOT_MSG_GADAT_BTN_THROW : 'Бросок';
                    $tg->sendMessage($dmChatId, $startText, '', array(array(array('text' => $btnText, 'callback_data' => 'gadat_throw'))));
                } elseif ($data === 'menu_tolkovanie') {
                    Db::setWaiting($userId, $dmChatId, 'tolkovanie');
                    $tg->sendMessage($dmChatId, defined('BOT_MSG_AFTER_TOLKOVANIE') ? BOT_MSG_AFTER_TOLKOVANIE : 'Опиши сон, ситуацию или символы.');
                } else {
                    Db::setWaiting($userId, $dmChatId, 'nomer');
                    $tg->sendMessage($dmChatId, defined('BOT_MSG_AFTER_NOMER') ? BOT_MSG_AFTER_NOMER : 'Напиши номер гексаграммы (1–64).');
                }
                if ($chatId !== $dmChatId) {
                    $tg->sendMessage($chatId, defined('BOT_MSG_SENT_TO_DM') ? BOT_MSG_SENT_TO_DM : 'Продолжение в личных сообщениях.');
                }
            } elseif ($data === 'gadat_throw') {
                $state = Db::getStateData($userId, $chatId);
                $isGadat = Db::getWaitingCommandKey($userId, $chatId) === 'gadat';
                if (!$isGadat || !is_array($state) || !isset($state['lines'])) {
                    continue;
                }
                $lines = $state['lines'];
                $nextThrow = count($lines) + 1;
                $line = mt_rand(0, 1) ? 'yang' : 'yin';
                $lines[] = $line;

                if (count($lines) < 6) {
                    Db::setWaiting($userId, $chatId, 'gadat', array('lines' => $lines));
                    $text = formatGadatLines($lines);
                    $btnNum = $nextThrow + 1;
                    $btnText = defined('BOT_MSG_GADAT_BTN_NEXT') ? sprintf(BOT_MSG_GADAT_BTN_NEXT, $btnNum) : "Сделать {$btnNum}-й бросок";
                    $keyboard = array(array(array('text' => $btnText, 'callback_data' => 'gadat_throw')));
                    try {
                        $tg->editMessageText($chatId, $messageId, $text, $keyboard);
                    } catch (Exception $e) {
                        echo date('Y-m-d H:i:s') . " [ОШИБКА] editMessage: " . $e->getMessage() . "\n";
                    }
                } else {
                    Db::clearWaiting($userId, $chatId);
                    $hexNum = Iching::hexagramNumber($lines);
                    $hexagramText = formatHexagram($lines);
                    if ($hexNum > 0) {
                        $hexagramText = "Гексаграмма №{$hexNum}\n\n" . $hexagramText;
                    }
                    try {
                        $tg->editMessageText($chatId, $messageId, $hexagramText, array());
                    } catch (Exception $e) {
                        echo date('Y-m-d H:i:s') . " [ОШИБКА] editMessage: " . $e->getMessage() . "\n";
                    }
                    $lookupMsg = defined('BOT_MSG_GADAT_LOOKUP') ? BOT_MSG_GADAT_LOOKUP : 'Ищем толкование…';
                    $tg->sendMessage($chatId, $lookupMsg);
                    $chatType = isset($callback['message']['chat']['type']) ? $callback['message']['chat']['type'] : 'private';
                    try {
                        echo date('Y-m-d H:i:s') . " [OK] /gadat от user_id=$userId, гексаграмма №{$hexNum}, DeepSeek...\n";
                        $interpretation = $deepseek->askHexagram($lines);
                        Db::addLog($userId, $username, $chatId, $chatType, 'gadat ' . json_encode($lines), $interpretation);
                        $tg->sendMessage($userId, $interpretation);
                        if ($chatId !== $userId) {
                            $tg->sendMessage($chatId, defined('BOT_MSG_SENT_TO_DM') ? BOT_MSG_SENT_TO_DM : 'Толкование отправлено в личные сообщения.');
                        }
                    } catch (Exception $e) {
                        echo date('Y-m-d H:i:s') . " [ОШИБКА] " . $e->getMessage() . "\n";
                        $tg->sendMessage($chatId, defined('BOT_MSG_ERROR') ? BOT_MSG_ERROR : 'Произошла ошибка, попробуй позже.');
                    }
                }
            }
            continue;
        }

        // Обработка сообщений
        $msg = isset($u['message']) ? $u['message'] : null;
        if (!$msg) {
            continue;
        }

        $chatId = (int) $msg['chat']['id'];
        $chatType = isset($msg['chat']['type']) ? $msg['chat']['type'] : 'private';
        $userId = (int) (isset($msg['from']['id']) ? $msg['from']['id'] : 0);
        $username = isset($msg['from']['username']) ? $msg['from']['username'] : null;
        $text = trim((string) (isset($msg['text']) ? $msg['text'] : ''));

        // Кнопки Reply Keyboard
        $keyboardMap = array('Предсказание 🐲' => '/gadat', 'Толкование снов 🐳' => '/tolkovanie', 'Толкование по цифрам 🎲' => '/nomer');
        if (isset($keyboardMap[$text])) {
            $text = $keyboardMap[$text];
        }

        try {
            // Любая команда (/) — сбросить ожидание и выйти из зависшего состояния
            $isCommand = preg_match('/^\/(start|gadat|vopros|nomer|tolkovanie|spravka|post_menu|chat_id)(\s|$)/', $text);
            if ($isCommand) {
                Db::clearWaiting($userId, $chatId);
            }

            // Обработка ожидаемого ввода
            if (Db::isWaiting($userId, $chatId)) {
                $waitKey = Db::getWaitingCommandKey($userId, $chatId);
                if ($waitKey === 'gadat') {
                    continue;
                }
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
                $mainKeyboard = array();
                if (defined('BOT_KEYBOARD_MAIN')) {
                    foreach (explode("\n", BOT_KEYBOARD_MAIN) as $line) {
                        $row = array();
                        foreach (explode('|', $line) as $btn) {
                            $t = trim($btn);
                            if ($t !== '') {
                                $row[] = array('text' => $t);
                            }
                        }
                        if (!empty($row)) {
                            $mainKeyboard[] = $row;
                        }
                    }
                }
                $tg->sendMessage($chatId, defined('BOT_MSG_START') ? BOT_MSG_START : 'Привет. Выбери действие.', '', null, $mainKeyboard);
                continue;
            }
            if ($text === '/spravka') {
                $tg->sendMessage($chatId, defined('BOT_MSG_SPRAVKA') ? BOT_MSG_SPRAVKA : '/gadat, /vopros, /nomer, /tolkovanie');
                continue;
            }
            if (preg_match('/^\/chat_id(@\w+)?$/i', $text)) {
                $tg->sendMessage($chatId, "chat_id этого чата: {$chatId}");
                continue;
            }
            if (preg_match('/^\/post_menu(@\w+)?$/i', $text)) {
                $groupChatId = defined('GROUP_CHAT_ID') ? GROUP_CHAT_ID : null;
                if (empty($groupChatId)) {
                    $tg->sendMessage($chatId, 'GROUP_CHAT_ID не задан в config. Узнай chat_id: /chat_id в группе, добавь в config.');
                    continue;
                }
                $postText = defined('BOT_MSG_GROUP_POST') ? BOT_MSG_GROUP_POST : 'Добро пожаловать!';
                $inlineKb = array();
                if (defined('BOT_GROUP_BUTTONS')) {
                    foreach (explode("\n", BOT_GROUP_BUTTONS) as $line) {
                        $line = trim($line);
                        if ($line === '') continue;
                        $row = array();
                        foreach (explode(';', $line) as $pair) {
                            $pair = trim($pair);
                            $p = strpos($pair, '|');
                            if ($p !== false) {
                                $btnText = trim(substr($pair, 0, $p));
                                $url = trim(substr($pair, $p + 1));
                                if ($btnText !== '' && $url !== '') {
                                    $row[] = array('text' => $btnText, 'url' => $url);
                                }
                            }
                        }
                        if (!empty($row)) {
                            $inlineKb[] = $row;
                        }
                    }
                }
                $tg->sendMessage($groupChatId, $postText, '', !empty($inlineKb) ? $inlineKb : null);
                continue;
            }

            if ($text === '/gadat') {
                Db::setWaiting($userId, $chatId, 'gadat', array('lines' => array()));
                $startText = defined('BOT_MSG_GADAT_START') ? BOT_MSG_GADAT_START : 'Для получения гексаграммы нужно сделать 6 бросков.';
                $btnText = defined('BOT_MSG_GADAT_BTN_THROW') ? BOT_MSG_GADAT_BTN_THROW : 'Бросок';
                $keyboard = array(array(array('text' => $btnText, 'callback_data' => 'gadat_throw')));
                $tg->sendMessage($chatId, $startText, '', $keyboard);
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
