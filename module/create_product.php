<?php
// Script para recibir datos del nuevo producto (via AJAX)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ASUME que 'config.php' incluye la configuración y conecta $pdo
require '../config.php'; 
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos de entrada inválidos (JSON).']);
    exit();
}

// Variables del producto
$supplier_id = (int)($data['supplier_id'] ?? 0);
$code = trim($data['code'] ?? '');
$name = trim($data['name'] ?? '');

// Datos de costo/precio
$cost_net = (float)($data['cost_net'] ?? 0);
$price_final = (float)($data['price_final'] ?? 0);

// Cantidad que se está comprando en ESTA factura
$quantity_for_invoice = (int)($data['quantity'] ?? 1); 

// --- 1. VALIDACIÓN ESENCIAL ---
if (empty($code) || empty($name) || $supplier_id <= 0 || $cost_net <= 0 || $price_final <= 0 || $quantity_for_invoice < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos o valores son inválidos (Código, Nombre, Precios, Cantidad, Proveedor).']);
    exit();
}

try {
    // --- 2. VERIFICAR CÓDIGO DE BARRAS EXISTENTE ---
    $stmt_check = $pdo->prepare("SELECT id FROM products WHERE code = ?");
    $stmt_check->execute([$code]);
    if ($stmt_check->fetch()) {
        // En un caso real, podrías querer añadirlo si ya existe y solo era un error de flujo.
        throw new Exception("El código de producto '{$code}' ya existe en el sistema.");
    }

    // --- 3. INSERCIÓN DEL NUEVO PRODUCTO ---
    // El stock inicial del producto debe ser 0. 
    // La actualización de stock se hará en el registro final de la factura.
    $stmt_insert = $pdo->prepare("
        INSERT INTO products (supplier_id, code, name, stock, cost_price, price)
        VALUES (?, ?, ?, 0, ?, ?)
    ");

    $stmt_insert->execute([
        $supplier_id,
        $code,
        $name,
        $cost_net,      // cost_price (Costo Neto)
        $price_final    // price (Precio de Venta)
    ]);

    $new_product_id = $pdo->lastInsertId();

    // --- 4. RESPUESTA EXITOSA ---
    // Se devuelve el ID crucial y la cantidad para que JS lo añada a INVOICE_ITEMS
    echo json_encode([
        'success' => true,
        'message' => 'Producto creado con éxito.',
        'product_id' => $new_product_id, // Redundante, pero útil
        'new_product_data' => [ 
            'id' => $new_product_id,
            'code' => $code,
            'name' => $name,
            'stock' => 0, // Stock actual en DB
            'cost_price_net' => $cost_net,
            'current_sale_price' => $price_final,
            'quantity' => $quantity_for_invoice, // Cantidad que se está comprando para la factura
            'new_cost_net' => $cost_net, // Se añade para el JS de la tabla de factura
            'new_sale_price_final' => $price_final // Se añade para el JS de la tabla de factura
        ]
    ]);

} catch (Exception $e) {
    // Manejo de errores de base de datos o validación previa
    http_response_code(500);
    error_log("Error al crear producto en DB: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor al crear producto: ' . $e->getMessage()]);
}
?>