
<?php
require_once __DIR__ . '/../init.php';
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$items = $data['items'] ?? [];
$paid = (int)($data['paid'] ?? 0);

if (!$items || !is_array($items)) {
    echo json_encode(['ok'=>false, 'error'=>'No hay items']); exit;
}

try {
    $pdo->beginTransaction();

    // Load products and validate stock
    $total = 0;
    $prepared = $pdo->prepare('SELECT * FROM products WHERE id=? FOR UPDATE');
    foreach ($items as &$it) {
        $pid = (int)$it['product_id'];
        $qty = max(1, (int)$it['quantity']);
        $prepared->execute([$pid]);
        $p = $prepared->fetch();
        if (!$p) { throw new Exception('Producto no existe'); }
        if ($p['stock'] < $qty) { throw new Exception('Stock insuficiente para: ' . $p['name']); }
        $it['unit_price'] = (int)$p['price'];
        $it['subtotal'] = $qty * (int)$p['price'];
        $total += $it['subtotal'];
    }

    $change = max(0, $paid - $total);
    $insSale = $pdo->prepare('INSERT INTO sales (total, paid, change_due, created_at) VALUES (?,?,?,?)');
    $insSale->execute([$total, $paid, $change, date('Y-m-d H:i:s')]);
    $sale_id = (int)$pdo->lastInsertId();

    $insItem = $pdo->prepare('INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?,?,?,?,?)');
    $updStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id=?');
    foreach ($items as $it) {
        $insItem->execute([$sale_id, (int)$it['product_id'], (int)$it['quantity'], (int)$it['unit_price'], (int)$it['subtotal']]);
        $updStock->execute([(int)$it['quantity'], (int)$it['product_id']]);
    }

    $pdo->commit();
    echo json_encode(['ok'=>true, 'sale_id'=>$sale_id, 'total'=>$total, 'paid'=>$paid, 'change'=>$change]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
