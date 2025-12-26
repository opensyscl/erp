<?php
// Reporte de Errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluye configuración de DB y Start Session
require '../config.php';
session_start();


// ----------------------------------------------------------------------
// --- 1. VERIFICACIÓN DE LOGIN Y CONEXIÓN CRÍTICA ---
// ----------------------------------------------------------------------

// 1.1 Redireccionar si el usuario no está logueado (Usamos user_id, necesario para la BD)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// 1.2 Asegurar que la conexión PDO esté lista para el chequeo
if (!isset($pdo)) {
    die('Error fatal: Conexión PDO ($pdo) no disponible para el chequeo de módulos.');
}

$current_user_id = $_SESSION['user_id'];


// ----------------------------------------------------------------------
// --- 2. VALIDACIÓN DE ROL ESPECÍFICO (MÓDULO DE CAJA) ---
// ----------------------------------------------------------------------

$user_can_access = false;
// RUTA CONFIGURADA: Módulo de Ranking
$module_path = '/erp/ranking/';

try {
    // Obtenemos el rol del usuario logueado
    $stmt_role = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_role->execute([$current_user_id]);
    $user_role = $stmt_role->fetchColumn();

    // LÓGICA DE ACCESO: Roles permitidos para el módulo de Ranking
    if (in_array($user_role, ['POS1', 'POS2', 'Admin', 'Manager'])) {
        $user_can_access = true;
    }

} catch (PDOException $e) {
    // Si falla la BD, por seguridad, denegamos el acceso.
    error_log("Error de BD al verificar rol del usuario: " . $e->getMessage());
    header('Location: ../not_authorized.php');
    exit();
}


// ----------------------------------------------------------------------
// --- 3. VALIDACIÓN GLOBAL DE MÓDULO (GUARDIÁN) ---
// ----------------------------------------------------------------------

// Solo se chequea si ya tiene permiso de rol.
if ($user_can_access) {
    // Se requiere el chequeador (asume que está en includes/module_check.php)
    require '../includes/module_check.php';

    if (!is_module_enabled($module_path)) {
        // Redirigir si el módulo está DESACTIVADO GLOBALMENTE por el admin
        $user_can_access = false;
    }
}


// ----------------------------------------------------------------------
// --- 4. REDIRECCIÓN FINAL ---
// ----------------------------------------------------------------------
if (!$user_can_access) {
    header('Location: ../not_authorized.php');
    exit();
}
// ----------------------------------------------------------------------

// --- ACCESO CONCEDIDO: COMIENZA EL CÓDIGO ESPECÍFICO DEL MÓDULO DE CAJA ---
if (!isset($_SESSION['user_username'])) {
    header('Location: ../../login.php');
    exit();
}

// Tasa de Impuesto al Valor Agregado (IVA) para Netear Precios de Venta (Asumido 19%)
const IVA_RATE = 0.19;
const IVA_DIVISOR = 1 + IVA_RATE; // 1.19


// --- 1. OBTENER Y VALIDAR EL MES SELECCIONADO ---
$selectedMonthYear = $_GET['month'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonthYear)) {
    $selectedMonthYear = date('Y-m');
}

$selectedYear = date('Y', strtotime($selectedMonthYear . '-01'));
$selectedMonth = date('m', strtotime($selectedMonthYear . '-01'));

// Calcular el primer y último día del mes seleccionado (Actual)
$startOfMonth = date('Y-m-01', strtotime($selectedMonthYear . '-01'));
$endOfMonth = date('Y-m-t', strtotime($selectedMonthYear . '-01'));

// Calcular el primer y último día del mes anterior
$previousMonthDate = date('Y-m-d', strtotime($startOfMonth . ' -1 month'));
$startOfPreviousMonth = date('Y-m-01', strtotime($previousMonthDate));
$endOfPreviousMonth = date('Y-m-t', strtotime($previousMonthDate));

$currentDate = date('Y-m-d');
$isCurrentMonth = ($selectedMonthYear === date('Y-m'));

$dailySaleDate = $isCurrentMonth ? $currentDate : $endOfMonth;
$endDateForCalculations = $isCurrentMonth ? $currentDate : $endOfMonth;


// --- 2. CÁLCULO DE MÉTRICAS (KPIs) DE PRODUCTOS ---

// **IMPORTANTE:** Definición de la lógica de la cantidad vendida (ajuste para productos a Granel)
$quantity_logic = "
(CASE
WHEN p.name LIKE '%Granel%' THEN si.quantity / 1000.0
ELSE si.quantity
END)
";

// Aseguramos que el precio sea por la UNIDAD MAYOR (Kilo) para el cálculo de Ingresos Totales.
$price_logic = "
(CASE
WHEN p.name LIKE '%Granel%' THEN si.price * 1000.0
ELSE si.price
END)
";

// **NUEVA LÓGICA DE COSTO:** Convertir Costo/Gramo (p.cost_price) a Costo/Kilo si es Granel.
$cost_price_logic = "
(CASE
WHEN p.name LIKE '%Granel%' THEN p.cost_price * 1000.0
ELSE p.cost_price
END)
";

// Lógica de cálculo del margen unitario por la UNIDAD BASE MAYOR (Kilo o Unidad).
// FÓRMULA DE MARGEN UNITARIO (Precio Neto - Costo Neto/Ajustado):
// (Precio Venta Bruto por Kilo / 1.19) - Costo Neto Ajustado (por Kilo)
$margin_logic = "(({$price_logic} / " . IVA_DIVISOR . ") - {$cost_price_logic})";


// 2.1. Productos con/sin ventas (Global)
$stmt_products_global = $pdo->query("SELECT id FROM products WHERE archived = 0");
$total_products = $stmt_products_global->rowCount();

$stmt_products_sold = $pdo->query("
SELECT COUNT(DISTINCT si.product_id)
FROM sale_items si
");
$products_with_sales = $stmt_products_sold->fetchColumn() ?: 0;
$products_without_sales = $total_products - $products_with_sales;

// 2.2. Producto Más Vendido y Más Rentable (Día)
$sql_daily = "
SELECT
p.name,
SUM({$quantity_logic}) AS units_sold,
SUM({$quantity_logic} * {$price_logic}) AS total_revenue,
SUM({$quantity_logic} * {$margin_logic}) AS total_margin
FROM sale_items si
JOIN sales s ON s.id = si.sale_id
JOIN products p ON p.id = si.product_id
WHERE DATE(s.created_at) = ?
AND p.archived = 0 -- Filtrar archivados
GROUP BY p.id, p.name
";

$stmt_daily_sold = $pdo->prepare($sql_daily . " ORDER BY units_sold DESC, total_revenue DESC LIMIT 1");
$stmt_daily_sold->execute([$dailySaleDate]);
$most_sold_day = $stmt_daily_sold->fetch(PDO::FETCH_ASSOC);

$stmt_daily_profitable = $pdo->prepare($sql_daily . " ORDER BY total_margin DESC, total_revenue DESC LIMIT 1");
$stmt_daily_profitable->execute([$dailySaleDate]);
$most_profitable_day = $stmt_daily_profitable->fetch(PDO::FETCH_ASSOC);

// 2.3. Producto Más Vendido y Más Rentable (Mes)
$sql_monthly = "
SELECT
p.name,
SUM({$quantity_logic}) AS units_sold,
SUM({$quantity_logic} * {$price_logic}) AS total_revenue,
SUM({$quantity_logic} * {$margin_logic}) AS total_margin
FROM sale_items si
JOIN sales s ON s.id = si.sale_id
JOIN products p ON p.id = si.product_id
WHERE DATE(s.created_at) BETWEEN ? AND ?
AND p.archived = 0 -- Filtrar archivados
GROUP BY p.id, p.name
";

$stmt_monthly_sold = $pdo->prepare($sql_monthly . " ORDER BY units_sold DESC, total_revenue DESC LIMIT 1");
$stmt_monthly_sold->execute([$startOfMonth, $endDateForCalculations]);
$most_sold_month = $stmt_monthly_sold->fetch(PDO::FETCH_ASSOC);

$stmt_monthly_profitable = $pdo->prepare($sql_monthly . " ORDER BY total_margin DESC, total_revenue DESC LIMIT 1");
$stmt_monthly_profitable->execute([$startOfMonth, $endDateForCalculations]);
$most_profitable_month = $stmt_monthly_profitable->fetch(PDO::FETCH_ASSOC);


// ----------------------------------------------------------------------
// --- 2.4. KPI 1: PRODUCTO CON MAYOR CRECIMIENTO (Mes vs Mes Anterior) ---
// ----------------------------------------------------------------------

// Obtener ventas del mes actual
$stmt_current_month_sales = $pdo->prepare("
SELECT
p.id,
p.name,
SUM({$quantity_logic}) AS current_units_sold
FROM sale_items si
JOIN sales s ON s.id = si.sale_id
JOIN products p ON p.id = si.product_id
WHERE DATE(s.created_at) BETWEEN ? AND ?
AND p.archived = 0 -- Filtrar archivados
GROUP BY p.id, p.name
");
$stmt_current_month_sales->execute([$startOfMonth, $endDateForCalculations]);
$current_month_results = $stmt_current_month_sales->fetchAll(PDO::FETCH_ASSOC);

// Reprocesar a la estructura deseada (ID => [name, current_units_sold])
$current_month_sales = [];
foreach ($current_month_results as $row) {
    $current_month_sales[$row['id']] = [
        'name' => $row['name'],
        'current_units_sold' => $row['current_units_sold']
    ];
}

// Obtener ventas del mes anterior
$stmt_previous_month_sales = $pdo->prepare("
SELECT
p.id,
SUM({$quantity_logic}) AS previous_units_sold
FROM sale_items si
JOIN sales s ON s.id = si.sale_id
JOIN products p ON p.id = si.product_id
WHERE DATE(s.created_at) BETWEEN ? AND ?
AND p.archived = 0 -- Filtrar archivados
GROUP BY p.id
");
$stmt_previous_month_sales->execute([$startOfPreviousMonth, $endOfPreviousMonth]);
$previous_month_sales = $stmt_previous_month_sales->fetchAll(PDO::FETCH_KEY_PAIR);

$growth_ranking = [];
foreach ($current_month_sales as $product_id => $data) {
    $product_name = $data['name'];
    $current_units = floatval($data['current_units_sold']);
    $previous_units = floatval($previous_month_sales[$product_id] ?? 0);

    $unit_difference = $current_units - $previous_units;

    if ($unit_difference > 0) {
        // Evitar división por cero
        $growth_pct = $previous_units > 0 ? ($unit_difference / $previous_units) * 100 : 10000;

        $growth_ranking[] = [
            'name' => $product_name,
            'unit_difference' => $unit_difference,
            'growth_pct' => $growth_pct,
            'current_units' => $current_units,
            'previous_units' => $previous_units,
        ];
    }
}

usort($growth_ranking, fn($a, $b) => $b['unit_difference'] <=> $a['unit_difference']);
$most_growth_month = $growth_ranking[0] ?? null;


// -------------------------------------------------------------------
// --- 2.5. KPI 2: PRODUCTO CON MENOR ROTACIÓN (Venta más baja con Stock) ---
// -------------------------------------------------------------------


$sql_low_rotation = "
SELECT
p.name,
p.stock,
COALESCE(SUM({$quantity_logic}), 0) AS units_sold_month
FROM products p
LEFT JOIN sale_items si ON p.id = si.product_id
LEFT JOIN sales s ON s.id = si.sale_id AND DATE(s.created_at) BETWEEN ? AND ?
WHERE p.stock > 0
AND p.archived = 0 -- Filtrar archivados
GROUP BY p.id, p.name, p.stock
HAVING units_sold_month > 0 OR (units_sold_month = 0 AND p.stock > 0)
ORDER BY units_sold_month ASC, p.stock DESC
LIMIT 1
";

$stmt_low_rotation = $pdo->prepare($sql_low_rotation);
$stmt_low_rotation->execute([$startOfMonth, $endDateForCalculations]);
$lowest_rotation_month = $stmt_low_rotation->fetch(PDO::FETCH_ASSOC);


// --- 3. OBTENER DATOS DE RANKING PARA LA TABLA Y EL GRÁFICO ---

function get_ranking_data($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$sum_quantity_adjusted = "SUM({$quantity_logic})";

// Margen unitario promedio (por Kilo/Unidad)
$avg_unit_margin_formula = "(SUM({$quantity_logic} * {$margin_logic}) / NULLIF({$sum_quantity_adjusted}, 0))";


// 🚨 MODIFICACIÓN CRÍTICA: JOIN con suppliers para obtener el nombre
$base_ranking_sql = "
SELECT
p.id,
p.name AS product_name,
COALESCE(s_p.name, 'N/A') AS supplier_name, -- 🚨 CAMBIO: Seleccionar el nombre del proveedor
p.archived,
p.stock,
{$sum_quantity_adjusted} AS units_sold,
SUM({$quantity_logic} * {$price_logic}) AS total_revenue,
SUM({$quantity_logic} * {$margin_logic}) AS total_margin,
{$avg_unit_margin_formula} AS avg_unit_margin
FROM sale_items si
JOIN sales s ON s.id = si.sale_id
JOIN products p ON p.id = si.product_id
LEFT JOIN suppliers s_p ON s_p.id = p.supplier_id -- 🚨 CAMBIO: Nuevo JOIN con suppliers
";

// Ranking Global
// 🚨 CAMBIO: Agrupar por el nombre del proveedor
$ranking_data_global = get_ranking_data($pdo, $base_ranking_sql . " WHERE p.archived = 0 GROUP BY p.id, p.name, s_p.name ORDER BY total_revenue DESC");

// Ranking Mensual
// 🚨 CAMBIO: Agrupar por el nombre del proveedor
$ranking_data_monthly = get_ranking_data($pdo, $base_ranking_sql . " WHERE DATE(s.created_at) BETWEEN ? AND ? AND p.archived = 0 GROUP BY p.id, p.name, s_p.name ORDER BY total_revenue DESC", [$startOfMonth, $endDateForCalculations]);

// Ranking Diario
// 🚨 CAMBIO: Agrupar por el nombre del proveedor
$ranking_data_daily = get_ranking_data($pdo, $base_ranking_sql . " WHERE DATE(s.created_at) = ? AND p.archived = 0 GROUP BY p.id, p.name, s_p.name ORDER BY total_revenue DESC", [$dailySaleDate]);

// Productos sin ventas (Mes Seleccionado)
$stmt_products_unsold_monthly = $pdo->prepare("
SELECT p.id, p.name AS product_name, COALESCE(s_p.name, 'N/A') AS supplier_name -- 🚨 CAMBIO
FROM products p
LEFT JOIN sale_items si ON p.id = si.product_id
LEFT JOIN sales s ON s.id = si.sale_id AND DATE(s.created_at) BETWEEN ? AND ?
LEFT JOIN suppliers s_p ON s_p.id = p.supplier_id -- 🚨 CAMBIO
WHERE s.id IS NULL
AND p.archived = 0 -- Mostrar solo productos activos sin ventas
GROUP BY p.id, p.name, s_p.name, p.stock
ORDER BY p.name ASC
");
$stmt_products_unsold_monthly->execute([$startOfMonth, $endOfMonth]);
$ranking_data_unsold_monthly = $stmt_products_unsold_monthly->fetchAll(PDO::FETCH_ASSOC);

// Productos sin ventas (Global)
$stmt_products_unsold_global = $pdo->prepare("
SELECT p.id, p.name AS product_name, COALESCE(s_p.name, 'N/A') AS supplier_name -- 🚨 CAMBIO
FROM products p
LEFT JOIN sale_items si ON p.id = si.product_id
LEFT JOIN suppliers s_p ON s_p.id = p.supplier_id -- 🚨 CAMBIO
WHERE si.id IS NULL -- Busca productos que nunca han tenido un sale_item asociado
AND p.archived = 0
GROUP BY p.id, p.name, s_p.name, p.stock
ORDER BY p.name ASC
");
$stmt_products_unsold_global->execute();
$ranking_data_unsold_global = $stmt_products_unsold_global->fetchAll(PDO::FETCH_ASSOC);


// Cálculo de la contribución porcentual y el total para el gráfico y JS
$total_global_revenue = array_sum(array_column($ranking_data_global, 'total_revenue'));
$total_global_margin = array_sum(array_column($ranking_data_global, 'total_margin'));
$total_monthly_revenue = array_sum(array_column($ranking_data_monthly, 'total_revenue'));
$total_monthly_margin = array_sum(array_column($ranking_data_monthly, 'total_margin'));
$days_passed_in_month = $isCurrentMonth ? (int)date('d') : (int)date('t', strtotime($selectedMonthYear . '-01'));

// Adjuntar métricas de participación para el ranking global (para el gráfico)
foreach ($ranking_data_global as &$product) {
    $product['revenue_share_pct'] = $total_global_revenue > 0 ? (floatval($product['total_revenue']) / $total_global_revenue) * 100 : 0;
    $product['margin_share_pct'] = $total_global_margin > 0 ? (floatval($product['total_margin']) / $total_global_margin) * 100 : 0;
}
unset($product);

// Limitar a los 10 productos de mayor contribución al margen (se mantiene por si se reincorpora el gráfico)
usort($ranking_data_global, fn($a, $b) => $b['margin_share_pct'] <=> $a['margin_share_pct']);
$chart_data = array_slice($ranking_data_global, 0, 10);


// --- 4. CÁLCULO DE DÍAS TOTALES DE OPERACIÓN PARA RANKING GLOBAL ---
$stmt_first_sale = $pdo->query("SELECT MIN(created_at) FROM sales WHERE created_at IS NOT NULL");
$first_sale_date_str = $stmt_first_sale->fetchColumn();

$total_operating_days = 0;
if ($first_sale_date_str) {
    $first_sale_date_only = date('Y-m-d', strtotime($first_sale_date_str));
    $first_sale_datetime = new DateTime($first_sale_date_only);
    $current_datetime = new DateTime($currentDate);
    if ($first_sale_datetime <= $current_datetime) {
        $date_diff = $first_sale_datetime->diff($current_datetime);
        $total_operating_days = $date_diff->days + 1;
    }
}


// --- 5. GENERACIÓN DE OPCIONES DE MESES PARA EL SELECTOR ---
$monthOptions = [];
if (class_exists('IntlDateFormatter')) {
    $formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'America/Santiago', IntlDateFormatter::GREGORIAN, 'MMMM yyyy');
    for ($i = 0; $i < 12; $i++) {
        $date = new DateTime("-$i month");
        $value = $date->format('Y-m');
        $label = $formatter->format($date->getTimestamp());
        $monthOptions[$value] = ucfirst($label);
    }
} else {
    $meses = ['January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'];
    for ($i = 0; $i < 12; $i++) {
        $date = new DateTime("-$i month");
        $value = $date->format('Y-m');
        $monthNameEn = $date->format('F');
        $year = $date->format('Y');
        $label = $meses[$monthNameEn] . ' ' . $year;
        $monthOptions[$value] = $label;
    }
}

// Variables para el encabezado
$current_page = 'ranking.php';
$stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
$stmt->execute();
$system_version = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ranking de Productos - Listto! ERP</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="icon" type="image/png" href="/erp/img/fav.png">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/ranking.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
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
<a href="ranking.php" class="active">Ranking de Productos</a>
</nav>
<div class="header-right">
<span class="app-version"><?php echo htmlspecialchars($system_version); ?></span>
<a href="../logout.php" class="btn-logout">Cerrar Sesi&oacute;n</a>
</div>
</header>

<main class="container">
<div class="page-header-controls">
</div>

<div class="kpi-grid">
<div class="kpi-card">
<h3>Productos con Ventas (Global)</h3>
<p class="value"><?= number_format($products_with_sales, 0, ',', '.') ?></p>
</div>
<div class="kpi-card">
<h3>Productos sin Ventas (Global)</h3>
<p class="value"><?= number_format($products_without_sales, 0, ',', '.') ?></p>
</div>
<div class="kpi-card" style="border-left: 5px solid var(--accent-color);">
<h3>🥇 Más Vendido del Mes</h3>
<p class="value" style="font-size: 1.4rem; font-weight: 600;">
<?= htmlspecialchars($most_sold_month['name'] ?? 'N/A') ?>
</p>
<p style="font-size: 1rem; color: var(--text-secondary); margin-top: 0.5rem;">
<?= number_format($most_sold_month['units_sold'] ?? 0, 2, ',', '.') ?> unidades
</p>
</div>
<div class="kpi-card" style="border-left: 5px solid #1ea769;">
<h3>💰 Más Rentable del Mes</h3>
<p class="value" style="font-size: 1.4rem; font-weight: 600;">
<?= htmlspecialchars($most_profitable_month['name'] ?? 'N/A') ?>
</p>
<p style="font-size: 1rem; color: var(--text-secondary); margin-top: 0.5rem;">
$ <?= number_format($most_profitable_month['total_margin'] ?? 0, 0, ',', '.') ?>
</p>
</div>
</div>

<div class="kpi-grid" style="margin-top: -1rem;">
<div class="kpi-card" style="grid-column: span 2; border-left: 5px solid #ff9900; padding: 1rem 1.5rem;">
<h3>📈 Mayor Crecimiento (Mes)</h3>
<p class="value" style="font-size: 1.2rem; font-weight: 600; color: #ff9900;">
<?= htmlspecialchars($most_growth_month['name'] ?? 'N/A') ?>
</p>
<?php if ($most_growth_month): ?>
<p style="font-size: 1rem; color: var(--text-secondary); margin-top: 0.3rem;">
Creció: <?= number_format($most_growth_month['unit_difference'], 0, ',', '.') ?> unidades
(<?= number_format($most_growth_month['growth_pct'], 0, ',', '.') ?>%)
</p>
<?php else: ?>
<p style="font-size: 1rem; color: var(--text-secondary); margin-top: 0.3rem;">No se detectó crecimiento.</p>
<?php endif; ?>
</div>

<div class="kpi-card" style="grid-column: span 2; border-left: 5px solid #d9534f; padding: 1rem 1.5rem;">
<h3>🐢 Menor Rotación (Mes con Stock)</h3>
<p class="value" style="font-size: 1.2rem; font-weight: 600; color: #d9534f;">
<?= htmlspecialchars($lowest_rotation_month['name'] ?? 'N/A') ?>
</p>
<?php if ($lowest_rotation_month): ?>
<p style="font-size: 1rem; color: var(--text-secondary); margin-top: 0.3rem;">
Ventas: <?= number_format($lowest_rotation_month['units_sold_month'] ?? 0, 0, ',', '.') ?> unidades
(Stock: <?= number_format($lowest_rotation_month['stock'] ?? 0, 0, ',', '.') ?>)
</p>
<?php else: ?>
<p style="font-size: 1rem; color: var(--text-secondary); margin-top: 0.3rem;">No se encontraron productos con stock y ventas bajas.</p>
<?php endif; ?>
</div>
</div>
<?php if ($isCurrentMonth): ?>
<div class="kpi-grid" style="margin-top: -1rem; margin-bottom: 2.5rem;">
<div class="kpi-card" style="grid-column: span 2; border-left: 5px solid var(--accent-color); padding: 1rem 1.5rem;">
<h3>🥇 Más Vendido del Día (<?= date('d/m/Y', strtotime($dailySaleDate)) ?>)</h3>
<p class="value" style="font-size: 1.2rem; font-weight: 600;"><?= htmlspecialchars($most_sold_day['name'] ?? 'N/A') ?></p>
<p style="font-size: 1rem; color: var(--text-secondary); margin-top: 0.3rem;"><?= number_format($most_sold_day['units_sold'] ?? 0, 0, ',', '.') ?> unidades</p>
</div>
<div class="kpi-card" style="grid-column: span 2; border-left: 5px solid #1ea769; padding: 1rem 1.5rem;">
<h3>💰 Más Rentable del Día (<?= date('d/m/Y', strtotime($dailySaleDate)) ?>)</h3>
<p class="value" style="font-size: 1.2rem; font-weight: 600;"><?= htmlspecialchars($most_profitable_day['name'] ?? 'N/A') ?></p>
<p style="font-size: 1rem; color: var(--text-secondary); margin-top: 0.3rem;">$ <?= number_format($most_profitable_day['total_margin'] ?? 0, 0, ',', '.') ?> de margen</p>
</div>
</div>
<?php endif; ?>

<div class="content-card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 1rem;">
        <h2>Gráfico Top 10 Productos</h2>
        <div class="month-selector-container">
            <label for="month-selector">Análisis para:</label>
            <select id="month-selector" onchange="window.location.href = 'ranking.php?month=' + this.value">
            <?php
            // Imprimir las opciones generadas en PHP
            foreach ($monthOptions as $value => $label) {
                $selected = ($value === $selectedMonthYear) ? 'selected' : '';
                echo "<option value=\"{$value}\" {$selected}>{$label}</option>";
            }
            ?>
            </select>
        </div>
    </div>
    
    <div class="chart-tabs" style="margin-bottom: 1rem; display: flex; gap: 10px; border-bottom: 2px solid #eee;">
        <button class="chart-tab-button active" data-chart-key="total_revenue" onclick="switchChartTab(this, 'total_revenue')" style="padding: 8px 15px; font-size: 16px; border: none; background: none; cursor: pointer; font-weight: 600; color: var(--text-secondary); border-bottom: 3px solid transparent;">
            Ingresos
        </button>
        <button class="chart-tab-button" data-chart-key="units_sold" onclick="switchChartTab(this, 'units_sold')" style="padding: 8px 15px;  font-size: 16px; border: none; background: none; cursor: pointer; font-weight: 600; color: var(--text-secondary); border-bottom: 3px solid transparent;">
            Unidades Vendidas
        </button>
        <button class="chart-tab-button" data-chart-key="total_margin" onclick="switchChartTab(this, 'total_margin')" style="padding: 8px 15px;  font-size: 16px; border: none; background: none; cursor: pointer; font-weight: 600; color: var(--text-secondary); border-bottom: 3px solid transparent;">
            Margen Total
        </button>
    </div>
    <div style="height: 400px; margin-top: 1rem;">
        <canvas id="rankingChart"></canvas>
    </div>
</div>


<div class="content-card" style="margin-top: 20px;">
<div class="table-header-controls">
<h2>Detalle de Ranking de Productos</h2>
<div class="controls-group">


<div class="tabs">
<button class="tab-button" data-tab="daily">Ventas del Día</button>
<button class="tab-button" data-tab="monthly">Ventas del Mes</button>
<button class="tab-button active" data-tab="global">Ventas Globales</button>
<button class="tab-button" data-tab="unsold_monthly">Productos sin Ventas (Mes)</button>
<button class="tab-button" data-tab="unsold_global">Productos sin Ventas (Global)</button> </div>
<div class="limit-selector-container">
<label for="limitSelector">Mostrar:</label>
<select id="limitSelector" onchange="handleLimitChange(this.value)">
<option value="5">5 Registros</option>
<option value="25" selected>25 Registros</option>
<option value="50">50 Registros</option>
<option value="100">100 Registros</option>
<option value="All">Todos los Registros</option>
</select>
</div>
</div>
</div>
<div class="table-container">
<table class="sales-table">
<thead>
<tr>
<th data-sort-key="index">#</th>
<th data-sort-key="product_name">Producto</th>
<th data-sort-key="supplier_name">Proveedor</th>
<th data-sort-key="stock">Stock Actual</th>
<th data-sort-key="units_sold">Unidades Vendidas</th>
<th data-sort-key="total_revenue">Ingresos Totales</th>
<th data-sort-key="total_margin">Margen Total</th>
<th data-sort-key="avg_unit_margin">Margen Unit. Prom.</th>
<th data-sort-key="revenue_share_pct">Aporte en Ingresos (%)</th>
<th data-sort-key="margin_share_pct">Aporte en Margen (%)</th>
<th data-sort-key="units_per_day">Unidades/Día</th>
</tr>
</thead>
<tbody id="ranking-table-body">
<tr>
<td colspan="10" style="text-align: center; padding: 2rem;">Cargando ranking...</td>
</tr>
</tbody>
</table>
</div>
</div>
</main>

<script>
// ** INICIO: CÓDIGO JS - INTEGRACIÓN DE GRÁFICA Y LÓGICA DE TABLA **

let rankingChartInstance;

// **Constantes de Datos de Ranking desde PHP**
const rankingDataGlobal = <?= json_encode($ranking_data_global); ?>;
const rankingDataMonthly = <?= json_encode($ranking_data_monthly); ?>;
const rankingDataDaily = <?= json_encode($ranking_data_daily); ?>;
const rankingDataUnsoldMonthly = <?= json_encode($ranking_data_unsold_monthly); ?>;
const rankingDataUnsoldGlobal = <?= json_encode($ranking_data_unsold_global); ?>;

const totalMonthlyRevenue = parseFloat(<?= $total_monthly_revenue ?>);
const totalMonthlyMargin = parseFloat(<?= $total_monthly_margin ?>);
const daysPassedInMonth = parseInt(<?= $days_passed_in_month ?>);
const totalOperatingDays = parseInt(<?= $total_operating_days ?>);

// **ESTADO GLOBAL DE ORDENACIÓN**
let sortState = {
key: 'total_revenue', // Orden inicial por Ingresos Totales
direction: 'desc', // Dirección descendente
tab: 'global' // Pestaña actual de la TABLA
};

// **ESTADO GLOBAL DEL GRÁFICO**
let chartSortKey = 'total_revenue'; // Métrica de ordenación inicial del gráfico: Ingresos

// **ESTADO GLOBAL DE LÍMITE**
let currentLimit = 25; // Por defecto: 25

// --- Función para manejar el cambio de límite (llamada desde el HTML) ---
const handleLimitChange = (value) => {
// Convertir a número si no es 'All'
currentLimit = (value === 'All') ? 'All' : parseInt(value, 10);

// Volver a renderizar la tabla con el nuevo límite, manteniendo la pestaña actual
updateRankingTable(sortState.tab);
};

// --- Función de Formato de Moneda sin Decimales (CLP) ---
const formatCurrency = (amount) => {
    // Se asegura que el valor sea numérico, y si es null/undefined usa 0
    const value = parseFloat(amount) || 0;
    return value.toLocaleString('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 0
    });
};

// Nueva Función de Formato de Moneda con Decimales
const formatCurrencyDecimals = (amount, decimals = 2) => {
    const value = parseFloat(amount) || 0;
    return value.toLocaleString('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
};

// Función de formato modificada para manejar 2 o 3 decimales
const number_format = (amount, decimals = 0, productName = '') => {
    const value = parseFloat(amount) || 0;
    let finalDecimals = decimals;

    // Lógica condicional: Si es producto Granel y la cantidad es < 1, usa 3 decimales
    if (productName.includes('Granel') && value < 1 && finalDecimals === 0) {
        finalDecimals = 3;
    } else if (finalDecimals === 0) {
        // Para unidades vendidas (units_sold) que no son granel, queremos 2 decimales si el valor es flotante
        finalDecimals = (value % 1 !== 0) ? 2 : 0;
    }


    return value.toLocaleString('es-CL', {
        minimumFractionDigits: finalDecimals,
        maximumFractionDigits: finalDecimals
    });
};

// --- Función de Lógica de Ordenación ---
const sortData = (data, key, direction) => {
    if (!data || data.length === 0) return [];
    const sortedData = [...data];

    sortedData.sort((a, b) => {
        let valA = a[key];
        let valB = b[key];

        // 🚨 CAMBIO: Solo las columnas de montos/unidades deben ser forzadas a float. 
        // 'supplier_name' es una cadena (string).
        if (key !== 'product_name' && key !== 'supplier_name') {
            valA = parseFloat(valA) || 0;
            valB = parseFloat(valB) || 0;
        }

        if (typeof valA === 'string') valA = valA.toUpperCase();
        if (typeof valB === 'string') valB = valB.toUpperCase();

        let comparison = 0;
        if (valA > valB) {
            comparison = 1;
        } else if (valA < valB) {
            comparison = -1;
        } else {
            // Desempate por nombre del producto
            comparison = a['product_name'] > b['product_name'] ? 1 : a['product_name'] < b['product_name'] ? -1 : 0;
        }

        return direction === 'asc' ? comparison : comparison * -1;
    });

    return sortedData;
};

// ----------------------------------------------------------------------
// --- FUNCIONES PARA LA GRÁFICA (MEJORADAS CON LÓGICA DE PESTAÑAS) ---
// ----------------------------------------------------------------------

const initChart = () => {
    const ctx = document.getElementById('rankingChart').getContext('2d');
    
    if (rankingChartInstance) {
        rankingChartInstance.destroy();
    }

    // Configuración base de la gráfica
    rankingChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [], 
            datasets: [{
                label: '', 
                data: [], 
                backgroundColor: 'rgba(54, 162, 235, 0.6)', // Color por defecto: Ingresos
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, 
            indexAxis: 'y', // Barras horizontales
            plugins: {
                legend: {
                    display: false, // Ocultamos la leyenda ya que solo hay un dataset que cambia
                },
                title: {
                    display: false, // ELIMINAR TÍTULO
                    text: '', 
                    font: {
                        size: 16
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            const value = context.parsed.x;
                            
                            if (label.includes('(CLP)')) {
                                return label + ': ' + formatCurrency(value);
                            } else {
                                // Para Unidades Vendidas
                                return label + ': ' + number_format(value, 2);
                            }
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Ingresos Totales (CLP)',
                    },
                    ticks: {
                        callback: function(value, index, values) {
                            // Se ajustará el formato del tick según la métrica activa (chartSortKey)
                            if (chartSortKey === 'total_revenue' || chartSortKey === 'total_margin') {
                                if (value >= 1000000) {
                                    return formatCurrencyDecimals(value / 1000000, 1) + ' M'; 
                                } else if (value >= 1000) {
                                    return formatCurrencyDecimals(value / 1000, 0) + ' K';
                                }
                                return formatCurrency(value);
                            } else if (chartSortKey === 'units_sold') {
                                // Formato para unidades (sólo números)
                                return number_format(value, 0); 
                            }
                            return value; // Retorno por defecto si la clave es desconocida
                        }
                    }
                },
                y: {
                    reverse: true, // Para que el más vendido quede arriba
                    autoSkip: false
                }
            }
        }
    });
};

// --- Función Principal de Actualización del Gráfico ---
const updateRankingChart = (tab, sortKey = 'total_revenue') => {
    let rawData;
    let chartTitle = 'Top 10 Productos por '; 
    let axisLabel = '';
    let backgroundColor = '';

    chartSortKey = sortKey; // Guardar la clave de ordenación actual del gráfico

    // 1. Seleccionar los datos según la pestaña de la TABLA
    switch (tab) {
        case 'global':
            rawData = rankingDataGlobal;
            chartTitle += 'Ventas Globales'; 
            break;
        case 'monthly':
            rawData = rankingDataMonthly;
            chartTitle += 'Ventas del Mes';
            break;
        case 'daily':
            rawData = rankingDataDaily;
            chartTitle += 'Ventas del Día';
            break;
        default:
            // Pestañas sin ventas: limpiar y salir
            rankingChartInstance.data.labels = [];
            rankingChartInstance.data.datasets[0].data = [];
            rankingChartInstance.update();
            return;
    }

    // 2. Configurar etiquetas y colores según la métrica del GRÁFICO (sortKey)
    switch (sortKey) {
        case 'total_revenue':
            axisLabel = 'Ingresos Totales (CLP)';
            backgroundColor = 'rgba(54, 162, 235, 0.6)'; // Azul
            break;
        case 'units_sold':
            axisLabel = 'Unidades Vendidas';
            backgroundColor = 'rgba(255, 159, 64, 0.6)'; // Naranja
            break;
        case 'total_margin':
            axisLabel = 'Margen Total (CLP)';
            backgroundColor = 'rgba(75, 192, 192, 0.6)'; // Verde/Cian
            break;
    }
    
    // 3. Ordenar por la clave de la métrica activa y limitar a 10
    const sortedData = sortData(rawData, sortKey, 'desc');
    const top10 = sortedData.slice(0, 10);

    // 4. Extraer etiquetas y valores
    const labels = top10.map(p => p.product_name);
    // Asegurarse de que los datos extraídos sean la métrica correcta
    const data = top10.map(p => parseFloat(p[sortKey]) || 0); 
    
    // 5. Actualizar la instancia de Chart.js
    // Invertir para que el de mayor valor quede en la parte superior del gráfico horizontal.
    rankingChartInstance.data.labels = labels.reverse(); 
    rankingChartInstance.data.datasets[0].data = data.reverse();

    // Actualizar configuración
    rankingChartInstance.data.datasets[0].label = axisLabel;
    rankingChartInstance.data.datasets[0].backgroundColor = backgroundColor;
    rankingChartInstance.data.datasets[0].borderColor = backgroundColor.replace('0.6', '1'); // Borde más opaco
    rankingChartInstance.options.scales.x.title.text = axisLabel;

    rankingChartInstance.update();
};


// --- Nueva función para cambiar la pestaña del gráfico ---
const switchChartTab = (element, key) => {
    // 1. Eliminar la clase 'active' de todos los botones de la gráfica
    document.querySelectorAll('.chart-tab-button').forEach(btn => btn.classList.remove('active'));

    // 2. Agregar la clase 'active' al botón clickeado
    element.classList.add('active');

    // 3. Actualizar la gráfica con la nueva clave de ordenación, pero manteniendo la pestaña de la tabla (sortState.tab)
    updateRankingChart(sortState.tab, key);
};

// ----------------------------------------------------------------------
// --- Función para la Tabla de Ranking (General) ---
// ----------------------------------------------------------------------

const updateRankingTable = (tab) => {
    const tableBody = document.getElementById('ranking-table-body');
    tableBody.innerHTML = '';

    let rawData;
    sortState.tab = tab;
    let isUnsold = false;

    // 1. Seleccionar los datos según la pestaña
    switch (tab) {
        case 'global':
            rawData = rankingDataGlobal;
            break;
        case 'monthly':
            rawData = rankingDataMonthly;
            break;
        case 'daily':
            rawData = rankingDataDaily;
            break;
        case 'unsold_monthly':
            rawData = rankingDataUnsoldMonthly;
            isUnsold = true;
            break;
        case 'unsold_global':
            rawData = rankingDataUnsoldGlobal;
            isUnsold = true;
            break;
        default:
            rawData = rankingDataGlobal;
            break;
    }

    if (!rawData || rawData.length === 0) {
        const colspan = 10;
        tableBody.innerHTML = `<tr><td colspan="${colspan}" style="text-align: center; padding: 2rem;">No hay datos de ranking para la selección actual.</td></tr>`;
        addSortingHeaders();
        return;
    }


    // 2. PRE-PROCESAR LOS DATOS (CALCULAR MÉTRICAS DINÁMICAS - SOLO PESTAÑAS DE VENTA)
    let dataToProcess = rawData;
    if (!isUnsold) {
        let totalCurrentRevenue = 0;
        let totalCurrentMargin = 0;

        if (tab === 'monthly') {
            totalCurrentRevenue = totalMonthlyRevenue;
            totalCurrentMargin = totalMonthlyMargin;
        } else if (tab === 'daily' || tab === 'global') {
            totalCurrentRevenue = rawData.reduce((sum, p) => sum + (parseFloat(p.total_revenue) || 0), 0);
            totalCurrentMargin = rawData.reduce((sum, p) => sum + (parseFloat(p.total_margin) || 0), 0);
        }

        dataToProcess = rawData.map(product => {
            let p = { ...product };

            const revenue = parseFloat(p.total_revenue) || 0;
            const margin = parseFloat(p.total_margin) || 0;
            const units = parseFloat(p.units_sold) || 0;

            // Calcular las métricas dinámicas
            p.revenue_share_pct = totalCurrentRevenue > 0 ? (revenue / totalCurrentRevenue) * 100 : 0;
            p.margin_share_pct = totalCurrentMargin > 0 ? (margin / totalCurrentMargin) * 100 : 0;

            if (tab === 'global') {
                p.units_per_day = totalOperatingDays > 0 ? units / totalOperatingDays : 0;
            } else if (tab === 'monthly') {
                p.units_per_day = daysPassedInMonth > 0 ? units / daysPassedInMonth : 0;
            } else if (tab === 'daily') {
                p.units_per_day = units; 
            }

            p.avg_unit_margin = parseFloat(p.avg_unit_margin) || 0;

            return p;
        });
    }

    // **3. Aplicar ordenación (incluyendo 'unsold')**
    let data = sortData(dataToProcess, sortState.key, sortState.direction);

    // **4. Aplicar el Límite de Registros**
    let dataToDisplay = data;
    if (currentLimit !== 'All') {
        const limit = parseInt(currentLimit, 10);
        dataToDisplay = data.slice(0, limit);
    }

    // Si es una pestaña de productos sin ventas, renderizar con datos fijos (cero)
if (isUnsold) {
        dataToDisplay.forEach((product, index) => {
            const row = document.createElement('tr');
            const displaySupplierName = product.supplier_name && product.supplier_name.trim() !== '' ? product.supplier_name : 'N/A';
            
            // 1. Calcular el stock ajustado
            const adjustedStock = calculateStock(product.product_name, product.stock);
            // 2. Determinar el sufijo
            const stockSuffix = product.product_name.includes('Granel') ? ' (kg)' : ''; // 🚨 NUEVA LÍNEA

            row.innerHTML = `
            <td>${index + 1}</td>
            <td style="font-weight: 600;">${product.product_name}</td>
            <td>${displaySupplierName}</td>
            <td>${number_format(adjustedStock, 2, product.product_name)}${stockSuffix}</td> <td>0.00</td><td>${formatCurrency(0)}</td><td>${formatCurrency(0)}</td>
            <td title="Margen por Unidad (Neto)">${formatCurrencyDecimals(0, 0)}</td><td>0.00 %</td><td>0.00 %</td><td>0.00</td>
            `;
            tableBody.appendChild(row);
        });
        addSortingHeaders();
        return;
    }


// 5. Renderizar la tabla (incluyendo el índice)
    dataToDisplay.forEach((product, index) => {
        const row = document.createElement('tr');

        product.index = index + 1;

        // Lógica para Margen Unitario Granel: convertir Margen/Kilo a Margen/Gramo
        let displayUtility = product.avg_unit_margin;
        let unitContext = 'Margen por Unidad (Neto)';
        let decimalsToDisplay = 0;

        if (product.product_name.includes('Granel')) {
            displayUtility = product.avg_unit_margin / 1000;
            unitContext = 'Margen por Gramo (Neto)';
            decimalsToDisplay = 3;
        } else {
            decimalsToDisplay = 0;
        }
        
        // 🚨 CAMBIO AQUÍ: Calcular el stock ajustado
        const displaySupplierName = product.supplier_name && product.supplier_name.trim() !== '' ? product.supplier_name : 'N/A';
        const adjustedStock = calculateStock(product.product_name, product.stock);
        const stockSuffix = product.product_name.includes('Granel') ? ' (kg)' : ''; // 🚨 NUEVA LÍNEA


// --- Renderizado ---
        row.innerHTML = `
        <td>${product.index}</td>
        <td style="font-weight: 600;">${product.product_name}</td>
        <td>${displaySupplierName}</td>
        <td>${number_format(adjustedStock, 2, product.product_name)}${stockSuffix}</td> <td>${number_format(product.units_sold, 2, product.product_name)}</td>
        <td>${formatCurrency(product.total_revenue)}</td>
        <td>${formatCurrency(product.total_margin)}</td>
        <td title="${unitContext}" style="cursor: help;">${formatCurrencyDecimals(displayUtility, decimalsToDisplay)}</td>
        <td>${(product.revenue_share_pct).toFixed(2)} %</td>
        <td>${(product.margin_share_pct).toFixed(2)} %</td>
        <td>${number_format(product.units_per_day, 2, product.product_name)}</td> `;
        tableBody.appendChild(row);
    });

    // 6. Aplicar eventos de ordenación
    addSortingHeaders();
};


// --- Función para Añadir Eventos de Clic a los Encabezados ---
const addSortingHeaders = () => {
    document.querySelectorAll('.sales-table th').forEach(header => {
        const key = header.getAttribute('data-sort-key');

        // Limpiar iconos de ordenación anteriores
        header.classList.remove('sort-asc', 'sort-desc');

        // El índice (#) siempre es una columna especial que no se ordena por clave de dato
        if (key === 'index') {
            header.onclick = null;
            header.style.cursor = 'default';
            header.classList.remove('sort-asc', 'sort-desc');
            header.classList.add('no-sort');
            return;
        }
        
        header.classList.remove('no-sort');


        // Si la columna actual es la que está siendo ordenada, añadir la clase
        if (key === sortState.key) {
            header.classList.add(`sort-${sortState.direction}`);
        }

        // Asignar el evento de clic
        header.onclick = function() {
            let newDirection = 'asc';
            if (sortState.key !== key) {
                // Dirección predeterminada: ascendente para nombres (string), descendente para números (métricas)
                newDirection = (key === 'product_name' || key === 'supplier_name') ? 'asc' : 'desc'; // 🚨 CORREGIDO: Incluir supplier_name como ordenación alfabética por defecto.
            } else {
                newDirection = sortState.direction === 'asc' ? 'desc' : 'asc';
            }

            // Actualizar el estado global
            sortState.key = key;
            sortState.direction = newDirection;

            // Renderizar solo la tabla, el gráfico se maneja por su propia pestaña
            updateRankingTable(sortState.tab);
        };
        header.style.cursor = 'pointer';
    });
};

// --- Lógica de cálculo de stock a mostrar (Se aplica en ambos bucles) ---
const calculateStock = (productName, rawStock) => {
    const stockValue = parseFloat(rawStock) || 0;
    if (productName.includes('Granel')) {
        // Si es Granel, dividir el stock (que asumimos está en gramos) por 1000
        return stockValue / 1000;
    }
    // Si no es Granel, usar el valor tal cual (que asumimos está en unidades)
    return stockValue;
};

// --- Manejo de Eventos y Inicialización ---
document.addEventListener('DOMContentLoaded', function() {
    // 1. Inicializar la gráfica antes que la tabla
    initChart();

    // 2. Inicializar la tabla y el gráfico con la pestaña 'global' y el filtro de gráfico por defecto ('total_revenue')
    updateRankingTable('global');
    // La gráfica se renderiza con el filtro por defecto.
    updateRankingChart('global', chartSortKey); 


    // 3. Manejar los cambios de pestaña de la TABLA
    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', function() {
            const activeTab = this.getAttribute('data-tab');

            // a. Actualizar clases de pestaña de la TABLA
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            // b. Resetear el estado de ordenación de la TABLA para la nueva pestaña
            sortState.key = 'total_revenue';
            sortState.direction = 'desc';

            // Ordenar por nombre ascendente si es una pestaña de productos sin ventas.
            if (activeTab.startsWith('unsold')) {
                sortState.key = 'product_name';
                sortState.direction = 'asc';
            }

            // c. Renderizar Tabla y Gráfica (usando el filtro de gráfico actual: chartSortKey)
            updateRankingTable(activeTab);
            updateRankingChart(activeTab, chartSortKey);
        });
    });

    // 4. Establecer el evento de clic para las nuevas PESTAÑAS DEL GRÁFICO (si existen en el HTML)
    document.querySelectorAll('.chart-tab-button').forEach(button => {
        button.addEventListener('click', function() {
            // Se llama a la función global con el elemento clickeado y la métrica
            switchChartTab(this, this.getAttribute('data-chart-key'));
        });
    });

});
</script>

</body>
</html>