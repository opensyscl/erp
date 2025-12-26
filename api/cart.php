<?php
// api/cart.php
session_start();
require '../config.php';

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add':
        $id = $_POST['id'];
        $stmt = $pdo->prepare("SELECT id, name, price, image_url FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            if (isset($_SESSION['cart'][$id])) {
                $_SESSION['cart'][$id]['quantity']++;
            } else {
                $_SESSION['cart'][$id] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'quantity' => 1,
                    'image_url' => $product['image_url'] // Añade la URL de la imagen
                ];
            }
        }
        break;

    case 'update':
        $id = $_POST['id'];
        $quantity = $_POST['quantity'];
        if ($quantity > 0) {
            $_SESSION['cart'][$id]['quantity'] = $quantity;
        } else {
            unset($_SESSION['cart'][$id]);
        }
        break;

    case 'clear':
        $_SESSION['cart'] = [];
        break;
}

echo json_encode($_SESSION['cart']);
?>