<?php
/**
 * Тексты и промпты бота. Можно менять без правки config.php.
 */

$GLOBALS['DEEPSEEK_PROMPTS'] = array(
    'gadat' => 'Ты мастер Ицзин (Книги Перемен). Получаешь JSON: {"hexagram": N}, где N — номер гексаграммы (1–64) по King Wen. Дай подробное толкование этой гексаграммы: смысл, совет, как применить в жизни. Пиши на русском.',
    'vopros' => 'Ты толкователь по «Книге перемен» (И-Цзин). Пользователь задал вопрос, и ему выпала определённая гексаграмма. Свяжи толкование этой гексаграммы с его вопросом: дай совет, разъясни, что может означать этот ответ в его ситуации. Пиши на русском, по делу.',
    'nomer' => 'Ты эксперт по «Книге перемен» (И-Цзин). Пользователь запрашивает толкование гексаграммы по номеру. Дай каноническое толкование: название гексаграммы, основной смысл, ключевые советы. Пиши на русском, чётко и развёрнуто.',
    'tolkovanie' => 'Ты толкователь по «Книге перемен» (И-Цзин). Пользователь присылает текст: сон, ситуацию, символы, образы. Истолкуй это в духе И-Цзин: найди связь с принципами Книги перемен, дай развёрнутое толкование и совет. Пиши на русском, глубоко и по существу.',
);

if (!defined('BOT_MSG_START')) {
    define('BOT_MSG_START', "Привет. Я бот по Книге перемен (И-Цзин).\n\nВыбери действие кнопкой ниже:");
}
if (!defined('BOT_MSG_SPRAVKA')) {
    define('BOT_MSG_SPRAVKA', "Справка — Книга перемен\n\nГадание — 6 бросков → гексаграмма.\nТолкование — сны, ситуации.\nПо номеру — толкование гексаграммы 1–64.\nВопрос — задай вопрос.");
}
if (!defined('BOT_MSG_AFTER_VOPROS')) {
    define('BOT_MSG_AFTER_VOPROS', 'Напиши свой вопрос в следующем сообщении.');
}
if (!defined('BOT_MSG_AFTER_TOLKOVANIE')) {
    define('BOT_MSG_AFTER_TOLKOVANIE', 'Опиши сон, ситуацию или символы в следующем сообщении.');
}
if (!defined('BOT_MSG_AFTER_NOMER')) {
    define('BOT_MSG_AFTER_NOMER', 'Напиши номер гексаграммы (1–64).');
}
if (!defined('BOT_MSG_SEND_VOPROS')) {
    define('BOT_MSG_SEND_VOPROS', 'Пришли вопрос одним сообщением.');
}
if (!defined('BOT_MSG_SEND_TOLKOVANIE')) {
    define('BOT_MSG_SEND_TOLKOVANIE', 'Пришли текст (сон, ситуация) одним сообщением.');
}
if (!defined('BOT_MSG_SEND_NOMER')) {
    define('BOT_MSG_SEND_NOMER', 'Пришли номер гексаграммы от 1 до 64.');
}
if (!defined('BOT_MSG_NOMER_INVALID')) {
    define('BOT_MSG_NOMER_INVALID', 'Номер должен быть от 1 до 64. Попробуй ещё раз.');
}
if (!defined('BOT_MSG_SENT_TO_DM')) {
    define('BOT_MSG_SENT_TO_DM', 'Толкование отправлено тебе в личные сообщения.');
}
if (!defined('BOT_MSG_ERROR')) {
    define('BOT_MSG_ERROR', 'Произошла ошибка, попробуй позже.');
}

if (!defined('BOT_MSG_GADAT_START')) {
    define('BOT_MSG_GADAT_START', 'Для получения гексаграммы нужно сделать 6 бросков.');
}
if (!defined('BOT_MSG_GADAT_BTN_THROW')) {
    define('BOT_MSG_GADAT_BTN_THROW', 'Бросок');
}
if (!defined('BOT_MSG_GADAT_BTN_NEXT')) {
    define('BOT_MSG_GADAT_BTN_NEXT', 'Сделать %d-й бросок');
}
if (!defined('BOT_MSG_GADAT_LINE_YANG')) {
    define('BOT_MSG_GADAT_LINE_YANG', '———');
}
if (!defined('BOT_MSG_GADAT_LINE_YIN')) {
    define('BOT_MSG_GADAT_LINE_YIN', '—      —');
}
if (!defined('BOT_MSG_GADAT_LOOKUP')) {
    define('BOT_MSG_GADAT_LOOKUP', 'Ищем толкование…');
}

// Reply Keyboard: кнопки для главной (Гадание, Толкование, По номеру)
if (!defined('BOT_KEYBOARD_MAIN')) {
    define('BOT_KEYBOARD_MAIN', 'Гадание|Толкование|По номеру');
}
