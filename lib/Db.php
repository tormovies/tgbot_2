<?php
/**
 * SQLite: логи запросов/ответов и состояние ожидания ввода. Совместимость: PHP 5.6+
 */
class Db
{
    private static $pdo = null;

    public static function get()
    {
        if (self::$pdo === null) {
            $dir = defined('DATA_DIR') ? DATA_DIR : (__DIR__ . '/../data');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $path = rtrim($dir, '/\\') . '/bot.sqlite';
            self::$pdo = new PDO('sqlite:' . $path);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::migrate();
        }
        return self::$pdo;
    }

    private static function migrate()
    {
        $pdo = self::$pdo;
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                username TEXT,
                chat_id INTEGER NOT NULL,
                chat_type TEXT,
                dream_text TEXT NOT NULL,
                interpretation TEXT NOT NULL,
                created_at TEXT NOT NULL
            )
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS state (
                user_id INTEGER NOT NULL,
                chat_id INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                PRIMARY KEY (user_id, chat_id)
            )
        ");
        try {
            $pdo->exec("ALTER TABLE state ADD COLUMN command_key TEXT DEFAULT 'son'");
        } catch (Exception $e) {
            /* колонка уже есть */
        }
    }

    public static function setWaiting($userId, $chatId, $commandKey = 'tolkovanie')
    {
        $pdo = self::get();
        $st = $pdo->prepare("REPLACE INTO state (user_id, chat_id, command_key, created_at) VALUES (?, ?, ?, datetime('now'))");
        $st->execute(array($userId, $chatId, $commandKey));
    }

    /** Возвращает ключ команды (son, mood, …) или false, если не ждём ввод. */
    public static function getWaitingCommandKey($userId, $chatId)
    {
        $pdo = self::get();
        $st = $pdo->prepare("SELECT command_key FROM state WHERE user_id = ? AND chat_id = ?");
        $st->execute(array($userId, $chatId));
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        return isset($row['command_key']) && $row['command_key'] !== '' ? $row['command_key'] : 'tolkovanie';
    }

    public static function isWaiting($userId, $chatId)
    {
        return self::getWaitingCommandKey($userId, $chatId) !== false;
    }

    public static function clearWaiting($userId, $chatId)
    {
        self::get()->prepare("DELETE FROM state WHERE user_id = ? AND chat_id = ?")
            ->execute(array($userId, $chatId));
    }

    private static $LOGS_TABLE_SCHEMA = "
        CREATE TABLE %s (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            username TEXT,
            chat_id INTEGER NOT NULL,
            chat_type TEXT,
            dream_text TEXT NOT NULL,
            interpretation TEXT NOT NULL,
            created_at TEXT NOT NULL
        )
    ";

    public static function addLog($userId, $username, $chatId, $chatType, $dreamText, $interpretation)
    {
        $pdo = self::get();
        $pdo->prepare("
            INSERT INTO logs (user_id, username, chat_id, chat_type, dream_text, interpretation, created_at)
            VALUES (?, ?, ?, ?, ?, ?, datetime('now'))
        ")->execute(array($userId, $username, $chatId, $chatType, $dreamText, $interpretation));

        $limit = defined('LOGS_ARCHIVE_AFTER') ? (int) LOGS_ARCHIVE_AFTER : 0;
        if ($limit > 0) {
            $n = (int) $pdo->query("SELECT COUNT(*) FROM logs")->fetchColumn();
            if ($n >= $limit) {
                self::rotateLogsTable($pdo);
            }
        }
    }

    private static function rotateLogsTable($pdo)
    {
        $archiveName = 'logs_archive_' . date('Ymd_His');
        $pdo->exec("ALTER TABLE logs RENAME TO " . self::safeTableName($archiveName));
        $pdo->exec(sprintf(self::$LOGS_TABLE_SCHEMA, 'logs'));
    }

    private static function safeTableName($name)
    {
        $s = preg_replace('/[^a-zA-Z0-9_]/', '', $name);
        return $s !== '' ? $s : 'logs_archive';
    }

    public static function getLogTableNames()
    {
        $pdo = self::get();
        $st = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND (name='logs' OR name LIKE 'logs_archive_%') ORDER BY name DESC");
        $out = array();
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[] = $row['name'];
        }
        return $out;
    }

    public static function getLogs($limit = 200, $table = 'logs')
    {
        $table = self::safeTableName($table);
        $pdo = self::get();
        $st = $pdo->prepare("
            SELECT id, user_id, username, chat_id, chat_type, dream_text, interpretation, created_at
            FROM " . $table . " ORDER BY id DESC LIMIT ?
        ");
        $st->execute(array($limit));
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
