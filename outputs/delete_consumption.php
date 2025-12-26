<?php
// delete_consumption.php
require '../config.php'; // Ajusta la ruta a tu archivo config.php si es diferente
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_username'])) {
    echo json_encode(['success' => false, 'message' => 'Sesiиоn no iniciada.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Solicitud invивlida.']);
    exit();
}

$consumption_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$consumption_id) {
    echo json_encode(['success' => false, 'message' => 'ID de consumo invивlido.']);
    exit();
}

$pdo->beginTransaction();

try {
    // 1. Obtener datos del consumo (product_id y cantidad) y bloquear la fila
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
    $quantity_to_restore = $consumption['quantity_removed'];

    // 2. Devolver la cantidad al stock del producto de forma segura
    // (Se usa la tabla products y se asume que existe la columna 'stock' y 'id')
    $stmt_update = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
    $stmt_update->execute([$quantity_to_restore, $product_id]);

    // 3. Eliminar el registro de consumo
    $stmt_delete = $pdo->prepare("DELETE FROM consumo_interno WHERE id = ?");
    $stmt_delete->execute([$consumption_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Consumo eliminado y stock revertido exitosamente.']);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error al eliminar consumo: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos al eliminar. Detalles: ' . $e->getMessage()]);
}
?>