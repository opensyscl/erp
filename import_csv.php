
<?php
require_once __DIR__ . '/init.php';
$err = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    if ($_FILES['csv']['error'] === UPLOAD_ERR_OK) {
        $path = $_FILES['csv']['tmp_name'];
        $fh = fopen($path, 'r');
        if ($fh) {
            $header = fgetcsv($fh);
            $map = array_flip($header);
            $required = ['barcode','name','price','stock','category'];
            foreach ($required as $r) { if (!isset($map[$r])) { $err = 'CSV debe tener cabeceras: barcode,name,price,stock,category'; break; } }
            if (!$err) {
                $ins = $pdo->prepare('INSERT INTO products (barcode,name,price,stock,category) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), price=VALUES(price), stock=VALUES(stock), category=VALUES(category)');
                $count = 0;
                while (($row = fgetcsv($fh)) !== false) {
                    if (count($row) < 5) continue;
                    $barcode = trim($row[$map['barcode']]);
                    $name = trim($row[$map['name']]);
                    $price = (int)$row[$map['price']];
                    $stock = (int)$row[$map['stock']];
                    $category = trim($row[$map['category']]);
                    if ($name === '') continue;
                    $ins->execute([$barcode,$name,$price,$stock,$category]);
                    $count++;
                }
                $ok = "Importados/actualizados: $count";
            }
            fclose($fh);
        } else {
            $err = 'No se pudo leer el archivo.';
        }
    } else {
        $err = 'Error al subir archivo.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Importar CSV - Listto! POS</title>
<link rel="stylesheet" href="assets/styles.css">
</head>
<body class="container">
<header class="nav">
  <a href="pos.php">POS</a>
  <a href="inventory.php" class="active">Inventario</a>
  <a href="sales.php">Ventas</a>
</header>

<h1>Importar CSV</h1>
<?php if ($err): ?><div class="alert error"><?php echo h($err); ?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert success"><?php echo h($ok); ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card">
  <p>El CSV debe tener cabeceras: <code>barcode,name,price,stock,category</code></p>
  <input type="file" name="csv" accept=".csv" required>
  <div class="form-actions">
    <a class="btn" href="inventory.php">Volver</a>
    <button class="btn primary" type="submit">Importar</button>
  </div>
</form>

</body>
</html>
