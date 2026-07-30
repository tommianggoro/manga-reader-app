<?php
require_once "config.php";
session_start();

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (($_POST["password"] ?? "") === ACCESS_PASSWORD) {
        $_SESSION["authenticated"] = true;
        header("Location: index.php");
        exit;
    } else {
        $error = "Password salah.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Manga Reader</title>
    <style>
        body { font-family: sans-serif; background: #1a1a1a; color: #eee; display: flex; height: 100vh; align-items: center; justify-content: center; }
        form { background: #2a2a2a; padding: 2rem; border-radius: 8px; }
        input { display: block; width: 100%; padding: 0.5rem; margin: 0.5rem 0; box-sizing: border-box; }
        button { padding: 0.5rem 1.5rem; background: #4a90d9; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .error { color: #ff6b6b; }
    </style>
</head>
<body>
    <form method="POST">
        <h2>Manga Reader Pribadi</h2>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <input type="password" name="password" placeholder="Password" required autofocus>
        <button type="submit">Masuk</button>
    </form>
</body>
</html>
