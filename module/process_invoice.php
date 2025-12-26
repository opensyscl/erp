<?php
// Muestra errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración y conexión PDO
// ASUME que '../config.php' inicializa la variable $pdo
require '../config.php';

// Ajustar encabezados para permitir JSON
header('Content-Type: application/json');

// Función para enviar una respuesta JSON
function sendResponse($success, $message, $data = []) {
  echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
  exit();
}

// 1. Verificar el método de solicitud
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  // Se envía 405 en el header, aunque el frontend solo lee el body JSON
  header('HTTP/1.1 405 Method Not Allowed'); 
  sendResponse(false, 'Método de solicitud no permitido.');
}

// 2. Obtener y decodificar los datos JSON
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (json_last_error() !== JSON_ERROR_NONE) {
  sendResponse(false, 'Datos JSON inválidos: ' . json_last_error_msg());
}

// 3. Validación de datos esenciales
if (empty($data['supplier_id']) || empty($data['invoice_number']) || empty($data['invoice_date']) || empty($data['total_amount']) || empty($data['items'])) {
  sendResponse(false, 'Faltan datos esenciales de la factura.');
}

$supplierId = (int)$data['supplier_id'];
$invoiceNumber = trim($data['invoice_number']);
$invoiceDate = trim($data['invoice_date']);
$totalAmount = (float)$data['total_amount'];
$items = $data['items'];

if ($totalAmount <= 0) {
  sendResponse(false, 'El monto total de la factura debe ser positivo.');
}

// ----------------------------------------------------
// 4. INICIO DE LA TRANSACCIÓN Y PREPARACIÓN DE SENTENCIAS
// ----------------------------------------------------
try {
  $pdo->beginTransaction();

  $now = date('Y-m-d H:i:s');
 
  // A. INSERCIÓN DE LA FACTURA PRINCIPAL
  $stmtInvoice = $pdo->prepare("
    INSERT INTO purchase_invoices (supplier_id, invoice_number, date, created_at, updated_at, total_amount, is_paid)
    VALUES (?, ?, ?, ?, ?, ?, 0)
  ");

  $stmtInvoice->execute([$supplierId, $invoiceNumber, $invoiceDate, $now, $now, $totalAmount]);
  $invoiceId = $pdo->lastInsertId();

  if (!$invoiceId) {
    throw new Exception("Error al obtener el ID de la factura insertada.");
  }

  // B. PREPARACIÓN PARA LA CREACIÓN/ACTUALIZACIÓN DE PRODUCTOS
  
  // B1. Sentencia para obtener el costo anterior (cost_price)
  $stmtGetPreviousCost = $pdo->prepare("SELECT cost_price FROM products WHERE id = ?");
 
  // B2. Sentencia para INSERTAR UN NUEVO PRODUCTO (¡COLUMNAS CORREGIDAS: barcode y price!)
  // El stock se inicializa en 0, y se actualizará con la cantidad recibida en el bucle
  $stmtProductInsert = $pdo->prepare("
    INSERT INTO products (supplier_id, barcode, name, stock, cost_price, price, created_at, updated_at)
    VALUES (?, ?, ?, 0, ?, ?, ?, ?)
  ");

  // B3. Sentencia para INSERTAR EL ÍTEM DE LA FACTURA (purchase_invoice_items)
  $stmtItem = $pdo->prepare("
    INSERT INTO purchase_invoice_items (invoice_id, product_id, previous_cost_price, new_cost_price, margin_percentage, calculated_sale_price, quantity, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
  ");

  // B4. Sentencia para ACTUALIZAR STOCK/COSTO/PRECIO del producto (¡COLUMNAS CORREGIDAS: cost_price y price!)
  $stmtProductUpdate = $pdo->prepare("
    UPDATE products
    SET
      stock = stock + ?,       
      cost_price = ?,        
      price = ?           
    WHERE id = ?
  ");
 
  // ----------------------------------------------------
  // 5. BUCLE DE PROCESAMIENTO DE ÍTEMS
  // ----------------------------------------------------
  foreach ($items as $item) {
    $productId = null;
    $isNew = $item['is_new'] ?? false; 
        
    $quantity = (int)$item['quantity'];
    $newCostPrice = (float)$item['cost_price_net'];
    $newSalePrice = (float)$item['new_sale_price'];
        
    // 1. MANEJO DE PRODUCTOS NUEVOS (CLAVE CORREGIDA)
    if ($isNew) {
      if (empty($item['code']) || empty($item['name'])) {
        throw new Exception("Datos incompletos para crear un producto nuevo.");
      }

            // 1.1. Insertar el nuevo producto, usando 'barcode'
      $stmtProductInsert->execute([
        $supplierId, 
        $item['code'], // El frontend envía el código en el campo 'code', que mapea a 'barcode' en la DB
        $item['name'], 
        $newCostPrice, 
        $newSalePrice, // Mapea a 'price' en la DB
        $now, 
        $now
      ]);
            
            // 1.2. Obtener y ASIGNAR el ID real generado
      $productId = $pdo->lastInsertId();
      $previousCostPrice = 0.0;
            
      if (!$productId) {
        throw new Exception("No se pudo obtener el ID del nuevo producto: " . $item['name']);
      }
    } else {
            // 1.3. Producto Existente: Obtener ID y costo anterior
            $productId = (int)$item['product_id'];

      $stmtGetPreviousCost->execute([$productId]);
      $previousCostPrice = $stmtGetPreviousCost->fetchColumn() ?? 0.0;
    }
    
    // 2. Calcular el margen real
    $marginPercentage = (($newSalePrice - $newCostPrice) / $newSalePrice) * 100;
   
    // 3. Insertar el ítem de la factura (purchase_invoice_items)
    $stmtItem->execute([
      $invoiceId,
      $productId,
      $previousCostPrice,
      $newCostPrice,
      round($marginPercentage, 2),
      $newSalePrice,
      $quantity,
      $now
    ]);

    // 4. Actualizar stock, costo y precio en la tabla 'products'
    $stmtProductUpdate->execute([$quantity, $newCostPrice, $newSalePrice, $productId]);
  }

  // Si todo va bien, confirmar la transacción
  $pdo->commit();

  sendResponse(true, 'Factura Nº ' . $invoiceNumber . ' registrada y ' . count($items) . ' productos actualizados con éxito.', ['invoice_id' => $invoiceId]);

} catch (Exception $e) {
  // Revertir en caso de cualquier error
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
 
  error_log("Error al registrar factura: " . $e->getMessage());
  sendResponse(false, 'Error de base de datos durante el registro de la factura. Detalle: ' . $e->getMessage());
} catch (PDOException $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  error_log("Error fatal de PDO al registrar factura: " . $e->getMessage());
  sendResponse(false, 'Error fatal de base de datos. Contacte a soporte.');
}