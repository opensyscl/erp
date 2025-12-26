<?php
// Configuración de errores y cabeceras
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Incluye el archivo de configuración y establece la conexión PDO.
require '../../config.php'; // Asegúrate de que la ruta a config.php sea correcta

// Función de respuesta JSON
function json_response($data, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode($data);
    exit;
}

// 1. Verificar el ID del producto
$productId = $_GET['product_id'] ?? null;

if (!$productId || !is_numeric($productId)) {
    json_response(['success' => false, 'message' => 'ID de producto inválido o faltante.'], 400);
}

// 2. Obtener la información base del producto
try {
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.name,
            p.sale_price,
            p.stock
        FROM products p
        WHERE p.id = :id
    ");
    $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        json_response(['success' => false, 'message' => 'Producto no encontrado.'], 404);
    }
    
    // Convertir a float
    $product['price'] = (float)$product['sale_price'];
    $product['stock'] = (int)$product['stock'];
    $product['image_url'] = 'https://via.placeholder.com/200?text=' . urlencode($product['name']); // Placeholder dinámico

} catch (PDOException $e) {
    error_log("Error al obtener el producto: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Error interno del servidor al consultar la base de datos.'], 500);
}

// 3. Generar las sugerencias de oferta (Bundles)
$suggestions = [];
$minQuantity = 2; // Mínimo para una oferta de bundle

if ($product['stock'] >= $minQuantity) {
    $basePrice = $product['price'];
    $currentStock = $product['stock'];

    // Calculamos sugerencias para 2x, 3x, y 4x (si hay stock suficiente)
    $bundleQuantities = [2, 3, 4];
    
    foreach ($bundleQuantities as $quantity) {
        if ($currentStock >= $quantity) {
            
            // Calculamos el precio normal sin descuento
            $regularTotalPrice = $basePrice * $quantity;
            
            // Aplicamos un descuento del 10%
            $discountRate = 0.10;
            $totalPrice = $regularTotalPrice * (1 - $discountRate);
            
            // Redondeamos el precio total a un número entero
            $totalPrice = round($totalPrice);
            
            // Calculamos cuánto ahorra el cliente
            $totalSavings = $regularTotalPrice - $totalPrice;
            
            // Calculamos cuántos bundles se pueden crear con el stock actual
            $maxBundles = floor($currentStock / $quantity);

            $suggestions[] = [
                'quantity' => $quantity,
                'total_price' => $totalPrice,
                'total_savings' => $totalSavings,
                'max_bundles' => (int)$maxBundles
            ];
        }
    }
}

// 4. Devolver la respuesta final
json_response([
    'success' => true,
    'product' => [
        'id' => $product['id'],
        'name' => $product['name'],
        'price' => $product['price'],
        'stock' => $product['stock'],
        'image_url' => $product['image_url']
    ],
    'suggestions' => $suggestions
]);

?>