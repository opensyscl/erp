<?php
// /erp/not_authorized.php - Página de Acceso Denegado
session_start();

// Opcional: Si quieres mantener el nombre de usuario o algún contexto de sesión
$username = $_SESSION['user_username'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Denegado - Listto! ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #334155;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            text-align: center;
        }
        .container {
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            max-width: 450px;
        }
        .icon {
            font-size: 4rem;
            color: #ef4444; /* Rojo */
            margin-bottom: 20px;
        }
        h1 {
            font-size: 1.8rem;
            color: #1e293b;
            margin-top: 0;
            margin-bottom: 10px;
        }
        p {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .btn-home {
            display: inline-block;
            background-color: #4f46e5; /* Morado */
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        .btn-home:hover {
            background-color: #3730a3;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="icon">🔒</div>
    <h1>Acceso Denegado</h1>
    <p>
        Hola, <?= htmlspecialchars($username) ?>. No tienes permiso para acceder a esta funcionalidad. 
        <br>Este módulo no está habilitado actualmente en tu plan o configuración.
    </p>
    <a href="./launcher.php" class="btn-home">Volver al Menú Principal</a>
</div>

</body>
</html>