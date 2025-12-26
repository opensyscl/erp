<?php 
// Reporte de Errores 
/*error_reporting(E_ALL); 
ini_set('display_errors', 1);*/ 

// Incluye configuración de DB y Start Session 
require '../config.php'; 
session_start(); 

// Descomentar si la autenticación es requerida 
if (!isset($_SESSION['user_username'])) { 
	header('Location: ../login.php'); 
	exit(); 
} 

// --- CONFIGURACIÓN DE IVA (TASA BRUTA) --- 
// Tasa de IVA: 19% (0.19) 
// Factor de división para obtener el precio NETO: 1 + 0.19 = 1.19 
$IVA_RATE = 1.19; 

// Tasa de IVA: 19% (0.19) - Factor para obtener el IVA
$IVA_FACTOR = 0.19; 

// --- CONFIGURACIÓN DE FECHAS Y FILTROS --- 

// Variables de estado inicial/por defecto (mes actual) 
$selectedYearMonth = date('Y-m'); 
$selectedDateStart = ''; 
$selectedDateEnd = ''; 

// ----------------------------------------------------------------------
// --- NUEVA LÓGICA DE PRIORIDAD DE FILTRO ---
// ----------------------------------------------------------------------

// Variables de filtro finales para la consulta SQL
$startFilter = '';
$endFilter = '';

// 1. COMPROBACIÓN DE FILTRO POR MES (PRIORITARIO)
if (isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) { 
    $selectedYearMonth = $_GET['month'];

    // Calculamos el inicio y el fin del mes completo
    $startOfMonth = $selectedYearMonth . '-01'; 
    $date = new DateTime($startOfMonth); 
    $date->modify('+1 month'); 
    $startOfNextMonth = $date->format('Y-m-d'); 

    // Usamos el rango del mes
    $startFilter = $startOfMonth; 
    $endFilter = $startOfNextMonth; 
    
    // Limpiamos el rango de fechas para que no se muestre seleccionado en el UI
    $selectedDateStart = ''; 
    $selectedDateEnd = '';

} 
// 2. COMPROBACIÓN DE FILTRO POR RANGO (Secundario, solo si no hay mes)
else if (isset($_GET['date_start']) && isset($_GET['date_end']) && $_GET['date_start'] && $_GET['date_end']) { 
    $selectedDateStart = $_GET['date_start']; 
    $selectedDateEnd = $_GET['date_end']; 

    // Usamos las fechas del rango
    $startFilter = $selectedDateStart; 

    // El filtro de fin debe ser exclusivo (menor que el día siguiente)
    $dateEndInclusive = new DateTime($selectedDateEnd); 
    $dateEndInclusive->modify('+1 day'); 
    $endFilter = $dateEndInclusive->format('Y-m-d'); 

    // Limpiamos el mes seleccionado para que no interfiera y el select se vacíe
    $selectedYearMonth = ''; 
} 
// 3. POR DEFECTO (Mes actual)
else { 
    // Usamos el mes actual como defecto
    $selectedYearMonth = date('Y-m');
    $startOfMonth = $selectedYearMonth . '-01'; 
    $date = new DateTime($startOfMonth); 
    $date->modify('+1 month'); 
    $startOfNextMonth = $date->format('Y-m-d'); 

    // Usamos el rango del mes actual (calculado al inicio) 
    $startFilter = $startOfMonth; 
    $endFilter = $startOfNextMonth; 
} 

// ----------------------------------------------------------------------
// --- CÁLCULO DE DÍAS EN EL RANGO SELECCIONADO ---
// ----------------------------------------------------------------------

if ($selectedDateStart && $selectedDateEnd) { 
    // Calcular la diferencia en días del rango libre seleccionado
    $date1 = new DateTime($selectedDateStart); 
    $date2 = new DateTime($selectedDateEnd); 
    $interval = $date1->diff($date2); 
    $total_days_in_range = $interval->days + 1; // +1 para incluir el día de fin
} else if ($selectedYearMonth) { 
    // Si es por mes, usamos el total de días del mes
    $total_days_in_range = date('t', strtotime($startFilter)); 
} else {
    // Si no hay filtro, se asume el mes actual
    $total_days_in_range = date('t');
}
$total_days_in_month = $total_days_in_range; // Renombramos para usar en la etiqueta KPI

// Generar lista de meses para el selector (ej: Últimos 12 meses) 
$spanish_months = [ 
1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 
5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 
9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre' 
]; 

$months = []; 
$currentMonth = new DateTime(); 
// Aseguramos que el mes actual siempre esté en la lista 
$currentMonthFormat = $currentMonth->format('Y-m'); 
$currentMonthLabel = $spanish_months[(int)$currentMonth->format('n')] . ' ' . $currentMonth->format('Y'); 
$months[] = ['value' => $currentMonthFormat, 'label' => $currentMonthLabel]; 

// Retrocedemos 11 meses adicionales 
$currentMonth->modify('-1 month'); 
for ($i = 0; $i < 11; $i++) { 
	$month_number = (int)$currentMonth->format('n'); 
    $year_number = $currentMonth->format('Y'); 

    $month_name_capitalized = $spanish_months[$month_number] . ' ' . $year_number; 

	$months[] = [ 
		'value' => $currentMonth->format('Y-m'), 
		'label' => $month_name_capitalized 
	]; 
	$currentMonth->modify('-1 month'); 
} 
// ---------------------------------------------------------------------------------- 


// --- CÁLCULO DE MÉTRICAS (KPIs) DE UTILIDAD EN PHP (Usando el rango de fechas) --- 
// La consulta ahora usa $startFilter y $endFilter, que son los límites finales del rango seleccionado. 

$dateFilter = "s.created_at >= ? AND s.created_at < ?"; // Usamos < $endFilter (día siguiente o fecha fin + 1 día) 

// 1. Venta Mensual Total (Ingresos Brutos - Mantiene el IVA para reflejar el ingreso real) 
$stmt_monthly_sales = $pdo->prepare(" 
	SELECT SUM(si.price * si.quantity) AS monthly_sales 
	FROM sale_items si 
	JOIN sales s ON si.sale_id = s.id 
	WHERE {$dateFilter} 
"); 
$stmt_monthly_sales->execute([$startFilter, $endFilter]); 
$monthly_sales = $stmt_monthly_sales->fetchColumn() ?: 0; 

// 1b. Venta Mensual Neta (Ingresos sin IVA) 
$monthly_sales_net = $monthly_sales / $IVA_RATE; 

// AÑADIDO PARA LA RECONCILIACIÓN: Cálculo del IVA Recaudado 
$monthly_iva = $monthly_sales - $monthly_sales_net; // Venta Bruta - Venta Neta

// 2. Costo de Mercancía Vendida (CMV) Mensual Total (Asumiendo cost_price es NETO) 
$stmt_monthly_cmv = $pdo->prepare(" 
	SELECT SUM(p.cost_price * si.quantity) AS monthly_cmv 
	FROM sale_items si 
	JOIN sales s ON si.sale_id = s.id 
	JOIN products p ON si.product_id = p.id 
	WHERE {$dateFilter} 
"); 
$stmt_monthly_cmv->execute([$startFilter, $endFilter]); 
$monthly_cmv = $stmt_monthly_cmv->fetchColumn() ?: 0; 

// 3. Utilidad Bruta Mensual (Cálculo: Ingresos Netos - CMV) 
$monthly_profit = $monthly_sales_net - $monthly_cmv; 

// 4. Utilidad Bruta Diaria Promedio (del Mes) - CORREGIDO 
$stmt_daily_profit_average = $pdo->prepare(" 
	SELECT AVG(daily_profit) AS daily_profit_average 
	FROM ( 
		SELECT 
			DATE(s.created_at) AS sale_date, 
			-- Utilidad Diaria = (Precio Venta Neto - Costo Neto) * Cantidad 
			SUM(((si.price / {$IVA_RATE}) - p.cost_price) * si.quantity) AS daily_profit 
		FROM sale_items si 
		JOIN sales s ON si.sale_id = s.id 
		JOIN products p ON si.product_id = p.id 
		WHERE {$dateFilter} 
		GROUP BY sale_date 
	) AS subquery 
"); 
$stmt_daily_profit_average->execute([$startFilter, $endFilter]); 
$daily_profit_average = $stmt_daily_profit_average->fetchColumn() ?: 0; 

// 5. CMV Diario Promedio 
$stmt_daily_cmv_average = $pdo->prepare(" 
	SELECT AVG(daily_cmv) AS daily_cmv_average 
	FROM ( 
		SELECT 
			DATE(s.created_at) AS sale_date, 
			SUM(p.cost_price * si.quantity) AS daily_cmv 
		FROM sale_items si 
		JOIN sales s ON si.sale_id = s.id 
		JOIN products p ON si.product_id = p.id 
		WHERE {$dateFilter} 
		GROUP BY sale_date 
	) AS subquery 
"); 
$stmt_daily_cmv_average->execute([$startFilter, $endFilter]); 
$daily_cmv_average = $stmt_daily_cmv_average->fetchColumn() ?: 0; 


// 6. Proyección de Utilidad Mensual 
$monthly_profit_projection = $daily_profit_average * $total_days_in_month; 

// 7. Proyección de Capital de Inversión Mensual 
$monthly_cmv_projection = $daily_cmv_average * $total_days_in_month; 

// 8. Total de Transacciones Únicas 
$dateFilterSales = "created_at >= ? AND created_at < ?"; // NUEVO FILTRO PARA LA TABLA 'sales'
$stmt_total_transactions = $pdo->prepare(" 
SELECT COUNT(DISTINCT id) AS total_transactions 
FROM sales 
WHERE {$dateFilterSales} 
"); 
$stmt_total_transactions->execute([$startFilter, $endFilter]); 
$total_transactions = $stmt_total_transactions->fetchColumn() ?: 0; 

// ---------------------------------------------------------------------------------- 
// --- NUEVO CÁLCULO DE IVA PAGADO (CRÉDITO FISCAL) Y IVA F22 ---
// ----------------------------------------------------------------------------------


$dateFilterPurchases = "pi.created_at >= ? AND pi.created_at < ?"; // Filtro para tabla purchase_invoices

// 9. Costo de Compras NETO (Base Imponible de Crédito Fiscal)
// NOTA: ASUMIMOS una tabla 'purchase_invoices' (pi) con created_at para el filtro.
$stmt_purchased_net = $pdo->prepare("
	SELECT SUM(pii.new_cost_price * pii.quantity) AS purchased_net_base
	FROM purchase_invoice_items pii
	JOIN purchase_invoices pi ON pii.invoice_id = pi.id
	WHERE {$dateFilterPurchases}
");
$stmt_purchased_net->execute([$startFilter, $endFilter]);
$purchased_net_base = $stmt_purchased_net->fetchColumn() ?: 0;

// 10. IVA Pagado (Crédito Fiscal)
$monthly_iva_paid = $purchased_net_base * $IVA_FACTOR;

// 11. IVA F22 (Débito Fiscal - Crédito Fiscal)
$monthly_iva_f22 = $monthly_iva - $monthly_iva_paid;


// ----------------------------------------------------------------------------------
// --- CÁLCULO DE MÉTRICAS DEL DÍA ANTERIOR (KPIs) ---
// ----------------------------------------------------------------------------------

// 1. Calcular la fecha de inicio y fin para el filtro del día anterior.

// La fecha del día anterior (ej: si hoy es 03/11, será 02/11)
$yesterday = new DateTime();
$yesterday->modify('-1 day');
// Formato YYYY-MM-DD
$yesterdayStart = $yesterday->format('Y-m-d'); 
$yesterdayLabel = $yesterday->format('d/m/Y'); // Para la etiqueta del KPI

// El filtro de fin debe ser el inicio del día actual (exclusivo)
$today = new DateTime();
$yesterdayEnd = $today->format('Y-m-d'); 

// 2. Venta Total (Ingreso Bruto) del Día Anterior
$stmt_sales_yesterday = $pdo->prepare("
	SELECT SUM(si.price * si.quantity) AS sales_yesterday
	FROM sale_items si
	JOIN sales s ON si.sale_id = s.id
	WHERE {$dateFilter}
");
$stmt_sales_yesterday->execute([$yesterdayStart, $yesterdayEnd]);
$sales_yesterday = $stmt_sales_yesterday->fetchColumn() ?: 0;

// 3. CMV del Día Anterior (CMV_YESTERDAY)
$stmt_cmv_yesterday = $pdo->prepare("
    SELECT SUM(p.cost_price * si.quantity) AS cmv_yesterday
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN products p ON si.product_id = p.id
    WHERE {$dateFilter}
");
$stmt_cmv_yesterday->execute([$yesterdayStart, $yesterdayEnd]);
$cmv_yesterday = $stmt_cmv_yesterday->fetchColumn() ?: 0;

// 4. Utilidad Bruta del Día Anterior (PROFIT_YESTERDAY)
// Utilidad = (Venta Neta Diaria - CMV Diaria)
$stmt_profit_yesterday = $pdo->prepare("
    SELECT
        -- Utilidad = (Precio Venta Neto - Costo Neto) * Cantidad
        SUM(((si.price / {$IVA_RATE}) - p.cost_price) * si.quantity) AS profit_yesterday
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN products p ON si.product_id = p.id
    WHERE {$dateFilter}
");
$stmt_profit_yesterday->execute([$yesterdayStart, $yesterdayEnd]);
$profit_yesterday = $stmt_profit_yesterday->fetchColumn() ?: 0;


// --- DATOS PARA EL GRÁFICO DE TENDENCIA DIARIA --- 
// Nota: Para este gráfico, solo se deben mostrar los días entre $startFilter y $endFilter 
$stmt_daily_data = $pdo->prepare(" 
SELECT 
DATE(s.created_at) AS sale_date, 
SUM(si.price * si.quantity) AS daily_sales, -- BRUTO 
-- Utilidad = (Precio Venta Neto - Costo Neto) * Cantidad 
SUM(((si.price / {$IVA_RATE}) - p.cost_price) * si.quantity) AS daily_profit 
FROM sale_items si 
JOIN sales s ON si.sale_id = s.id 
JOIN products p ON si.product_id = p.id 
WHERE {$dateFilter} 
GROUP BY sale_date 
ORDER BY sale_date ASC 
"); 
$stmt_daily_data->execute([$startFilter, $endFilter]); 
$daily_data_full = $stmt_daily_data->fetchAll(PDO::FETCH_ASSOC); 

// --- OBTENER DATOS DETALLADOS POR ÍTEM VENDIDO PARA LA TABLA --- 
$stmt_sales_data = $pdo->prepare(" 
	SELECT 
		s.receipt_number, 
		s.method, 
		s.created_at, 
		p.name AS product_name, 
		si.quantity AS item_quantity, 
		-- Venta Bruta (Lo que paga el cliente) 
		(si.price * si.quantity) AS item_sale_price_bruto, 
		-- Venta Neta (Ingreso sin IVA) 
		(si.price * si.quantity / {$IVA_RATE}) AS item_sale_price_neto, 
		-- IVA (Venta Bruta - Venta Neta) 
		(si.price * si.quantity) - (si.price * si.quantity / {$IVA_RATE}) AS item_sale_iva, 
		-- CMV (Costo Neto) 
		(p.cost_price * si.quantity) AS item_cost_price, 
		-- Utilidad Bruta: (Venta Neta - CMV) 
		(((si.price / {$IVA_RATE}) - p.cost_price) * si.quantity) AS item_gross_profit 
	FROM sale_items si 
	JOIN sales s ON si.sale_id = s.id 
	JOIN products p ON si.product_id = p.id 
	WHERE {$dateFilter} 
	ORDER BY s.created_at DESC 
"); 
$stmt_sales_data->execute([$startFilter, $endFilter]); 
$sales_data_full = $stmt_sales_data->fetchAll(PDO::FETCH_ASSOC); 

// Variables para el encabezado 
$current_page = 'profit_analysis.php'; 
$stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'"); 
$stmt->execute(); 
$system_version = $stmt->fetchColumn(); 
?>
<!DOCTYPE html> 
<html lang="es"> 
<head> 
	<meta charset="UTF-8"> 
	<meta name="viewport" content="width=device-width, initial-scale=1.0"> 
	<title>Análisis de Utilidad y Reinversión - Mi Sistema</title> 
	<link rel="preconnect" href="https://fonts.googleapis.com"> 
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin> 
	<link rel="stylesheet" href="css/capital.css"> 
	<link rel="icon" type="image/png" href="/erp/img/fav.png"> 
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"> 
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script> 


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
    <a href="capital.php" class="active">Análisis de Utilidad</a>
    <a href="provcapital.php">Capital por Proveedor</a>
</nav>

		<div class="header-right"> 
			<span class="app-version"><?php echo htmlspecialchars($system_version); ?></span> 
			<a href="../logout.php" class="btn-logout">Cerrar Sesi&oacute;n</a> 
		</div> 
	</header> 

<main class="container"> 
	 
<div class="kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
		
	<div class="kpi-card">
		<h3>Venta Total (Ingreso Bruto)</h3>
		<p class="value"><?= number_format($monthly_sales, 0, ',', '.') ?></p>
	</div>

	<div class="kpi-card">
		<h3>Venta Neta (Ingreso Neto)</h3>
		<p class="value utility-projection"><?= number_format($monthly_sales_net, 0, ',', '.') ?></p>
	</div>
	
	<div class="kpi-card">
		<h3>IVA Recaudado (Ventas)</h3>
		<p class="value tax"><?= number_format($monthly_iva, 0, ',', '.') ?></p>
	</div>

	<div class="kpi-card">
		<h3>IVA Pagado (Compras)</h3>
		<p class="value tax-f22"><?= number_format($monthly_iva_paid, 0, ',', '.') ?></p>
	</div>

	<div class="kpi-card">
		<h3>IVA F22 (A Pagar/Crédito)</h3>
		<p class="value tax-f22"><?= number_format($monthly_iva_f22, 0, ',', '.') ?></p>
	</div>

	<div class="kpi-card">
		<h3>Transacciones del Período</h3>
		<p class="value transactions"><?= number_format($total_transactions, 0, ',', '.') ?></p>
	</div>
	
	<div class="kpi-card">
		<h3>Capital de Reinversi&oacute;n (CMV)</h3>
		<p class="value reinvestment"><?= number_format($monthly_cmv, 0, ',', '.') ?></p>
	</div>
	
	<div class="kpi-card">
		<h3>Capital de Operaciones</h3>
		<p class="value utility">
			<?= number_format($monthly_profit, 0, ',', '.') ?>
		</p>
	</div>
	
	<div class="kpi-card">
		<h3>Proyecci&oacute;n Capital de Inversi&oacute;n</h3>
		<p class="value reinvestment-projection"><?= number_format($monthly_cmv_projection, 0, ',', '.') ?></p>
	</div>

	<div class="kpi-card">
		<h3>Proyecci&oacute;n Utilidad (Días: <?= $total_days_in_month ?>)</h3>
		<p class="value projection"><?= number_format($monthly_profit_projection, 0, ',', '.') ?></p>
	</div>

    
    <div class="kpi-card">
        <h3>Utilidad Bruta (<?= $yesterdayLabel ?>)</h3>
        <p class="value utility">
            <?= number_format($profit_yesterday, 0, ',', '.') ?>
        </p>
    </div>

    <div class="kpi-card">
        <h3>CMV día anterior (<?= $yesterdayLabel ?>)</h3>
        <p class="value reinvestment">
            <?= number_format($cmv_yesterday, 0, ',', '.') ?>
        </p>
    </div>
</div>

	<div class="main-content-layout"> 
	 
		<div class="chart-row-top"> 
			 
			<div class="chart-column-proportion"> 
				 
				<div class="content-card chart-card"> 
					<h2>Proporci&oacute;n Utilidad sobre CMV</h2> 
					<div style="height: 300px;"> 
						<canvas id="profitChart"></canvas> 
					</div> 
					<div id="chart-legend" style="margin-top: 1.5rem; font-size: 0.9rem;"> 
					</div> 
				</div> 
			</div> 

			<div class="chart-column-daily"> 
				 
				<div class="content-card full-width-chart"> 
					<h2>Utilidad vs Venta Diaria (Rango Seleccionado)</h2> 
					<div style="height: 300px;"> 
						<canvas id="dailyProfitChart"></canvas> 
					</div> 
				</div> 
			</div> 
		</div> 

		<div class="table-column-full"> 
			 
			<div class="content-card table-card full-height-card"> 
				<div class="table-header-controls"> 
					<h2>Desglose de &Iacute;tems Vendidos</h2> 
					<form id="filter-form" class="table-controls" method="GET" action="capital.php"> 
						 
						<div class="month-selector-container"> 
							<label for="month-select">Mes:</label> 
							<select id="month-select" name="month" onchange="handleMonthChange(this)"> 
								<option value="">-- Seleccionar Mes --</option> 
								<?php foreach ($months as $month): ?> 
									<option 
										value="<?= htmlspecialchars($month['value']) ?>" 
										<?= ($selectedYearMonth === $month['value']) ? 'selected' : '' ?> 
									> 
										<?= htmlspecialchars($month['label']) ?> 
									</option> 
								<?php endforeach; ?> 
							</select> 
						</div> 

						<div class="date-range-controls"> 
							<label for="date-start">Desde:</label> 
							<input type="date" id="date-start" name="date_start" value="<?= htmlspecialchars($selectedDateStart) ?>"> 
							<label for="date-end">Hasta:</label> 
							<input type="date" id="date-end" name="date_end" value="<?= htmlspecialchars($selectedDateEnd) ?>"> 
						</div> 

						<button type="submit" id="apply-date-filter" class="btn-primary">Aplicar Rango</button> 
						 
						<label for="limit">Mostrar:</label> 
						<select id="limit"> 
							<option value="10">10</option> 
							<option value="25">25</option> 
							<option value="50">50</option> 
							<option value="all">Todas</option> 
						</select> 
					</form> 
				</div> 
				<div class="table-container"> 
					<table class="sales-table"> 
						<thead> 
							<tr> 
								<th>Fecha</th> 
								<th>Recibo</th> 
								<th>Producto</th> 
								<th style="text-align: right;">Cant.</th> 
								<th style="text-align: right;">Venta</th> 
								<th style="text-align: right;">Neto</th> 
								<th style="text-align: right;">IVA (Venta)</th> 
								<th style="text-align: right;">CMV</th> 
								<th style="text-align: right;">Util. Bruta</th> 
							</tr> 
						</thead> 
						<tbody id="sales-table-body"> 
							</tbody> 
					</table> 
				</div> 
			</div> 
		</div> 
	</div> 
</main>


	<script> 
		let profitChart; 
		let dailyProfitChart; 
		 
		// Se cargan todos los datos de ítems vendidos 
		const salesData = <?= json_encode($sales_data_full); ?>; 
		const dailyData = <?= json_encode($daily_data_full); ?>; 
		const monthlySales = <?= $monthly_sales; ?>; // BRUTO 
		const monthlySalesNet = <?= $monthly_sales_net; ?>; // NETO (para gráfico pie) 
		const monthlyCMV = <?= $monthly_cmv; ?>; 
		const monthlyProfit = <?= $monthly_profit; ?>; 
		 
		// Este valor ahora puede ser dinámico si se usa un rango de fechas. 
		const totalDaysInMonth = <?= $total_days_in_month; ?>; 
		const startDate = "<?= $startFilter ?>"; 
		const endDate = "<?= $endFilter ?>"; 

		/** * Formatea un número a moneda chilena (CLP) sin decimales. 
		 * @param {number} amount 
		 * @returns {string} 
		 */ 
		const formatCurrency = (amount) => { 
			const number = parseFloat(amount) || 0; 
			// Usamos el locale 'es-CL' para el formato CLP con separadores de miles 
			return number.toLocaleString('es-CL', { 
				style: 'currency', 
				currency: 'CLP', 
				minimumFractionDigits: 2, 
				maximumFractionDigits: 2 
			}); 
		}; 

		/** * Calcula el porcentaje de una parte respecto al total. 
		 * @param {number} part 
		 * @param {number} total 
		 * @returns {string} 
		 */ 
		const calculatePercentage = (part, total) => { 
			// Evitar división por cero 
			return total > 0 ? ((part / total) * 100).toFixed(1) : 0; 
		}; 

		/** * Inicializa o actualiza el gráfico de pastel. (Ahora usa VENTA NETA como total) 
		 */ 
		const updateProfitChart = () => { 
			const chartCanvas = document.getElementById('profitChart'); 
			const chartContainer = chartCanvas ? chartCanvas.parentNode : null; 
			 
			// Usamos Venta Neta como el 100% del total para el cálculo de la Proporción 
			const totalForChart = monthlySalesNet; 

			if (totalForChart <= 0) { 
				if (chartContainer) { 
					chartContainer.innerHTML = '<p style="text-align: center; margin-top: 2rem; color: var(--text-secondary);">No hay ventas registradas para el periodo seleccionado.</p>'; 
				} 
				document.getElementById('chart-legend').innerHTML = ''; 
				return; 
			} 
			 
			// Aseguramos que el canvas vuelva si se había reemplazado 
			if (!chartCanvas.getContext) { 
				const newCanvas = document.createElement('canvas'); 
				newCanvas.id = 'profitChart'; 
				chartContainer.innerHTML = ''; 
				chartContainer.appendChild(newCanvas); 
				chartCanvas = newCanvas; 
			} 


			// COLORES: Usamos las variables CSS para consistencia (Asegúrate que existen en :root) 
			const cmvColor = getComputedStyle(document.documentElement).getPropertyValue('--reinvestment-color').trim() || '#ffc107'; // Azul 
			const utilityColor = getComputedStyle(document.documentElement).getPropertyValue('--utility-color').trim() || '#28a745'; // Verde 

			// Los datos del gráfico son CMV y Utilidad Bruta 
			const chartData = [monthlyCMV, monthlyProfit]; 
			const chartLabels = ['Capital de Reinversión (CMV)', 'Utilidad Bruta - Capital de Operaciones']; 
			const chartColors = [cmvColor, utilityColor]; 

			// Los porcentajes se calculan sobre la VENTA NETA (monthlySalesNet) 
			const cmvPercentage = calculatePercentage(monthlyCMV, totalForChart); 
			const profitPercentage = calculatePercentage(monthlyProfit, totalForChart); 

			const ctx = chartCanvas.getContext('2d'); 

			if (profitChart) { 
				profitChart.data.datasets[0].data = chartData; 
				profitChart.update(); 
			} else { 
				profitChart = new Chart(ctx, { 
					type: 'pie', 
					data: { 
						labels: chartLabels, 
						datasets: [{ 
							data: chartData, 
							backgroundColor: chartColors, 
							hoverOffset: 10 
						}] 
					}, 
					options: { 
						responsive: true, 
						maintainAspectRatio: false, 
						plugins: { 
							legend: { 
								display: false 
							}, 
							tooltip: { 
								callbacks: { 
									label: function(context) { 
										const label = context.label || ''; 
										const value = context.parsed; 
										// Usamos el total NETO para el porcentaje 
										const percentage = calculatePercentage(value, totalForChart); 
										return `${label}: ${formatCurrency(value)} (${percentage}%)`; 
									} 
								} 
							} 
						} 
					} 
				}); 
			} 

			// Generar Leyenda Personalizada 
			const legendHtml = ` 
				<div style="display: flex; flex-wrap: wrap; justify-content: space-around; gap: 1rem;"> 
					<p style="color: ${cmvColor}; margin-bottom: 0;"> 
						<span style="font-weight: 600;">Reinversión:</span> ${formatCurrency(monthlyCMV)} (${cmvPercentage}%) 
					</p> 
					<p style="color: ${utilityColor}; margin-bottom: 0;"> 
						<span style="font-weight: 600;">Utilidad:</span> ${formatCurrency(monthlyProfit)} (${profitPercentage}%) 
					</p> 
				</div> 
			`; 
			document.getElementById('chart-legend').innerHTML = legendHtml; 
		}; 

		/** * NUEVO GRÁFICO: Tendencia de Utilidad y Venta Diaria 
		 */ 
		const updateDailyChart = () => { 
			const chartCanvas = document.getElementById('dailyProfitChart'); 
			if (!chartCanvas) return; 
			const ctx = chartCanvas.getContext('2d'); 

			// --- 1. Calcular el número total de días en el rango seleccionado --- 
			const start = new Date(startDate); 
			const end = new Date(endDate); 
			// El PHP ya calcula la diferencia, pero lo recalculo para el etiquetado 
			const diffTime = Math.abs(end.getTime() - start.getTime()); 
			const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 
			const totalDays = diffDays > 0 ? diffDays : 1; // Mínimo 1 día si las fechas son iguales. 

			// --- 2. Preparar datos para el rango --- 
			const profitMap = {}; 
			const salesMap = {}; 
			 
			// Llenar mapas con datos existentes, mapeados por fecha completa 
			dailyData.forEach(item => { 
				const dateKey = item.sale_date; // YYYY-MM-DD 
				profitMap[dateKey] = parseFloat(item.daily_profit); 
				salesMap[dateKey] = parseFloat(item.daily_sales); 
			}); 

			const labels = []; 
			const profitData = []; 
			const salesDataChart = []; 
			 
			let currentDate = new Date(startDate); 
			let dayCount = 0; 

			// Iterar sobre todos los días del rango para asegurar el eje X completo 
			while (currentDate.getTime() < end.getTime()) { 
				const dateKey = currentDate.toISOString().split('T')[0]; // Formato YYYY-MM-DD 
				 
				labels.push(dateKey); // La etiqueta es la fecha 
				profitData.push(profitMap[dateKey] || 0); 
				salesDataChart.push(salesMap[dateKey] || 0); 

				// Avanzar al siguiente día 
				currentDate.setDate(currentDate.getDate() + 1); 
				dayCount++; 
				if (dayCount > totalDays + 1) break; // Límite de seguridad 
			} 

			// COLORES 
			const utilityColor = getComputedStyle(document.documentElement).getPropertyValue('--utility-color').trim() || 'rgba(40, 167, 69, 0.8)'; // Verde 
			const salesColor = getComputedStyle(document.documentElement).getPropertyValue('--sales-color').trim() || 'rgba(0, 123, 255, 0.5)'; // Azul claro 

			if (dailyProfitChart) { 
				dailyProfitChart.data.labels = labels; 
				dailyProfitChart.data.datasets[0].data = profitData; 
				dailyProfitChart.data.datasets[1].data = salesDataChart; 
				dailyProfitChart.update(); 
			} else { 
				dailyProfitChart = new Chart(ctx, { 
					type: 'bar', 
					data: { 
						labels: labels, 
						datasets: [ 
							{ 
								label: 'Utilidad Bruta Diaria (NETA)', 
								data: profitData, 
								borderColor: utilityColor.replace('0.8', '1'), // Línea opaca 
								backgroundColor: utilityColor, 
								type: 'line', // Es una línea para destacar la tendencia 
								yAxisID: 'y-profit', 
								tension: 0.2, 
								pointRadius: 3 
							}, 
							{ 
								label: 'Venta Total Diaria (BRUTA)', 
								data: salesDataChart, 
								backgroundColor: salesColor, 
								yAxisID: 'y-sales', 
							} 
						] 
					}, 
					options: { 
						responsive: true, 
						maintainAspectRatio: false, 
						interaction: { 
							mode: 'index', 
							intersect: false, 
						}, 
						scales: { 
							x: { 
								title: { 
									display: true, 
									text: 'Fecha' 
								}, 
								// Si hay muchos días, rotar las etiquetas o usar un adaptador de fecha 
								ticks: { 
									autoSkip: true, 
									maxRotation: 45, 
									minRotation: 45 
								} 
							}, 
							'y-profit': { 
								type: 'linear', 
								display: true, 
								position: 'left', 
								title: { 
									display: true, 
									text: 'Utilidad (CLP)' 
								}, 
								// Formato de ticks para utilidad 
								ticks: { 
									callback: function(value, index, ticks) { 
										return formatCurrency(value); 
									} 
								} 
							}, 
							'y-sales': { 
								type: 'linear', 
								display: true, 
								position: 'right', 
								title: { 
									display: true, 
									text: 'Venta (CLP)' 
								}, 
								grid: { 
									drawOnChartArea: false, // Solo dibujar la cuadrícula para el eje izquierdo 
								}, 
								// Formato de ticks para venta 
								ticks: { 
									callback: function(value, index, ticks) { 
										return formatCurrency(value); 
									} 
								} 
							} 
						}, 
						plugins: { 
							tooltip: { 
								callbacks: { 
									label: function(context) { 
										let label = context.dataset.label || ''; 
										if (label) { 
											label += ': '; 
										} 
										return label + formatCurrency(context.parsed.y); 
									} 
								} 
							} 
						} 
					} 
				}); 
			} 
		}; 

		/** * Actualiza la tabla de transacciones de ventas con el límite seleccionado. 
		 */ 
		const updateTable = () => { 
			const limit = document.getElementById('limit').value; 
			const tableBody = document.getElementById('sales-table-body'); 
			tableBody.innerHTML = ''; // Limpia la tabla 

			let finalData; 
			if (limit === 'all') { 
				finalData = salesData; 
			} else { 
				finalData = salesData.slice(0, parseInt(limit)); 
			} 

			if (!finalData || finalData.length === 0) { 
				tableBody.innerHTML = `<tr><td colspan="9" style="text-align: center; padding: 2rem; color: var(--text-secondary);">No hay ítems vendidos registrados.</td></tr>`; 
				return; 
			} 

			finalData.forEach(item => { 
				const row = document.createElement('tr'); 

				// Formateo de Fecha 
				const saleDate = new Date(item.created_at); 
				// El formato YYYY-MM-DD HH:MM:SS de MySQL lo convierte a una fecha local de JS 
				const formattedDate = `${String(saleDate.getDate()).padStart(2, '0')}-${String(saleDate.getMonth() + 1).padStart(2, '0')}-${saleDate.getFullYear()} ${String(saleDate.getHours()).padStart(2, '0')}:${String(saleDate.getMinutes()).padStart(2, '0')}`; 

				// Variables 
				const itemSalePriceBruto = parseFloat(item.item_sale_price_bruto); 
				const itemSalePriceNeto = parseFloat(item.item_sale_price_neto); 
				const itemSaleIVA = parseFloat(item.item_sale_iva); 
				const itemCostPrice = parseFloat(item.item_cost_price); 
				const itemGrossProfit = parseFloat(item.item_gross_profit); 
				const itemQuantity = parseInt(item.item_quantity); 


				// Calcular el porcentaje de utilidad (Margen Bruto) sobre la VENTA NETA 
				const profitPercent = calculatePercentage(itemGrossProfit, itemSalePriceNeto); 

				row.innerHTML = ` 
					<td>${formattedDate}</td> 
					<td>${item.receipt_number}</td> 
					<td><strong>${item.product_name}</strong></td> 
					<td style="text-align: right;">${itemQuantity}</td> 
					<td style="text-align: right;">${formatCurrency(itemSalePriceBruto)}</td> 
					<td style="text-align: right; color: var(--sales-color); font-weight: 500;">${formatCurrency(itemSalePriceNeto)}</td> 
					<td style="text-align: right; color: var(--reinvestment-color); font-weight: 400;">${formatCurrency(itemSaleIVA)}</td> 
					<td style="text-align: right;">${formatCurrency(itemCostPrice)}</td>
					<td style="color: var(--utility-color); font-weight: 600; text-align: right;"> 
						${formatCurrency(itemGrossProfit)} <span style="font-size: 0.8em; font-weight: 400; color: var(--text-secondary);">(${profitPercent}%)</span> 
					</td> 
				`; 
				tableBody.appendChild(row); 
			}); 
		}; 
		
		/**
		 * Handler para cuando el usuario selecciona un mes.
		 * Limpia los campos de rango y envía el formulario.
		 * @param {HTMLSelectElement} selectElement
		 */
		const handleMonthChange = (selectElement) => {
			// Prioridad: Si seleccionas un mes, anulas el rango.
			document.getElementById('date-start').value = '';
			document.getElementById('date-end').value = '';
			
			// Solo enviar si se ha seleccionado un valor (no la opción por defecto)
			if (selectElement.value !== '') {
				selectElement.form.submit();
			} else {
				// Si se selecciona la opción "-- Seleccionar Mes --", 
				// dejamos que el submit actúe, limpiando todos los filtros.
				selectElement.form.submit();
			}
		};


		/** * Controla el envío del formulario de filtros. 
		 */ 
		const handleFilterSubmit = (event) => { 
			const form = document.getElementById('filter-form'); 
			const monthSelect = document.getElementById('month-select'); 
			const dateStart = document.getElementById('date-start').value; 
			const dateEnd = document.getElementById('date-end').value; 

			// Este handler solo se activa al presionar el botón "Aplicar Rango" o por un submit general.
			// La selección de mes tiene su propio onchange/submit (handleMonthChange)

			// Si hay un rango de fechas incompleto y se intenta enviar
			if ((dateStart && !dateEnd) || (!dateStart && dateEnd)) {
				console.error('Por favor, selecciona tanto la fecha de inicio como la de fin para aplicar el rango.'); 
				event.preventDefault(); 
				return;
			}
			
			// Si hay fechas válidas en los campos de rango
			if (dateStart && dateEnd) {
				// Si se intenta enviar un rango, se debe anular el filtro de mes.
				// Esto asegura que al presionar 'Aplicar Rango', se borre la selección de mes
				// ANTES de que el formulario se envíe con los parámetros de rango.
				monthSelect.value = '';
			} 
			
			// Si no hay rango y el mes está vacío, se enviará el formulario sin filtros,
			// lo cual PHP maneja como el mes actual (por defecto).
			
			// El formulario se envía si pasa las validaciones (o si no hay filtros).
		}; 

		// Event Listeners y Carga Inicial 
		document.addEventListener('DOMContentLoaded', function() { 
			updateProfitChart(); 
			updateDailyChart(); 
			updateTable(); 

			// Event listener para el filtro de la tabla (por JS) 
			document.getElementById('limit').addEventListener('change', updateTable); 
			 
			// Event listener para el formulario (para el botón Aplicar Rango) 
			document.getElementById('filter-form').addEventListener('submit', handleFilterSubmit); 
			 
			// Si se cargó con un filtro de rango (por PHP), nos aseguramos que el select de mes no tenga nada.
			// Esto solo es necesario si se hubiera forzado a través de la URL sin usar el UI.
			const phpSelectedDateStart = '<?= $selectedDateStart ?>';
			const phpSelectedDateEnd = '<?= $selectedDateEnd ?>';
			if (phpSelectedDateStart || phpSelectedDateEnd) { 
				document.getElementById('month-select').value = ''; 
			}
		}); 
	</script> 
</body> 

</html>
