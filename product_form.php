<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'config.php';

$edit = false;
$product = [
    'barcode' => '',
    'name' => '',
    'category' => '',
    'price' => 0,
    'stock' => 0
];

if (isset($_GET['id'])) {
    $edit = true;
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode  = trim($_POST['barcode']);
    $name     = trim($_POST['name']);
    $price    = floatval($_POST['price']);
    $stock    = intval($_POST['stock']);
    $category = trim($_POST['category']);

    try {
        if ($edit) {
            $stmt = $pdo->prepare("UPDATE products SET barcode=?, name=?, price=?, stock=?, category=? WHERE id=?");
            $stmt->execute([$barcode, $name, $price, $stock, $category, $_GET['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO products (barcode, name, price, stock, category) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$barcode, $name, $price, $stock, $category]);
        }
        header("Location: inventory.php");
        exit();
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo "<h2 style='color:red; font-family: Arial'>⚠️ El código de barras <strong>$barcode</strong> ya existe en el inventario.</h2>";
            echo "<p><a href='inventory.php'>⬅️ Volver al inventario</a></p>";
            exit();
        } else {
            echo "<h2 style='color:red'>Error: " . $e->getMessage() . "</h2>";
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $edit ? 'Editar' : 'Nuevo'; ?> producto - Listto! POS</title>
<link rel="stylesheet" href="assets/styles.css">
</head>
<body class="container">
<header class="nav">
  <a href="pos.php">POS</a>
  <a href="inventory.php" class="active">Inventario</a>
  <a href="sales.php">Ventas</a>
</header>

<h1><?php echo $edit ? 'Editar' : 'Nuevo'; ?> producto</h1>

<form method="post" class="card">
  <label>Código de barras
    <input type="text" name="barcode" value="<?php echo htmlspecialchars($product['barcode']); ?>" autofocus>
  </label>
  <label>Nombre
    <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
  </label>
  <label>Categoría
    <input type="text" name="category" value="<?php echo htmlspecialchars($product['category']); ?>">
  </label>
  <label>Precio (CLP)
    <input type="number" name="price" min="0" step="1" value="<?php echo (int)$product['price']; ?>" required>
  </label>
  <label>Stock
    <input type="number" name="stock" min="0" step="1" value="<?php echo (int)$product['stock']; ?>" required>
  </label>
  <div class="form-actions">
    <a class="btn" href="inventory.php">Cancelar</a>
    <button class="btn primary" type="submit">Guardar</button>
  </div>
</form>

</body>
</html>