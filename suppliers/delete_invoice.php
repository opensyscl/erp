<?php
// delete_invoice.php

header('Content-Type: application/json');

require '../config.php'; // Ajusta esta ruta si es necesario
session_start();

// Validar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Usuario no autenticado.']);
    exit();
}

$user_id = $_SESSION['user_id']; 

$data = json_decode(file_get_contents('php://input'), true);

$invoice_id = $data['invoice_id'] ?? null;
$password_attempt = $data['password'] ?? null;

if (!$invoice_id || !$password_attempt || !is_numeric($invoice_id)) {
    echo json_encode(['success' => false, 'message' => 'Datos de factura o clave inválidos.']);
    exit();
}

try {
    // 1. OBTENER Y VALIDAR CONTRASEÑA
    // Se asume que las contraseñas están hasheadas (e.g., usando password_hash)
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password_attempt, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Contraseña de usuario incorrecta.']);
        exit();
    }

    // 2. INICIAR TRANSACCIÓN
    $pdo->beginTransaction();

    // 3. REVERTIR STOCK (CRÍTICO)
    // Antes de eliminar los ítems, debemos restar la cantidad del stock en la tabla 'products'.
    
    // Obtener los productos y cantidades de la factura
    $stmt_items_to_revert = $pdo->prepare("SELECT product_id, quantity FROM purchase_invoice_items WHERE invoice_id = ?");
    $stmt_items_to_revert->execute([$invoice_id]);
    $items_to_revert = $stmt_items_to_revert->fetchAll(PDO::FETCH_ASSOC);

    // Revertir el stock en la tabla `products`
    $stmt_update_stock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
    
    foreach ($items_to_revert as $item) {
        $stmt_update_stock->execute([$item['quantity'], $item['product_id']]);
    }


    // 4. ELIMINACIÓN EN CASCADA
    
    // CORRECCIÓN 1: Eliminar ítems de la factura usando 'invoice_id' (soluciona el error SQL 1054)
    $stmt_items = $pdo->prepare("DELETE FROM purchase_invoice_items WHERE invoice_id = ?");
    $stmt_items->execute([$invoice_id]);

    // Eliminar la factura principal
    $stmt_invoice = $pdo->prepare("DELETE FROM purchase_invoices WHERE id = ?");
    $stmt_invoice->execute([$invoice_id]);
    
    $invoice_deleted = $stmt_invoice->rowCount();

    if ($invoice_deleted > 0) {
        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'message' => "Factura y stock revertido eliminados con éxito."
        ]);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: No se pudo encontrar o eliminar la factura.']);
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error al eliminar factura: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de Base de Datos. Contacte al administrador.']);
}