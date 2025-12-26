<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de producto inválido.");
}

$id = (int) $_GET['id'];

// Obtener datos actuales del producto
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Producto no encontrado.");
}

// 🔹 Obtener todas las categorías disponibles
$categoriesStmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode     = trim($_POST['barcode']);
    $name        = trim($_POST['name']);
    $cost_price  = floatval($_POST['cost_price']);
    $sale_price  = floatval($_POST['sale_price']);
    $stock       = intval($_POST['stock']);
    $category_id = intval($_POST['category_id']);

    // Imagen actual si no se sube nueva
    $image_url = $product['image_url'];

    if (!empty($_FILES['image']['name'])) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = time() . "_" . basename($_FILES['image']['name']);
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $image_url = $fileName; // 🔹 solo guardamos el nombre
        }
    }

    $update = $pdo->prepare("UPDATE products 
                             SET barcode = :barcode, name = :name, stock = :stock,
                                 cost_price = :cost_price, sale_price = :sale_price,
                                 category_id = :category_id, image_url = :image_url, 
                                 updated_at = NOW()
                             WHERE id = :id");

    $update->execute([
        'barcode'     => $barcode,
        'name'        => $name,
        'stock'       => $stock,
        'cost_price'  => $cost_price,
        'sale_price'  => $sale_price,
        'category_id' => $category_id,
        'image_url'   => $image_url,
        'id'          => $id
    ]);

    header("Location: inventario.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f6f9; }
        h1 { text-align: center; }
        form {
            max-width: 600px; margin: auto; background: white; padding: 20px; border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        label { display: block; margin-top: 15px; font-weight: bold; }
        input[type="text"], input[type="number"], input[type="file"], select {
            width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 5px;
        }
        img { max-width: 100%; margin-top: 10px; border-radius: 8px; }
        button {
            margin-top: 20px; padding: 10px 20px; background: #28a745; color: white;
            border: none; border-radius: 5px; cursor: pointer;
        }
        button:hover { background: #218838; }
        .back { display: block; margin-top: 20px; text-align: center; }
        .margin-box { margin-top: 10px; font-weight: bold; color: #007bff; }
    </style>
    <script>
        function calcularMargen() {
            let cost = parseFloat(document.getElementById('cost_price').value) || 0;
            let sale = parseFloat(document.getElementById('sale_price').value) || 0;
            if (cost > 0 && sale > 0) {
                let margen = ((sale - cost) / cost) * 100;
                document.getElementById('margen').innerText = "Margen actual: " + margen.toFixed(2) + "%";
            } else {
                document.getElementById('margen').innerText = "";
            }
        }

        function aplicarMargen() {
            let cost = parseFloat(document.getElementById('cost_price').value) || 0;
            let porcentaje = parseFloat(prompt("Ingrese el % de ganancia (ej: 30):", "30"));
            if (!isNaN(cost) && !isNaN(porcentaje)) {
                let nuevoPrecio = cost / (1 - (porcentaje/100));
                document.getElementById('sale_price').value = nuevoPrecio.toFixed(0);
                calcularMargen();
            }
        }
    </script>
</head>
<body onload="calcularMargen()">

<h1>Editar Producto</h1>

<form method="POST" enctype="multipart/form-data">
    <label>Código de barras:</label>
    <input type="text" name="barcode" value="<?php echo htmlspecialchars($product['barcode']); ?>" required>

    <label>Nombre:</label>
    <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>

    <label>Precio costo:</label>
    <input type="number" step="0.01" id="cost_price" name="cost_price" value="<?php echo htmlspecialchars($product['cost_price']); ?>" oninput="calcularMargen()" required>

    <label>Precio de venta:</label>
    <input type="number" step="0.01" id="sale_price" name="sale_price" value="<?php echo htmlspecialchars($product['sale_price']); ?>" oninput="calcularMargen()" required>
    
    <div class="margin-box" id="margen"></div>
    <button type="button" onclick="aplicarMargen()">📈 Calcular desde % de ganancia</button>

    <label>Stock:</label>
    <input type="number" name="stock" value="<?php echo htmlspecialchars($product['stock']); ?>" required>

    <label>Categoría:</label>
    <select name="category_id" required>
        <option value="">-- Selecciona una categoría --</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?php echo $cat['id']; ?>" <?php echo ($product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($cat['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Imagen actual:</label>
    <?php if (!empty($product['image_url'])): ?>
        <img src="uploads/<?php echo htmlspecialchars($product['image_url']); ?>" alt="Imagen producto">
    <?php else: ?>
        <p>No hay imagen</p>
    <?php endif; ?>

    <label>Subir nueva imagen:</label>
    <input type="file" name="image">

    <button type="submit">💾 Guardar Cambios</button>
</form>

<div class="back">
    <a href="inventario.php">⬅️ Volver al Inventario</a>
</div>

</body>
</html>
