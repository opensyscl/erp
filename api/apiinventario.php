<?php
// ==================================================================
// CONTROLADOR DE INVENTARIO (CRUD y Lógica de Negocio)
// ==================================================================

// 1. CONFIGURACIÓN E INCLUSIÓN DE DEPENDENCIAS
// ------------------------------------------------------------------
require '../config.php';

// --- INCLUSIÓN DE FPDF (REQUIERE INSTALAR LA LIBRERÍA) ---
// Si usas FPDF, descomenta la siguiente línea y asegúrate de que fpdf.php esté en la ruta correcta.
// require('../fpdf/fpdf.php'); // Ejemplo de inclusión

// 2. FUNCIÓN DE UTILIDAD: SANITIZACIÓN PARA ORDENAMIENTO
// ------------------------------------------------------------------
/**
 * Sanitiza y prepara la cláusula ORDER BY, asegurando que los nombres de columna 
 * sean válidos y estén protegidos por acentos graves (`).
 * @param string $sort_by Cadena con el formato 'columna_direccion' (ej: 'price_asc').
 * @return string Cláusula SQL ORDER BY sanitizada.
 */
function get_sanitized_order_clause($sort_by) {
    // Definir columnas permitidas y sus prefijos de tabla
    $allowed_columns = [
        'name' => 'p', 
        'price' => 'p', 
        'stock' => 'p', 
        'barcode' => 'p',
        'updated_at' => 'p', // La columna que daba el error (se mantiene)
    ];
    
    // Ordenamiento por defecto si no se proporciona
    if (empty($sort_by)) {
        return " ORDER BY p.`updated_at` DESC ";
    }

    // Dividir la columna y la dirección
    $parts = explode('_', $sort_by);
    $col = $parts[0] ?? 'updated_at';
    $dir = strtoupper($parts[1] ?? 'desc');

    // Validar dirección de ordenamiento
    if (!in_array($dir, ['ASC', 'DESC'])) {
        $dir = 'DESC';
    }

    // Validar columna y obtener prefijo
    if (isset($allowed_columns[$col])) {
        $prefix = $allowed_columns[$col];
        // CORRECCIÓN CLAVE: Usar comillas de acento grave (`) para proteger el nombre de columna.
        return " ORDER BY {$prefix}.`{$col}` {$dir} ";
    }

    // Si la columna no está permitida, se devuelve el ordenamiento por defecto
    return " ORDER BY p.`updated_at` DESC ";
}

// 3. INICIALIZACIÓN Y CONFIGURACIÓN DE RESPUESTA
// ------------------------------------------------------------------
$action = $_GET['action'] ?? '';
$response = ['success' => false, 'message' => ''];

// Establecer cabecera de JSON por defecto, EXCEPTO para la acción de exportación
if ($action !== 'export_products' && !headers_sent()) {
    header('Content-Type: application/json');
}

// 4. LÓGICA PRINCIPAL (Switch/Case)
// ------------------------------------------------------------------
try {
    switch ($action) {

        // --- CASOS CRUD: AGREGAR/EDITAR PRODUCTO ---
        case 'add_product':
        case 'edit_product':
            $id = $_POST['id'] ?? null;
            $barcode = $_POST['barcode'];
            $name = $_POST['name'];
            $price = floatval($_POST['price']);
            $cost_price = floatval($_POST['cost_price']);
            $stock = (int)$_POST['stock'];
            $category_id = (int)$_POST['category_id'];
            $supplier_id = (int)$_POST['supplier_id'];
            $image_url = $_POST['image_url'] ?? null;

            // Manejo de la subida de la imagen
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['product_image']['tmp_name'];
                $fileName = uniqid() . '_' . basename($_FILES['product_image']['name']);
                $destPath = '../uploads/' . $fileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $image_url = 'uploads/' . $fileName;
                } else {
                    $response['message'] = 'Error al subir la imagen.';
                    echo json_encode($response);
                    exit;
                }
            }
            
            // Asignar NULL si el supplier_id es 0 (para bases de datos MySQL)
            $supplier_id_db = ($supplier_id === 0) ? null : $supplier_id;
            
            if ($id) {
                // UPDATE
                $sql = "UPDATE products SET barcode=?, name=?, price=?, cost_price=?, stock=?, category_id=?, supplier_id=?, image_url=?, updated_at=NOW() WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$barcode, $name, $price, $cost_price, $stock, $category_id, $supplier_id_db, $image_url, $id]);
            } else {
                // INSERT
                $sql = "INSERT INTO products (barcode, name, price, cost_price, stock, category_id, supplier_id, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$barcode, $name, $price, $cost_price, $stock, $category_id, $supplier_id_db, $image_url]);
            }
            $response['success'] = true;
            $response['message'] = 'Producto guardado correctamente.';
            break;

        // --- CASOS CRUD: OBTENER PRODUCTO POR ID ---
        case 'get_product':
            $id = $_GET['id'] ?? null;
            if ($id) {
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($product) {
                    $response = $product; // Devolver el producto directamente
                } else {
                    $response['message'] = 'Producto no encontrado.';
                }
            }
            break;

        // --- CASOS CRUD: DUPLICAR PRODUCTO ---
        case 'duplicate_product':
            $id = $_GET['id'] ?? null;
            if ($id) {
                // Se agrega 'archived = 0' al duplicado por defecto.
                $stmt = $pdo->prepare("INSERT INTO products (barcode, name, price, cost_price, stock, category_id, supplier_id, image_url, archived) SELECT CONCAT(barcode, '-copy'), name, price, cost_price, stock, category_id, supplier_id, image_url, 0 FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $response['success'] = true;
                $response['message'] = 'Producto duplicado correctamente.';
            }
            break;

        // --- CASOS CRUD: ELIMINAR PRODUCTO (Hard Delete) ---
        case 'delete_product':
            $id = $_GET['id'] ?? null;
            if ($id) {
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $response['success'] = true;
                $response['message'] = 'Producto eliminado correctamente.';
            }
            break;

        // ------------------------------------------------------------------
        // 🚀 CASOS: ARCHIVAR Y RESTAURAR (Soft Delete)
        // ------------------------------------------------------------------
        case 'archive_product':
        case 'restore_product':
            $product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$product_id) {
                $response['message'] = 'ID de producto no especificado.';
                break;
            }

            $is_archiving = ($action === 'archive_product');
            $archive_value = $is_archiving ? 1 : 0;

            $stmt = $pdo->prepare("UPDATE products SET archived = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$archive_value, $product_id]);

            $response['success'] = true;
            $response['message'] = 'Producto ' . ($is_archiving ? 'archivado' : 'restaurado') . ' correctamente.';
            break;
        
        // ------------------------------------------------------------------
        // 📊 CASO: KPI CONTEO DE ARCHIVADOS
        // ------------------------------------------------------------------
        case 'count_archived':
            try {
                // Contar todos los productos donde archived = 1
                $stmt = $pdo->prepare("SELECT COUNT(id) as count FROM products WHERE archived = 1");
                $stmt->execute();
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                $response['success'] = true;
                $response['count'] = (int)$count;
                $response['message'] = 'Conteo de archivados exitoso.';
            } catch (PDOException $e) {
                http_response_code(500);
                $response['success'] = false;
                $response['message'] = 'Error de base de datos al contar archivados.';
            }
            break;

        // --- CASOS DE CATEGORÍAS/PROVEEDORES: AÑADIR CATEGORÍA ---
        case 'add_category':
            $name = $_POST['name'] ?? null;
            if ($name) {
                $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
                $stmt->execute([$name]);
                $newId = $pdo->lastInsertId();
                $response['success'] = true;
                $response['message'] = 'Categoría añadida correctamente.';
                $response['new_category'] = ['id' => $newId, 'name' => $name];
            } else {
                $response['message'] = 'El nombre de la categoría es obligatorio.';
            }
            break;

        // --- CASOS DE CATEGORÍAS/PROVEEDORES: AÑADIR PROVEEDOR ---
        case 'add_supplier':
            $name = $_POST['name'] ?? null;
            $contact_info = $_POST['contact_info'] ?? null;
            
            if ($name) {
                $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_info) VALUES (?, ?)");
                $stmt->execute([$name, $contact_info]);
                $newId = $pdo->lastInsertId();
                $response['success'] = true;
                $response['message'] = 'Proveedor añadido correctamente.';
                $response['new_supplier'] = ['id' => $newId, 'name' => $name];
            } else {
                $response['message'] = 'El nombre del proveedor es obligatorio.';
            }
            break;
            
        // --- CASOS DE CATEGORÍAS/PROVEEDORES: ELIMINAR PROVEEDOR ---
        case 'delete_supplier':
            $id = $_GET['id'] ?? null;
            if ($id) {
                $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
                $stmt->execute([$id]);
                $response['success'] = true;
                $response['message'] = 'Proveedor eliminado correctamente.';
            } else {
                $response['message'] = 'ID de proveedor no proporcionado.';
            }
            break;

        // ------------------------------------------------------------------
        // 📄 CASO: LÓGICA DE EXPORTACIÓN (CSV/XLS)
        // ------------------------------------------------------------------
        case 'export_products':
            // 1. Obtener y sanitizar filtros
            $format = strtolower($_GET['format'] ?? 'xls');
            $search = $_GET['search'] ?? '';
            $category_id = $_GET['category_id'] ?? 'all';
            $supplier_id = $_GET['supplier_id'] ?? 'all';
            $kpi_filter = $_GET['kpi_filter'] ?? 'all';
            $sort_by = $_GET['sort_by'] ?? 'updated_at_desc';

            // 2. Construir la consulta SQL
            $sql_select = "p.barcode, p.name AS product_name, p.stock, p.cost_price, p.price, c.name AS category_name, s.name AS supplier_name";
            $sql_from = "FROM products p
                         LEFT JOIN categories c ON p.category_id = c.id
                         LEFT JOIN suppliers s ON p.supplier_id = s.id";
            $sql_where = " WHERE p.archived = 0 "; // Filtrar solo productos activos por defecto
            $params = [];
            
            // Lógica de filtrado por KPI
            if ($kpi_filter !== 'all') {
                switch ($kpi_filter) {
                    case 'low-stock':
                        $sql_where .= " AND p.stock < 5 AND p.stock > 0 ";
                        break;
                    case 'out-of-stock':
                        $sql_where .= " AND p.stock = 0 ";
                        break;
                    case 'no-image':
                        $sql_where .= " AND (p.image_url IS NULL OR p.image_url = '') ";
                        break;
                    case 'no-supplier':
                        $sql_where .= " AND (p.supplier_id IS NULL OR p.supplier_id = 0) ";
                        break;
                    case 'negative-stock':
                        $sql_where .= " AND p.stock < 0 ";
                        break;
                }
            }

            // Lógica de filtrado por Categoría
            if ($category_id !== 'all') {
                $sql_where .= " AND p.category_id = :category_id ";
                $params[':category_id'] = $category_id;
            }
            
            // Lógica de filtrado por Proveedor
            if ($supplier_id !== 'all') {
                if ($supplier_id === '0' || $supplier_id === 0) {
                    $sql_where .= " AND (p.supplier_id IS NULL OR p.supplier_id = 0) ";
                } else {
                    $sql_where .= " AND p.supplier_id = :supplier_id ";
                    $params[':supplier_id'] = (int)$supplier_id;
                }
            }

            // Lógica de búsqueda
            if (!empty($search)) {
                $sql_where .= " AND (p.name LIKE :search OR p.barcode LIKE :search) ";
                $params[':search'] = '%' . $search . '%';
            }
            
            // Lógica de ordenamiento (¡Utilizando la función sanitizada!)
            $order_clause = get_sanitized_order_clause($sort_by);
            
            // 3. Obtener los datos completos
            $stmt = $pdo->prepare("SELECT " . $sql_select . " " . $sql_from . $sql_where . $order_clause);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Manejo de datos vacíos
            if (empty($products)) {
                header('Content-Type: text/plain');
                echo "No se encontraron productos activos para exportar con los filtros seleccionados.";
                exit;
            }
            
            // 4. Generar el archivo CSV/XLS
            if ($format === 'xls' || $format === 'csv') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="ExportKPI_' . date('Ymd_His') . '.csv"');
                
                $output = fopen('php://output', 'w');
                
                // Cabeceras del CSV
                $headers = [
                    'Código de Barras',
                    'Nombre del Producto',
                    'Stock',
                    'Precio Costo',
                    'Precio Venta',
                    'Categoría',
                    'Proveedor'
                ];
                // Escribir las cabeceras (usando ';' como delimitador para compatibilidad con Excel)
                fputcsv($output, $headers, ';');

                // Escribir los datos
                foreach ($products as $row) {
                    fputcsv($output, [
                        $row['barcode'],
                        $row['product_name'],
                        $row['stock'],
                        number_format($row['cost_price'], 2, '.', ''), // Formato numérico
                        number_format($row['price'], 2, '.', ''),     // Formato numérico
                        $row['category_name'] ?? 'N/A',
                        $row['supplier_name'] ?? 'N/A',
                    ], ';');
                }
                
                fclose($output);
                exit; // Terminar ejecución después de la descarga

            } elseif ($format === 'pdf') {
                // Generación de PDF (Requiere una librería externa)
                header('Content-Type: text/plain');
                echo "Para exportar a PDF, debes incluir una librería de PHP como FPDF o TCPDF.";
                exit;
            }
            break;

        // --- CASO POR DEFECTO ---
        default:
            $response['message'] = 'Acción no válida.';
            break;
    }

} catch (PDOException $e) {
    // 5. MANEJO CENTRALIZADO DE ERRORES DE BASE DE DATOS
    // ------------------------------------------------------------------
    
    // Si la acción es exportación, se devuelve texto simple para no corromper la descarga
    if ($action === 'export_products') {
        header('Content-Type: text/plain');
        die('Error de base de datos al exportar: ' . $e->getMessage()); 
    }
    
    // Para todas las demás acciones (que esperan JSON)
    http_response_code(500);
    $response['success'] = false;
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
}

// 6. RESPUESTA FINAL
// ------------------------------------------------------------------

// Solo devolvemos JSON si no se ha iniciado una exportación de archivo
if ($action !== 'export_products') {
    echo json_encode($response);
}

// Asegurar que no se imprima nada más después del archivo o el JSON
exit;
?>