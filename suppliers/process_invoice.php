<?php
// Muestra errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración y conexión PDO
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
if (
  empty($data['supplier_id']) ||
  empty($data['invoice_number']) ||
  empty($data['invoice_date']) ||
  empty($data['total_amount']) ||
  empty($data['items'])
) {
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

try {
  $pdo->beginTransaction();

  $now = date('Y-m-d H:i:s');

  // A. Inserción de la factura principal
  $stmtInvoice = $pdo->prepare("
    INSERT INTO purchase_invoices (supplier_id, invoice_number, date, created_at, updated_at, total_amount, is_paid)
    VALUES (?, ?, ?, ?, ?, ?, 0)
  ");

  $stmtInvoice->execute([$supplierId, $invoiceNumber, $invoiceDate, $now, $now, $totalAmount]);
  $invoiceId = $pdo->lastInsertId();

  if (!$invoiceId) {
    throw new Exception("Error al obtener el ID de la factura insertada.");
  }

  // B. Preparación de sentencias
  $stmtGetPreviousCost = $pdo->prepare("SELECT cost_price FROM products WHERE id = ?");

  // IMPORTANTE: ORDEN CORRECTO DE COLUMNAS
  $stmtProductInsert = $pdo->prepare("
    INSERT INTO products (supplier_id, barcode, name, stock, cost_price, price, created_at, updated_at)
    VALUES (?, ?, ?, 0, ?, ?, ?, ?)
  ");

  $stmtItem = $pdo->prepare("
    INSERT INTO purchase_invoice_items
    (invoice_id, product_id, previous_cost_price, new_cost_price, margin_percentage, calculated_sale_price, quantity, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
  ");

// *********************************************************************************
// ✅ CORRECCIÓN APLICADA: Se eliminó el comentario de código (//) de esta línea.
// *********************************************************************************
$stmtProductUpdate = $pdo->prepare("
 UPDATE products
 SET
  stock = stock + ?,
  cost_price = ?,
  price = ? 
 WHERE id = ?
");

  // ----------------------------------------------------
  // 5. BUCLE DE ITEMS
  // ----------------------------------------------------
  foreach ($items as $item) {
    $productId = null;
    $isNew = $item['is_new'] ?? false;

    // 🔥 CORRECCIÓN DE DECIMALES (coma → punto)
    // NOTA: str_replace puede ser innecesario si el JSON ya viene con punto, 
    // pero lo mantenemos por seguridad en caso de recibir inputs como '5,60'.
    $newCostPrice = (float) str_replace(',', '.', $item['cost_price_net']);
    $newSalePrice = (float)str_replace(',', '.', $item['new_sale_price']); // Usar new_sale_price, no new_sale_price_final
    
    $quantity = (int)$item['quantity'];
        
        // Verificación de valores positivos
        if ($newCostPrice <= 0 || $newSalePrice <= 0 || $quantity <= 0) {
            throw new Exception("Valores de costo, precio o cantidad no son positivos para el producto: " . ($item['name'] ?? $item['code']));
        }


    // 1. Producto Nuevo
    if ($isNew) {
      if (empty($item['code']) || empty($item['name'])) {
        throw new Exception("Datos incompletos para crear un producto nuevo.");
      }

      // ORDEN CORRECTO DE PARÁMETROS
      $stmtProductInsert->execute([
        $supplierId,
        $item['code'],
        $item['name'],
        $newCostPrice,  // cost_price
        $newSalePrice,  // price
        $now,
        $now
      ]);

      $productId = $pdo->lastInsertId();
      $previousCostPrice = 0.00;

      if (!$productId) {
        throw new Exception("No se pudo obtener el ID del nuevo producto: " . $item['name']);
      }
    } else {
      // Producto existente
      $productId = (int)$item['product_id'];

      $stmtGetPreviousCost->execute([$productId]);
      $previousCostPrice = (float)($stmtGetPreviousCost->fetchColumn() ?? 0.0);
      
            if ($productId <= 0) {
                throw new Exception("ID de producto existente inválido.");
            }
    }

    // 2. Calcular margen
    $marginPercentage = ($newSalePrice > 0)
      ? (($newSalePrice - $newCostPrice) / $newSalePrice) * 100
      : 0;

    // 3. Insertar item
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

    // 4. Actualizar stock, costo y precio
    $stmtProductUpdate->execute([
      $quantity,
      $newCostPrice,
      $newSalePrice,
      $productId
    ]);
  }

  $pdo->commit();

  sendResponse(true, 'Factura Nº ' . $invoiceNumber . ' registrada y ' . count($items) . ' productos actualizados con éxito.', [
    'invoice_id' => $invoiceId
  ]);

} catch (Exception $e) {
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