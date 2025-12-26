<?php
// Configuración de errores y cabeceras
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Incluye el archivo de configuración y establece la conexión PDO.
require '../config.php'; // Asegúrate de que la ruta a config.php sea correcta

// Función de respuesta JSON
function json_response($data, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode($data);
    exit;
}

// 1. Verificar y Sanitizar los datos de entrada (POST)
$originalProductId = $_POST['original_product_id'] ?? null;
$quantity = $_POST['quantity'] ?? null;
$promoPriceTotal = $_POST['promo_price_total'] ?? null;

if (!$originalProductId || !is_numeric($originalProductId) || !$quantity || !is_numeric($quantity) || !$promoPriceTotal || !is_numeric($promoPriceTotal)) {
    json_response(['success' => false, 'message' => 'Datos de oferta incompletos o inválidos.'], 400);
}

// Convertir a tipos numéricos
$originalProductId = (int)$originalProductId;
$quantity = (int)$quantity;
$promoPriceTotal = round((float)$promoPriceTotal); // Redondear el precio final

// Validaciones básicas de lógica de negocio
if ($quantity < 2) {
    json_response(['success' => false, 'message' => 'La cantidad agrupada debe ser al menos 2.'], 400);
}

// 2. Obtener la información del producto original
try {
    $stmt = $pdo->prepare("
        SELECT id, name, sale_price, cost_price, stock, category_id
        FROM products
        WHERE id = :id
    ");
    $stmt->bindParam(':id', $originalProductId, PDO::PARAM_INT);
    $stmt->execute();
    $originalProduct = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$originalProduct) {
        json_response(['success' => false, 'message' => 'Producto original no encontrado.'], 404);
    }

    $originalPrice = (float)$originalProduct['sale_price'];
    $originalCost = (float)$originalProduct['cost_price'];
    $originalStock = (int)$originalProduct['stock'];

} catch (PDOException $e) {
    error_log("Error al obtener el producto original: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Error interno del servidor (Consulta original).'], 500);
}

// 3. Cálculos para el nuevo producto Bundle
$newProductName = $quantity . 'x ' . $originalProduct['name'] . ' (OFERTA)';

// a) Costo total del bundle: Costo unitario original * Cantidad
$newProductCostPrice = $originalCost * $quantity;

// b) Stock del bundle: Cuántos bundles se pueden crear con el stock actual
$newProductStock = floor($originalStock / $quantity);

// c) Precio de venta unitario del bundle (es el precio total de la promo)
$newProductSalePrice = $promoPriceTotal;

if ($newProductStock < 1) {
    json_response(['success' => false, 'message' => 'Stock insuficiente. Solo se pueden crear ' . $newProductStock . ' bundles.'], 400);
}

// 4. Inserción del nuevo producto Bundle en la base de datos
try {
    // Comprobar si ya existe un producto con el mismo nombre (para evitar duplicados exactos)
    $stmtCheck = $pdo->prepare("SELECT id FROM products WHERE name = :name");
    $stmtCheck->bindParam(':name', $newProductName);
    $stmtCheck->execute();
    if ($stmtCheck->fetch()) {
        json_response(['success' => false, 'message' => 'Ya existe un producto de oferta con el nombre "' . $newProductName . '".'], 409);
    }
    
    // Iniciar transacción para garantizar atomicidad
    $pdo->beginTransaction();

    // Insertar el nuevo producto de oferta
    $stmtInsert = $pdo->prepare("
        INSERT INTO products (name, sale_price, cost_price, stock, category_id, is_bundle, original_product_id)
        VALUES (:name, :sale_price, :cost_price, :stock, :category_id, 1, :original_id)
    ");

    $stmtInsert->execute([
        ':name' => $newProductName,
        ':sale_price' => $newProductSalePrice,
        ':cost_price' => $newProductCostPrice,
        ':stock' => $newProductStock,
        ':category_id' => $originalProduct['category_id'], // Mantiene la categoría original
        ':original_id' => $originalProductId
    ]);

    $newProductId = $pdo->lastInsertId();

    // 5. Opcional: Descontar el stock del producto original (Asegurando la integridad)
    // El stock original se descuenta en la cantidad total utilizada: $newProductStock * $quantity
    $stockUsed = $newProductStock * $quantity;
    
    $stmtUpdateStock = $pdo->prepare("
        UPDATE products SET stock = stock - :stock_used WHERE id = :id
    ");
    $stmtUpdateStock->bindParam(':stock_used', $stockUsed, PDO::PARAM_INT);
    $stmtUpdateStock->bindParam(':id', $originalProductId, PDO::PARAM_INT);
    $stmtUpdateStock->execute();

    $pdo->commit();

    // 6. Devolver la respuesta exitosa
    json_response([
        'success' => true,
        'message' => 'Producto de oferta creado exitosamente.',
        'new_product_id' => $newProductId,
        'new_product_name' => $newProductName,
        'new_product_stock' => $newProductStock . ' bundles',
        'original_stock_remaining' => $originalStock - $stockUsed
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error al insertar el producto de oferta: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()], 500);
}

?>