<?php
require '../config.php';

header('Content-Type: application/json');

// --- Parámetros de entrada ---
$offset = isset($_GET['offset']) && is_numeric($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 24;
$searchTerm = $_GET['search'] ?? '';
// Aseguramos que la categoría sea un entero si no es 'all'
$selected_category_id = isset($_GET['category_id']) && $_GET['category_id'] !== 'all' ? (int)$_GET['category_id'] : 'all'; 
$kpi_filter = $_GET['kpi_filter'] ?? 'all';

// 🚀 NUEVOS PARÁMETROS
$supplier_filter = $_GET['supplier_id'] ?? 'all';
$sort_by = $_GET['sort_by'] ?? 'updated_at_desc'; // Criterio de ordenamiento

// 🚨 CORRECCIÓN CLAVE: Determinar si debemos anular el filtro de activos.
// Usaremos 'show-archived' como el valor específico de $kpi_filter para activarlo.
$show_archived = ($kpi_filter === 'show-archived'); 

try {
    $sql = "SELECT * FROM products";
    $params = [];
    $where_clauses = [];

    // 🛑 LÓGICA DE FILTRADO ARCHIVADO
    if ($show_archived) {
        // Si el KPI 'Archivados' está activo, solo mostramos los archivados.
        $where_clauses[] = "archived = 1";
    } else {
        // Por defecto o cualquier otro filtro, mostramos solo los productos activos.
        $where_clauses[] = "archived = 0";
    }
    // -----------------------------------------------------
    
    // Si el filtro es el de 'show-archived', no aplicamos NINGÚN otro filtro KPI
    if (!$show_archived) {
        // --- Filtro por KPI ---
        switch ($kpi_filter) {
            case 'low-stock':
                $where_clauses[] = "stock < 5 AND stock > 0";
                break;
            case 'out-of-stock':
                $where_clauses[] = "stock = 0";
                break;
            case 'no-image':
                $where_clauses[] = "(image_url IS NULL OR image_url = '' OR image_url = 'placeholder.png')";
                break;
            case 'no-supplier':
                $where_clauses[] = "(supplier_id IS NULL OR supplier_id = 0)";
                break;
            case 'negative-stock':
                $where_clauses[] = "stock < 0";
                break;
            default:
                // "all" o desconocido → sin filtro adicional
                break;
        }
    }

    // --- Filtro por búsqueda de texto (Se aplica siempre) ---
    if (!empty($searchTerm)) {
        $where_clauses[] = "(name LIKE ? OR barcode LIKE ?)";
        $params[] = '%' . $searchTerm . '%';
        $params[] = '%' . $searchTerm . '%';
    }

    // --- Filtro por categoría (Se aplica siempre) ---
    if ($selected_category_id !== 'all') {
        $where_clauses[] = "category_id = ?";
        $params[] = $selected_category_id;
    }

    // --- Filtro por proveedor (Se aplica siempre) ---
    if ($supplier_filter !== 'all') {
        if ($supplier_filter === '0' || $supplier_filter === 0) {
            // Sin proveedor asignado (ID es NULL o 0)
            $where_clauses[] = "(supplier_id IS NULL OR supplier_id = 0)";
        } else {
            // Proveedor específico
            $where_clauses[] = "supplier_id = ?";
            $params[] = (int)$supplier_filter;
        }
    }


    // --- Construcción dinámica del WHERE ---
    // Siempre habrá al menos un filtro de 'archived'
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
    

    // ------------------------------------------------------------------
    // 🚀 LÓGICA DE ORDENAMIENTO (ORDER BY)
    // ------------------------------------------------------------------
    switch ($sort_by) {
        case 'name_asc':
            $order_clause = "ORDER BY name ASC";
            break;
        case 'name_desc':
            $order_clause = "ORDER BY name DESC";
            break;
        case 'price_asc':
            $order_clause = "ORDER BY price ASC";
            break;
        case 'price_desc':
            $order_clause = "ORDER BY price DESC";
            break;
        case 'stock_asc':
            $order_clause = "ORDER BY stock ASC";
            break;
        case 'stock_desc':
            $order_clause = "ORDER BY stock DESC";
            break;
        case 'created_at_asc':
            $order_clause = "ORDER BY created_at ASC";
            break;
        case 'created_at_desc':
            $order_clause = "ORDER BY created_at DESC";
            break;
        case 'updated_at_asc':
            $order_clause = "ORDER BY updated_at ASC";
            break;
        case 'updated_at_desc':
        default:
            $order_clause = "ORDER BY updated_at DESC";
            break;
    }

    // ------------------------------------------------------------------
    // --- Orden y paginación ---
    $sql .= " " . $order_clause . " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    // --- Ejecución segura ---
    $stmt = $pdo->prepare($sql);
    
    // DEBUG: Puedes descomentar esto para ver la consulta que se está ejecutando
    // error_log("SQL: " . $sql);
    // error_log("Params: " . print_r($params, true));

    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($products);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>