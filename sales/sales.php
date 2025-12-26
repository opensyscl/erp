<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config.php';
session_start();

// ----------------------------------------------------------------------
// --- 1. VERIFICACIÓN DE LOGIN Y CONEXIÓN CRÍTICA ---
// ----------------------------------------------------------------------

// 1.1 Redireccionar si el usuario no está logueado
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
// --- 2. VALIDACIÓN DE ROL ESPECÍFICO (NUEVA LÓGICA) ---
// ----------------------------------------------------------------------

$user_can_access = false;
$module_path = '/erp/sales/';

try {
  // A) Chequeo de acceso de Rol (POS1 vs POS2)
  // Obtenemos el rol del usuario logueado
  $stmt_role = $pdo->prepare("SELECT role FROM users WHERE id = ?");
  $stmt_role->execute([$current_user_id]);
  $user_role = $stmt_role->fetchColumn();

  // LÓGICA DE NEGOCIO: Solo POS1 puede acceder. POS2 es denegado.
  if ($user_role === 'POS1') {
    $user_can_access = true;
  }
  // Si el rol es POS2, el $user_can_access queda en false.

} catch (PDOException $e) {
  // Si falla la BD, por seguridad, denegamos el acceso.
  error_log("Error de BD al verificar rol del usuario: " . $e->getMessage());
  header('Location: ../not_authorized.php');
  exit();
}


// ----------------------------------------------------------------------
// --- 3. VALIDACIÓN GLOBAL DE MÓDULO (GUARDIÁN) ---
// ----------------------------------------------------------------------

if ($user_can_access) {
  // Solo si el rol es POS1, chequeamos si el admin GLOBAL lo activó.
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

// Inicializar la conexión PDO
if (!isset($pdo)) {
die('Error: PDO connection not established.');
}


// ----------------------------------------------------------------------
// --- 1. OBTENER Y VALIDAR EL RANGO DE FECHAS (PRIORITARIO) O EL MES ---
// ----------------------------------------------------------------------

// 1.1 Intentar obtener el rango de fechas personalizado
$customStartDate = $_GET['start_date'] ?? null;
$customEndDate = $_GET['end_date'] ?? null;
$isCustomRange = false;

// Validar y sanear el formato AAAA-MM-DD
// CORRECCIÓN: Usar ?? '' para asegurar que los argumentos de preg_match sean siempre strings.
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $customStartDate ?? '') && preg_match('/^\d{4}-\d{2}-\d{2}$/', $customEndDate ?? '')) {
// Rango de fechas personalizado válido, se usa este.
$startDate = $customStartDate;
$endDate = $customEndDate;
$selectedMonthYear = ''; // Limpiar el mes seleccionado
$isCustomRange = true;
} else {
// 1.2 Si no hay rango personalizado, usar el filtro por mes/año
$selectedMonthYear = $_GET['month'] ?? date('Y-m');

// Validar y asegurar el formato YYYY-MM para el filtro por mes
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonthYear)) {
 $selectedMonthYear = date('Y-m'); // Por defecto, el mes actual
}

$selectedYear = date('Y', strtotime($selectedMonthYear . '-01'));
$selectedMonth = date('m', strtotime($selectedMonthYear . '-01'));

// Calcular el primer y último día del mes seleccionado (este será el rango por defecto)
$startOfMonth = date('Y-m-01', strtotime($selectedMonthYear . '-01'));
$endOfMonth = date('Y-m-t', strtotime($selectedMonthYear . '-01'));

$startDate = $startOfMonth; // Inicio del filtro: 1er día del mes
$endDate = $endOfMonth; // Fin del filtro: Último día del mes
}

// Bandera y fechas de corte para cálculos de acumulados y promedio.
$currentDate = date('Y-m-d');
$isCurrentMonth = ($selectedMonthYear === date('Y-m')) && !$isCustomRange; // Solo mes actual si no hay rango custom.

// Si estamos en el mes actual (y no hay rango custom), el fin de los cálculos acumulados es HOY.
// Si es un mes pasado o un rango custom, el fin de los cálculos es el $endDate del filtro.
$endDateForCalculations = ($isCurrentMonth && !$isCustomRange) ? $currentDate : $endDate;

// Determina la fecha específica para el KPI de VENTA DIARIA:
// Si es el mes actual, usa la fecha de hoy.
// Si es un mes anterior o un rango custom, usa el $endDateForCalculations.
$dailySaleDate = $endDateForCalculations;
// ----------------------------------------------------------------------
// --- 2. CÁLCULO DE MÉTRICAS (KPIs) CON BASE EN EL RANGO DEFINIDO ---
// ----------------------------------------------------------------------

// Venta Diaria (Siempre para la fecha de corte $dailySaleDate)
$stmt_daily = $pdo->prepare("
 SELECT SUM(total) AS daily_sales
 FROM sales
 WHERE DATE(created_at) = ?
");
$stmt_daily->execute([$dailySaleDate]);
$daily_sales = $stmt_daily->fetchColumn() ?: 0;

// Lógica de Formato Condicional (Semáforo) para Venta Diaria
$daily_sales_class = 'default-color';
if ($daily_sales < 200000) {
 $daily_sales_class = 'danger-color'; // Rojo: < 200.000
} elseif ($daily_sales >= 300000) {
 $daily_sales_class = 'success-color'; // Verde: >= 300.000
} elseif ($daily_sales >= 200000) {
 $daily_sales_class = 'warning-color'; // Naranja: >= 200.000 y < 300.000
}

// Venta Mensual/Rango (Acumulado hasta $endDateForCalculations)
// USAMOS $startDate y $endDateForCalculations
$stmt_monthly = $pdo->prepare("
 SELECT SUM(total) AS monthly_sales
 FROM sales
 WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt_monthly->execute([$startDate, $endDateForCalculations]);
$monthly_sales = $stmt_monthly->fetchColumn() ?: 0;

// Venta Diaria Promedio (En el rango $startDate a $endDateForCalculations)
$stmt_daily_average = $pdo->prepare("
 SELECT AVG(daily_total) AS daily_average
 FROM (
  SELECT SUM(total) AS daily_total
  FROM sales
  WHERE DATE(created_at) BETWEEN ? AND ?
  GROUP BY DATE(created_at)
 ) AS subquery
");
$stmt_daily_average->execute([$startDate, $endDateForCalculations]);
$daily_average = $stmt_daily_average->fetchColumn() ?: 0;

// Lógica de Formato Condicional (Semáforo) para Venta Diaria Promedio
$daily_average_class = 'default-color';
if ($daily_average < 250000) {
 $daily_average_class = 'danger-color'; // Rojo: < 250.000
} elseif ($daily_average >= 300000) {
 $daily_average_class = 'success-color'; // Verde: >= 300.000
} elseif ($daily_average >= 250000) {
 $daily_average_class = 'warning-color'; // Naranja: >= 250.000 y < 300.000
}

// Proyección de Ventas (Solo tiene sentido para meses completos)
$monthly_projection = 0;
// Calcular la diferencia de días entre $startDate y $endDate para la proyección
$diff = date_diff(date_create($startDate), date_create($endDate));
$daysInFilterRange = $diff->days + 1;

if ($daily_average > 0) {
 // Si es un filtro por mes, proyectamos al final del mes
 if (!$isCustomRange) {
  $daysInSelectedMonth = date('t', strtotime($selectedMonthYear . '-01'));
  $monthly_projection = $daily_average * $daysInSelectedMonth;
 } else {
  // Si es un rango custom, la "proyección" es la Venta Mensual Acumulada
  // o se puede omitir, ya que la proyección no aplica a rangos parciales/pasados
  $monthly_projection = $monthly_sales;
 }
}


// -----------------------------------------------------------------------------
// --- 3. OBTENER DATOS DE VENTAS PARA LA TABLA Y EL GRÁFICO (RANGO COMPLETO) ---
// -----------------------------------------------------------------------------
// Usamos $startDate y $endDate para obtener toda la data dentro del rango de filtro.
$stmt_sales_data = $pdo->prepare("
 SELECT id, total, paid, receipt_number, change_due, method, created_at, DATE(created_at) AS sale_date_formatted
 FROM sales
 WHERE DATE(created_at) BETWEEN ? AND ?
 ORDER BY created_at DESC;
");
$stmt_sales_data->execute([$startDate, $endDate]);
$sales_data_full = $stmt_sales_data->fetchAll(PDO::FETCH_ASSOC);


// --- 4. GENERACIÓN DE OPCIONES DE MESES PARA EL SELECTOR (ULTIMOS 12 MESES) ---
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
 // Fallback para entornos sin extensión intl
 $meses = [
  'January' => 'Enero', 'February' => 'Febrero', 'May' => 'Mayo',
  'March' => 'Marzo', 'April' => 'Abril', 'June' => 'Junio',
  'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre',
  'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
 ];
 for ($i = 0; $i < 12; $i++) {
  $date = new DateTime("-$i month");
  $value = $date->format('Y-m');
  $monthNameEn = $date->format('F');
  $year = $date->format('Y');
  $label = ($meses[$monthNameEn] ?? $monthNameEn) . ' ' . $year;
  $monthOptions[$value] = $label;
 }
}

// Variables para el encabezado
$current_page = 'sales.php';
$stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
$stmt->execute();
$system_version = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <title>Reporte de Ventas - Listto! ERP</title>
 <link rel="preconnect" href="https://fonts.googleapis.com">
 <link rel="icon" type="image/png" href="img/fav.png">
 <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
 <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
 <link rel="stylesheet" href="css/sales.css">
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
    <h1 class="page-title">Reporte de Ventas</h1>
 
    <div class="filter-controls-group" style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
  
      <div class="month-selector-container">
    <label for="month-selector">Filtrar por Mes:</label>
    <select id="month-selector" onchange="window.location.href = 'sales.php?month=' + this.value">
     <option value="">Seleccione Mes</option>
     <?php
     foreach ($monthOptions as $value => $label) {
      $selected = (!$isCustomRange && $value === $selectedMonthYear) ? 'selected' : '';
      echo "<option value=\"" . htmlspecialchars($value) . "\" {$selected}>" . htmlspecialchars($label) . "</option>";
     }
     ?>
    </select>
   </div>

      <form id="range-filter-form" class="range-selector-styled" action="sales.php" method="GET">
   
    <div class="date-input-group">
     <label for="start_date">Desde:</label>
     <input type="date" id="start_date" name="start_date"
       value="<?= htmlspecialchars($isCustomRange ? $startDate : '') ?>">
    </div>
   
    <div class="date-input-group">
     <label for="end_date">Hasta:</label>
     <input type="date" id="end_date" name="end_date"
       value="<?= htmlspecialchars($isCustomRange ? $endDate : '') ?>">
    </div>
   
    <button type="submit" class="btn-filter">
     Filtrar
    </button>
   
    <?php if ($isCustomRange): // Botón para resetear si hay un rango custom activo ?>
     <button type="button" class="btn-reset" onclick="window.location.href = 'sales.php'">
      Reset
     </button>
    <?php endif; ?>
   </form>
  </div>
 
 </div>
  <p class="filter-toast">
 <?php
 // Mostrar el rango de fechas que se está utilizando
 if ($isCustomRange) {
 echo "Filtrando: <strong>" . date('d/m/Y', strtotime($startDate)) . "</strong> a <strong>" . date('d/m/Y', strtotime($endDate)) . "</strong>";
 } elseif ($selectedMonthYear) {
 echo "Filtrando: <strong>" . htmlspecialchars($monthOptions[$selectedMonthYear] ?? 'Mes Actual') . "</strong> (" . date('d/m/Y', strtotime($startDate)) . " a " . date('d/m/Y', strtotime($endDateForCalculations)) . ($endDateForCalculations < $endDate ? ' Acum.' : '') . ")";
 }
 ?>
</p>

 <div class="kpi-grid">
  <div class="kpi-card">
 <h3>Venta Diaria (<?= date('d/m/Y', strtotime($dailySaleDate)) ?>)</h3>
 <p class="value <?= htmlspecialchars($daily_sales_class) ?>"><?= number_format($daily_sales, 0, ',', '.') ?></p>
</div>
   <div class="kpi-card">
    <h3>Venta Diaria Promedio</h3>
    <p class="value <?= htmlspecialchars($daily_average_class) ?>"><?= number_format($daily_average, 0, ',', '.') ?></p>
   </div>
   <div class="kpi-card">
    <h3>Venta Acumulada (<?= date('d/m', strtotime($startDate)) ?>-<?= date('d/m', strtotime($endDateForCalculations)) ?>)</h3>
    <p class="value"><?= number_format($monthly_sales, 0, ',', '.') ?></p>
   </div>
<div class="kpi-card">
 <h3>Proyecci&oacute;n Mensual</h3>
 <?php if (!$isCustomRange): ?>
  <?php if ($monthly_projection > 0): ?>
   <p class="value projection"><?= number_format($monthly_projection, 0, ',', '.') ?></p>
  <?php else: ?>
   <p class="value projection" style="font-size: 1rem; color: #9ca3af;">Sin promedio</p>
  <?php endif; ?>
 <?php else: ?>
  <p class="value projection" style="font-size: 1rem; color: #9ca3af;">N/A para Rango</p>
 <?php endif; ?>
</div>
  </div>

  <div class="content-card">
   <h2>Evoluci&oacute;n de Ventas (<?= $isCustomRange ? 'Rango ' . date('d/m/Y', strtotime($startDate)) . ' a ' . date('d/m/Y', strtotime($endDate)) : htmlspecialchars($monthOptions[$selectedMonthYear] ?? 'Mes') ?>)</h2>
   <div style="height: 400px;">
    <canvas id="salesChart"></canvas>
   </div>
  </div>

<div class="content-card">
 <div class="table-header-controls">
  <h2>Ventas Detalle</h2>
  <div class="table-controls">
   <label for="limit">Mostrar:</label>
   <select id="limit">
    <option value="10">10</option>
    <option value="25">25</option>
    <option value="50">50</option>
    <option value="100">100</option>
    <option value="all">Todas</option>
   </select>
  </div>
 </div>
 <div class="table-container">
  <table class="sales-table">
   <thead>
    <tr>
     <th data-sort-column="id">ID</th>
     <th data-sort-column="receipt_number">Recibo</th>
     <th data-sort-column="total">Total</th>
     <th data-sort-column="paid">Pagado</th>
     <th data-sort-column="change_due">Cambio</th>
     <th data-sort-column="method">M&eacute;todo</th>
     <th data-sort-column="created_at" class="sort-desc">Fecha y Hora</th>
          <th>Acci&oacute;n</th>
    </tr>
   </thead>
   <tbody id="sales-table-body">
    <tr>
     <td colspan="8" style="text-align: center; padding: 2rem;">Cargando ventas...</td>
    </tr>
   </tbody>
  </table>
 </div>
</div>
 </main>


 <script>
 let salesChart;
 const salesData = <?= json_encode($sales_data_full); ?>;
 const monthlyDailyAverage = parseFloat('<?= $daily_average ?>');

 // Nuevas variables JS para el gráfico
 const startDate = '<?= $startDate ?>';
 const endDate = '<?= $endDate ?>';
 const isCustomRange = <?= json_encode($isCustomRange); ?>;

 let currentSortColumn = 'created_at';
 let currentSortDirection = 'desc';

 const formatCurrency = (amount) => {
  return parseFloat(amount).toLocaleString('es-CL', {
   style: 'currency',
   currency: 'CLP',
   minimumFractionDigits: 2
  });
 };

 /**
 * Genera un array de fechas entre dos fechas.
 * @param {string} start AAAA-MM-DD
 * @param {string} end AAAA-MM-DD
 */
 const getDatesBetween = (start, end) => {
  const dateArray = [];
  let currentDate = new Date(start + 'T00:00:00'); // Asegura que se interprete como local (UTC medianoche)
  const stopDate = new Date(end + 'T00:00:00');

  while (currentDate <= stopDate) {
   const year = currentDate.getFullYear();
   const month = String(currentDate.getMonth() + 1).padStart(2, '0');
   const day = String(currentDate.getDate()).padStart(2, '0');
   dateArray.push(`${year}-${month}-${day}`);
  
   // Avanzar un día
   currentDate.setDate(currentDate.getDate() + 1);
  }
  return dateArray;
 };

 const updateChart = () => {
  const chartCanvas = document.getElementById('salesChart');
  const chartContainer = chartCanvas.parentNode;

  if (!salesData || salesData.length === 0) {
   if (salesChart) salesChart.destroy();
   chartContainer.innerHTML = '<p style="text-align: center; margin-top: 2rem; color: #4b5563;">No hay datos de ventas para mostrar la gráfica en el rango seleccionado.</p>';
   return;
  }

  // Si se destruyó el chart, lo recreamos antes de continuar
  if (chartContainer.querySelector('canvas') === null) {
   chartContainer.innerHTML = '';
   chartContainer.appendChild(chartCanvas);
  }
  
  // Usar la función para obtener todos los días en el rango de filtro
  const allDays = getDatesBetween(startDate, endDate);
  const salesByDay = {};

  // Inicializar todas las ventas a 0
  allDays.forEach(key => { salesByDay[key] = 0; });

  // Acumular las ventas por día en el rango
  salesData.forEach(sale => {
   const key = sale.sale_date_formatted;
   if (salesByDay.hasOwnProperty(key)) {
    salesByDay[key] += parseFloat(sale.total);
   }
  });

  // Generar labels (solo el día o día/mes si es rango custom)
  const chartLabels = allDays.map(key => {
   const dateParts = key.split('-');
   if (isCustomRange) {
    // Mostrar Día/Mes si es un rango custom
    return `${dateParts[2]}/${dateParts[1]}`;
   } else {
    // Mostrar solo el Día si es un filtro por Mes
    return `${dateParts[2]}`;
   }
  });
 
  const chartData = allDays.map(key => salesByDay[key]);
  
  // Datos para la línea de promedio
  const averageData = chartLabels.map(() => monthlyDailyAverage);
  
  const datasets = [
   {
    label: 'Ventas Totales',
    data: chartData,
    backgroundColor: 'rgba(59, 130, 246, 0.1)',
    borderColor: 'rgba(59, 130, 246, 1)',
    borderWidth: 3,
    tension: 0.4,
    pointBackgroundColor: 'rgba(59, 130, 246, 1)',
    pointRadius: 4,
    pointHoverRadius: 6,
    fill: true,
    type: 'line',
    order: 1
   },
   {
    label: 'Promedio Diario',
    data: averageData,
    borderColor: 'rgba(239, 68, 68, 0.8)',
    borderWidth: 2,
    borderDash: [5, 5],
    pointRadius: 0,
    fill: false,
    type: 'line',
    order: 2
   }
  ];


  if (salesChart) {
   salesChart.data.labels = chartLabels;
   salesChart.data.datasets = datasets;
   salesChart.update();
  } else {
   const ctx = document.getElementById('salesChart').getContext('2d');
   salesChart = new Chart(ctx, {
    type: 'line',
    data: {
     labels: chartLabels,
     datasets: datasets
    },
    options: {
     responsive: true,
     maintainAspectRatio: false,
     scales: {
      y: {
       beginAtZero: true,
       ticks: {
        callback: function(value) {
         return formatCurrency(value);
        }
       }
      }
     },
     plugins: {
      legend: {
       display: true,
       position: 'top',
      },
      tooltip: {
       callbacks: {
        label: function(context) {
         return context.dataset.label + ': ' + formatCurrency(context.parsed.y);
        }
       }
      }
     }
    }
   });
  }
 };

 // La función sortData se mantiene igual, ya que opera sobre el array salesData
 const sortData = (data, column, direction) => {
  const numericColumns = ['id', 'total', 'paid', 'change_due'];
  const isNumeric = numericColumns.includes(column);

  return data.sort((a, b) => {
   let aVal = a[column];
   let bVal = b[column];
   let comparison = 0;

   if (isNumeric) {
    aVal = parseFloat(aVal) || 0;
    bVal = parseFloat(bVal) || 0;
    comparison = aVal - bVal;
   } else if (column === 'created_at') {
    const dateA = new Date(aVal);
    const dateB = new Date(bVal);
    comparison = dateA - dateB;
   } else {
    comparison = String(aVal).localeCompare(String(bVal));
   }

   return direction === 'asc' ? comparison : -comparison;
  });
 };

// La función updateTable se mantiene igual, ya que usa salesData (ya filtrada por PHP)
const updateTable = () => {
  const limit = document.getElementById('limit').value;
  const tableBody = document.getElementById('sales-table-body');
  tableBody.innerHTML = '';

  let filteredData = salesData;
  
  let sortedData = sortData([...filteredData], currentSortColumn, currentSortDirection);

  let finalData;
  if (limit === 'all') {
   finalData = sortedData;
  } else {
   finalData = sortedData.slice(0, parseInt(limit));
  }

  if (!finalData || finalData.length === 0) {
   // Se corrigió el colspan a 8 para reflejar la nueva columna
   tableBody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 2rem; color: #4b5563;">No hay ventas registradas para este rango de fechas.</td></tr>`;
   return;
  }

  finalData.forEach(sale => {
   const row = document.createElement('tr');
   const saleDate = new Date(sale.created_at);
   const formattedDate = `${String(saleDate.getDate()).padStart(2, '0')}-${String(saleDate.getMonth() + 1).padStart(2, '0')}-${saleDate.getFullYear()} ${String(saleDate.getHours()).padStart(2, '0')}:${String(saleDate.getMinutes()).padStart(2, '0')}`;
   row.innerHTML = `
    <td>${sale.id}</td>
    <td>${sale.receipt_number}</td>
    <td class="numeric-cell">${formatCurrency(sale.total)}</td>
    <td class="numeric-cell">${formatCurrency(sale.paid)}</td>
    <td class="numeric-cell">${formatCurrency(sale.change_due)}</td>
    <td>${sale.method}</td>
    <td>${formattedDate}</td>
        <td>
     <a href="../print_ticket.php?id=${sale.id}" target="_blank" class="btn-print-ticket">
      Imprimir Venta
     </a>
    </td>
   `;
   tableBody.appendChild(row);
  });

  const headers = document.querySelectorAll('.sales-table th');
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
     currentSortDirection = (column === 'id' || column === 'total' || column === 'created_at') ? 'desc' : 'asc';
    }
   
    updateTable();
   });
  });
 };

 // Función para manejar el envío del formulario de rango
 const handleRangeFormSubmission = (event) => {
  const start = document.getElementById('start_date').value;
  const end = document.getElementById('end_date').value;

  if (!start || !end) {
   event.preventDefault(); // Evitar el envío si falta una fecha
   console.error('Error: Por favor, selecciona tanto la fecha de inicio como la fecha de fin.');
   // Aquí podrías agregar lógica para mostrar un mensaje de error visible al usuario si lo deseas.
   return;
  }
 
  // Validar que la fecha de inicio no es posterior a la de fin
  if (new Date(start) > new Date(end)) {
   event.preventDefault();
   console.error('Error: La fecha de inicio no puede ser posterior a la fecha de fin.');
   return;
  }
 
  // Eliminar el parámetro 'month' para limpiar la URL y asegurar la prioridad en PHP
  const form = event.target;
  const currentUrl = new URL(window.location.href);
  currentUrl.searchParams.delete('month');
  form.action = currentUrl.pathname;
 };

 document.addEventListener('DOMContentLoaded', function() {
  setupTableHeaders();
  updateChart();
  updateTable();

  document.getElementById('limit').addEventListener('change', updateTable);
 
  // Listener para el formulario de rango de fechas
  const rangeForm = document.getElementById('range-filter-form');
  if (rangeForm) {
   rangeForm.addEventListener('submit', handleRangeFormSubmission);
  }
 });
 </script>
</body>

</html>