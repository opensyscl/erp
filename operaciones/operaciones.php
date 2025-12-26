<?php
// Reporte de Errores
/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/

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
// RUTA CONFIGURADA: Módulo de Gastos Operacionales
$module_path = '/erp/operaciones/'; 

try {
    // Obtenemos el rol del usuario logueado
    $stmt_role = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_role->execute([$current_user_id]);
    $user_role = $stmt_role->fetchColumn();

    // LÓGICA DE ACCESO: Roles permitidos para el módulo de Caja
    // Uso de in_array para manejar múltiples POS fácilmente
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

// --- CONFIGURACIÓN DE IVA ---
$IVA_RATE = 1.19;

// --- MENSAJES DE SESIÓN ---
$message = '';
$toast_message = '';
$is_error = false;

if (isset($_SESSION['message'])) {
    $raw_message = $_SESSION['message'];
    unset($_SESSION['message']);

    if (strpos($raw_message, 'Gasto operacional registrado') !== false) {
        $toast_message = strip_tags($raw_message);
    } else {
        $message = $raw_message;
        $is_error = strpos($raw_message, 'alert error') !== false;
    }
}

// --- SELECCIÓN DE PERIODO (validación) ---
$current_ym = date('Y-m');
$selected_month = $_GET['month'] ?? $current_ym;
// Validar formato YYYY-MM
if (!preg_match('/^\d{4}-\d{2}$/', $selected_month)) {
    $selected_month = $current_ym;
}

$current_month_start = date('Y-m-01', strtotime($selected_month));
$current_month_end = date('Y-m-t', strtotime($selected_month));

$previous_month_start = date('Y-m-01', strtotime($selected_month . ' -1 month'));
$previous_month_end = date('Y-m-t', strtotime($selected_month . ' -1 month'));

// --- PROCESAMIENTO DEL FORMULARIO (PRG) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $date_paid = $_POST['date_paid'] ?? date('Y-m-d');
        $expense_type = $_POST['expense_type'] ?? 'Fijo';
        $description = $_POST['description'] ?? 'Gasto sin descripción';

        $light = (float)($_POST['light'] ?? 0);
        $water = (float)($_POST['water'] ?? 0);
        $rent = (float)($_POST['rent'] ?? 0);
        $alarm_adt = (float)($_POST['alarm_adt'] ?? 0);
        $internet_movistar = (float)($_POST['internet_movistar'] ?? 0);
        $iva = (float)($_POST['iva'] ?? 0);
        $repairs = (float)($_POST['repairs'] ?? 0);
        $supplies = (float)($_POST['supplies'] ?? 0);
        $other_variable = (float)($_POST['other_variable'] ?? 0);

        $total_amount = $light + $water + $rent + $alarm_adt + $internet_movistar + $iva + $repairs + $supplies + $other_variable;

        $stmt = $pdo->prepare("
            INSERT INTO operational_expenses (
                date_paid, expense_type, description, total_amount,
                light, water, rent, alarm_adt, internet_movistar, iva,
                repairs, supplies, other_variable
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $date_paid, $expense_type, $description, $total_amount,
            $light, $water, $rent, $alarm_adt, $internet_movistar, $iva,
            $repairs, $supplies, $other_variable
        ]);

        $_SESSION['message'] = 'Gasto operacional registrado exitosamente. Total: $' . number_format($total_amount, 0, ',', '.');
        $paid_month = date('Y-m', strtotime($date_paid));
        header("Location: " . $_SERVER['PHP_SELF'] . "?month=" . $paid_month);
        exit();
    } catch (PDOException $e) {
        $message = '<div class="alert error">Error al guardar el gasto: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// --- FUNCIONES AUXILIARES ---
function translate_month_to_spanish($month_name_en) {
    $months_en = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $months_es = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    return str_ireplace($months_en, $months_es, $month_name_en);
}

if (!function_exists('get_kpi_value')) {
    function get_kpi_value($pdo, $sql, $params) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchColumn();
        return $result ?: 0;
    }
}

// --- OBTENCIÓN DE DATOS (KPIs y tabla) ---
try {
    $params_selected = [$current_month_start, $current_month_end];
    $params_previous = [$previous_month_start, $previous_month_end];

    $base_sql = "SELECT SUM(%s) FROM operational_expenses WHERE date_paid BETWEEN ? AND ?";

    $kpis = [
        'total_month' => get_kpi_value($pdo, "SELECT SUM(total_amount) FROM operational_expenses WHERE date_paid BETWEEN ? AND ?", $params_selected),
        'total_fixed' => get_kpi_value($pdo, "SELECT SUM(total_amount) FROM operational_expenses WHERE expense_type = 'Fijo' AND date_paid BETWEEN ? AND ?", $params_selected),
        'total_variable' => get_kpi_value($pdo, "SELECT SUM(total_amount) FROM operational_expenses WHERE expense_type = 'Variable' AND date_paid BETWEEN ? AND ?", $params_selected),
        'light' => get_kpi_value($pdo, sprintf($base_sql, 'light'), $params_selected),
        'water' => get_kpi_value($pdo, sprintf($base_sql, 'water'), $params_selected),
        'rent' => get_kpi_value($pdo, sprintf($base_sql, 'rent'), $params_selected),
        'iva_credit_month' => get_kpi_value($pdo, sprintf($base_sql, 'iva'), $params_selected),
    ];

    $kpis['iva_credit_previous'] = get_kpi_value($pdo, sprintf($base_sql, 'iva'), $params_previous);

    $date = new DateTime($current_month_start);
    $date->modify('+1 month');
    $startOfNextMonth = $date->format('Y-m-d');

    $sql_iva_mensual = "
        SELECT 
            SUM((si.price * si.quantity) - (si.price * si.quantity / {$IVA_RATE})) AS total_iva
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        WHERE s.created_at >= ? AND s.created_at < ?
    ";
    $stmt_iva = $pdo->prepare($sql_iva_mensual);
    $stmt_iva->execute([$current_month_start, $startOfNextMonth]);
    $kpis['total_iva'] = $stmt_iva->fetchColumn() ?: 0;

    $recent_expenses_stmt = $pdo->prepare("
        SELECT * FROM operational_expenses 
        WHERE date_paid BETWEEN ? AND ? 
        ORDER BY date_paid DESC, id DESC LIMIT 15
    ");
    $recent_expenses_stmt->execute($params_selected);
    $recent_expenses = $recent_expenses_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- GENERACIÓN CORRECTA DE OPCIONES DE MES (desde agosto 2025 en adelante) ---
$month_options = [];

// Fecha mínima fija: agosto 2025
$min_reference_date = new DateTime('2025-08-01');
// Fecha actual (inicio del mes)
$iterator = new DateTime(date('Y-m-01'));

while ($iterator >= $min_reference_date) {
    $ym = $iterator->format('Y-m');
    $month_name_en = $iterator->format('F Y');
    $label = translate_month_to_spanish($month_name_en);

    $month_options[$ym] = [
        'label' => $label,
        'selected' => ($ym === $selected_month)
    ];

    $iterator->modify('-1 month');
}

} catch (PDOException $e) {
    $kpis = array_fill_keys(['total_month', 'total_fixed', 'total_variable', 'light', 'water', 'rent', 'iva_credit_month', 'iva_credit_previous', 'total_iva'], 0);
    $message .= '<div class="alert error">Error al cargar datos (KPIs/Tabla): ' . htmlspecialchars($e->getMessage()) . '</div>';
    $month_options = [];
}

// --- DATOS ADICIONALES ---
$current_page = 'operaciones.php';
$system_version = '1.0';
try {
    $stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
    $stmt->execute();
    $version_result = $stmt->fetchColumn();
    if ($version_result) $system_version = $version_result;
} catch (PDOException $e) {
    // silencio
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gastos Operacionales - Mi Sistema</title>
    <link rel="icon" type="image/png" href="/erp/img/fav.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/operaciones.css">
    <style>
        /* Estilos mínimos para que el toast se vea si no tienes CSS */
        .toast-notification {
            position: fixed;
            right: 20px;
            bottom: 20px;
            background: #2d7a2d;
            color: #fff;
            padding: 12px 16px;
            border-radius: 6px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.12);
            opacity: 0;
            transform: translateY(10px);
            transition: all 300ms ease;
            z-index: 1200;
        }
        .toast-notification.show { opacity: 1; transform: translateY(0); }
    </style>
</head>
<body>
<header class="main-header">
    <div class="header-left">
        <a href="../launcher.php" class="launcher-icon" title="Ir al Lanzador de Aplicaciones" aria-label="Lanzador">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="5" cy="5" r="3"/><circle cx="12" cy="5" r="3"/><circle cx="19" cy="5" r="3"/>
                <circle cx="5" cy="12" r="3"/><circle cx="12" cy="12" r="3"/><circle cx="19" cy="12" r="3"/>
                <circle cx="5" cy="19" r="3"/><circle cx="12" cy="19" r="3"/><circle cx="19" cy="19" r="3"/>
            </svg>
        </a>
        <span>Hola, <strong><?= htmlspecialchars($_SESSION['user_username'] ?? 'Usuario') ?></strong></span>
    </div>
    <nav class="header-nav"><a href="operaciones.php" class="active">Gastos Operacionales</a></nav>
    <div class="header-right">
        <span class="app-version"><?= htmlspecialchars($system_version) ?></span>
        <a href="../logout.php" class="btn-logout">Cerrar Sesión</a>
    </div>
</header>

<main class="container">
    <?= $message ?>

    <div class="kpi-grid kpi-grid-8">
        <div class="kpi-card"><h3>Gasto Total (Mes)</h3><p class="value danger-color">$<?= number_format($kpis['total_month'], 0, ',', '.') ?></p></div>
        <div class="kpi-card"><h3>Gastos Fijos</h3><p class="value">$<?= number_format($kpis['total_fixed'], 0, ',', '.') ?></p></div>
        <div class="kpi-card"><h3>Gastos Variables</h3><p class="value">$<?= number_format($kpis['total_variable'], 0, ',', '.') ?></p></div>
        <div class="kpi-card"><h3>Luz</h3><p class="value">$<?= number_format($kpis['light'], 0, ',', '.') ?></p></div>
        <div class="kpi-card"><h3>Agua</h3><p class="value">$<?= number_format($kpis['water'], 0, ',', '.') ?></p></div>
        <div class="kpi-card"><h3>Arriendo</h3><p class="value">$<?= number_format($kpis['rent'], 0, ',', '.') ?></p></div>
        <div class="kpi-card"><h3>IVA Crédito (Mes)</h3><p class="value iva-color">$<?= number_format($kpis['iva_credit_month'], 0, ',', '.') ?></p></div>
        <div class="kpi-card"><h3>IVA Débito (Ventas)</h3><p class="value iva-color">$<?= number_format($kpis['total_iva'], 0, ',', '.') ?></p></div>
        <div class="kpi-card full-width-kpi"><h3>IVA Crédito (Mes anterior)</h3><p class="value">$<?= number_format($kpis['iva_credit_previous'], 0, ',', '.') ?></p></div>
    </div>

    <div class="content-card">
        <div class="form-header-row">
            <h2>Registrar Nuevo Gasto Operacional</h2>
            <div class="month-selector-container">
                <label for="month_selector">Datos mostrados para:</label>
                <select id="month_selector" onchange="window.location.href='operaciones.php?month='+this.value">
                    <?php foreach ($month_options as $ym => $data): ?>
                        <option value="<?= htmlspecialchars($ym) ?>" <?= $data['selected'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($data['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <form action="operaciones.php" method="POST" novalidate>
            <div class="form-row-2-cols">
                <div class="form-group">
                    <label for="date_paid">Fecha de Pago</label>
                    <input type="date" id="date_paid" name="date_paid" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label for="description">Descripción</label>
                    <input type="text" id="description" name="description" placeholder="Ej: Gastos Septiembre" required>
                </div>
            </div>

            <div class="form-grid-operaciones">
                <div class="expense-category fixed-expenses-grid">
                    <h4>Gastos Fijos 🏠</h4>
                    <div class="form-group"><label for="rent">Arriendo</label><input type="number" id="rent" name="rent" value="0" min="0"></div>
                    <div class="form-group"><label for="light">Luz</label><input type="number" id="light" name="light" value="0" min="0"></div>
                    <div class="form-group"><label for="water">Agua</label><input type="number" id="water" name="water" value="0" min="0"></div>
                    <div class="form-group"><label for="alarm_adt">Alarma</label><input type="number" id="alarm_adt" name="alarm_adt" value="0" min="0"></div>
                    <div class="form-group"><label for="internet_movistar">Internet</label><input type="number" id="internet_movistar" name="internet_movistar" value="0" min="0"></div>
                    <div class="form-group"><label for="iva">IVA (Crédito Fiscal)</label><input type="number" id="iva" name="iva" value="0" min="0"></div>
                    <input type="hidden" name="expense_type" id="expense_type_fijo" value="Fijo">
                </div>

                <div class="expense-category variable-expenses-grid">
                    <h4>Gastos Variables 🛠️</h4>
                    <div class="form-group"><label for="repairs">Reparaciones</label><input type="number" id="repairs" name="repairs" value="0" min="0"></div>
                    <div class="form-group"><label for="supplies">Suministros</label><input type="number" id="supplies" name="supplies" value="0" min="0"></div>
                    <div class="form-group"><label for="other_variable">Otros Gastos Variables</label><input type="number" id="other_variable" name="other_variable" value="0" min="0"></div>

                    <div class="expense-type-group">
                        <label><input type="radio" name="expense_type_radio" value="Fijo" checked> Fijo</label>
                        <label><input type="radio" name="expense_type_radio" value="Variable"> Variable</label>
                    </div>
                </div>

                <div class="form-footer-actions">
                    <div class="expense-total-display">
                        <span>Total Gasto a Registrar</span>
                        <strong id="total-expense-display">$0</strong>
                        <input type="hidden" name="total_amount_check" id="total_amount_check" value="0">
                    </div>
                    <button type="submit" class="btn-submit">Guardar Gasto Operacional</button>
                </div>
            </div>
        </form>
    </div>

    <div class="content-card">
        <h2>Últimos Gastos Registrados (Mes Seleccionado)</h2>
        <div class="table-container">
            <table class="sales-table">
                <thead>
                    <tr>
                        <th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Total</th>
                        <th>Luz</th><th>Agua</th><th>Arriendo</th><th>Alarma</th><th>Internet</th>
                        <th>IVA</th><th>Reparaciones</th><th>Suministros</th><th>Otros Var.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_expenses)): ?>
                        <tr><td colspan="13" style="text-align:center;padding:2rem;">No hay gastos registrados para este mes.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recent_expenses as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars(date("d-m-Y", strtotime($row['date_paid']))) ?></td>
                                <td><?= htmlspecialchars($row['expense_type']) ?></td>
                                <td><?= htmlspecialchars($row['description']) ?></td>
                                <td style="font-weight:600;">$<?= number_format($row['total_amount'], 0, ',', '.') ?></td>
                                <td>$<?= number_format($row['light'], 0, ',', '.') ?></td>
                                <td>$<?= number_format($row['water'], 0, ',', '.') ?></td>
                                <td>$<?= number_format($row['rent'], 0, ',', '.') ?></td>
                                <td>$<?= number_format($row['alarm_adt'], 0, ',', '.') ?></td>
                                <td>$<?= number_format($row['internet_movistar'], 0, ',', '.') ?></td>
                                <td>$<?= number_format($row['iva'], 0, ',', '.') ?></td>
                                <td>$<?= number_format($row['repairs'], 0, ',', '.') ?></td>
                                <td>$<?= number_format($row['supplies'], 0, ',', '.') ?></td>
                                <td>$<?= number_format($row['other_variable'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>



<!-- JS inline: calcula totales y muestra toast -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    // Toast
    const toastMessage = "<?= addslashes($toast_message) ?>";
    if (toastMessage && toastMessage.length > 0) {
        const toast = document.createElement('div');
        toast.className = 'toast-notification';
        toast.textContent = toastMessage;
        document.body.appendChild(toast);
        // show
        setTimeout(() => toast.classList.add('show'), 80);
        setTimeout(() => toast.classList.remove('show'), 4000);
        setTimeout(() => toast.remove(), 4600);
    }

    // Inputs
    const ids = ['light','water','rent','alarm_adt','internet_movistar','iva','repairs','supplies','other_variable'];
    const inputs = ids.map(id => document.getElementById(id));
    const totalDisplay = document.getElementById('total-expense-display');
    const totalAmountCheck = document.getElementById('total_amount_check');

    function formatCurrency(value) {
        return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', minimumFractionDigits: 0 }).format(value);
    }

    function calculateTotalExpense() {
        let total = 0;
        inputs.forEach(el => {
            const val = parseFloat(el.value) || 0;
            total += val;
        });
        totalDisplay.textContent = formatCurrency(total);
        totalAmountCheck.value = total.toFixed(2);
    }

    inputs.forEach(el => {
        if (el) el.addEventListener('input', calculateTotalExpense);
    });

    // expense type radio -> hidden
    const radios = document.querySelectorAll('input[name="expense_type_radio"]');
    const hiddenType = document.getElementById('expense_type_fijo');
    function updateExpenseType() {
        const sel = Array.from(radios).find(r => r.checked);
        if (sel && hiddenType) hiddenType.value = sel.value;
    }
    radios.forEach(r => r.addEventListener('change', updateExpenseType));

    // Inicialización
    calculateTotalExpense();
    updateExpenseType();
});
</script>
</body>
</html>
