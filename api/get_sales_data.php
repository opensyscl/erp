<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config.php';

header('Content-Type: application/json');

try {
    $stmt_sales_data = $pdo->prepare("
        SELECT 
            id, 
            total, 
            paid, 
            receipt_number, 
            change_due,
            created_at, 
            DATE(created_at) AS sale_date_formatted,  /* <-- NUEVA LÍNEA */
            method
        FROM sales
        WHERE created_at >= CURDATE() - INTERVAL 30 DAY
        ORDER BY created_at DESC
    ");
    $stmt_sales_data->execute();
    $sales_data = $stmt_sales_data->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($sales_data);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener datos de ventas: ' . $e->getMessage()]);
}
?>