<?php
require 'config.php'; // Aquí se conecta a la DB
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_username'])) {
    header("Location: login.php");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $version = trim($_POST['version'] ?? '');
    if ($version) {
        $stmt = $pdo->prepare("INSERT INTO config (`name`, `value`) VALUES ('version', ?) 
                               ON DUPLICATE KEY UPDATE value = ?");
        $stmt->execute([$version, $version]);
        $message = 'Versión actualizada correctamente.';
    } else {
        $message = 'Por favor ingrese una versión válida.';
    }
}

// Obtener la versión actual
$stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
$stmt->execute();
$currentVersion = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Actualizar versión del sistema</title>
<style>
body { font-family: Arial, sans-serif; margin: 2rem; }
input { padding: 8px; font-size: 1rem; }
button { padding: 8px 16px; font-size: 1rem; margin-left: 0.5rem; }
.message { margin-top: 1rem; color: green; }
</style>
</head>
<body>
<h2>Actualizar versión del sistema</h2>
<form method="POST">
    <label>Versión actual: <strong><?php echo htmlspecialchars($currentVersion); ?></strong></label><br><br>
    <input type="text" name="version" placeholder="Nueva versión" required>
    <button type="submit">Actualizar</button>
</form>
<?php if ($message): ?>
<p class="message"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>
</body>
</html>
