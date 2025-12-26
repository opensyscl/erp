<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "../config.php";

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$supplier_id = $data['supplier_id'] ?? null;
$order_date = $data['order_date'] ?? null; // Usaremos el campo 'order_date' como 'date'
$total_amount = $data['total_amount'] ?? 0;
$items = $data['items'] ?? [];
// Obtener el ID del usuario logueado de la sesión
$created_by = $_SESSION['user_id'] ?? null; 
$creator_username = $_SESSION['username'] ?? 'Usuario Desconocido'; // Para la respuesta de éxito

// =========================================================
// VERIFICACIÓN INICIAL DE DATOS Y SESIÓN
// =========================================================
if (!$supplier_id || !$order_date || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos para generar la cotización (Falta Proveedor, Fecha o Items).']);
    exit;
}

if (!$created_by) {
    // Si no hay user_id, es el origen del error 1452.
    echo json_encode(['success' => false, 'message' => 'Error de autenticación. El usuario actual no está definido o no ha iniciado sesión.']);
    exit;
}

// =========================================================
// INICIO DE TRANSACCIÓN
// =========================================================
$pdo->beginTransaction();

try {
    // 1. Obtener el próximo correlativo del proveedor
    // Se usa FOR UPDATE para bloquear la fila durante la transacción y evitar duplicados.
    $stmt = $pdo->prepare("SELECT id, name AS supplier_name, next_quotation_number FROM suppliers WHERE id = ? FOR UPDATE");
    $stmt->execute([$supplier_id]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        throw new Exception("Proveedor no encontrado.");
    }
    
    $quotation_correlative = $supplier['next_quotation_number'];
    
    // Formatear ID del proveedor a 2 dígitos
    $supplier_id_padded = str_pad($supplier_id, 2, '0', STR_PAD_LEFT);
    // Formatear Correlativo a 3 dígitos
    $correlative_padded = str_pad($quotation_correlative, 3, '0', STR_PAD_LEFT);
    
    // 2. CONSTRUIR LA NOMENCLATURA: C-XX-XXX
    $quotation_number = "C-{$supplier_id_padded}-{$correlative_padded}";
    
    // 3. Insertar la Cotización Maestra (quotations)
    $stmt = $pdo->prepare("
        INSERT INTO quotations (
            quotation_number, 
            supplier_id, 
            date, 
            total_amount,
            created_by         /* <-- CAMBIO: Se añade el campo creado por el usuario */
        ) VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $quotation_number,
        $supplier_id,
        $order_date,
        $total_amount,
        $created_by         /* <-- CAMBIO: Se pasa la variable de sesión */
    ]);
    
    $quotation_id = $pdo->lastInsertId();

    // 4. Insertar los Items de la Cotización
    $item_sql = "
        INSERT INTO quotation_items (
            quotation_id, 
            product_id, 
            quantity, 
            cost_net, 
            cost_gross, 
            product_code, 
            product_name
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ";
    $stmt_item = $pdo->prepare($item_sql);
    
    foreach ($items as $item) {
        // Aseguramos que product_id sea NULL si es temporal (producto_id <= 0)
        $product_id = ($item['product_id'] > 0) ? $item['product_id'] : null;
        
        $stmt_item->execute([
            $quotation_id,
            $product_id,
            $item['quantity'],
            $item['cost_net'],
            $item['cost_gross'],
            $item['code'],
            $item['name']
        ]);
    }

    // 5. Actualizar el próximo número correlativo en la tabla suppliers
    $stmt = $pdo->prepare("UPDATE suppliers SET next_quotation_number = ? WHERE id = ?");
    $stmt->execute([$quotation_correlative + 1, $supplier_id]);

    // 6. Confirmar Transacción
    $pdo->commit();
    
    // 7. Respuesta de Éxito
    echo json_encode([
        'success' => true,
        'message' => 'Cotización registrada con éxito.',
        'quotation_id' => $quotation_id,
        'quotation_number' => $quotation_number,
        // CAMBIO AQUI: Usar el nombre real obtenido en la consulta
        'supplier_name' => $supplier['supplier_name'], 
        'total_amount' => $total_amount,
        'creator_username' => $creator_username,
        'order_date_formatted' => date("d/m/Y", strtotime($order_date)),
    ]);

} catch (Exception $e) {
    // Revertir Transacción en caso de error
    $pdo->rollBack();
    error_log("Error al registrar cotización: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Fallo interno al procesar la cotización: ' . $e->getMessage()
    ]);
}
?>