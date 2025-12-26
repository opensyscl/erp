<?php
require 'config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id         = $_POST['id'] ?? null;
    $name       = $_POST['name'] ?? '';
    $barcode    = $_POST['barcode'] ?? '';
    $cost_price = $_POST['cost_price'] ?? 0;
    $price      = $_POST['price'] ?? 0;
    $stock      = $_POST['stock'] ?? 0;
    $category_id= $_POST['category_id'] ?? null;

    // --- Manejo de la imagen ---
    $image_url = $_POST['image_url'] ?? '';

    if (!empty($_FILES['image_file']['name'])) {
        $targetDir  = __DIR__ . "/img/"; // Carpeta física
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $fileName   = time() . "_" . basename($_FILES["image_file"]["name"]);
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES["image_file"]["tmp_name"], $targetFile)) {
            // Ruta pública (ajusta según tu dominio/ruta)
            $image_url = "https://tiendaslistto.cl/erp/img/" . $fileName;
        } else {
            echo json_encode(["success" => false, "error" => "Error al subir la imagen"]);
            exit;
        }
    }

    $stmt = $pdo->prepare("UPDATE products 
        SET name=?, barcode=?, cost_price=?, price=?, stock=?, image_url=?, category_id=? 
        WHERE id=?"
    );

    $success = $stmt->execute([
        $name, $barcode, $cost_price, $price, $stock, $image_url, $category_id, $id
    ]);

    echo json_encode(["success" => $success]);
}
?>
