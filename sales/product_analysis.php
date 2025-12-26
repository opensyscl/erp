<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config.php';
session_start();

// ----------------------------------------------------------------------
// --- 1. VERIFICACIÓN DE LOGIN Y CONEXIÓN CRÍTICA ---
// ----------------------------------------------------------------------

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if (!isset($pdo)) {
    die('Error fatal: Conexión PDO ($pdo) no disponible para el chequeo de módulos.');
}

$current_user_id = $_SESSION['user_id'];

// ----------------------------------------------------------------------
// --- 2. VALIDACIÓN DE ROL Y MÓDULO (Manteniendo la estructura de seguridad) ---
// ----------------------------------------------------------------------

$user_can_access = false;
$module_path = '/erp/product_analysis/';

try {
    $stmt_role = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_role->execute([$current_user_id]);
    $user_role = $stmt_role->fetchColumn();

    // LÓGICA DE NEGOCIO: Permitir acceso a roles gerenciales o POS1.
    if ($user_role === 'POS1' || $user_role === 'Admin') {
        $user_can_access = true;
    }
} catch (PDOException $e) {
    error_log("Error de BD al verificar rol del usuario: " . $e->getMessage());
    header('Location: ../not_authorized.php');
    exit();
}

// ----------------------------------------------------------------------
// --- 3. REDIRECCIÓN DE SEGURIDAD FINAL (Si el acceso es denegado) ---
// ----------------------------------------------------------------------
if (!$user_can_access) {
    header('Location: ../not_authorized.php');
    exit();
}

// ----------------------------------------------------------------------
// --- 4. PARÁMETROS DE FILTRO (FECHA, CATEGORÍA, PROVEEDOR) ---
// ----------------------------------------------------------------------

// --- A. FILTROS ADICIONALES (Se leen primero para ser incluidos en la URL de mes/rango)
$selectedCategory = $_GET['category'] ?? 'all';
$selectedSupplier = $_GET['supplier'] ?? 'all';

// --- B. FILTRO DE FECHA
$customStartDate = $_GET['start_date'] ?? null;
$customEndDate = $_GET['end_date'] ?? null;
$isCustomRange = false;
$selectedMonthYear = $_GET['month'] ?? date('Y-m');

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $customStartDate ?? '') && preg_match('/^\d{4}-\d{2}-\d{2}$/', $customEndDate ?? '')) {
    $startDate = $customStartDate;
    $endDate = $customEndDate;
    $selectedMonthYear = '';
    $isCustomRange = true;
} else {
    if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonthYear)) {
        $selectedMonthYear = date('Y-m');
    }
    $startDate = date('Y-m-01', strtotime($selectedMonthYear . '-01'));
    $endDate = date('Y-m-t', strtotime($selectedMonthYear . '-01'));
}


// ----------------------------------------------------------------------
// --- 5. OBTENER OPCIONES DE FILTRO Y DATOS BASE ---
// ----------------------------------------------------------------------

// --- A. Obtener Categorías
$stmt_categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories_options = $stmt_categories->fetchAll(PDO::FETCH_KEY_PAIR);

// --- B. Obtener Proveedores
$stmt_suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC");
$suppliers_options = $stmt_suppliers->fetchAll(PDO::FETCH_KEY_PAIR);

// --- C. Generación de opciones de meses (Ultimos 12 meses)
$monthOptions = [];
if (class_exists('IntlDateFormatter')) {
    $formatter = new IntlDateFormatter('es-ES', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'America/Santiago', IntlDateFormatter::GREGORIAN, 'MMMM yyyy');
    for ($i = 0; $i < 12; $i++) {
        $date = new DateTime("-$i month");
        $value = $date->format('Y-m');
        $label = $formatter->format($date->getTimestamp());
        $monthOptions[$value] = ucfirst($label);
    }
} else {
    // Fallback para entornos sin extensión intl
    $meses = ['January' => 'Enero', 'February' => 'Febrero', 'May' => 'Mayo', 'March' => 'Marzo', 'April' => 'Abril', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'];
    for ($i = 0; $i < 12; $i++) {
        $date = new DateTime("-$i month");
        $value = $date->format('Y-m');
        $monthNameEn = $date->format('F');
        $year = $date->format('Y');
        $label = ($meses[$monthNameEn] ?? $monthNameEn) . ' ' . $year;
        $monthOptions[$value] = $label;
    }
}


// ----------------------------------------------------------------------
// --- 6. CONSULTA PRINCIPAL DE DATOS AGREGADOS (KPIs y Tabla) ---
// ----------------------------------------------------------------------

// Lógica de corrección de unidades para productos a granel:
// CASE WHEN p.name LIKE '%Granel%' THEN SUM(si.quantity) / 1000 ELSE SUM(si.quantity) END AS total_units_sold
$sql_base = "
    SELECT
        p.id AS product_id,
        p.name AS product_name,
        c.name AS category_name,
        s.name AS supplier_name,
        CASE 
            WHEN p.name LIKE '%Granel%' THEN SUM(si.quantity) / 1000 
            ELSE SUM(si.quantity) 
        END AS total_units_sold, -- CORRECCIÓN A GRANEL APLICADA AQUÍ
        SUM(si.quantity * si.price) AS total_revenue,
        AVG(si.price) AS avg_price_per_unit_sale
    FROM sale_items si
    JOIN sales sl ON si.sale_id = sl.id
    JOIN products p ON si.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE DATE(sl.created_at) BETWEEN :start_date AND :end_date
    AND (p.archived IS NULL OR p.archived = 0) -- EXCLUIR PRODUCTOS ARCHIVADOS
";

$where_clauses = [];
$params = [
    ':start_date' => $startDate,
    ':end_date' => $endDate
];

if ($selectedCategory !== 'all') {
    $where_clauses[] = "p.category_id = :category_id";
    $params[':category_id'] = $selectedCategory;
}

if ($selectedSupplier !== 'all') {
    $where_clauses[] = "p.supplier_id = :supplier_id";
    $params[':supplier_id'] = $selectedSupplier;
}

if (!empty($where_clauses)) {
    $sql_base .= " AND " . implode(' AND ', $where_clauses); 
}

$sql_base .= " GROUP BY p.id, p.name, c.name, s.name ORDER BY total_revenue DESC";

try {
    $stmt_product_data = $pdo->prepare($sql_base);
    $stmt_product_data->execute($params);
    $product_analysis_data = $stmt_product_data->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error de BD al generar análisis de producto: " . $e->getMessage());
    $product_analysis_data = [];
}


// ----------------------------------------------------------------------
// --- 7. CONSULTAS DETALLADAS PARA EL SEGUNDO GRÁFICO (Cantidad Vendida por Producto) ---
// ----------------------------------------------------------------------

// Lógica de corrección de unidades para productos a granel:
// CASE WHEN p.name LIKE '%Granel%' THEN SUM(si.quantity) / 1000 ELSE SUM(si.quantity) END AS total_units_sold
$sql_detailed_analysis = "
    SELECT
        p.id AS product_id,
        p.name AS product_name,
        p.category_id,
        p.supplier_id,
        c.name AS category_name,
        s.name AS supplier_name,
        CASE 
            WHEN p.name LIKE '%Granel%' THEN SUM(si.quantity) / 1000 
            ELSE SUM(si.quantity) 
        END AS total_units_sold -- CORRECCIÓN A GRANEL APLICADA AQUÍ
    FROM sale_items si
    JOIN sales sl ON si.sale_id = sl.id
    JOIN products p ON si.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE DATE(sl.created_at) BETWEEN :start_date AND :end_date
    AND (p.archived IS NULL OR p.archived = 0) -- EXCLUIR PRODUCTOS ARCHIVADOS
";

$detailed_params = [
    ':start_date' => $startDate,
    ':end_date' => $endDate
];

// Replicar filtros generales si existen
if ($selectedCategory !== 'all') {
    $sql_detailed_analysis .= " AND p.category_id = :category_id_detail";
    $detailed_params[':category_id_detail'] = $selectedCategory;
}

if ($selectedSupplier !== 'all') {
    $sql_detailed_analysis .= " AND p.supplier_id = :supplier_id_detail";
    $detailed_params[':supplier_id_detail'] = $selectedSupplier;
}

// Importante: El gráfico de detalle se ordena por unidades vendidas DESC para el ranking.
$sql_detailed_analysis .= " GROUP BY p.id, p.name, p.category_id, p.supplier_id, c.name, s.name ORDER BY total_units_sold DESC";

try {
    $stmt_detailed_data = $pdo->prepare($sql_detailed_analysis);
    $stmt_detailed_data->execute($detailed_params);
    $detailed_product_data = $stmt_detailed_data->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error de BD al generar análisis detallado de producto: " . $e->getMessage());
    $detailed_product_data = [];
}


// Estructuración de los datos detallados para el JS (Agrupados por ID)
$detailed_grouped_by_category = [];
$detailed_grouped_by_supplier = [];

foreach ($detailed_product_data as $row) {
    // Agrupación por Categoría
    $cat_id = $row['category_id'] ?: 0; // Usar 0 para 'Sin Categoría'
    if (!isset($detailed_grouped_by_category[$cat_id])) {
        $detailed_grouped_by_category[$cat_id] = [
            'id' => $cat_id,
            'name' => $row['category_name'] ?: 'Sin Categoría',
            'products' => []
        ];
    }
    // Aseguramos que los productos se guarden en el array asociativo PHP, el orden se corregirá en JS.
    $detailed_grouped_by_category[$cat_id]['products'][$row['product_name']] = $row['total_units_sold'];

    // Agrupación por Proveedor
    $sup_id = $row['supplier_id'] ?: 0; // Usar 0 para 'Sin Proveedor'
    if (!isset($detailed_grouped_by_supplier[$sup_id])) {
        $detailed_grouped_by_supplier[$sup_id] = [
            'id' => $sup_id,
            'name' => $row['supplier_name'] ?: 'Sin Proveedor',
            'products' => []
        ];
    }
    // Aseguramos que los productos se guarden en el array asociativo PHP, el orden se corregirá en JS.
    $detailed_grouped_by_supplier[$sup_id]['products'][$row['product_name']] = $row['total_units_sold'];
}


// ----------------------------------------------------------------------
// --- 8. CÁLCULO DE MÉTRICAS (KPIs) ---
// ----------------------------------------------------------------------

$total_units_sold = array_sum(array_column($product_analysis_data, 'total_units_sold'));
$total_revenue = array_sum(array_column($product_analysis_data, 'total_revenue'));

// Cálculo de Ticket Promedio por Unidad Vendida
$avg_ticket_per_unit = ($total_units_sold > 0) ? ($total_revenue / $total_units_sold) : 0;

// Top 3
$top_products = array_slice($product_analysis_data, 0, 3);

// Agregación para el primer gráfico (Ingresos)
$chart_data_by_category = [];
$chart_data_by_supplier = [];

foreach ($product_analysis_data as $row) {
    $category = $row['category_name'] ?: 'Sin Categoría';
    $supplier = $row['supplier_name'] ?: 'Sin Proveedor';

    $chart_data_by_category[$category] = ($chart_data_by_category[$category] ?? 0) + $row['total_revenue'];
    $chart_data_by_supplier[$supplier] = ($chart_data_by_supplier[$supplier] ?? 0) + $row['total_revenue'];
}


// Variables para el encabezado
$current_page = 'product_analysis.php';
$stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
$stmt->execute();
$system_version = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análisis de Productos - Listto! ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" type="image/png" href="img/fav.png">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/product_analysis.css"> <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body>

    <header class="main-header">
        <div class="header-left">
            <a href="../launcher.php" class="launcher-icon" title="Ir al Lanzador de Aplicaciones">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="5" cy="5" r="3"/>
                    <circle cx="12" cy="5" r="3"/>
                    <circle cx="19" cy="5" r="3"/>
                    <circle cx="5" cy="12" r="3"/>
                    <circle cx="12" cy="12" r="3"/>
                    <circle cx="19" cy="12" r="3"/>
                    <circle cx="5" cy="19" r="3"/>
                    <circle cx="12" cy="19" r="3"/>
                    <circle cx="19" cy="19" r="3"/>
                </svg>
            </a>
            <span>Hola, <strong><?php echo htmlspecialchars($_SESSION['user_username'] ?? 'Usuario'); ?></strong></span>
        </div>

<nav class="header-nav">
    <a href="sales.php" 
       class="<?= ($current_page === 'sales.php' ? 'active' : ''); ?>">
       Informe de Ventas
    </a>

    <a href="product_analysis.php" 
       class="<?= ($current_page === 'product_analysis.php' ? 'active' : ''); ?>">
       Análisis de Productos
    </a>

</nav>
        <div class="header-right">
            <span class="app-version"><?php echo htmlspecialchars($system_version); ?></span>
            <a href="../logout.php" class="btn-logout">Cerrar Sesi&oacute;n</a>
        </div>
    </header>

<main class="container">

    <div class="page-header-controls">
        <h1 class="page-title">Análisis de Productos y Categorías</h1>

        <div class="filter-controls-group" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">

            <div class="month-selector-container">
                <label for="month-selector">Mes:</label>
                <select id="month-selector" onchange="applyFilters(this.value, 'month')">
                    <option value="">Seleccione Mes</option>
                    <?php
                    foreach ($monthOptions as $value => $label) {
                        $selected = (!$isCustomRange && $value === $selectedMonthYear) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($value) . "\" {$selected}>" . htmlspecialchars($label) . "</option>";
                    }
                    ?>
                </select>
            </div>
            
            <form id="range-filter-form" class="range-selector-styled" action="product_analysis.php" method="GET">

                <div class="date-input-group">
                    <label for="start_date">Desde:</label>
                    <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($isCustomRange ? $startDate : '') ?>">
                </div>

                <div class="date-input-group">
                    <label for="end_date">Hasta:</label>
                    <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($isCustomRange ? $endDate : '') ?>">
                </div>

                <button type="submit" class="btn-filter">
                    Aplicar Rango
                </button>

                <?php if ($isCustomRange): ?>
                    <button type="button" class="btn-reset" onclick="window.location.href = 'product_analysis.php'">
                        Reset
                    </button>
                <?php endif; ?>
                <input type="hidden" name="category" id="hidden_category_id" value="<?= htmlspecialchars($selectedCategory) ?>">
                <input type="hidden" name="supplier" id="hidden_supplier_id" value="<?= htmlspecialchars($selectedSupplier) ?>">
            </form>
        </div>

    </div>
    <p class="filter-toast">
        <?php
        $filter_info = '';
        if ($isCustomRange) {
            $filter_info .= "Rango: <strong>" . date('d/m/Y', strtotime($startDate)) . "</strong> a <strong>" . date('d/m/Y', strtotime($endDate)) . "</strong>";
        } elseif ($selectedMonthYear) {
            $filter_info .= "Mes: <strong>" . htmlspecialchars($monthOptions[$selectedMonthYear] ?? 'Mes Actual') . "</strong>";
        }
        if ($selectedCategory !== 'all' && isset($categories_options[$selectedCategory])) {
            $filter_info .= " | Cat.: <strong>" . htmlspecialchars($categories_options[$selectedCategory]) . "</strong>";
        }
        if ($selectedSupplier !== 'all' && isset($suppliers_options[$selectedSupplier])) {
            $filter_info .= " | Prov.: <strong>" . htmlspecialchars($suppliers_options[$selectedSupplier]) . "</strong>";
        }
        echo "Filtrando: " . ($filter_info ?: "Sin filtros de fecha");
        ?>
    </p>

    <div class="kpi-grid">
        <div class="kpi-card">
            <h3>Unidades Vendidas (Total)</h3>
            <p class="value"><?= number_format($total_units_sold, 2, ',', '.') ?></p>
        </div>
        <div class="kpi-card">
            <h3>Ingreso Total</h3>
            <p class="value"><?= number_format($total_revenue, 0, ',', '.') ?></p>
        </div>
        <div class="kpi-card">
            <h3>Ticket Promedio por Unidad</h3>
            <p class="value"><?= number_format($avg_ticket_per_unit, 0, ',', '.') ?></p>
        </div>
        <div class="kpi-card">
            <h3>Producto M&aacute;s Vendido (Rev.)</h3>
            <?php if (!empty($top_products)): ?>
                <p class="value" style="font-size: 1.1rem; color: #3b82f6;"><?= htmlspecialchars($top_products[0]['product_name']) ?></p>
            <?php else: ?>
                <p class="value" style="font-size: 1rem; color: #9ca3af;">N/A</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="content-card">
        <div class="chart-header">
            <h2>Ingresos por Agrupación (Total)</h2>
            <div class="filter-selects-inline">
                <div class="selector-container">
                    <label for="category-selector">Categor&iacute;a:</label>
                    <select id="category-selector" onchange="applyFilters(this.value, 'category')">
                        <option value="all">Todas</option>
                        <?php
                        foreach ($categories_options as $id => $name) {
                            $selected = ($id == $selectedCategory) ? 'selected' : '';
                            echo "<option value=\"{$id}\" {$selected}>" . htmlspecialchars($name) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="selector-container">
                    <label for="supplier-selector">Proveedor:</label>
                    <select id="supplier-selector" onchange="applyFilters(this.value, 'supplier')">
                        <option value="all">Todos</option>
                        <?php
                        foreach ($suppliers_options as $id => $name) {
                            $selected = ($id == $selectedSupplier) ? 'selected' : '';
                            echo "<option value=\"{$id}\" {$selected}>" . htmlspecialchars($name) . "</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            </div>

        <div class="tabs-container primary-tabs" style="margin-bottom: 20px;">
            <button class="tab-button active" data-group="category" onclick="setChartGroup('analysisChart', 'category', this)">
                Por Categor&iacute;a
            </button>
            <button class="tab-button" data-group="supplier" onclick="setChartGroup('analysisChart', 'supplier', this)">
                Por Proveedor
            </button>
        </div>
        
        <div style="height: 400px;">
            <canvas id="productAnalysisChart"></canvas>
        </div>
    </div>
    
    <div class="content-card" style="margin-top: 20px;">
        <h2>Detalle de Unidades Vendidas por Producto y Filtro</h2>
        
        <div class="tabs-container detail-primary-tabs" style="margin-bottom: 10px;">
            <button class="tab-button active" data-group="supplier" onclick="showDetailTabs('supplier', this)">
                Proveedores
            </button>
            <button class="tab-button" data-group="category" onclick="showDetailTabs('category', this)">
                Categor&iacute;as
            </button>
        </div>

        <div id="secondary-tabs-container" class="tabs-container secondary-tabs" style="margin-bottom: 20px;">
            <p style="padding-top: 10px; color: #6b7280;">Cargando pestañas de detalle...</p>
        </div>
        
        <div id="detailed-pagination-controls" style="text-align: center; margin-top: 15px; margin-bottom: 5px;">
            </div>

        <div style="height: 400px;">
            <canvas id="detailedProductsChart"></canvas>
        </div>
    </div>


    <div class="content-card">
        <div class="tabs-container detail-table-tabs" style="border-bottom: 1px solid #e5e7eb; margin-bottom: 10px;">
            <button class="tab-button active" data-tab-content="product-detail-table" onclick="showDetailContent('product-detail-table', this)">
                Detalle de productos vendidos
            </button>
            <button class="tab-button" data-tab-content="sales-avg-content" onclick="showDetailContent('sales-avg-content', this)">
                Promedio de ventas de productos
            </button>
            <button class="tab-button" data-tab-content="stock-analysis-content" onclick="showDetailContent('stock-analysis-content', this)">
                An&aacute;lisis de stock
            </button>
        </div>
        <div id="product-detail-table" class="tab-content active">
            <div class="table-header-controls">
                <h2>Detalle de Productos Vendidos</h2>
                <div class="table-controls">
                    <label for="limit">Mostrar:</label>
                    <select id="limit" onchange="updateTable()">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="all">Todos</option>
                    </select>
                </div>
            </div>
            <div class="table-container">
                <table class="sales-table">
                    <thead>
                        <tr>
                            <th data-sort-column="product_id">ID Prod.</th>
                            <th data-sort-column="product_name">Producto</th>
                            <th data-sort-column="category_name">Categor&iacute;a</th>
                            <th data-sort-column="supplier_name">Proveedor</th>
                            <th data-sort-column="total_units_sold" class="sort-desc">Unidades Vendidas</th>
                            <th data-sort-column="total_revenue">Ingreso Total</th>
                            <th data-sort-column="avg_price_per_unit_sale">Precio Promedio Unit.</th>
                            <th>Alarma Stock (Ej.)</th>
                        </tr>
                    </thead>
                    <tbody id="product-analysis-table-body">
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2rem;">Cargando datos...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div id="sales-avg-content" class="tab-content">
            <h2>Promedio de Ventas por Producto</h2>
            <p style="color: #6b7280;">Aquí se mostraría una tabla o gráfico con el promedio mensual o semanal de ventas de productos, según la lógica de negocio.</p>
             <div class="table-container">
                <table class="sales-table" id="sales-avg-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Unidades Vendidas Promedio (Mes)</th>
                            <th>Días con Venta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Ejemplo Pan Granel</td><td>40.50</td><td>15</td></tr>
                        <tr><td>Ejemplo Coca Cola 1.5L</td><td>12.00</td><td>30</td></tr>
                        <tr><td colspan="3" style="text-align: center; padding: 1rem;">La lógica de esta tabla debe ser implementada en PHP/JS.</td></tr>
                    </tbody>
                </table>
             </div>
        </div>

        <div id="stock-analysis-content" class="tab-content">
            <h2>Análisis de Stock y Rotación</h2>
            <p style="color: #6b7280;">Este espacio se destinaría al análisis de rotación (Días de Inventario) y la predicción de quiebre de stock, basándose en la data de inventario y la tasa de venta (total_units_sold).</p>
            <div style="height: 300px; background-color: #f3f4f6; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #9ca3af;">
                [Área para Gráfico/Tabla de Días de Inventario o Quiebre de Stock]
            </div>
        </div>

    </div>
</main>


<script>
    let productAnalysisChart; // Gráfico 1
    let detailedProductsChart; // Gráfico 2

    // --- CONSTANTES Y DATOS GLOBALES ---
    const PRODUCTS_PER_PAGE = 15; // Define cuántos productos se muestran por página en el gráfico de detalle
    
    // PHP ha excluido ya los productos archivados y corregido las unidades a granel
    const analysisData = <?= json_encode($product_analysis_data); ?>;
    const chartDataByCategory = <?= json_encode($chart_data_by_category); ?>;
    const chartDataBySupplier = <?= json_encode($chart_data_by_supplier); ?>;
    
    const detailedGroupedByCategory = <?= json_encode($detailed_grouped_by_category); ?>;
    const detailedGroupedBySupplier = <?= json_encode($detailed_grouped_by_supplier); ?>;

    // --- ESTADOS ---
    let currentSortColumn = 'total_revenue';
    let currentSortDirection = 'desc';
    let currentChartGroup = 'category'; // Gráfico 1: Agrupación
    
    let currentDetailGroupType = 'supplier'; // Gráfico 2: Nivel superior (supplier o category)
    let currentDetailGroupId = 0; // Gráfico 2: ID de la pestaña secundaria activa
    let currentDetailPage = 1; // Gráfico 2: Página actual de la paginación

    // --- FUNCIONES AUXILIARES ---

    function htmlspecialchars(str) {
        if (typeof str !== 'string') return str;
        return str.replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
    }
    
    // Formato de número que acepta decimales para la tabla y KPIs
    function number_format(number, decimals = 0) {
        return parseFloat(number).toLocaleString('es-CL', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    }

    const formatCurrency = (amount, decimals = 0) => {
        return parseFloat(amount).toLocaleString('es-CL', {
            style: 'currency',
            currency: 'CLP',
            minimumFractionDigits: decimals
        });
    };

    const getStockAlarm = (unitsSold) => {
        // Nota: Esta es una alarma de stock *ejemplo* basada en unidades vendidas.
        // La lógica real de stock debería venir de la tabla 'products' o 'inventory'.
        if (unitsSold > 100) { return '<span class="stock-danger">¡ALTA DEMANDA!</span>'; }
        if (unitsSold >= 50) { return '<span class="stock-warning">Revisar Stock</span>'; }
        return '<span class="stock-safe">Demanda Baja</span>';
    };


    // ----------------------------------------------------------------------
    // --- LÓGICA GRÁFICO 1: Ingresos por Agrupación (Simple) ---
    // ----------------------------------------------------------------------

    const setChartGroup = (chartName, group, buttonElement) => {
        const parent = buttonElement.closest('.tabs-container');
        parent.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        buttonElement.classList.add('active');

        if (chartName === 'analysisChart') {
            currentChartGroup = group;
            updateChart();
        }
    };
    
    const updateChart = () => {
        const groupSelector = currentChartGroup; 
        const selectedData = (groupSelector === 'category') ? chartDataByCategory : chartDataBySupplier;
        
        const chartLabels = Object.keys(selectedData);
        const chartValues = Object.values(selectedData);
        
        const chartCanvas = document.getElementById('productAnalysisChart');
        const chartContainer = chartCanvas.parentNode;
        const hasData = chartValues.some(v => v > 0); 
        
        if (!hasData || chartLabels.length === 0) {
            if (productAnalysisChart) productAnalysisChart.destroy();
            chartContainer.innerHTML = '<p style="text-align: center; margin-top: 2rem; color: #4b5563;">No hay datos para esta agrupación y filtros.</p>';
            return;
        }

        if (chartContainer.querySelector('canvas') === null) {
            chartContainer.innerHTML = '<canvas id="productAnalysisChart"></canvas>';
        }

        const datasets = [{
            label: 'Ingreso Total (CLP)',
            data: chartValues,
            backgroundColor: 'rgba(59, 130, 246, 0.7)',
            borderColor: 'rgba(59, 130, 246, 1)',
            borderWidth: 1
        }];

        if (productAnalysisChart) {
            productAnalysisChart.data.labels = chartLabels;
            productAnalysisChart.data.datasets = datasets;
            productAnalysisChart.options.scales.x.title.text = (groupSelector === 'category') ? 'Categoría' : 'Proveedor';
            productAnalysisChart.update();
        } else {
            const ctx = chartCanvas.getContext('2d');
            productAnalysisChart = new Chart(ctx, {
                type: 'bar',
                data: { labels: chartLabels, datasets: datasets },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'Ingreso Total (CLP)' }, ticks: { callback: function(value) { return formatCurrency(value); } } },
                        x: { display: true, title: { display: true, text: (groupSelector === 'category') ? 'Categoría' : 'Proveedor' } }
                    },
                    plugins: { tooltip: { callbacks: { label: function(context) { return context.dataset.label + ': ' + formatCurrency(context.parsed.y); } } } }
                }
            });
        }
    };


    // ----------------------------------------------------------------------
    // --- LÓGICA GRÁFICO 2: Detalle de Productos Vendidos (Doble Pestaña y Paginación) ---
    // ----------------------------------------------------------------------

    /**
     * Nivel 1: Muestra las pestañas secundarias (Categorías o Proveedores).
     */
    const showDetailTabs = (groupType, buttonElement) => {
        // 1. Actualiza el estado del nivel superior
        const primaryTabs = document.querySelector('.detail-primary-tabs');
        primaryTabs.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        buttonElement.classList.add('active');
        
        currentDetailGroupType = groupType;
        const targetData = (groupType === 'supplier') ? detailedGroupedBySupplier : detailedGroupedByCategory;
        const tabsContainer = document.getElementById('secondary-tabs-container');
        tabsContainer.innerHTML = '';
        currentDetailGroupId = 0; 
        currentDetailPage = 1; // Resetear paginación al cambiar de grupo principal
        let firstId = null;

        if (Object.keys(targetData).length === 0) {
            tabsContainer.innerHTML = `<p style="padding-top: 10px; color: #6b7280;">No hay ${groupType === 'supplier' ? 'proveedores' : 'categorías'} con ventas en el rango seleccionado.</p>`;
            if(detailedProductsChart) detailedProductsChart.destroy();
            updateDetailedPaginationControls(0, 0); // Ocultar paginación
            return;
        }

        // 2. Genera las pestañas secundarias
        Object.entries(targetData).forEach(([id, data], index) => {
            if (index === 0) firstId = id; 
            
            const button = document.createElement('button');
            button.className = 'tab-button secondary-tabs-btn';
            button.setAttribute('data-id', id);
            button.textContent = htmlspecialchars(data.name);
            button.onclick = () => { updateDetailedChart(id, button); };
            tabsContainer.appendChild(button);
        });

        // 3. Simula el clic en la primera pestaña para cargar los datos
        if (firstId !== null) {
            const firstButton = tabsContainer.querySelector(`.secondary-tabs-btn[data-id="${firstId}"]`);
            if (firstButton) {
                updateDetailedChart(firstId, firstButton);
            }
        }
    };

    /**
     * Nivel 2: Carga los datos del producto asociado a la pestaña secundaria seleccionada, aplicando paginación.
     */
    const updateDetailedChart = (id, buttonElement) => {
        // 1. Actualiza el estado de las pestañas secundarias
        if (id !== currentDetailGroupId) {
            currentDetailPage = 1; // Resetear página si la pestaña cambió
            const secondaryTabs = document.getElementById('secondary-tabs-container');
            secondaryTabs.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            buttonElement.classList.add('active');
        }
        currentDetailGroupId = id;

        // 2. Obtener la lista completa de productos
        const targetDataGroup = (currentDetailGroupType === 'supplier') ? detailedGroupedBySupplier : detailedGroupedByCategory;
        const productData = targetDataGroup[id].products;

        // Crear un array de objetos {name, unitsSold} para ordenar
        let orderedProducts = Object.entries(productData).map(([name, unitsSold]) => ({
            name: name,
            unitsSold: unitsSold
        }));

        // Asegurar que el ordenamiento sea DESCENDENTE (de mayor a menor venta)
        orderedProducts.sort((a, b) => b.unitsSold - a.unitsSold);

        const allProductNames = orderedProducts.map(p => p.name);
        const allUnitsSold = orderedProducts.map(p => p.unitsSold);
        const totalProducts = allProductNames.length;
        
        // 3. Aplicar lógica de paginación
        const totalPages = Math.ceil(totalProducts / PRODUCTS_PER_PAGE);
        const startIndex = (currentDetailPage - 1) * PRODUCTS_PER_PAGE;
        const endIndex = startIndex + PRODUCTS_PER_PAGE;

        // Aplicar slicing: Obtener el segmento correcto de la lista ordenada (Mayor a Menor).
        let chartLabels = allProductNames.slice(startIndex, endIndex);
        let chartValues = allUnitsSold.slice(startIndex, endIndex);

        // --- SE REVIERTE LA CORRECCIÓN DE INVERSIÓN DE ARRAY DE LA SOLUCIÓN ANTERIOR ---
        // Y se confía en Chart.js `reverse: true` para hacer el ranking visual correcto.
        
        const chartCanvas = document.getElementById('detailedProductsChart');
        const chartContainer = chartCanvas.parentNode;
        
        // Si el chart fue destruido, recrea el canvas
        if (chartContainer.querySelector('canvas') === null) {
            chartContainer.innerHTML = '<canvas id="detailedProductsChart"></canvas>';
        }

        const datasets = [{
            label: 'Unidades Vendidas',
            data: chartValues, // Datos en orden: Mayor a Menor
            backgroundColor: 'rgba(239, 68, 68, 0.7)', 
            borderColor: 'rgba(239, 68, 68, 1)',
            borderWidth: 1
        }];

        if (detailedProductsChart) {
            detailedProductsChart.data.labels = chartLabels;
            detailedProductsChart.data.datasets = datasets;
            detailedProductsChart.options.scales.x.title.text = 'Unidades Vendidas';
            detailedProductsChart.options.scales.y.title.text = 'Producto';
            detailedProductsChart.update();
        } else {
            const ctx = chartCanvas.getContext('2d');
            detailedProductsChart = new Chart(ctx, {
                type: 'bar',
                data: { labels: chartLabels, datasets: datasets },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    indexAxis: 'y', // Barras horizontales
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            title: { display: true, text: 'Producto' },
                            // --- CORRECCIÓN CLAVE AQUÍ ---
                            // `reverse: true` asegura que el primer elemento del array (el más vendido) 
                            // se dibuje en la parte superior del gráfico horizontal.
                            reverse: true 
                        },
                        x: { 
                            title: { display: true, text: 'Unidades Vendidas' }, 
                            ticks: { precision: 2 } 
                        } 
                    },
                    plugins: { tooltip: { callbacks: { label: function(context) { return context.dataset.label + ': ' + number_format(context.parsed.x, 2); } } } }
                }
            });
        }
        
        // 4. Actualizar controles de paginación
        updateDetailedPaginationControls(totalPages, totalProducts);
    };


    /**
     * Muestra y actualiza los botones de paginación para el gráfico de detalle.
     */
    const updateDetailedPaginationControls = (totalPages, totalProducts) => {
        const container = document.getElementById('detailed-pagination-controls');
        
        if (totalPages <= 1) {
            container.innerHTML = `<span style="color: #6b7280; font-size: 0.9rem;">Mostrando ${totalProducts} productos.</span>`;
            return;
        }

        container.innerHTML = `
            <button id="prev-page-btn" ${currentDetailPage <= 1 ? 'disabled' : ''} onclick="changeDetailedPage(-1)">
                &lt; Anterior
            </button>
            <span style="font-weight: 600; color: #3b82f6; margin: 0 15px;">Página ${currentDetailPage} de ${totalPages}</span>
            <button id="next-page-btn" ${currentDetailPage >= totalPages ? 'disabled' : ''} onclick="changeDetailedPage(1)">
                Siguiente &gt;
            </button>
        `;
    };

    /**
     * Cambia la página del gráfico de detalle y lo redibuja.
     */
    const changeDetailedPage = (direction) => {
        const targetDataGroup = (currentDetailGroupType === 'supplier') ? detailedGroupedBySupplier : detailedGroupedByCategory;
        const productData = targetDataGroup[currentDetailGroupId].products;
        const totalProducts = Object.keys(productData).length;
        const totalPages = Math.ceil(totalProducts / PRODUCTS_PER_PAGE);

        let newPage = currentDetailPage + direction;

        if (newPage >= 1 && newPage <= totalPages) {
            currentDetailPage = newPage;
            // Rerun chart update to apply new slice
            const activeTabButton = document.querySelector(`.secondary-tabs-btn[data-id="${currentDetailGroupId}"]`);
            if (activeTabButton) {
                updateDetailedChart(currentDetailGroupId, activeTabButton);
            }
        }
    };


    // ----------------------------------------------------------------------
    // --- LÓGICA DE TABLA Y FILTROS (Mantiene decimales en la tabla) ---
    // ----------------------------------------------------------------------
    
    const sortData = (data, column, direction) => {
        const numericColumns = ['product_id', 'total_units_sold', 'total_revenue', 'avg_price_per_unit_sale'];
        const isNumeric = numericColumns.includes(column);

        return data.sort((a, b) => {
            let aVal = a[column];
            let bVal = b[column];
            let comparison = 0;

            if (isNumeric) {
                aVal = parseFloat(aVal) || 0;
                bVal = parseFloat(bVal) || 0;
                comparison = aVal - bVal;
            } else {
                comparison = String(aVal).localeCompare(String(bVal));
            }

            return direction === 'asc' ? comparison : -comparison;
        });
    };

    const updateTable = () => {
        const limit = document.getElementById('limit').value;
        const tableBody = document.getElementById('product-analysis-table-body');
        tableBody.innerHTML = '';

        let sortedData = sortData([...analysisData], currentSortColumn, currentSortDirection);

        let finalData;
        if (limit === 'all') {
            finalData = sortedData;
        } else {
            finalData = sortedData.slice(0, parseInt(limit));
        }

        if (!finalData || finalData.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 2rem; color: #4b5563;">No hay datos de ventas para este rango y filtros.</td></tr>`;
            return;
        }

        finalData.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.product_id}</td>
                <td>${htmlspecialchars(item.product_name)}</td>
                <td>${htmlspecialchars(item.category_name || 'N/A')}</td>
                <td>${htmlspecialchars(item.supplier_name || 'N/A')}</td>
                <td class="numeric-cell">${number_format(item.total_units_sold, 2)}</td> <td class="numeric-cell">${formatCurrency(item.total_revenue, 0)}</td> <td class="numeric-cell">${formatCurrency(item.avg_price_per_unit_sale, 2)}</td> <td>${getStockAlarm(item.total_units_sold)}</td>
            `;
            tableBody.appendChild(row);
        });

        const headers = document.querySelectorAll('.sales-table th[data-sort-column]');
        headers.forEach(header => header.classList.remove('sort-asc', 'sort-desc'));

        const currentHeader = document.querySelector(`.sales-table th[data-sort-column="${currentSortColumn}"]`);
        if (currentHeader) {
            currentHeader.classList.add(`sort-${currentSortDirection}`);
        }
    };

    const setupTableHeaders = () => {
        const headers = document.querySelectorAll('.sales-table th[data-sort-column]');

            headers.forEach(header => {
                header.style.cursor = 'pointer';

                header.addEventListener('click', function() {
                    const column = this.getAttribute('data-sort-column');

                    if (currentSortColumn === column) {
                        currentSortDirection = (currentSortDirection === 'asc') ? 'desc' : 'asc';
                    } else {
                        currentSortColumn = column;
                        // Regla: Ordenar por descendente por defecto para métricas de valor/cantidad
                        currentSortDirection = (column === 'total_revenue' || column === 'total_units_sold' || column === 'product_id' || column === 'avg_price_per_unit_sale') ? 'desc' : 'asc'; 
                    }

                    updateTable();
                });
            });
        };

    // --- NUEVA FUNCIÓN PARA GESTIONAR LAS PESTAÑAS DE LA TABLA ---
    const showDetailContent = (contentId, buttonElement) => {
        // 1. Desactivar todos los botones de pestaña en este grupo
        const tabsContainer = buttonElement.closest('.detail-table-tabs');
        tabsContainer.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        
        // 2. Activar el botón seleccionado
        buttonElement.classList.add('active');

        // 3. Ocultar todos los contenidos de la pestaña
        const contentContainer = document.querySelector('.content-card:last-child'); // Selecciona el último content-card
        contentContainer.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

        // 4. Mostrar el contenido seleccionado
        const targetContent = document.getElementById(contentId);
        if (targetContent) {
            targetContent.classList.add('active');
        }

        // Nota: Si 'sales-avg-content' o 'stock-analysis-content' tuvieran lógica de tabla o gráfico, 
        // se debería llamar a una función de inicialización aquí.
    };
    // -------------------------------------------------------------


    const handleRangeFormSubmission = (event) => {
        const start = document.getElementById('start_date').value;
        const end = document.getElementById('end_date').value;

        if (!start || !end) {
            event.preventDefault();
            alert('Error: Por favor, selecciona tanto la fecha de inicio como la fecha de fin.');
            return;
        }

        if (new Date(start) > new Date(end)) {
            event.preventDefault();
            alert('Error: La fecha de inicio no puede ser posterior a la fecha de fin.');
            return;
        }
        
        // Al enviar el rango, los valores de Categoría y Proveedor se copian
        document.getElementById('hidden_category_id').value = document.getElementById('category-selector').value;
        document.getElementById('hidden_supplier_id').value = document.getElementById('supplier-selector').value;
    };


    const applyFilters = (value, type) => {
        const url = new URL(window.location.pathname, window.location.origin);
        
        // Obtener valores actuales (desde la nueva ubicación en el chart-header)
        const currentCategory = document.getElementById('category-selector').value;
        const currentSupplier = document.getElementById('supplier-selector').value;
        const currentMonth = document.getElementById('month-selector').value;

        let month = (type === 'month') ? value : currentMonth;
        let category = (type === 'category') ? value : currentCategory;
        let supplier = (type === 'supplier') ? value : currentSupplier;

        if(category !== 'all') url.searchParams.set('category', category); else url.searchParams.delete('category');
        if(supplier !== 'all') url.searchParams.set('supplier', supplier); else url.searchParams.delete('supplier');

        url.searchParams.delete('start_date');
        url.searchParams.delete('end_date');
        
        if (month) url.searchParams.set('month', month); else url.searchParams.delete('month');

        window.location.href = url.toString();
    };


    document.addEventListener('DOMContentLoaded', function() {
        setupTableHeaders();
        updateTable();

        // Setup inicial del Gráfico 1
        updateChart();
        
        // Setup inicial del Gráfico 2
        const initialPrimaryTab = document.querySelector('.detail-primary-tabs .tab-button.active');
        if (initialPrimaryTab) {
            showDetailTabs(initialPrimaryTab.getAttribute('data-group'), initialPrimaryTab);
        }

        // Setup inicial de las NUEVAS PESTAÑAS DE TABLA
        // Esto asegura que la primera pestaña esté activa al cargar
        const initialDetailTab = document.querySelector('.detail-table-tabs .tab-button.active');
        if(initialDetailTab) {
            showDetailContent(initialDetailTab.getAttribute('data-tab-content'), initialDetailTab);
        }

        // Listener para el formulario de rango de fechas
        const rangeForm = document.getElementById('range-filter-form');
        if (rangeForm) {
            rangeForm.addEventListener('submit', handleRangeFormSubmission);
        }
    });
</script>
</body>

</html>