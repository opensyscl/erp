<?php
require_once "config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = $_POST['supplier_id'];
    $invoice_number = $_POST['invoice_number'];
    $items = $_POST['items'];

    // Crear factura
    $stmt = $pdo->prepare("INSERT INTO purchase_invoices (supplier_id, invoice_number) VALUES (?, ?)");
    $stmt->execute([$supplier_id, $invoice_number]);
    $invoice_id = $pdo->lastInsertId();

    foreach ($items as $item) {
        $product_id = $item['product_id'];
        $new_cost = $item['new_cost_price'];
        $margin = $item['margin_percentage'];
        $sale_price = $item['calculated_sale_price'];
        $quantity = $item['quantity'];

        // Obtener costo anterior
        $stmt = $pdo->prepare("SELECT cost_price FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $prev = $stmt->fetchColumn();

        // Insertar detalle
        $stmt = $pdo->prepare("INSERT INTO purchase_invoice_items 
            (invoice_id, product_id, previous_cost_price, new_cost_price, margin_percentage, calculated_sale_price, quantity)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$invoice_id, $product_id, $prev, $new_cost, $margin, $sale_price, $quantity]);

        // Actualizar producto
        $stmt = $pdo->prepare("UPDATE products SET 
            stock = stock + ?, 
            cost_price = ?, 
            sale_price = ? 
            WHERE id = ?");
        $stmt->execute([$quantity, $new_cost, $sale_price, $product_id]);
    }

    header("Location: modulos.php?section=proveedores&success=1");
    exit;
}
