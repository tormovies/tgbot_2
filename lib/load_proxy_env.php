<?php
/**
 * Читает .proxy.env в корне проекта (рядом с bot.php): export VAR=value или VAR=value.
 * Пустые строки и # — пропуск. Файл может отсутствовать.
 */
function load_proxy_env($rootDir)
{
    $path = $rootDir . DIRECTORY_SEPARATOR . '.proxy.env';
    if (!is_file($path) || !is_readable($path)) {
        return;
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return;
    }
    $lines = preg_split('/\r\n|\r|\n/', $raw);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strncasecmp($line, 'export ', 7) === 0) {
            $line = trim(substr($line, 7));
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $name = trim(substr($line, 0, $eq));
        $value = trim(substr($line, $eq + 1));
        if ($name === '') {
            continue;
        }
        if (strlen($value) >= 2) {
            $q = $value[0];
            if (($q === '"' || $q === "'") && substr($value, -1) === $q) {
                $value = substr($value, 1, -1);
            }
        }
        putenv($name . '=' . $value);
    }
}
