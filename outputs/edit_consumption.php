<?php
// edit_consumption.php
require '../config.php'; // Ajusta la ruta a tu archivo config.php si es diferente
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_username'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'], $_POST['quantity'], $_POST['notes'])) {
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida o datos faltantes.']);
    exit();
}

$consumption_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$new_quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
$new_notes = htmlspecialchars($_POST['notes'], ENT_QUOTES, 'UTF-8');
$current_user = $_SESSION['user_username'] ?? 'Sistema';

if (!$consumption_id || $new_quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos de edición inválidos (ID o Cantidad).']);
    exit();
}

$pdo->beginTransaction();

try {
    // 1. Obtener datos antiguos del consumo y bloquear la fila
    $stmt = $pdo->prepare("
        SELECT product_id, quantity_removed 
        FROM consumo_interno 
        WHERE id = ? FOR UPDATE
    ");
    $stmt->execute([$consumption_id]);
    $consumption = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$consumption) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Registro de consumo no encontrado.']);
        exit();
    }

    $product_id = $consumption['product_id'];
    $old_quantity = $consumption['quantity_removed'];
    $quantity_change = $old_quantity - $new_quantity; 
    // Si $quantity_change es POSITIVO, debemos SUMAR (devolver) stock.
    // Si $quantity_change es NEGATIVO, debemos RESTAR (sacar más) stock.

    
    // 2. Si la cantidad cambió, ajustar el stock
    if ($quantity_change !== 0) {
        // Bloquear la fila del producto para la actualización
        $stmt_product = $pdo->prepare("SELECT stock FROM products WHERE id = ? FOR UPDATE");
        $stmt_product->execute([$product_id]);
        $product = $stmt_product->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Producto asociado no encontrado.']);
            exit();
        }

        $new_stock = $product['stock'] + $quantity_change;

        if ($new_stock < 0) {
            $pdo->rollBack();
            // Calcula la cantidad máxima que se puede retirar si se quiere editar.
            $max_retirable = $product['stock'] + $old_quantity; 
            echo json_encode(['success' => false, 'message' => "Error de stock: El stock actual solo permite retirar hasta $max_retirable unidades en este registro."]);
            exit();
        }
        
        // Aplicar el cambio de stock
        $stmt_update_stock = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $stmt_update_stock->execute([$new_stock, $product_id]);
    }
    
    // 3. Actualizar el registro de consumo con la nueva cantidad y notas
    $stmt_update_consumption = $pdo->prepare("
        UPDATE consumo_interno 
        SET quantity_removed = ?, notes = ?, last_edited_by = ?, last_edited_at = NOW()
        WHERE id = ?
    ");
    $stmt_update_consumption->execute([$new_quantity, $new_notes, $current_user, $consumption_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Consumo actualizado exitosamente.']);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error al editar consumo: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos al editar. Detalles: ' . $e->getMessage()]);
}
?>