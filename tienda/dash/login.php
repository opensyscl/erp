<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "../config.php";

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($usuario) || empty($password)) {
        $error = "Por favor, completa todos los campos.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "Usuario no encontrado.";
        } else {
            // Debug hashes
            // echo "Hash en DB: " . $user['password'] . "<br>";
            // echo "Password ingresada: " . $password . "<br>";
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];

                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Contraseña incorrecta.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Login - Tiendas Listto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f8; padding: 30px; }
        .login-container { max-width: 400px; margin: auto; background: #fff; padding: 20px; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h2 { text-align: center; margin-bottom: 20px; }
        .form-control { margin-bottom: 15px; }
        .btn-login { width: 100%; background-color: #3F51B5; color: white; border: none; padding: 10px; border-radius: 4px; }
        .btn-login:hover { background-color: #2c3e9e; }
        .error-message { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 15px; border-radius: 4px; }
    </style>
</head>
<body>

<div class="login-container">
    <h2>Iniciar sesión</h2>

    <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" autocomplete="off">
        <input type="text" name="usuario" class="form-control" placeholder="Usuario" required autofocus>
        <input type="password" name="password" class="form-control" placeholder="Contraseña" required>
        <button type="submit" class="btn-login">Entrar</button>
    </form>
</div>

</body>
</html>
