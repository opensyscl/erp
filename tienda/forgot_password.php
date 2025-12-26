<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- Carga de archivos (asegúrate de que las rutas son correctas) ---
require 'config.php'; // Contiene tu conexión $pdo
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$success = $error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "⚠️ Debes ingresar un formato de correo válido.";
    } else {
        try {
            // 1. VERIFICAR SI EL CORREO EXISTE EN LA BASE DE DATOS
            $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // 2. SI EL USUARIO EXISTE, PROCEDER
            if ($user) {
                // Generar un token seguro
                $token = bin2hex(random_bytes(32));
                
                // 3. (IMPORTANTE) GUARDAR EL TOKEN HASH Y LA FECHA DE EXPIRACIÓN
                // Guardamos un hash del token por seguridad, no el token en texto plano.
                $hashed_token = hash('sha256', $token);
                $expiry_date = date('Y-m-d H:i:s', strtotime('+1 hour')); // El token expira en 1 hora

                $update_stmt = $pdo->prepare(
                    "UPDATE customers SET reset_token = ?, reset_expires = ? WHERE email = ?"

                );
                $update_stmt->execute([$hashed_token, $expiry_date, $email]);

                // --- Lógica de envío de correo (movida aquí dentro) ---
                $mail = new PHPMailer(true);
                
                // Configuración del servidor SMTP
                $mail->isSMTP();
            $mail->Host       = "mail.tiendaslistto.cl";   // 🔹 Cambiar por tu servidor SMTP
            $mail->SMTPAuth   = true;
            $mail->Username   = "contacto@tiendaslistto.cl"; // 🔹 Cambiar por tu usuario
            $mail->Password   = "TListto.2025";          // 🔹 Cambiar por tu contraseña
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

                $mail->CharSet    = 'UTF-8'; // Para caracteres especiales

                // Remitente y destinatario
                $mail->setFrom("contacto@tiendaslistto.cl", "Soporte Listto!");
                $mail->addAddress($email);

                // Contenido del correo
                $link = "https://tiendaslistto.cl/app/reset_password.php?token=$token";
                $mail->isHTML(true);
                $mail->Subject = "Hola! Es momento de recuperar tu contraseña en Listto!";
                $mail->Body    = "
                    <p>Hola,</p>
                    <p>Has solicitado restablecer tu contraseña. Haz clic en el siguiente enlace para continuar. Este enlace es válido por 1 hora.</p>
                    <p><a href='$link'>Restablecer mi contraseña</a></p>
                    <p>Si no solicitaste este cambio, puedes ignorar este correo de forma segura.</p>
                ";

                $mail->send();
            }

            // 4. MOSTRAR SIEMPRE UN MENSAJE DE ÉXITO
            // Por seguridad, siempre mostramos este mensaje, exista o no el correo.
            // Así evitamos que alguien pueda adivinar qué correos están registrados.
            $success = "📩 Si tu correo está registrado, recibirás un enlace de recuperación en breve.";

        } catch (Exception $e) {
            // Error genérico para no exponer detalles del servidor
            $error = "⚠️ Hubo un problema al procesar tu solicitud. Intenta de nuevo más tarde.";
            // Opcional: registrar el error real para ti
            // error_log("PHPMailer Error: {$mail->ErrorInfo}");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recuperar contraseña</title>
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
    h2 { margin-bottom: 20px; color: #333; font-size: 1.8rem; }
    .form-group { margin-bottom: 20px; }
    label { display: block; font-size: 14px; margin-bottom: 6px; color: #555; }
    input {
      width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 14px;
    }
    button {
      width: 100%; padding: 12px; background: #ff3c3c; color: #fff; border: none;
      border-radius: 8px; font-size: 16px; cursor: pointer; transition: background 0.3s;
    }
    button:hover { background: #e63939; }
    .extra-links { text-align: center; margin-top: 20px; }
    .extra-links a { color: #ff3c3c; text-decoration: none; font-size: 14px; }
    .extra-links a:hover { text-decoration: underline; }
    .alert {
      padding: 12px;
      border-radius: 6px;
      margin-bottom: 15px;
      font-size: 14px;
      text-align: center;
    }
    .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
  </style>
</head>
<body>
  <div class="card">
    <img src="img/img.jpg" alt="Forgot Password Image">
    <div class="form-container">
      <h2>Recuperar contraseña</h2>

      <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
      <?php endif; ?>

      <form action="forgot_password.php" method="POST">
        <div class="form-group">
          <label for="email">Correo electrónico</label>
          <input type="email" name="email" id="email" required placeholder="ejemplo@correo.com">
        </div>
        <button type="submit">Enviar enlace de recuperación</button>
      </form>

      <div class="extra-links">
        <a href="login.php">← Volver al inicio de sesión</a>
      </div>
    </div>
  </div>
</body>
</html>
