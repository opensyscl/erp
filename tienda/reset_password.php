<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'config.php';

use PDOException;

$success = $error = "";
$token = $_GET['token'] ?? $_POST['token'] ?? null; // ← Asegura que el token sobreviva al POST

if (!$token) {
    $error = "⚠️ Token inválido.";
} else {
    $hashed_token = hash('sha256', $token);
    $stmt = $pdo->prepare("SELECT id, reset_expires FROM customers WHERE reset_token = ?");
    $stmt->execute([$hashed_token]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "⚠️ Token inválido o ya fue usado.";
    } elseif (strtotime($user['reset_expires']) < time()) {
        $error = "⚠️ El enlace ha expirado. Solicita uno nuevo.";
    }
}

// Procesar cambio de contraseña si no hay error previo y es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $error = "⚠️ La contraseña debe tener al menos 6 caracteres.";
    } elseif ($password !== $confirm_password) {
        $error = "⚠️ Las contraseñas no coinciden.";
    } else {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE customers SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
            $update->execute([$hashed_password, $user['id']]);
            $success = "✅ Tu contraseña ha sido restablecida correctamente. Ya puedes iniciar sesión.";
        } catch (PDOException $e) {
            $error = "⚠️ Hubo un error al actualizar la contraseña.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Restablecer contraseña</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    body {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
      font-family: Arial, sans-serif;
      background: linear-gradient(135deg, #ff3c3c, #ff7676);
    }
    .card {
      display: flex;
      width: 900px;
      height: 500px;
      background: #fff;
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
      border-radius: 12px;
      overflow: hidden;
    }
    .card img {
      width: 50%;
      object-fit: cover;
    }
    .form-container {
      width: 50%;
      padding: 40px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    h2 {
      margin-bottom: 20px;
      color: #333;
      font-size: 1.8rem;
    }
    .form-group {
      margin-bottom: 20px;
    }
    label {
      display: block;
      font-size: 14px;
      margin-bottom: 6px;
      color: #555;
    }
    input {
      width: 100%;
      padding: 12px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 14px;
    }
    button {
      width: 100%;
      padding: 12px;
      background: #ff3c3c;
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      cursor: pointer;
      transition: background 0.3s;
    }
    button:hover {
      background: #e63939;
    }
    .extra-links {
      text-align: center;
      margin-top: 20px;
    }
    .extra-links a {
      color: #ff3c3c;
      text-decoration: none;
      font-size: 14px;
    }
    .extra-links a:hover {
      text-decoration: underline;
    }
    .alert {
      padding: 12px;
      border-radius: 6px;
      margin-bottom: 15px;
      font-size: 14px;
      text-align: center;
    }
    .alert-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    .alert-error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
  </style>
</head>
<body>
  <div class="card">
    <img src="img/img.jpg" alt="Reset Password Image">
    <div class="form-container">
      <h2>Restablecer contraseña</h2>

      <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
      <?php elseif ($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
      <?php endif; ?>

      <?php if (!$success): ?>
        <form action="reset_password.php" method="POST">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          <div class="form-group">
            <label for="password">Nueva contraseña</label>
            <input type="password" name="password" id="password" required placeholder="Ingresa tu nueva contraseña">
          </div>
          <div class="form-group">
            <label for="confirm_password">Confirmar contraseña</label>
            <input type="password" name="confirm_password" id="confirm_password" required placeholder="Repite tu contraseña">
          </div>
          <button type="submit">Guardar nueva contraseña</button>
        </form>
      <?php endif; ?>

      <div class="extra-links">
        <a href="login.php">← Volver al inicio de sesión</a>
      </div>
    </div>
  </div>
</body>
</html>
