<?php
// register_order.php

// Muestra errores de forma estricta para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

// -------------------------------------------------------------------------
// PASO 1: LECTURA Y VALIDACIÓN DEL JSON (Debe ser lo primero)
// -------------------------------------------------------------------------

// Establecer el Content-Type para la respuesta JSON
header('Content-Type: application/json');

// 1. Obtener los datos JSON de la solicitud POST
$json_data = file_get_contents('php://input');

// 2. Decodificar el JSON
$data = json_decode($json_data, true);

// Validación crítica: Si la decodificación falla, si el JSON no es un array válido, o está vacío.
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || empty($data)) {
    // Si la decodificación falla, mostramos un error HTTP 400
    http_response_code(400);  
    echo json_encode([
        'success' => false,  
        'message' => 'Error: JSON no válido, no se recibieron datos o el cuerpo de la solicitud está vacío.'
    ]);
    exit;
}
// -------------------------------------------------------------------------
// FIN DE LA VALIDACIÓN INICIAL DEL JSON
// -------------------------------------------------------------------------


// Incluye configuración de la base de datos y conecta PDO
require '../config.php';
session_start();

// 🛑 CORRECCIÓN CLAVE: Definir el ID del usuario creador 🛑
// Asumimos que el ID está guardado en $_SESSION['user_id']
$userId = $_SESSION['user_id'] ?? null; 

// Validar que el usuario esté logueado
if (empty($userId)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Error: Sesión no válida. El usuario no está autenticado o falta su ID.']);
    exit;
}
// 🛑 FIN DE LA CORRECCIÓN CLAVE 🛑


// 3. Extracción y validación de datos
$supplierId = $data['supplier_id'] ?? null;
$orderDate = $data['order_date'] ?? date('Y-m-d');  
$totalAmount = $data['total_amount'] ?? 0;
$items = $data['items'] ?? [];

// Validar que el total amount sea un número
$totalAmount = (float)$totalAmount; 

if (empty($supplierId) || empty($items)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error: Faltan datos esenciales (Proveedor o Ítems).']);
    exit;
}

// 4. INICIO DE TRANSACCIÓN 
try {
    $pdo->beginTransaction();

    // =========================================================
    // LÓGICA DE GENERACIÓN DEL CORRELATIVO: "OC-X-XXXX"
    // =========================================================

    // a. Buscar el último correlativo numérico para este proveedor
    $stmt = $pdo->prepare("
        SELECT order_correlative 
        FROM purchase_orders 
        WHERE supplier_id = ? 
        ORDER BY order_correlative DESC 
        LIMIT 1
    ");
    $stmt->execute([$supplierId]);
    $lastCorrelative = $stmt->fetchColumn();

    // b. Determinar el nuevo correlativo numérico
    $startingCorrelative = 101; 
    $newCorrelative = $startingCorrelative;

    if ($lastCorrelative !== false) {
        // Aseguramos que el correlativo sea un entero antes de incrementar.
        $newCorrelative = (int)$lastCorrelative + 1;
    } 

    // c. Formatear el número de orden completo (OC-X-XXXX)
    $formattedCorrelative = str_pad($newCorrelative, 4, '0', STR_PAD_LEFT);
    $orderNumber = "OC-{$supplierId}-{$formattedCorrelative}";
    
    // =========================================================
    // 5. INSERCIÓN DE LA ORDEN PRINCIPAL EN purchase_orders
    // =========================================================

    $stmt_order = $pdo->prepare("
        INSERT INTO purchase_orders 
            (supplier_id, order_number, date, order_correlative, total_amount, created_by) 
        VALUES 
            (?, ?, ?, ?, ?, ?)
    ");
    // ⚠️ Ahora $userId está definido correctamente y guarda el ID del usuario.
    $stmt_order->execute([
        $supplierId, 
        $orderNumber, 
        $orderDate, 
        $newCorrelative, 
        $totalAmount, 
        $userId  // 👈 CORREGIDO: $userId definido desde la sesión
    ]);
    
    // Obtener el ID de la orden recién insertada
    $orderId = $pdo->lastInsertId();

    // =========================================================
    // 6. INSERCIÓN DE ÍTEMS Y CREACIÓN DE PRODUCTOS NUEVOS (SI APLICA)
    // =========================================================
    
    // Statement para insertar ítem de orden
    $stmt_item = $pdo->prepare("
        INSERT INTO purchase_order_items 
            (order_id, product_id, quantity, cost_price) 
        VALUES 
            (?, ?, ?, ?)
    ");

    // Statement para crear producto nuevo
    $stmt_new_product = $pdo->prepare("
        INSERT INTO products 
            (supplier_id, barcode, name, stock, cost_price, price) 
        VALUES 
            (?, ?, ?, 0, ?, 0)
    ");
    
    // Statement para actualizar solo el COSTO REGISTRADO del producto existente
    // 🛑 CORRECCIÓN: SE ELIMINA LA ACTUALIZACIÓN DE STOCK 🛑
    $stmt_update_product = $pdo->prepare("
        UPDATE products 
        SET cost_price = ? 
        WHERE id = ?
    ");
    
    $productosCreados = [];
    
    foreach ($items as $item) {
        $productId = (int)($item['product_id'] ?? 0);
        $quantity = (int)($item['quantity'] ?? 0);
        $costPriceNet = (float)($item['cost_price_net'] ?? 0);
        
        // Datos adicionales para productos nuevos
        $productCode = $item['product_code'] ?? null;
        $productName = $item['product_name'] ?? null;

        // Validar datos mínimos del ítem
        if ($quantity <= 0 || $costPriceNet <= 0) {
              throw new Exception("Ítem inválido: Cantidad y/o costo neto deben ser mayores a cero.");
        }

        // ----------------------------------------------------
        // Lógica de Creación de Producto Nuevo (ID < 0)
        // ----------------------------------------------------
        if ($productId < 0) {
            
            // ⚠️ Validación crucial para la creación
            if (empty($productCode) || empty($productName)) {
                 throw new Exception("No se puede crear producto nuevo (ID: {$productId}). Faltan Código de Barras o Nombre.");
            }
            
            // Insertar el nuevo producto en la tabla products
            $stmt_new_product->execute([
                $supplierId, 
                $productCode, 
                $productName, 
                $costPriceNet, // cost_price inicial (neto)
            ]);
            
            // Obtener el ID real del producto que acabamos de crear
            $productId = $pdo->lastInsertId(); 
            $productosCreados[] = $productName;
        }
        // ----------------------------------------------------
        // Fin Lógica de Creación
        // ----------------------------------------------------

        // Insertar el ítem de la orden (usando el ID real o el recién creado)
        $stmt_item->execute([
            $orderId, 
            $productId, 
            $quantity, 
            $costPriceNet // Columna 'cost_price' en purchase_order_items
        ]);
        
        // 7. ACTUALIZAR SOLO EL COSTO REGISTRADO EN products
        // 🛑 Ya no se actualiza el stock aquí.
        $stmt_update_product->execute([
            $costPriceNet,  
            $productId
        ]);

    }

    // 8. Finalizar la transacción
    $pdo->commit();

 // ----------------------------------------------------
    // 9. Respuesta de éxito 
    // ----------------------------------------------------

    // a. Obtener el nombre del proveedor
    $stmt_supplier = $pdo->prepare("SELECT name FROM suppliers WHERE id = ?");
    $stmt_supplier->execute([$supplierId]);
    $supplierName = $stmt_supplier->fetchColumn() ?? 'Proveedor Desconocido';

    // b. Formatear la fecha para la visualización en la tabla (d-m-Y)
    $orderDateFormatted = date('d-m-Y', strtotime($orderDate));
    
    // c. Obtener el nombre de usuario para el live update
    $creatorUsername = $_SESSION['user_username'] ?? 'Usuario';
    
    // d. Mensaje y productos creados
    $message = 'Orden de Compra registrada.';
    if (!empty($productosCreados)) {
        $message .= " Se crearon " . count($productosCreados) . " nuevos productos: " . implode(', ', $productosCreados) . ".";
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'order_number' => $orderNumber,
        'order_id' => $orderId,
        'supplier_name' => $supplierName,
        'order_date_formatted' => $orderDateFormatted,
        'total_amount' => $totalAmount,
        'created_by_username' => $creatorUsername // 👈 CORREGIDO
    ]);

} catch (Exception $e) {
    // Si falla, revertir todos los cambios
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Devolvemos el mensaje de error de la excepción (más informativo)
    http_response_code(500);
    echo json_encode([
        'success' => false,  
        'message' => 'Error al registrar la orden: ' . $e->getMessage()
    ]);
} catch (PDOException $e) {
    // Manejo de errores de base de datos específicos
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,  
        'message' => 'Error en la base de datos al registrar la orden. Detalles: ' . $e->getMessage()
    ]);
}
?>