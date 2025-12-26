<?php
header('Content-Type: application/json; charset=utf-8');

// Incluir archivo de configuración (asegúrate de que la ruta sea correcta)
require_once __DIR__ . '/config.php';

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Validar entrada
if (!isset($_POST['name']) || trim($_POST['name']) === '') {
    echo json_encode(['success' => false, 'message' => 'El nombre de la categoría está vacío']);
    exit;
}

$name = trim($_POST['name']);

try {
    $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (:name)");
    $stmt->bindParam(':name', $name, PDO::PARAM_STR);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'id'      => $pdo->lastInsertId(),
        'name'    => htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Excepción: ' . $e->getMessage()
    ]);
}
