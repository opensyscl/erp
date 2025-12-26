<?php
// Configuración de la base de datos
$servername = "localhost";
$username   = "puntodat_erplistto";
$password   = "TListto.2025";
$dbname     = "puntodat_erplistto";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("❌ Conexión fallida: " . $conn->connect_error);
}

// Variables para los mensajes de estado
$message = '';
$message_class = ''; // 'success' o 'error'

// Procesar formulario
if (isset($_POST["importar"])) {
    if ($_FILES["archivo"]["error"] == UPLOAD_ERR_OK) {
        $filename = $_FILES["archivo"]["tmp_name"];
        $mime_type = mime_content_type($filename);

        if ($mime_type === 'text/plain' || $mime_type === 'text/csv') {
            if ($_FILES["archivo"]["size"] > 0) {
                $file = fopen($filename, "r");

                // Leer cabecera y descartarla
                fgetcsv($file, 1000, ";");

                $insertados = 0;
                $actualizados = 0;

                while (($col = fgetcsv($file, 1000, ";")) !== FALSE) {
                    // Validar que la fila tenga suficientes columnas
                    if (count($col) < 7) {
                        continue; // Salta esta fila si no tiene los datos completos
                    }

                    $barcode   = $conn->real_escape_string($col[0]);
                    $name      = $conn->real_escape_string($col[1]);
                    $stock     = (int)$col[2];
                    $cost      = (float)$col[3];
                    $sale      = (float)$col[4];
                    $category  = $conn->real_escape_string($col[5]);
                    $image_url = isset($col[6]) ? $conn->real_escape_string($col[6]) : '';

                    // Insertar o actualizar el producto
                    $sql = "INSERT INTO products
                            (barcode, name, stock, cost_price, sale_price, category, image_url, price, created_at, updated_at)
                            VALUES ('$barcode', '$name', $stock, $cost, $sale, '$category', '$image_url', $sale, NOW(), NOW())
                            ON DUPLICATE KEY UPDATE
                                name=VALUES(name),
                                stock=VALUES(stock),
                                cost_price=VALUES(cost_price),
                                sale_price=VALUES(sale_price),
                                category=VALUES(category),
                                image_url=VALUES(image_url),
                                price=VALUES(price),
                                updated_at=NOW()";

                    if ($conn->query($sql) === TRUE) {
                        if ($conn->affected_rows == 1) {
                            $insertados++;
                        } elseif ($conn->affected_rows == 2) {
                            $actualizados++;
                        }
                    }
                }

                fclose($file);
                $message = "✅ Importación completada. <br>📦 **Nuevos productos:** $insertados<br>🔄 **Productos actualizados:** $actualizados";
                $message_class = 'success';
            } else {
                $message = "❌ El archivo está vacío.";
                $message_class = 'error';
            }
        } else {
            $message = "❌ Tipo de archivo no válido. Por favor, suba un archivo .csv.";
            $message_class = 'error';
        }
    } else {
        $message = "❌ Error al subir el archivo. Código: " . $_FILES["archivo"]["error"];
        $message_class = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Productos CSV</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            color: #333;
        }

        .container {
            background-color: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 100%;
            max-width: 500px;
            box-sizing: border-box;
        }

        h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 25px;
            color: #444;
        }

        .form-group {
            margin-bottom: 25px;
        }

        input[type="file"] {
            display: block;
            width: 100%;
            padding: 12px;
            border: 2px dashed #ccc;
            border-radius: 8px;
            font-size: 1em;
            color: #555;
            cursor: pointer;
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }
        
        input[type="file"]:hover {
            border-color: #999;
        }

        button[type="submit"] {
            width: 100%;
            padding: 15px;
            background-color: #4A90E2;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        
        button[type="submit"]:hover {
            background-color: #357ABD;
            transform: translateY(-2px);
        }

        .message-container {
            margin-top: 25px;
            padding: 20px;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 500;
            line-height: 1.6;
        }
        
        .message-container.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message-container.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Importar Productos desde CSV</h2>
        <?php if ($message): ?>
            <div class="message-container <?php echo $message_class; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <input type="file" name="archivo" accept=".csv" required>
            </div>
            <button type="submit" name="importar">Importar Productos</button>
        </form>
    </div>
</body>
</html>