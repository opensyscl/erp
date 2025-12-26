<?php
session_start();
header('Content-Type: application/json');

// Inicializar carrito si no existe
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Verificar que se recibió un ID válido
if (isset($_POST['product_id']) && is_numeric($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);

    // Si el producto no está en el carrito, agregarlo con cantidad 1
    if (!isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] = 1;
    } else {
        // Si ya existe, incrementar la cantidad
        $_SESSION['cart'][$product_id]++;
    }

    $total_items = array_sum($_SESSION['cart']);

    echo json_encode([
        'success' => true,
        'cartCount' => $total_items,
        'message' => "Producto añadido al carrito.",
        'productId' => $product_id,
        'quantity' => $_SESSION['cart'][$product_id]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => "ID de producto inválido."
    ]);
}
