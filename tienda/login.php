<?php
session_start();
require_once "config.php";

// PHPMailer includes
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$login_error = '';
$register_error = '';
$register_success = '';
$active_tab = 'login'; // Valor por defecto: pestaña login activa

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        // Lógica de login
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $login_error = "Correo no válido.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, password FROM customers WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['customer_id'] = $user['id'];
                    header("Location: index.php");
                    exit;
                } else {
                    $login_error = "Correo o contraseña incorrectos.";
                }
            } catch (Exception $e) {
                $login_error = "Error del servidor. Intenta más tarde.";
            }
        }

    } elseif ($action === 'register') {
        // Lógica de registro
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $extra_address = trim($_POST['extra_address'] ?? '');
        $number = trim($_POST['number']);
        $block = trim($_POST['block'] ?? '');
        $apartment = trim($_POST['apartment'] ?? '');
        $password = $_POST['password'];
        $password2 = $_POST['password2'];

        $active_tab = 'register'; // Si ocurre un error, mantenemos esta pestaña activa

        // Validaciones
        if (empty($name) || empty($email) || empty($phone) || empty($address) || empty($number) || empty($password) || empty($password2)) {
            $register_error = "Debes completar todos los campos obligatorios.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $register_error = "Correo no válido.";
        } elseif ($password !== $password2) {
            $register_error = "Las contraseñas no coinciden.";
        } elseif (strlen($password) < 6) {
            $register_error = "La contraseña debe tener al menos 6 caracteres.";
        } else {
            try {
                // Verificar que el correo no esté ya registrado
                $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $register_error = "El correo ya está registrado.";
                } else {
                    // Insertar usuario
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt2 = $pdo->prepare("
                        INSERT INTO customers
                          (name, email, phone, address, extra_address, number, block, apartment, password, created_at)
                        VALUES
                          (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $now = date('Y-m-d H:i:s');
                    $stmt2->execute([
                        $name, $email, $phone, $address, $extra_address,
                        $number, $block, $apartment, $hashed, $now
                    ]);

                    // Enviar correo de bienvenida
                    try {
                        $mail = new PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host       = "mail.tiendaslistto.cl";
                        $mail->SMTPAuth   = true;
                        $mail->Username   = "contacto@tiendaslistto.cl";
                        $mail->Password   = "TListto.2025";
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;
                        $mail->CharSet    = 'UTF-8';

                        $mail->setFrom("contacto@tiendaslistto.cl", "Listto Soporte");
                        $mail->addAddress($email, $name);
                        $mail->isHTML(true);
                        $mail->Subject = "Bienvenido a Listto!";
                        $mail->Body = "
                            <p>Hola $name,</p>
                            <p>Gracias por registrarte en Listto.</p>
                            <p>Ya puedes iniciar sesión con tu correo electrónico.</p>
                            <p>¡Te damos la bienvenida al equipo!</p>
                        ";
                        $mail->send();
                    } catch (Exception $e) {
                        // error_log($mail->ErrorInfo);
                    }

                    $register_success = "✔ Listto! Te has registrado con éxito.";
                    $register_error = '';
                    $active_tab = 'login'; // Forzar login como pestaña activa tras registro
                }
            } catch (Exception $e) {
                $register_error = "Error del servidor al registrar usuario.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Login / Registro</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
/* --- RAÍZ Y VARIABLES GLOBALES (ESTILO APLICADO) --- */
:root {
    --background-start: #f4f4f5;
    --background-end: #e9e9ed;
    --card-background: rgba(255, 255, 255, 0.65);
    --card-border: rgba(255, 255, 255, 0.9);
    --card-shadow: rgba(0, 0, 0, 0.07);
    --text-primary: #1a202c;
    --text-secondary: #4a5568;
    --accent-color: #e81c1c;
    --accent-color-dark: #c01515;
}

/* --- RESET & BASE --- */
* { box-sizing: border-box; margin:0; padding:0; }

body {
    font-family: 'Inter', sans-serif;
    background-color: var(--background-start);
    background-image: linear-gradient(135deg, var(--background-start) 0%, var(--background-end) 100%);
    color: var(--text-primary);
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 20px;
}

/* --- TARJETA DE FORMULARIO (CON EFECTO GLASS) --- */
.card {
    background: var(--card-background);
    border: 1px solid var(--card-border);
    border-radius: 18px;
    box-shadow: 0 8px 32px 0 var(--card-shadow);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    overflow: hidden;
    display: flex;
    flex-direction: row;
    width: 100%;
    max-width: 950px;
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
    color: var(--text-primary);
    font-weight: 700;
    font-size: 24px;
}

/* --- PESTAÑAS (TABS) --- */
.tabs {
    display: flex;
    justify-content: center;
    margin-bottom: 30px;
    border-bottom: 1px solid #ddd;
}

.tab {
    flex: 1;
    text-align: center;
    padding: 12px;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    font-weight: 600;
    color: var(--text-secondary);
    transition: all 0.3s ease;
    margin-bottom: -1px; /* Para que el borde de la tab se alinee con el borde de la sección */
}

.tab.active {
    border-color: var(--accent-color);
    color: var(--accent-color);
}

.tab:not(.active):hover {
    color: var(--text-primary);
}

/* --- FORMULARIO Y ENTRADAS --- */
form {
    display: none;
    flex-direction: column;
}

form.active {
    display: flex;
}

input {
    width: 100%;
    padding: 14px 20px;
    margin-bottom: 14px;
    border: 1px solid transparent;
    border-radius: 10px;
    font-size: 15px;
    background-color: rgba(255, 255, 255, 0.5);
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

input:focus {
    outline: none;
    border-color: var(--accent-color);
    background-color: #fff;
    box-shadow: 0 0 0 3px rgba(232, 28, 28, 0.2);
}

/* --- BOTÓN MODERNIZADO --- */
button {
    width: 100%;
    padding: 14px;
    background-image: linear-gradient(145deg, var(--accent-color), var(--accent-color-dark));
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    margin-top: 8px;
}

button:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(232, 28, 28, 0.3);
}

/* --- ALERTAS Y ENLACES --- */
.alert {
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
    text-align: center;
    font-size: 14px;
    font-weight: 500;
}

.alert.error {
    background: rgba(232, 28, 28, 0.1);
    color: var(--accent-color-dark);
}

.alert.success {
    background: rgba(76, 175, 80, 0.1);
    color: #388E3C;
}

.extra-links {
    text-align: center;
    margin-top: 20px;
    font-size: 14px;
}

.extra-links a {
    color: var(--accent-color);
    text-decoration: none;
    font-weight: 600;
}

/* --- RESPONSIVE --- */
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
        padding: 30px 20px;
    }
}
  </style>
</head>
<body>
<div class="card">
  <img src="img/img.jpg" alt="Login Image">
  <div class="form-container">
    <div class="tabs">
      <div class="tab <?= $active_tab === 'login' ? 'active' : '' ?>" data-target="login-form">Iniciar Sesión</div>
      <div class="tab <?= $active_tab === 'register' ? 'active' : '' ?>" data-target="register-form">Registrarse</div>
    </div>

    <!-- Login -->
    <form id="login-form" class="<?= $active_tab === 'login' ? 'active' : '' ?>" method="post" autocomplete="off">
      <h2>Bienvenido de nuevo</h2>
      <?php if($register_success): ?>
        <div class="alert success"><?= htmlspecialchars($register_success) ?></div>
      <?php endif; ?>
      <?php if ($login_error): ?>
        <div class="alert error"><?= htmlspecialchars($login_error) ?></div>
      <?php endif; ?>
      <input type="hidden" name="action" value="login">
      <input type="email" name="email" placeholder="Correo electrónico" required>
      <input type="password" name="password" placeholder="Contraseña" required>
      <button type="submit">Ingresar</button>
      <div class="extra-links">
        <a href="forgot_password.php">¿Olvidaste tu contraseña?</a>
      </div>
    </form>

    <!-- Registro -->
    <form id="register-form" class="<?= $active_tab === 'register' ? 'active' : '' ?>" method="post" autocomplete="off">
      <h2>Crea tu cuenta</h2>
      <?php if ($register_error): ?>
        <div class="alert error"><?= htmlspecialchars($register_error) ?></div>
      <?php endif; ?>
      <input type="hidden" name="action" value="register">
      <input type="text" name="name" placeholder="Nombre completo" required>
      <input type="email" name="email" placeholder="Correo electrónico" required>
      <input type="text" name="phone" placeholder="Teléfono" required>
      <input type="text" name="address" placeholder="Dirección principal" required>
      <input type="text" name="extra_address" placeholder="Dirección adicional (opcional)">
      <input type="text" name="number" placeholder="Número" required>
      <input type="text" name="block" placeholder="Bloque (opcional)">
      <input type="text" name="apartment" placeholder="Departamento (opcional)">
      <input type="password" name="password" placeholder="Contraseña" required>
      <input type="password" name="password2" placeholder="Confirmar contraseña" required>
      <button type="submit">Registrarse</button>
    </form>
  </div>
</div>

<script>
  const tabs = document.querySelectorAll('.tab');
  const forms = document.querySelectorAll('form');
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      forms.forEach(f => f.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById(tab.dataset.target).classList.add('active');
    });
  });
</script>

</body>
</html>
