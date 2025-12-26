<?php
// Reporte de Errores (Mantener comentado en producción)
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
$IVA_RATE = 1.19;
$IVA_FACTOR = 0.19; // 19%

// --- CONFIGURACIÓN DE FECHAS Y FILTROS (Reutilizada) ---

// Variables de estado inicial/por defecto (mes actual)
$selectedYearMonth = date('Y-m');
$selectedDateStart = '';
$selectedDateEnd = '';

// Variables de filtro finales para la consulta SQL
$startFilter = '';
$endFilter = '';

// Lógica de Prioridad de Filtro (Igual que capital.php)
if (isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
    $selectedYearMonth = $_GET['month'];
    $startOfMonth = $selectedYearMonth . '-01';
    $date = new DateTime($startOfMonth);
    $date->modify('+1 month');
    $startOfNextMonth = $date->format('Y-m-d');
    $startFilter = $startOfMonth;
    $endFilter = $startOfNextMonth;
    $selectedDateStart = '';
    $selectedDateEnd = '';
}
else if (isset($_GET['date_start']) && isset($_GET['date_end']) && $_GET['date_start'] && $_GET['date_end']) {
    $selectedDateStart = $_GET['date_start'];
    $selectedDateEnd = $_GET['date_end'];
    $startFilter = $selectedDateStart;
    $dateEndInclusive = new DateTime($selectedDateEnd);
    $dateEndInclusive->modify('+1 day');
    $endFilter = $dateEndInclusive->format('Y-m-d');
    $selectedYearMonth = '';
}
else {
    $selectedYearMonth = date('Y-m');
    $startOfMonth = $selectedYearMonth . '-01';
    $date = new DateTime($startOfMonth);
    $date->modify('+1 month');
    $startOfNextMonth = $date->format('Y-m-d');
    $startFilter = $startOfMonth;
    $endFilter = $startOfNextMonth;
}

// Cálculo de días en el rango seleccionado (Reutilizada)
if ($selectedDateStart && $selectedDateEnd) {
    $date1 = new DateTime($selectedDateStart);
    $date2 = new DateTime($selectedDateEnd);
    $interval = $date1->diff($date2);
    $total_days_in_range = $interval->days + 1;
} else if ($selectedYearMonth) {
    $total_days_in_range = date('t', strtotime($startFilter));
} else {
    $total_days_in_range = date('t');
}
$total_days_in_month = $total_days_in_range;


// Generar lista de meses para el selector (Reutilizada)
$spanish_months = [
1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
$months = [];
$currentMonth = new DateTime();
$currentMonthFormat = $currentMonth->format('Y-m');
$currentMonthLabel = $spanish_months[(int)$currentMonth->format('n')] . ' ' . $currentMonth->format('Y');
$months[] = ['value' => $currentMonthFormat, 'label' => $currentMonthLabel];
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
// --- CONSULTA 1: VENTAS Y UTILIDAD (IVA RECAUDADO) por Proveedor ---
// ----------------------------------------------------------------------------------

$dateFilterSales = "s.created_at >= ? AND s.created_at < ?"; 

$stmt_supplier_metrics = $pdo->prepare("
    SELECT
        sup.id AS supplier_id,
        sup.name AS supplier_name,
        -- Venta Bruta (Ingresos Brutos)
        SUM(si.price * si.quantity) AS total_sales_bruto,
        -- Venta Neta (Ingresos sin IVA)
        SUM((si.price * si.quantity) / {$IVA_RATE}) AS total_sales_neto,
        -- CMV (Costo Neto)
        SUM(p.cost_price * si.quantity) AS total_cmv,
        -- Utilidad Neta: (Venta Neta - CMV)
        SUM(((si.price / {$IVA_RATE}) - p.cost_price) * si.quantity) AS total_net_profit
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN products p ON si.product_id = p.id
    JOIN suppliers sup ON p.supplier_id = sup.id
    WHERE {$dateFilterSales}
    GROUP BY sup.id, sup.name
    ORDER BY total_net_profit DESC
");
$stmt_supplier_metrics->execute([$startFilter, $endFilter]);
$supplier_metrics = $stmt_supplier_metrics->fetchAll(PDO::FETCH_ASSOC);


// ----------------------------------------------------------------------------------
// --- CONSULTA 2: COMPRAS (IVA COMPRA) por Proveedor ---
// ----------------------------------------------------------------------------------

$dateFilterPurchases = "pi.date >= ? AND pi.date < ?"; 

$stmt_purchase_iva = $pdo->prepare("
    SELECT
        pi.supplier_id,
        -- IVA Compra: Total Bruto - Total Neto (Total Bruto / 1.19)
        SUM(pi.total_amount - (pi.total_amount / {$IVA_RATE})) AS total_purchase_iva 
    FROM purchase_invoices pi
    WHERE {$dateFilterPurchases}
    GROUP BY pi.supplier_id
");
$stmt_purchase_iva->execute([$startFilter, $endFilter]);
$purchase_ivas = $stmt_purchase_iva->fetchAll(PDO::FETCH_KEY_PAIR); // supplier_id => total_purchase_iva


// ----------------------------------------------------------------------------------
// --- CONSOLIDACIÓN DE DATOS Y CÁLCULO DE TOTALES GENERALES Y NUEVOS KPIs ---
// ----------------------------------------------------------------------------------

// Inicializar totales
$overall_total_sales_bruto = 0;
$overall_total_sales_neto = 0;
$overall_total_cmv = 0;
$overall_total_net_profit = 0;
$overall_total_iva_recaudado = 0;
$overall_total_purchase_iva = 0; // Total de IVA Compra (Crédito Fiscal)
$overall_total_real_iva = 0; 

// Nuevos KPIs de IVA por proveedor
$count_suppliers_with_credit = 0; 
$count_suppliers_to_pay = 0;      
$total_credit_fiscal_remanente = 0; 
$overall_iva_recaudado_suppliers_to_pay = 0; 

// Consolidar y calcular métricas por proveedor
foreach ($supplier_metrics as $index => &$metric) {
    $supplier_id = $metric['supplier_id'];

    $metric['total_sales_bruto'] = (float)$metric['total_sales_bruto'];
    $metric['total_sales_neto'] = (float)$metric['total_sales_neto'];
    $metric['total_cmv'] = (float)$metric['total_cmv'];
    $metric['total_net_profit'] = (float)$metric['total_net_profit'];
    
    // Calcular IVA Recaudado
    $metric['total_iva_recaudado'] = $metric['total_sales_bruto'] - $metric['total_sales_neto'];
    
    // Obtener y almacenar IVA Compra (Crédito Fiscal)
    $metric['total_purchase_iva'] = (float)($purchase_ivas[$supplier_id] ?? 0);

    // Calcular la métrica IVA Real a Pagar (IVA Recaudado - IVA Compra)
    $metric['total_real_iva'] = $metric['total_iva_recaudado'] - $metric['total_purchase_iva'];
    
    // --- Lógica para los Nuevos KPIs ---
    if ($metric['total_real_iva'] < 0) {
        $count_suppliers_with_credit++;
        $total_credit_fiscal_remanente += abs($metric['total_real_iva']);
    } else {
        $count_suppliers_to_pay++;
        $overall_iva_recaudado_suppliers_to_pay += $metric['total_iva_recaudado']; 
    }

    // Sumar a los totales generales
    $overall_total_sales_bruto += $metric['total_sales_bruto'];
    $overall_total_sales_neto += $metric['total_sales_neto'];
    $overall_total_cmv += $metric['total_cmv'];
    $overall_total_net_profit += $metric['total_net_profit'];
    $overall_total_iva_recaudado += $metric['total_iva_recaudado'];
    $overall_total_purchase_iva += $metric['total_purchase_iva']; // Sumar total de IVA Compra
}
unset($metric);

// Cálculo final del IVA Real a Pagar Total (Consolidado de todos los proveedores)
$overall_total_real_iva = $overall_total_iva_recaudado - $overall_total_purchase_iva;

// Cálculo del Nuevo KPI: IVA Total Final a Pagar
$iva_total_final_a_pagar = max(0, $overall_total_real_iva); 


// ----------------------------------------------------------------------------------
// --- DATOS PARA EL GRÁFICO DE PROPORCIÓN DE UTILIDAD POR PROVEEDOR (sin cambios) ---
// ----------------------------------------------------------------------------------

$chart_data = [];

// Pre-procesar datos para el gráfico y la tabla
foreach ($supplier_metrics as &$metric) {
    $profit = $metric['total_net_profit'];
    $net_sales = $metric['total_sales_neto'];

    // 1. Cálculo de Margen Neto del Proveedor
    $metric['margin_percent'] = ($net_sales > 0) ? ($profit / $net_sales) * 100 : 0;

    // 2. Cálculo de Contribución a la Utilidad Total 
    $metric['contribution_percent'] = ($overall_total_net_profit > 0) ? ($profit / $overall_total_net_profit) * 100 : 0;

    // Agregar datos al array para el gráfico de torta (solo si la utilidad es positiva)
    if ($profit > 0) {
        $chart_data[] = [
            'label' => $metric['supplier_name'],
            'value' => $profit
        ];
    }
}
unset($metric); // Romper referencia para evitar efectos secundarios

// Variables para el encabezado (Reutilizadas)
$current_page = 'provcapital.php';
$stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
$stmt->execute();
$system_version = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Análisis de Capital por Proveedor - Mi Sistema</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/provcapital.css">
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
<a href="capital.php">Análisis de Utilidad</a>
    <a href="provcapital.php" class="active">Capital por Proveedor</a>
</nav>

<div class="header-right">
<span class="app-version"><?php echo htmlspecialchars($system_version); ?></span>
<a href="../logout.php" class="btn-logout">Cerrar Sesi&oacute;n</a>
</div>
</header>

<main class="container">

<div class="kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));">
    <div class="kpi-card kpi-card-total">
        <h3>Venta Neta Total</h3>
        <p class="value utility-projection"><?= number_format($overall_total_sales_neto, 0, ',', '.') ?></p>
    </div>

    <div class="kpi-card kpi-card-total">
        <h3>CMV Total (Reinversión)</h3>
        <p class="value reinvestment"><?= number_format($overall_total_cmv, 0, ',', '.') ?></p>
    </div>

    <div class="kpi-card kpi-card-total">
        <h3>Utilidad Neta Total</h3>
        <p class="value utility"><?= number_format($overall_total_net_profit, 0, ',', '.') ?></p>
    </div>

    <div class="kpi-card kpi-card-total">
        <h3>Días del Período</h3>
        <p class="value transactions"><?= $total_days_in_month ?></p>
    </div>

    <div class="kpi-card kpi-card-total" style="grid-column: span 1;">
        <h3>Proveedores con Crédito</h3> 
        <p class="value" style="color: var(--reinvestment-color); font-weight: 600;"><?= number_format($count_suppliers_with_credit, 0, ',', '.') ?></p>
    </div>
    
    <div class="kpi-card kpi-card-total" style="grid-column: span 1;">
        <h3>Total Crédito Fiscal (Remanente)</h3> 
        <p class="value" style="color: var(--reinvestment-color); font-weight: 600;">-<?= number_format($total_credit_fiscal_remanente, 0, ',', '.') ?></p>
    </div>

    <div class="kpi-card kpi-card-total" style="grid-column: span 1;">
        <h3>IVA Total Final a Pagar</h3> 
        <p class="value" style="color: <?= $iva_total_final_a_pagar > 0 ? 'var(--utility-color)' : '#999' ?>; font-weight: 600;">
            <?= number_format($iva_total_final_a_pagar, 0, ',', '.') ?>
        </p>
    </div>
    
    <div class="kpi-card kpi-card-total" style="grid-column: span 1;">
        <h3>IVA Recaudado Total</h3> 
        <p class="value" style="color: #ffc107;"><?= number_format($overall_total_iva_recaudado, 0, ',', '.') ?></p>
    </div>

</div>

<div class="main-content-layout">

    <div class="chart-row-top">

        <div class="chart-column-proportion">

            <div class="content-card chart-card">
                <h2>Contribución de Utilidad Neta por Proveedor</h2>
                <div style="height: 300px;">
                    <canvas id="supplierProfitChart"></canvas>
                </div>
                <div id="chart-legend" style="margin-top: 1.5rem; font-size: 0.9rem;">
                </div>
            </div>
        </div>

        <div class="chart-column-daily">

            <div class="content-card full-width-chart">
                <h2>Top 5 Proveedores por Capital Generado</h2>
                <div style="height: 300px;">
                    <canvas id="topSupplierChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="table-column-full">

        <div class="content-card table-card full-height-card">
            <div class="table-header-controls">
                <h2>Desglose de Capital por Proveedor</h2>
                <form id="filter-form" class="table-controls" method="GET" action="provcapital.php">
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

                    <button type="submit" id="apply-date-filter" class="btn-primary">Aplicar Filtro</button>
                </form>
            </div>
            <div class="table-container">
                <table class="sales-table">
                    <thead>
                        <tr>
                            <th>Proveedor</th>
                            <th style="text-align: right;">Venta Bruta</th>
                            <th style="text-align: right;">Venta Neta</th>
                            <th style="text-align: right; color: #ffc107;">IVA (Débito)</th>
                            <th style="text-align: right; color: var(--reinvestment-color);">IVA Crédito</th>
                            <th style="text-align: right; color: #17a2b8;">IVA a Pagar</th>
                            <th style="text-align: right;">CMV</th>
                            <th style="text-align: right;">Utilidad Neta</th>
                            <th style="text-align: right;">Margen (%)</th>
                            <th style="text-align: right;">Contribución (%)</th>
                        </tr>
                    </thead>
                    <tbody id="supplier-table-body">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</main>


<script>

  // Variables de PHP inyectadas
  const supplierMetrics = <?= json_encode($supplier_metrics); ?>;
  const chartData = <?= json_encode($chart_data); ?>; 
  const overallTotalNetProfit = <?= $overall_total_net_profit; ?>;
  const overallTotalCMV = <?= $overall_total_cmv; ?>;
  const overallTotalSalesNeto = <?= $overall_total_sales_neto; ?>;
  const overallTotalIVARecaudado = <?= $overall_total_iva_recaudado; ?>;
  const overallTotalRealIVA = <?= $overall_total_real_iva; ?>; 


  let supplierProfitChart;
  let topSupplierChart;


  /**
  * Formatea un número a moneda chilena (CLP).
  */
  const formatCurrency = (amount) => {
    const number = parseFloat(amount) || 0;
    return number.toLocaleString('es-CL', {
      style: 'currency',
      currency: 'CLP',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0 // Sin decimales
    });
  };


  /**
  * Calcula el porcentaje de una parte respecto al total.
  */
  const calculatePercentage = (part, total) => {
    return total > 0 ? ((part / total) * 100).toFixed(1) : 0.0;
  };


  /**
  * Genera colores dinámicos.
  */
  const generateColors = (num) => {
    const colors = [];
    // Colores de base para evitar el blanco
    const baseColors = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#17a2b8', '#6610f2', '#fd7e14', '#20c997', '#e83e8c', '#6f42c1'];
    for (let i = 0; i < num; i++) {
      colors.push(baseColors[i % baseColors.length]);
    }
    return colors;
  };


  /**
  * Inicializa o actualiza el gráfico de pastel de Contribución de Utilidad.
  */
  const updateSupplierProfitChart = () => {
    const chartCanvas = document.getElementById('supplierProfitChart');
    const chartContainer = chartCanvas ? chartCanvas.parentNode : null;

    if (chartData.length === 0) {
      if (chartContainer) {
        chartContainer.innerHTML = '<p style="text-align: center; margin-top: 2rem; color: var(--text-secondary);">No hay utilidad registrada para el periodo seleccionado.</p>';
      }
      document.getElementById('chart-legend').innerHTML = '';
      return;
    }

    const labels = chartData.map(item => item.label);
    const dataValues = chartData.map(item => item.value);
    const colors = generateColors(labels.length);

    const ctx = chartCanvas.getContext('2d');

    if (supplierProfitChart) {
      supplierProfitChart.data.labels = labels;
      supplierProfitChart.data.datasets[0].data = dataValues;
      supplierProfitChart.data.datasets[0].backgroundColor = colors;
      supplierProfitChart.update();
    } else {
      supplierProfitChart = new Chart(ctx, {
        type: 'doughnut', 
        data: {
          labels: labels,
          datasets: [{
            data: dataValues,
            backgroundColor: colors,
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
                  const percentage = calculatePercentage(value, overallTotalNetProfit);
                  return `${label}: ${formatCurrency(value)} (${percentage}%)`;
                }
              }
            }
          }
        }
      });
    }

    // Generar Leyenda Personalizada (Top 5)
    const legendHtml = chartData
      .sort((a, b) => b.value - a.value)
      .slice(0, 5) 
      .map((item, index) => {
        const profit = item.value;
        const totalValidProfit = chartData.reduce((sum, current) => sum + current.value, 0); 
        const percentage = calculatePercentage(profit, totalValidProfit); 
        const color = colors[labels.indexOf(item.label)] || 'grey';
        return `<p style="color: ${color}; margin-bottom: 0;">
          <span style="font-weight: 600;">${item.label}:</span> ${formatCurrency(profit)} (${percentage}%)
        </p>`;
      }).join('');

    document.getElementById('chart-legend').innerHTML = `<div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 1rem;">${legendHtml}</div>`;
  };


  /**
  * Inicializa el gráfico de barras del Top 5 de Capital Generado.
  * Muestra 5 barras: Venta Bruta, Utilidad Neta, IVA Recaudado, IVA Compra, IVA Real a Pagar.
  */
  const updateTopSupplierChart = () => {
    const chartCanvas = document.getElementById('topSupplierChart');
    if (!chartCanvas) return;
    const ctx = chartCanvas.getContext('2d');

    // Obtener el Top 5 por utilidad (total_net_profit)
    const topFive = [...supplierMetrics]
      .sort((a, b) => b.total_net_profit - a.total_net_profit)
      .slice(0, 5);

    if (topFive.length === 0) {
      chartCanvas.parentNode.innerHTML = '<p style="text-align: center; margin-top: 2rem; color: var(--text-secondary);">No hay datos de proveedores para el Top 5.</p>';
      return;
    }


    const labels = topFive.map(item => item.supplier_name);
    // Datos para las 5 barras
    const salesBrutoData = topFive.map(item => parseFloat(item.total_sales_bruto)); 
    const profitData = topFive.map(item => parseFloat(item.total_net_profit)); 
    const ivaRecaudadoData = topFive.map(item => parseFloat(item.total_iva_recaudado));
    const ivaCompraData = topFive.map(item => parseFloat(item.total_purchase_iva)); // NUEVO DATO
    const ivaRealData = topFive.map(item => parseFloat(item.total_real_iva)); 


    // Colores: Se usan variables CSS o un fallback
    const salesBrutoColor = '#3b82f6'; // Blue
    const profitColor = getComputedStyle(document.documentElement).getPropertyValue('--utility-color')?.trim() || '#10b981'; // Green
    const ivaRecaudadoColor = '#ffc107'; // Yellow/Orange
    const ivaCompraColor = getComputedStyle(document.documentElement).getPropertyValue('--reinvestment-color')?.trim() || '#f97316'; // Orange/Red - Usando el color de reinversión que suele ser rojo/naranja
    const ivaRealColor = '#17a2b8'; // Cyan


    // Destruir el gráfico si existe para asegurar la recreación con la nueva configuración
    if (topSupplierChart) {
      topSupplierChart.destroy();
      topSupplierChart = null;
    }
    
    // Crear el nuevo gráfico (5 datasets)
    topSupplierChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Venta Bruta', 
            data: salesBrutoData,
            backgroundColor: salesBrutoColor,
            yAxisID: 'y-axis-main', 
            order: 5 // Venta Bruta (más grande) va al fondo
          },
          {
            label: 'IVA Recaudado (Débito)', 
            data: ivaRecaudadoData,
            backgroundColor: ivaRecaudadoColor,
            yAxisID: 'y-axis-main',
            order: 4
          },
          {
            label: 'IVA Compra (Crédito)', // NUEVA BARRA
            data: ivaCompraData,
            backgroundColor: ivaCompraColor,
            yAxisID: 'y-axis-main',
            order: 3
          },
          {
            label: 'IVA Real a Pagar', 
            data: ivaRealData,
            backgroundColor: ivaRealColor,
            yAxisID: 'y-axis-main',
            order: 2
          },
          {
            label: 'Utilidad Neta',
            data: profitData,
            backgroundColor: profitColor,
            yAxisID: 'y-axis-main',
            order: 1 // Utilidad (más importante) al frente
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: {
            stacked: false, 
            title: {
              display: true,
              text: 'Proveedor'
            }
          },
          'y-axis-main': {
            type: 'linear',
            display: true,
            position: 'left',
            title: {
              display: true,
              text: 'Monto (CLP)' 
            },
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
  };


  /**
  * Actualiza la tabla de métricas por proveedor.
  */
  const updateTable = () => {
    const tableBody = document.getElementById('supplier-table-body');
    tableBody.innerHTML = ''; 

    if (!supplierMetrics || supplierMetrics.length === 0) {
      // Colspan debe ser 10 (10 columnas: Proveedor + 9 métricas)
      tableBody.innerHTML = `<tr><td colspan="10" style="text-align: center; padding: 2rem; color: var(--text-secondary);">No hay ventas asociadas a proveedores en este periodo.</td></tr>`;
      return;
    }

    // Ya están ordenados por Utilidad Neta DESC
    supplierMetrics.forEach(item => {
      const row = document.createElement('tr');

      const totalSalesBruto = item.total_sales_bruto;
      const totalSalesNeto = item.total_sales_neto;
      const totalCMV = item.total_cmv;
      const totalNetProfit = item.total_net_profit; 
      const totalIVARecaudado = item.total_iva_recaudado;
      const totalIVACompra = item.total_purchase_iva; // NUEVA VARIABLE
      const totalRealIVA = item.total_real_iva;

      // Determinar color de IVA Real (Verde si es deuda (>=0), Rojo si es Crédito (<0))
      const realIvaColor = totalRealIVA >= 0 ? 'var(--utility-color)' : 'var(--reinvestment-color)';


      const marginPercent = item.margin_percent.toFixed(1);
      const contributionPercent = item.contribution_percent.toFixed(1);

      row.innerHTML = `
        <td><strong>${item.supplier_name}</strong></td>
        <td style="text-align: right; color: #3b82f6; font-weight: 500;">${formatCurrency(totalSalesBruto)}</td>
        <td style="text-align: right; color: var(--sales-color); font-weight: 500;">${formatCurrency(totalSalesNeto)}</td>
        <td style="text-align: right; color: #ffc107; font-weight: 600;">${formatCurrency(totalIVARecaudado)}</td> 
        <td style="text-align: right; color: var(--reinvestment-color); font-weight: 600;">${formatCurrency(totalIVACompra)}</td> <td style="text-align: right; color: ${realIvaColor}; font-weight: 600;">
            ${formatCurrency(totalRealIVA)}
        </td>
        <td style="text-align: right; color: var(--reinvestment-color); font-weight: 500;">${formatCurrency(totalCMV)}</td>
        <td style="color: var(--utility-color); font-weight: 600; text-align: right;">
          ${formatCurrency(totalNetProfit)}
        </td>
        <td style="text-align: right;">${marginPercent}%</td>
        <td style="text-align: right; font-weight: 600;">${contributionPercent}%</td>
      `;
      tableBody.appendChild(row);
    });
  };


  /**
  * Handler para la selección de mes (Reutilizado del archivo original).
  */
  const handleMonthChange = (selectElement) => {
    document.getElementById('date-start').value = '';
    document.getElementById('date-end').value = '';

    if (selectElement.value !== '') {
      selectElement.form.submit();
    } else {
      selectElement.form.submit();
    }
  };


  /**
  * Handler para el envío de formulario de rango (Reutilizado del archivo original).
  */
  const handleFilterSubmit = (event) => {
    const monthSelect = document.getElementById('month-select');
    const dateStart = document.getElementById('date-start').value;
    const dateEnd = document.getElementById('date-end').value;

    if ((dateStart && !dateEnd) || (!dateStart && dateEnd)) {
      alert('Por favor, selecciona tanto la fecha de inicio como la de fin para aplicar el rango.');
      event.preventDefault();
      return;
    }

    if (dateStart && dateEnd) {
      // Anular el filtro de mes al usar el rango
      monthSelect.value = '';
    }
  };


  // Event Listeners y Carga Inicial
  document.addEventListener('DOMContentLoaded', function() {
    updateSupplierProfitChart();
    updateTopSupplierChart();
    updateTable();

    // Event listener para el formulario (para el botón Aplicar Rango)
    document.getElementById('filter-form').addEventListener('submit', handleFilterSubmit);

    // Si se cargó con un filtro de rango (por PHP), nos aseguramos que el select de mes no tenga nada.
    const phpSelectedDateStart = '<?= $selectedDateStart ?>';
    const phpSelectedDateEnd = '<?= $selectedDateEnd ?>';
    if (phpSelectedDateStart || phpSelectedDateEnd) {
      document.getElementById('month-select').value = '';
    }
  });

</script>

</body>
</html>