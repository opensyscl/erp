<?php
include(__DIR__ . "/../config.php"); // Ajusta la ruta según tu estructura

if (isset($_POST["submit"])) {
    $file = $_FILES["csv_file"]["tmp_name"];

    if (($handle = fopen($file, "r")) !== FALSE) {
        // Saltar encabezados
        fgetcsv($handle, 1000, ";");

        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            $codigo_barras = trim($data[0]);
            $producto      = trim($data[1]);
            $proveedor     = trim($data[2]);

            // 1. Insertar proveedor si no existe
            $stmt = $pdo->prepare("INSERT IGNORE INTO suppliers (name) VALUES (?)");
            $stmt->execute([$proveedor]);

            // 2. Obtener supplier_id
            $stmt = $pdo->prepare("SELECT id FROM suppliers WHERE name = ?");
            $stmt->execute([$proveedor]);
            $row = $stmt->fetch();
            $supplier_id = $row["id"];

            // 3. Actualizar producto con el supplier_id
            $stmt = $pdo->prepare("UPDATE products SET supplier_id = ? WHERE barcode = ?");
            $stmt->execute([$supplier_id, $codigo_barras]);
        }

        fclose($handle);
        echo "✅ Carga completada con éxito";
    }
}
?>

<!-- Formulario -->
<form method="post" enctype="multipart/form-data">
    <input type="file" name="csv_file" accept=".csv" required>
    <button type="submit" name="submit">Subir y Procesar</button>
</form>
