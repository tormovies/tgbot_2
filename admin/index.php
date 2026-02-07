<?php
/**
 * Вход в админку: логин + пароль из config.php
 */
session_start();

$config = dirname(__DIR__) . '/config.php';
if (!is_file($config)) {
    die('Создайте config.php из config.sample.php');
}
require $config;

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($login === ADMIN_LOGIN && $pass === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
        header('Location: logs.php');
        exit;
    }
    $error = 'Неверный логин или пароль.';
}

if (!empty($_SESSION['admin'])) {
    header('Location: logs.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход — Книга перемен</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: sans-serif; max-width: 320px; margin: 3rem auto; padding: 0 1rem; }
        h1 { font-size: 1.25rem; margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.25rem; }
        input { width: 100%; padding: 0.5rem; margin-bottom: 1rem; }
        button { width: 100%; padding: 0.6rem; cursor: pointer; }
        .error { color: #c00; margin-bottom: 1rem; font-size: 0.9rem; }
    </style>
</head>
<body>
    <h1>Вход — Книга перемен</h1>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form method="post">
        <label>Логин</label>
        <input type="text" name="login" autocomplete="username" required>
        <label>Пароль</label>
        <input type="password" name="password" autocomplete="current-password" required>
        <button type="submit">Войти</button>
    </form>
</body>
</html>
