<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Configuración y conexión a la base de datos (ajusta la ruta si es necesario)
require_once "../config.php";

$response = [
    'success' => true,
    'duplicates' => []
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['new_products'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida.']);
    exit;
}

$new_products_data = $_POST['new_products'];

try {
    // 1. OBTENER CÓDIGOS DE BARRAS EXISTENTES
    $stmtB = $pdo->query("SELECT barcode, name FROM products");
    $existing_products = $stmtB->fetchAll(PDO::FETCH_KEY_PAIR); // ['barcode' => 'name']

    // 2. VALIDAR CADA PRODUCTO NUEVO
    foreach ($new_products_data as $data) {
        $barcode = trim($data['barcode'] ?? '');
        $name = trim($data['name'] ?? '');

        // Validar si el barcode ya existe en la BD
        if (!empty($barcode) && isset($existing_products[$barcode])) {
            $response['success'] = false;
            $response['duplicates'][] = [
                'name' => $name,
                'barcode' => $barcode,
                'existing_name' => $existing_products[$barcode]
            ];
        }
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    exit;
}

echo json_encode($response);
exit;