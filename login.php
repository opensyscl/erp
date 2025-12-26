<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

session_start();
require_once "config.php";

// Si ya hay sesión iniciada, redirige al launcher
if (isset($_SESSION['user_id'])) {
    header("Location: launcher.php");
    exit;
}

$login_error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $login_error = "Por favor ingresa usuario y contraseña.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header("Location: launcher.php");
                exit;
            } else {
                $login_error = "Usuario o contraseña incorrectos.";
            }
        } catch (Exception $e) {
            $login_error = "Error del servidor. Intenta más tarde.";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Iniciar Sesión</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin:0; padding:0; }
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #ff0000, #9b2a2a);
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 20px;
    }
    .card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
      overflow: hidden;
      display: flex;
      flex-direction: row;
      width: 100%;
      max-width: 650px;
    }
    .card img {
      width: 45%;
      object-fit: cover;
      display: block;
    }
    .form-container {
      width: 55%;
      padding: 40px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    h2 {
      text-align: center;
      margin-bottom: 20px;
      color: #222;
      font-weight: 600;
    }
    form {
      display: flex;
      flex-direction: column;
    }
    input {
      width: 100%;
      padding: 14px;
      margin-bottom: 14px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 15px;
      transition: border-color 0.2s;
    }
    input:focus {
      border-color: #2575fc;
      outline: none;
    }
    button {
      width: 100%;
      padding: 14px;
      background: #2575fc;
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 500;
      cursor: pointer;
      transition: background 0.3s ease;
      margin-top: 8px;
    }
    button:hover {
      background: #1b5edb;
    }
    .alert {
      padding: 12px;
      border-radius: 6px;
      margin-bottom: 15px;
      text-align: center;
      font-size: 14px;
      background: #fdecea;
      color: #b00020;
    }
    @media (max-width: 768px) {
      .card {
        flex-direction: column;
      }
      .card img {
        width: 100%;
        height: 200px;
      }
      .form-container {
        width: 100%;
        padding: 20px;
      }
    }
  </style>
</head>
<body>
  <div class="card">
    <img src="img/img.jpg" alt="Login Image">
    <div class="form-container">
      <form method="post" autocomplete="off">
        <h2>Iniciar Sesión</h2>
        <?php if ($login_error): ?>
          <div class="alert"><?= htmlspecialchars($login_error) ?></div>
        <?php endif; ?>
        <input type="text" name="username" placeholder="Usuario" required autofocus>
        <input type="password" name="password" placeholder="Contraseña" required>
        <button type="submit">Ingresar</button>
      </form>
    </div>
  </div>
</body>
</html>
