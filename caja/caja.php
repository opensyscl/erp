<?php

/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/

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
// --- 2. VALIDACIÓN DE ROL ESPECÍFICO ---
// ----------------------------------------------------------------------

$user_can_access = false;
// ** MODIFICAR ESTA LÍNEA **: Ajustar la ruta de la carpeta del módulo (ej: /erp/inventory/)
$module_path = '/erp/caja/'; 

try {
    // Obtenemos el rol del usuario logueado
    $stmt_role = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_role->execute([$current_user_id]);
    $user_role = $stmt_role->fetchColumn();

    // ** MODIFICAR ESTA LÓGICA **: Definir qué roles tienen acceso.
    // Ejemplo para 'Sales':
    if (in_array($user_role, ['POS1', 'POS2'])) {
        $user_can_access = true;
    }
    // Ejemplo para 'Inventory' (podría aceptar 'Admin' y 'Manager'):
    /*
    if (in_array($user_role, ['Admin', 'Manager', 'InventoryUser'])) {
        $user_can_access = true;
    }
    */

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


// --- 3. PROCESAMIENTO DEL FORMULARIO DE CIERRE DE CAJA ---
$message = ''; // Inicializa la variable para mensajes de éxito o error.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['apply_filters'])) { // Evita que el filtro ejecute el cierre de caja
    try {
        // Asigna las variables de Ingresos (con conversión a flotante o 0)
        $starting_cash = (float)($_POST['starting_cash'] ?? 0);
        $ending_cash = (float)($_POST['ending_cash'] ?? 0);
        $pos1_sales = (float)($_POST['pos1_sales'] ?? 0);
        $pos2_sales = (float)($_POST['pos2_sales'] ?? 0);
        
        // Asigna las variables de Egresos
        $deposit_meli = (float)($_POST['deposit_meli'] ?? 0);
        $deposit_bchile = (float)($_POST['deposit_bchile'] ?? 0);
        $deposit_bsantander = (float)($_POST['deposit_bsantander'] ?? 0);
        $other_outgoings = (float)($_POST['other_outgoings'] ?? 0);
        
        // --- CÁLCULOS SEGÚN LA LÓGICA SOLICITADA ---
        
        // 1. Total Egresos
        $total_outgoings = $deposit_meli + $deposit_bchile + $deposit_bsantander + $other_outgoings;

        // 2. Total Ingresos ($total_day_income): Flujo de Caja Neto
        // Fórmula: (Efectivo Final - Efectivo Inicial) + Ventas POS 1 + Ventas POS 2
        $total_day_income = ($ending_cash - $starting_cash) + $pos1_sales + $pos2_sales;

        // 3. Ingresos + Egresos (Métrica Final): Total Ingresos + Total Egresos
        $income_plus_outgoings = $total_day_income + $total_outgoings;
        
        // 4. Total de ventas del día (Se mantiene para la tabla)
        $total_day_cash = $pos1_sales + $pos2_sales;

        // Prepara la consulta SQL para insertar los datos en la tabla `cash_closings`.
        // SE REQUIERE QUE LA TABLA TENGA LAS COLUMNAS CREADAS PREVIAMENTE.
        $stmt = $pdo->prepare("
            INSERT INTO cash_closings (closing_date, starting_cash, ending_cash, pos1_sales, pos2_sales, total_day_cash, 
                                     deposit_meli, deposit_bchile, deposit_bsantander, other_outgoings, total_outgoings, 
                                     total_day_income, income_plus_outgoings)
            VALUES (CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Ejecuta la consulta
        $stmt->execute([
            $starting_cash, $ending_cash, $pos1_sales, $pos2_sales, $total_day_cash,
            $deposit_meli, $deposit_bchile, $deposit_bsantander, $other_outgoings, $total_outgoings,
            $total_day_income, $income_plus_outgoings
        ]);

        // Muestra un mensaje de éxito.
        $message = '<div class="alert success">Cierre de caja guardado exitosamente.</div>';
    } catch (PDOException $e) {
        // En caso de error en la base de datos (ej. Columna no encontrada), muestra error.
        $message = '<div class="alert error">Error al guardar el cierre: ' . $e->getMessage() . '</div>';
    }
}

// --- 4. OBTENCIÓN DE DATOS PARA KPIS Y TABLA (CON FILTROS) ---

// Definición de las variables de filtro
$month_filter = $_GET['month_filter'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Definición del rango de fechas para los KPIs (Siempre el mes actual)
$kpi_month_start = date('Y-m-01');
$kpi_month_end = date('Y-m-t');

/**
 * Función auxiliar para ejecutar consultas SQL que devuelven un único valor (KPIs).
 */
function get_kpi_value($pdo, $sql, $params) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchColumn();
    return $result ?: 0;
}

try {
    // Obtiene los valores de los KPIs del mes actual
    $total_cash_month = get_kpi_value($pdo, "SELECT SUM(ending_cash) FROM cash_closings WHERE closing_date BETWEEN ? AND ?", [$kpi_month_start, $kpi_month_end]);
    $total_meli_deposits = get_kpi_value($pdo, "SELECT SUM(deposit_meli) FROM cash_closings WHERE closing_date BETWEEN ? AND ?", [$kpi_month_start, $kpi_month_end]);
    $total_bchile_deposits = get_kpi_value($pdo, "SELECT SUM(deposit_bchile) FROM cash_closings WHERE closing_date BETWEEN ? AND ?", [$kpi_month_start, $kpi_month_end]);
    $total_bsantander_deposits = get_kpi_value($pdo, "SELECT SUM(deposit_bsantander) FROM cash_closings WHERE closing_date BETWEEN ? AND ?", [$kpi_month_start, $kpi_month_end]);
    $total_other_outgoings = get_kpi_value($pdo, "SELECT SUM(other_outgoings) FROM cash_closings WHERE closing_date BETWEEN ? AND ?", [$kpi_month_start, $kpi_month_end]);
    $total_pos1_sales = get_kpi_value($pdo, "SELECT SUM(pos1_sales) FROM cash_closings WHERE closing_date BETWEEN ? AND ?", [$kpi_month_start, $kpi_month_end]);
    $total_pos2_sales = get_kpi_value($pdo, "SELECT SUM(pos2_sales) FROM cash_closings WHERE closing_date BETWEEN ? AND ?", [$kpi_month_start, $kpi_month_end]);

    // --- LÓGICA DE FILTROS PARA LA TABLA ---
    $sql_filter = "";
    $params = [];
    $limit = "LIMIT 10"; // Límite por defecto

    if (!empty($start_date) && !empty($end_date)) {
        // Filtro por RANGO DE FECHAS
        $sql_filter = " WHERE closing_date BETWEEN ? AND ?";
        $params = [$start_date, $end_date];
        $limit = ""; // Si se filtra por rango, no se limita a 10
    } elseif (!empty($month_filter)) {
        // Filtro por MES (YYYY-MM)
        $year_month = explode('-', $month_filter);
        if (count($year_month) === 2) {
            $month_start = $month_filter . '-01';
            $month_end = date('Y-m-t', strtotime($month_start));
            $sql_filter = " WHERE closing_date BETWEEN ? AND ?";
            $params = [$month_start, $month_end];
            $limit = ""; // Si se filtra por mes, no se limita a 10
        }
    }
    
    // Obtiene los cierres de caja para la tabla, aplicando el filtro si existe.
    $sql = "SELECT *, total_day_income AS total_ingresos, total_outgoings AS total_egresos, income_plus_outgoings AS ingresos_mas_egresos FROM cash_closings" . $sql_filter . " ORDER BY closing_date DESC, id DESC " . $limit;
    
    $recent_closings_stmt = $pdo->prepare($sql);
    $recent_closings_stmt->execute($params);
    $recent_closings = $recent_closings_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Si hay un error al cargar los datos, inicializa las variables
    $total_cash_month = $total_meli_deposits = $total_bchile_deposits = $total_bsantander_deposits = $total_other_outgoings = $total_pos1_sales = $total_pos2_sales = 0;
    $recent_closings = [];
    $message = '<div class="alert error">Error al cargar datos (KPIs/Tabla): ' . $e->getMessage() . '</div>';
}

// --- 5. OBTENCIÓN DE DATOS ADICIONALES PARA LA VISTA ---
$current_page = 'caja.php';
$system_version = '1.0'; 
try {
    $stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
    $stmt->execute();
    $version_result = $stmt->fetchColumn();
    if ($version_result) {
        $system_version = $version_result;
    }
} catch (PDOException $e) {
    // Si la consulta de la versión falla, se mantiene el valor predeterminado.
}

// Generar una lista de los últimos 12 meses para el filtro
$months = [];
for ($i = 0; $i < 12; $i++) {
    $timestamp = strtotime(date('Y-m-01') . " -$i months");
    $value = date('Y-m', $timestamp);
    // Nota: strftime podría necesitar configuración de locale en algunos entornos
    // Usaremos una traducción simple para evitar problemas de locale en el canvas.
    $month_name = date('F', $timestamp);
    $year = date('Y', $timestamp);
    $month_names_es = [
        'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril',
        'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto',
        'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
    ];
    $label = ($month_names_es[$month_name] ?? $month_name) . " " . $year;
    $months[$value] = $label;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuadre de Caja - Mi Sistema</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" type="image/png" href="img/fav.png">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/caja.css"> 

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
            <a href="caja.php" class="active">Cuadre de caja</a>
        </nav>
        <div class="header-right">
            <span class="app-version"><?php echo htmlspecialchars($system_version); ?></span>
            <a href="../logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </header>

    <main class="container">
        <?php echo $message; // Muestra mensajes de éxito o error ?>

        <div class="kpi-grid">
            <div class="kpi-card"><h3>Efectivo del Mes</h3><p class="value">$<?= number_format($total_cash_month, 0, ',', '.') ?></p></div>
            <div class="kpi-card"><h3>Ventas POS 1 (Mes)</h3><p class="value">$<?= number_format($total_pos1_sales, 0, ',', '.') ?></p></div>
            <div class="kpi-card"><h3>Ventas POS 2 (Mes)</h3><p class="value">$<?= number_format($total_pos2_sales, 0, ',', '.') ?></p></div>
            <div class="kpi-card"><h3>Dep. B. Santander</h3><p class="value">$<?= number_format($total_bsantander_deposits, 0, ',', '.') ?></p></div>
            <div class="kpi-card"><h3>Dep. B. de Chile</h3><p class="value">$<?= number_format($total_bchile_deposits, 0, ',', '.') ?></p></div>
            <div class="kpi-card"><h3>Dep. MELI</h3><p class="value">$<?= number_format($total_meli_deposits, 0, ',', '.') ?></p></div>
            <div class="kpi-card"><h3>Otras Salidas</h3><p class="value">$<?= number_format($total_other_outgoings, 0, ',', '.') ?></p></div>
            
        </div>

        <div class="content-card">
            <h2>Registrar Cierre Diario</h2>
            <form action="caja.php" method="POST">
                <div class="form-grid">
                    <div class="form-column">
                        <h3>Cierre del Día (Ingresos)</h3>
                        <div class="form-group"><label for="starting_cash">Efectivo Inicial</label><input type="number" id="starting_cash" name="starting_cash" required></div>
                        <div class="form-group"><label for="pos1_sales">Ventas POS 1</label><input type="number" id="pos1_sales" name="pos1_sales" required></div>
                        <div class="form-group"><label for="pos2_sales">Ventas POS 2</label><input type="number" id="pos2_sales" name="pos2_sales" required></div>
                        <div class="form-group"><label for="ending_cash">Efectivo Final (en caja)</label><input type="number" id="ending_cash" name="ending_cash" required></div>
                    </div>
                    
                    <div class="form-column">
                        <h3>Egresos</h3>
                        <div class="form-group"><label for="deposit_meli">Depósito a MELI</label><input type="number" id="deposit_meli" name="deposit_meli" value="0"></div>
                        <div class="form-group"><label for="deposit_bchile">Depósito a Banco de Chile</label><input type="number" id="deposit_bchile" name="deposit_bchile" value="0"></div>
                        <div class="form-group"><label for="deposit_bsantander">Depósito a Banco Santander</label><input type="number" id="deposit_bsantander" name="deposit_bsantander" value="0"></div>
                        <div class="form-group"><label for="other_outgoings">Otras Salidas</label><input type="number" id="other_outgoings" name="other_outgoings" value="0"></div>
                        
                        
                    </div>
                    <div class="results-grid">
                        <div class="total-display">
                            <span>Total Ingresos</span>
                            <strong id="total-income-display">$0</strong>
                        </div>
                        
                        <div class="total-display">
                            <span>Total Egresos</span>
                            <strong id="total-outgoings-display">$0</strong>
                        </div>
                        
                        <div class="total-display total-net-display">
                            <span>Ingresos + Egresos</span>
                            <strong id="net-balance-display">$0</strong>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-submit">Guardar Cierre de Caja</button>
                </div>
            </form>
        </div>

        <div class="content-card">
            <div class="header-with-filters">
                <h2>Últimos Cierres Registrados</h2>
                <form method="GET" class="filter-form-inline" id="filter-form">
                    <label for="month_filter">Mes:</label>
                    <select id="month_filter" name="month_filter">
                        <option value="">Últimos 10</option>
                        <?php foreach ($months as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($month_filter === $value ? 'selected' : '') ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <span style="margin-left: 10px;">o Rango:</span>
                    <div class="filter-group">
                        <label for="start_date">Desde:</label>
                        <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="filter-group">
                        <label for="end_date">Hasta:</label>
                        <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                    </div>

                    <button type="submit" name="apply_filters">Filtrar</button>
                    
                    <?php if (!empty($month_filter) || (!empty($start_date) && !empty($end_date))): ?>
                        <a href="caja.php" class="btn-clear-filters" style="text-decoration: none; color: #dc3545; font-weight: bold;">❌</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-container">
                <table class="sales-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Efectivo Inicial</th>
                            <th>Ventas POS 1</th>
                            <th>Ventas POS 2</th>
                            <th>Efectivo Final</th>
                            <th>Dep. MELI</th>
                            <th>Dep. B.Chile</th>
                            <th>Dep. B.Santander</th>
                            <th>Otras Salidas</th>
                            <th>Total Egresos</th>
                            <th>Total Ingresos</th> 
                            <th>Ingresos + Egresos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_closings)): ?>
                            <tr>
                                <td colspan="12" style="text-align: center; padding: 2rem;">No hay cierres registrados para los filtros seleccionados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_closings as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars(date("d-m-Y", strtotime($row['closing_date']))) ?></td>
                                <td>$<?= number_format($row['starting_cash'], 0, ',', '.') ?></td>
                                <td>$<?= number_format($row['pos1_sales'], 0, ',', '.') ?></td>
                                <td>$<?= number_format($row['pos2_sales'], 0, ',', '.') ?></td>
                                <td>$<?= number_format($row['ending_cash'], 0, ',', '.') ?></td>
                                <td>$<?= number_format($row['deposit_meli'] ?? 0, 0, ',', '.') ?></td> 
                                <td>$<?= number_format($row['deposit_bchile'], 0, ',', '.') ?></td>
                                <td>$<?= number_format($row['deposit_bsantander'], 0, ',', '.') ?></td>
                                <td>$<?= number_format($row['other_outgoings'] ?? 0, 0, ',', '.') ?></td>
                                <td>$<?= number_format($row['total_egresos'] ?? 0, 0, ',', '.') ?></td>
                                <td class="<?= ($row['total_ingresos'] < 0) ? 'danger-color' : '' ?>">$<?= number_format($row['total_ingresos'] ?? 0, 0, ',', '.') ?></td>
                                <td class="<?= ($row['ingresos_mas_egresos'] < 0) ? 'danger-color' : '' ?>">$<?= number_format($row['ingresos_mas_egresos'] ?? 0, 0, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // --- CÁLCULO EN VIVO DEL FORMULARIO DE CIERRE ---
        const startingCashInput = document.getElementById('starting_cash');
        const pos1Input = document.getElementById('pos1_sales');
        const pos2Input = document.getElementById('pos2_sales');
        const endingCashInput = document.getElementById('ending_cash');
        const depositMeliInput = document.getElementById('deposit_meli');
        const depositBChileInput = document.getElementById('deposit_bchile');
        const depositBSantanderInput = document.getElementById('deposit_bsantander');
        const otherOutgoingsInput = document.getElementById('other_outgoings');

        const totalIncomeDisplay = document.getElementById('total-income-display'); 
        const totalOutgoingsDisplay = document.getElementById('total-outgoings-display');
        const netBalanceDisplay = document.getElementById('net-balance-display');

        function formatCurrency(value) {
            // Usa el local de Chile (CLP) sin decimales
            return value.toLocaleString('es-CL', { style: 'currency', currency: 'CLP', minimumFractionDigits: 0 });
        }

        function calculateDailyTotals() {
            // Obtener valores como números (usa 0 si está vacío o no es un número válido)
            const startingCash = parseFloat(startingCashInput.value) || 0;
            const pos1Sales = parseFloat(pos1Input.value) || 0;
            const pos2Sales = parseFloat(pos2Input.value) || 0;
            const endingCash = parseFloat(endingCashInput.value) || 0;
            
            const depositMeli = parseFloat(depositMeliInput.value) || 0;
            const depositBChile = parseFloat(depositBChileInput.value) || 0;
            const depositBSantander = parseFloat(depositBSantanderInput.value) || 0;
            const otherOutgoings = parseFloat(otherOutgoingsInput.value) || 0;
            
            // --- CÁLCULOS SOLICITADOS ---
            
            // CÁLCULO A: Total Egresos (Suma de todos los campos de la columna Egresos)
            const totalOutgoings = depositMeli + depositBChile + depositBSantander + otherOutgoings;

            // CÁLCULO B: Total Ingresos (Flujo de Caja Neto)
            // Fórmula: (Efectivo Final - Efectivo Inicial) + Ventas POS 1 + Ventas POS 2
            const totalIncome = (endingCash - startingCash) + pos1Sales + pos2Sales;

            // CÁLCULO C: Ingresos + Egresos (Métrica Final: Total Ingresos + Total Egresos)
            const incomePlusOutgoings = totalIncome + totalOutgoings;


            // --- ACTUALIZAR VISTA ---
            
            // 1. Total Ingresos
            totalIncomeDisplay.textContent = formatCurrency(totalIncome);

            // 2. Total Egresos
            totalOutgoingsDisplay.textContent = formatCurrency(totalOutgoings);

            // 3. Ingresos + Egresos
            netBalanceDisplay.textContent = formatCurrency(incomePlusOutgoings);
            
            // Estilo para el Ingresos + Egresos
            if (incomePlusOutgoings < 0) {
                netBalanceDisplay.style.color = '#d9534f'; 
            } else {
                netBalanceDisplay.style.color = '#1ea769';
            }
        }

        // Lista de todos los inputs a escuchar
        const inputsToWatch = [
            startingCashInput, pos1Input, pos2Input, endingCashInput,
            depositMeliInput, depositBChileInput, depositBSantanderInput, otherOutgoingsInput
        ];

        // Agrega 'input' event listeners a todos los campos
        inputsToWatch.forEach(input => {
            input.addEventListener('input', calculateDailyTotals);
        });
        
        // Ejecuta el cálculo al cargar la página (útil si hay valores iniciales)
        calculateDailyTotals();

        // --- LÓGICA DE FILTRO (AHORA CON AUTO-SUBMIT) ---
        const monthFilterSelect = document.getElementById('month_filter');
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        const filterForm = document.getElementById('filter-form'); // Usamos el ID para mayor claridad

        // 1. **Auto-submit al cambiar el mes (REQUERIDO)**
        monthFilterSelect.addEventListener('change', function() {
            if (this.value) { // Solo si se selecciona un mes (no "Últimos 10")
                // Asegura que los campos de rango estén vacíos antes de enviar
                startDateInput.value = '';
                endDateInput.value = '';
                filterForm.submit();
            } else {
                 // Si se selecciona "Últimos 10", también envía el formulario
                 filterForm.submit();
            }
        });

        // 2. Clear month selection if user interacts with date range inputs.
        function clearMonthFilterOnRangeInput() {
            // Si el usuario empieza a poner fechas, borra la selección de mes
            if (startDateInput.value || endDateInput.value) {
                 monthFilterSelect.value = '';
            }
        }
        startDateInput.addEventListener('input', clearMonthFilterOnRangeInput);
        endDateInput.addEventListener('input', clearMonthFilterOnRangeInput);

        // 3. Optional: Final guard on manual range submit (click 'Aplicar Filtro')
        if (filterForm) {
            filterForm.addEventListener('submit', function(e) {
                // Si el rango está lleno, aseguramos que el mes se anule
                if (startDateInput.value && endDateInput.value) {
                    monthFilterSelect.value = '';
                }
            });
        }
    });
</script>
</body>
</html>
