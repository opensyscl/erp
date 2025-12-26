<?php
require 'config.php';

$id = $_POST['id'] ?? 0;
$name = $_POST['name'] ?? '';
$barcode = $_POST['barcode'] ?? '';
$cost_price = $_POST['cost_price'] ?? 0;
$margin = $_POST['margin'] ?? 30;
$sale_price = $_POST['sale_price'] ?? 0;
$stock = $_POST['stock'] ?? 0;
$category_id = $_POST['category_id'] ?? 0;
$image_url = $_POST['image_url'] ?? '';

try {
    // Recalcular precio de venta si se especifica margin
    if ($cost_price > 0 && $margin > 0 && $margin < 100) {
        $sale_price = $cost_price / (1 - ($margin / 100));
    }

    $stmt = $pdo->prepare("UPDATE products SET name=?, barcode=?, cost_price=?, sale_price=?, stock=?, category_id=?, image_url=? WHERE id=?");
    $stmt->execute([$name, $barcode, $cost_price, $sale_price, $stock, $category_id, $image_url, $id]);

    echo json_encode(["success" => true]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
