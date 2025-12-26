<?php
// api/products_search.php
require '../config.php';

header('Content-Type: application/json');

if (isset($_GET['q'])) {
    $query = '%' . $_GET['q'] . '%';
    $stmt = $pdo->prepare("SELECT id, name, barcode, price, image_url FROM products WHERE name LIKE ? OR barcode LIKE ? LIMIT 10");
    $stmt->execute([$query, $query]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($products);
}
?>