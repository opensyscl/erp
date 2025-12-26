<?php
require 'config.php';
header('Content-Type: application/json');

$q = $_GET['q'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? '';

$sql = "SELECT * FROM products WHERE 1=1";
$params = [];

// Búsqueda
if ($q !== '') {
    $sql .= " AND (name LIKE ? OR barcode LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

// Filtro por categoría
if ($category !== '') {
    $sql .= " AND category_id = ?";
    $params[] = $category;
}

// Ordenamiento
switch ($sort) {
    case "price_asc":
        $sql .= " ORDER BY price ASC";
        break;
    case "price_desc":
        $sql .= " ORDER BY price DESC";
        break;
    case "stock_asc":
        $sql .= " ORDER BY stock ASC";
        break;
    case "stock_desc":
        $sql .= " ORDER BY stock DESC";
        break;
    case "name_asc":
        $sql .= " ORDER BY name ASC";
        break;
    case "name_desc":
        $sql .= " ORDER BY name DESC";
        break;
    default:
        $sql .= " ORDER BY id DESC"; // predeterminado
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
