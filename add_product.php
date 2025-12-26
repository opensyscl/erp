<?php
require 'config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Muestra errores en desarrollo (coméntalo en producción)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Asegura que PDO lance excepciones (idealmente hazlo en config.php)
if (isset($pdo)) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["success" => false, "error" => "Método no permitido"]);
        exit;
    }

    // Normaliza números con coma o punto
    $norm = function($v, $def = '0') {
        $v = $v ?? $def;
        $v = str_replace(',', '.', $v);
        return is_numeric($v) ? $v : $def;
    };

    $name        = trim($_POST['name'] ?? '');
    $barcode     = trim($_POST['barcode'] ?? '');
    $cost_price  = $norm($_POST['cost_price'] ?? '0');
    $price       = $norm($_POST['price'] ?? '0');
    $stock       = (int)($_POST['stock'] ?? 0);
    $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;

    if ($name === '' || $barcode === '') {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Nombre y código son obligatorios"]);
        exit;
    }

    // Imagen: URL inicial (si vino)
    $image_url = trim($_POST['image_url'] ?? '');

    // Si subieron archivo, guárdalo
    if (!empty($_FILES['image_file']['name'])) {
        $targetDir = __DIR__ . "/img/";
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                throw new RuntimeException("No se pudo crear carpeta de imágenes");
            }
        }

        // Seguridad simple de nombre
        $safeName  = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES["image_file"]["name"]));
        $fileName  = time() . "_" . $safeName;
        $targetFile = $targetDir . $fileName;

        if (!is_uploaded_file($_FILES["image_file"]["tmp_name"])) {
            throw new RuntimeException("Archivo inválido");
        }

        if (!move_uploaded_file($_FILES["image_file"]["tmp_name"], $targetFile)) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Error al subir la imagen"]);
            exit;
        }

        // Construye URL pública sin hardcodear dominio
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'];
        $base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // ej: /erp
        $image_url = $scheme . "://" . $host . $base . "/img/" . $fileName;
    }

    // Inserción
    $sql = "INSERT INTO products 
            (name, barcode, cost_price, price, stock, image_url, category_id) 
            VALUES (:name, :barcode, :cost_price, :price, :stock, :image_url, :category_id)";
    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':name'        => $name,
        ':barcode'     => $barcode,
        ':cost_price'  => $cost_price,
        ':price'       => $price,
        ':stock'       => $stock,
        ':image_url'   => $image_url ?: null,
        ':category_id' => $category_id,
    ]);

    echo json_encode(["success" => true, "id" => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "DB: " . $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
