<?php
// Reportar todos los errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluye el archivo de configuración de la base de datos
require 'config.php';

// Autocargador personalizado para PhpSpreadsheet
spl_autoload_register(function ($class) {
    $prefix = 'PhpOffice\\PhpSpreadsheet\\';
    $base_dir = __DIR__ . '/PhpOffice/PhpSpreadsheet/src/PhpSpreadsheet/'; // Asegúrate de que esta ruta sea correcta
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use PhpOffice\PhpSpreadsheet\IOFactory;

$message = '';
$is_success = false;

// Verifica si se ha subido un archivo
if (isset($_FILES['xlsx_file'])) {
    $file = $_FILES['xlsx_file'];
    
    // Verifica si el archivo es un XLSX
    $allowed_extensions = ['xlsx'];
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    
    if (!in_array(strtolower($file_extension), $allowed_extensions)) {
        $message = "Error: El formato del archivo debe ser .xlsx.";
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $message = "Error al subir el archivo. Código: " . $file['error'];
    } else {
        try {
            // --- INICIA LA TRANSACCIÓN AQUÍ ---
            $pdo->beginTransaction();

            // Cargar el archivo con PhpSpreadsheet
            $spreadsheet = IOFactory::load($file['tmp_name']);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $inserted_count = 0;
            
            // Preparamos las sentencias SQL fuera del bucle para mayor eficiencia
            $product_stmt = $pdo->prepare("INSERT INTO products (name, barcode, cost_price, sale_price, stock, category_id) VALUES (?, ?, ?, ?, ?, ?)");
            $category_stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
            $insert_category_stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");

            foreach ($worksheet->getRowIterator() as $row) {
                if ($row->getRowIndex() === 1) { // Saltar la fila de encabezado
                    continue;
                }

                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                $data = [];
                foreach ($cellIterator as $cell) {
                    $data[] = $cell->getValue();
                }

                // Asumiendo el orden: barcode, name, category, stock, cost, margin, sale_price
                if (count($data) >= 7) {
                    $barcode = $data[0] ?? null;
                    $name = $data[1] ?? null;
                    $category_name = $data[2] ?? 'Sin Categoría';
                    $stock = is_numeric($data[3]) ? intval($data[3]) : 0;
                    $cost_price = is_numeric($data[4]) ? floatval($data[4]) : 0;
                    $margin = is_numeric($data[5]) ? floatval($data[5]) : 0;
                    
                    $sale_price = 0; // Initialize sale_price
                    // Calcula el precio de venta si el margen es válido
                    if ($cost_price > 0 && $margin > 0 && $margin < 100) {
                        $sale_price = $cost_price / (1 - ($margin / 100));
                    } else {
                        // Usa el precio de venta proporcionado en el archivo si el cálculo no es posible
                        $sale_price = is_numeric($data[6]) ? floatval($data[6]) : 0;
                    }
                    
                    // Verifica si ya existe una categoría con ese nombre
                    $category_stmt->execute([$category_name]);
                    $category = $category_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $category_id = null;
                    if (!$category) {
                        // Si la categoría no existe, la inserta
                        $insert_category_stmt->execute([$category_name]);
                        $category_id = $pdo->lastInsertId();
                    } else {
                        // Si la categoría ya existe, usa su ID
                        $category_id = $category['id'];
                    }

                    // Inserta el producto
                    $product_stmt->execute([$name, $barcode, $cost_price, $sale_price, $stock, $category_id]);
                    $inserted_count++;
                } else {
                    // Opcional: Registrar o avisar sobre filas con datos insuficientes
                    // error_log("Fila saltada por datos insuficientes: " . implode(" | ", $data));
                }
            }
            
            // --- CONFIRMA LA TRANSACCIÓN SI TODO SALIO BIEN ---
            $pdo->commit();
            $message = "Carga masiva completada. Se insertaron $inserted_count productos.";
            $is_success = true;

        } catch (\Exception $e) {
            // --- REVIERTE LA TRANSACCIÓN EN CASO DE ERROR ---
            // La línea 105 original estaba aquí, pero ahora está protegida por el try-catch
            $pdo->rollBack(); 
            $message = "Error al procesar el archivo XLSX: " . $e->getMessage();
            // Opcional: Loguear el error detallado para depuración
            error_log("PDOException in process_upload.php: " . $e->getMessage());
        }
    }
} else {
    $message = "No se ha subido ningún archivo.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resultado de Carga</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
        }
        .result-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 100%;
        }
        .message {
            padding: 15px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.1em;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: #4A90E2;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="result-container">
        <h1>Resultado de la Carga</h1>
        <div class="message <?php echo $is_success ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <a href="upload.html" class="back-link">Volver al Formulario de Carga</a>
    </div>
</body>
</html>