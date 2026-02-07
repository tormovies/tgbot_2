<?php
session_start();
if (empty($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

$config = dirname(__DIR__) . '/config.php';
require $config;
require dirname(__DIR__) . '/lib/Db.php';

$table = isset($_GET['table']) ? $_GET['table'] : 'logs';
$tables = Db::getLogTableNames();
if (!in_array($table, $tables, true)) {
    $table = 'logs';
}
$logs = Db::getLogs(200, $table);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Логи — Книга перемен</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: sans-serif; margin: 0; padding: 1rem; background: #f5f5f5; }
        .top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        a { color: #06c; }
        .card { background: #fff; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .meta { font-size: 0.85rem; color: #666; margin-bottom: 0.5rem; }
        .dream { margin-bottom: 0.75rem; padding: 0.5rem; background: #f9f9f9; border-left: 3px solid #ccc; white-space: pre-wrap; word-break: break-word; }
        .interp { padding: 0.5rem; background: #f0f7ff; border-left: 3px solid #06c; white-space: pre-wrap; word-break: break-word; }
        .empty { color: #666; }
    </style>
</head>
<body>
    <div class="top">
        <h1>Запросы и ответы</h1>
        <a href="logout.php">Выйти</a>
    </div>
    <?php if (count($tables) > 1): ?>
    <form method="get" style="margin-bottom: 1rem;">
        <label>Таблица: </label>
        <select name="table" onchange="this.form.submit()">
            <?php foreach ($tables as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>" <?= $t === $table ? 'selected' : '' ?>>
                    <?= $t === 'logs' ? 'Текущая (logs)' : htmlspecialchars($t) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>
    <?php if (empty($logs)): ?>
        <p class="empty">Пока нет записей.</p>
    <?php else: ?>
        <?php foreach ($logs as $row): ?>
            <div class="card">
                <div class="meta">
                    <?= htmlspecialchars($row['created_at']) ?>
                    · user_id <?= (int) $row['user_id'] ?>
                    <?php if ($row['username']): ?> (<?= htmlspecialchars($row['username']) ?>)<?php endif; ?>
                    · чат <?= (int) $row['chat_id'] ?> (<?= htmlspecialchars($row['chat_type'] ?? '') ?>)
                </div>
                <div class="dream"><strong>Запрос:</strong><br><?= htmlspecialchars($row['dream_text']) ?></div>
                <div class="interp"><strong>Толкование:</strong><br><?= htmlspecialchars($row['interpretation']) ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
