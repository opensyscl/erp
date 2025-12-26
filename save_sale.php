<?php
require "config.php";
header("Content-Type: application/json; charset=utf-8");

try {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input || !isset($input["cart"]) || count($input["cart"]) === 0) {
        echo json_encode(["success" => false, "error" => "Carrito vacío"]);
        exit;
    }

    $total   = $input["total"] ?? 0;
    $paid    = $input["paid"] ?? $total;   // asumimos que paga el total
    $method  = $input["method"] ?? "efectivo";
    $voucher = $input["voucher"] ?? "";
    $change  = $paid - $total;
    $cart    = $input["cart"];

    $pdo->beginTransaction();

    // --- Generar correlativo de boleta/factura ---
    $pdo->exec("UPDATE counters SET value = value + 1 WHERE name = 'receipt_number'");
    $stmtCounter = $pdo->query("SELECT value FROM counters WHERE name = 'receipt_number'");
    $receiptNumber = $stmtCounter->fetchColumn();

    // --- Insertar en sales ---
    $stmt = $pdo->prepare("
        INSERT INTO sales (total, paid, `change`, change_due, method, voucher, receipt_number, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$total, $paid, $change, $change, $method, $voucher, $receiptNumber]);
    $saleId = $pdo->lastInsertId();

    // --- Insertar productos en sale_items ---
    $stmtItem = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price)
                               VALUES (?, ?, ?, ?)");
foreach ($data['cart'] as $item) {
    $id = intval($item['id']);
    $qty = intval($item['quantity']);
    $price = floatval($item['price']);

    if ($qty <= 0) {
        continue; // ❌ salta los productos sin cantidad válida
    }

    $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    $stmt->execute([$saleId, $id, $qty, $price]);
}
    $pdo->commit();
    echo json_encode(["success" => true, "sale_id" => $saleId]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
