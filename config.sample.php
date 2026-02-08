<?php
/**
 * Книга перемен — системная конфигурация.
 * Скопируйте в config.php и заполните значения.
 * config.php не должен попадать в репозиторий.
 */

define('BOT_TOKEN', '');           // Токен от @BotFather
define('DEEPSEEK_API_KEY', '');   // Ключ API DeepSeek (обязательно)
define('DEEPSEEK_MAX_TOKENS', 800);  // 0 = без лимита

define('ADMIN_LOGIN', 'admin');
define('ADMIN_PASSWORD', '');

define('DATA_DIR', __DIR__ . '/data');
define('LOGS_ARCHIVE_AFTER', 1000);
