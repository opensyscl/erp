<?php
// api/new_offer_creator.php

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir tu configuración de base de datos
require '../config.php';

// --- Lógica Principal ---

// Validar que la solicitud sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// Obtener datos de la oferta
$original_product_id = $_POST['original_product_id'] ?? null;
$quantity = $_POST['quantity'] ?? null;
$promo_price_total = $_POST['promo_price_total'] ?? null;

if (!is_numeric($original_product_id) || !is_numeric($quantity) || !is_numeric($promo_price_total) || $quantity < 2) {
    echo json_encode(['success' => false, 'message' => 'Datos de oferta incompletos o inválidos.']);
    exit;
}

try {
    // 1. Obtener datos del producto original
    $stmt = $pdo->prepare("
        SELECT name, stock, cost_price, category_id, supplier_id, image_url
        FROM products
        WHERE id = ?
    ");
    $stmt->execute([$original_product_id]);
    $original_product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$original_product) {
        echo json_encode(['success' => false, 'message' => 'Producto original no encontrado.']);
        exit;
    }
    
    $original_stock = (int)$original_product['stock'];
    $cost_price_unit = (float)$original_product['cost_price'];
    $promo_price_total = (float)$promo_price_total;
    $quantity = (int)$quantity;

    // 2. Calcular los valores para el nuevo producto (el bundle)
    $new_name = $quantity . ' x ' . $original_product['name'] . ' (OFERTA)';
    
    // El stock del nuevo bundle es cuántos paquetes se pueden crear
    $new_stock = floor($original_stock / $quantity);
    
    // El costo del nuevo bundle es el costo unitario por la cantidad
    $new_cost_price = $cost_price_unit * $quantity;

    // 3. Insertar el nuevo producto bundle
    $stmt_insert = $pdo->prepare("
        INSERT INTO products (
            barcode, name, price, stock, cost_price, sale_price, category, 
            created_at, updated_at, image_url, category_id, supplier_id
        ) VALUES (
            :barcode, :name, :price, :stock, :cost_price, :sale_price, :category,
            NOW(), NOW(), :image_url, :category_id, :supplier_id
        )
    ");

    $insert_data = [
        // Usar un código de barras único, quizás un prefijo + ID original
        ':barcode' => 'BUNDLE-' . $original_product_id . '-' . $quantity, 
        ':name' => $new_name,
        ':price' => $promo_price_total, // El precio de venta (precio/sale_price) es el precio del bundle
        ':stock' => $new_stock,
        ':cost_price' => $new_cost_price,
        ':sale_price' => $promo_price_total, // Es la oferta, por lo que es el sale_price
        ':category' => 'BUNDLE', // Asumimos una categoría especial para ofertas
        ':image_url' => $original_product['image_url'],
        ':category_id' => $original_product['category_id'],
        ':supplier_id' => $original_product['supplier_id']
    ];

    $stmt_insert->execute($insert_data);
    
    // 4. Retorno exitoso
    echo json_encode([
        'success' => true, 
        'message' => 'Nuevo producto bundle creado con éxito.',
        'new_product_name' => $new_name,
        'new_product_stock' => $new_stock,
        'new_product_id' => $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al guardar en la base de datos: ' . $e->getMessage()]);
}
?>