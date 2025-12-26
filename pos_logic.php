<?php
// pos_logic.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';
session_start();
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (isset($_POST['finalize'])) {
    $total = 0;
    foreach ($_SESSION['cart'] as $item) {
        $total += $item['price'] * $item['quantity'];
    }

    $method = $_POST['method'] ?? 'efectivo';
    $paid = intval($_POST['paid'] ?? 0);
    $voucher = $_POST['voucher'] ?? '';
    $change = 0;

    if (empty($_SESSION['cart'])) {
        http_response_code(400);
        echo json_encode(['error' => 'El carrito está vacío.']);
        exit();
    }

    if ($method === 'efectivo') {
        if ($paid < $total) {
            http_response_code(400);
            echo json_encode(['error' => 'El monto recibido es insuficiente.']);
            exit();
        }
        $change = $paid - $total;
    } else {
        $paid = $total;
        if (empty($voucher)) {
            http_response_code(400);
            echo json_encode(['error' => 'Debe ingresar una referencia del voucher.']);
            exit();
        }
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO sales (total, paid, change, method, voucher, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$total, $paid, $change, $method, $voucher]);
        $sale_id = $pdo->lastInsertId();

        $stmt_item = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        $stmt_stock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");

        foreach ($_SESSION['cart'] as $item) {
            $stmt_item->execute([$sale_id, $item['id'], $item['quantity'], $item['price']]);
            $stmt_stock->execute([$item['quantity'], $item['id']]);
        }

        $pdo->commit();
        $_SESSION['cart'] = [];
        header("Location: print_ticket.php?id=$sale_id");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Error al guardar venta: ' . $e->getMessage()]);
        exit();
    }
}
?>