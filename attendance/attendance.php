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
// RUTA CONFIGURADA: Módulo de Asistencias
$module_path = '/erp/attendance/'; 

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
// ...

// ----------------------------------------------------------------------
// --- 0. MANEJADOR DE PETICIONES AJAX (CHECK-IN KIOSK) ---
// ----------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'Acción no válida.'];

    try {
        // --- ACCIÓN 1: Validar RUT y devolver user_id ---
        if ($_POST['action'] === 'check_rut' && isset($_POST['rut'])) {
            $rut_cleaned = preg_replace('/[^0-9kK]/', '', $_POST['rut']);
            
            $stmt = $pdo->prepare("SELECT id FROM users WHERE rut = ?");
            $stmt->execute([$rut_cleaned]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $response = [
                    'status' => 'success_rut',
                    'user_id' => $user['id'],
                    'name' => htmlspecialchars($_POST['rut'])
                ];
            } else {
                $response = ['status' => 'error', 'message' => 'RUT no encontrado. Verifica los datos.'];
            }
        }

        // --- ACCIÓN 2: Validar PIN y Obtener Estatus de Asistencia de Hoy ---
        if ($_POST['action'] === 'check_pin_and_status' && isset($_POST['user_id'], $_POST['pin'])) {
            $user_id_kiosk = $_POST['user_id'];
            $pin_attempt = $_POST['pin'];
            $today_date = date('Y-m-d');

            // 1. Verificar PIN
            $stmt_pass = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt_pass->execute([$user_id_kiosk]);
            $user_data = $stmt_pass->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data && password_verify($pin_attempt, $user_data['password'])) {
                
                // 2. PIN Correcto. Obtener el registro de asistencia de hoy.
                // IMPORTANTE: Se asume que la tabla 'attendance' ahora tiene check_in, lunch_out, lunch_in, check_out
                $stmt_status = $pdo->prepare("
                    SELECT 
                        id,
                        check_in, 
                        lunch_out, 
                        lunch_in, 
                        check_out
                    FROM attendance 
                    WHERE user_id = ? 
                    AND DATE(check_in) = ?
                ");
                $stmt_status->execute([$user_id_kiosk, $today_date]);
                $record = $stmt_status->fetch(PDO::FETCH_ASSOC);

                // Si no hay registro o si el check_in es null, creamos un registro temporal para asegurar el ID
                if (!$record) {
                    $record = [
                        'id' => null,
                        'check_in' => null, 
                        'lunch_out' => null, 
                        'lunch_in' => null, 
                        'check_out' => null
                    ];
                }

                $response = [
                    'status' => 'pin_ok',
                    'message' => 'PIN Validado.',
                    'attendance_id' => $record['id'],
                    'attendance_status' => [
                        'check_in' => $record['check_in'] ? date('H:i:s', strtotime($record['check_in'])) : null,
                        'lunch_out' => $record['lunch_out'] ? date('H:i:s', strtotime($record['lunch_out'])) : null,
                        'lunch_in' => $record['lunch_in'] ? date('H:i:s', strtotime($record['lunch_in'])) : null,
                        'check_out' => $record['check_out'] ? date('H:i:s', strtotime($record['check_out'])) : null,
                    ]
                ];

            } else {
                // PIN Incorrecto
                $response = ['status' => 'error', 'message' => 'PIN/Clave incorrecta. Intenta de nuevo.'];
            }
        }

        // --- ACCIÓN 3: Registrar el Evento Seleccionado ---
        if ($_POST['action'] === 'register_event' && isset($_POST['user_id'], $_POST['event_type'])) {
            $user_id_kiosk = $_POST['user_id'];
            $event_type = $_POST['event_type'];
            $attendance_id = $_POST['attendance_id'] ?? null;
            $today_date = date('Y-m-d');
            $now = date('Y-m-d H:i:s');
            $column_name = $event_type; // check_in, lunch_out, lunch_in, check_out

            // Mensajes de éxito
            $messages = [
                'check_in' => '✅ Entrada registrada correctamente. ¡Bienvenido/a!',
                'lunch_out' => '🍔 Salida a Colación registrada. ¡Que disfrutes!',
                'lunch_in' => '💼 Entrada a Jornada registrada. ¡De vuelta al trabajo!',
                'check_out' => '👋 Salida de Turno registrada. ¡Hasta mañana!',
            ];

            // A. Registrar el primer evento (check_in)
            if ($event_type === 'check_in') {
                $stmt_check = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND DATE(check_in) = ?");
                $stmt_check->execute([$user_id_kiosk, $today_date]);
                if ($stmt_check->fetchColumn()) {
                    $response = ['status' => 'error', 'message' => '¡Error! Ya registraste tu Entrada de hoy.'];
                } else {
                    $stmt_insert = $pdo->prepare("INSERT INTO attendance (user_id, check_in) VALUES (?, ?)");
                    if ($stmt_insert->execute([$user_id_kiosk, $now])) {
                        $response = ['status' => 'success_register', 'message' => $messages[$event_type]];
                    } else {
                        $response = ['status' => 'error', 'message' => '❌ Error al insertar el registro de entrada.'];
                    }
                }
            } 
            // B. Registrar eventos subsiguientes (UPDATE)
            elseif ($attendance_id) {
                // Verificar si la columna ya tiene un valor (no permitir doble registro del mismo evento)
                $stmt_check_col = $pdo->prepare("SELECT {$column_name} FROM attendance WHERE id = ?");
                $stmt_check_col->execute([$attendance_id]);
                if ($stmt_check_col->fetchColumn()) {
                     $response = ['status' => 'error', 'message' => "¡Error! La {$event_type} ya fue registrada hoy."];
                } else {
                    $stmt_update = $pdo->prepare("UPDATE attendance SET {$column_name} = ? WHERE id = ?");
                    if ($stmt_update->execute([$now, $attendance_id])) {
                        $response = ['status' => 'success_register', 'message' => $messages[$event_type]];
                    } else {
                        $response = ['status' => 'error', 'message' => "❌ Error al actualizar {$event_type}. Asegúrate de seguir el orden."];
                    }
                }
            } else {
                 $response = ['status' => 'error', 'message' => '❌ Debe registrar la Entrada antes de cualquier otro evento.'];
            }
        }

    } catch (PDOException $e) {
        // error_log("Error AJAX Check-in: " . $e->getMessage()); // Log real
        $response = ['status' => 'error', 'message' => '❌ Error de base de datos. Contacta a soporte: ' . $e->getMessage()];
    }

    echo json_encode($response);
    exit(); // Detener la ejecución para no renderizar el HTML
}


// Redireccionar si el usuario no está logueado (para la carga de la página)
if (!isset($_SESSION['user_username'])) {
    header('Location: ../login.php');
    exit();
}

// Inicializar la conexión PDO si no es una petición AJAX
if (!isset($pdo)) {
    die('Error: PDO connection not established.');
}

// --- CONFIGURACIÓN DE HORARIOS ---
const ON_TIME_LIMIT = '09:00:00';
$ON_TIME_LIMIT_FORMATTED = date('H:i', strtotime(ON_TIME_LIMIT));

// Variables para el reporte (para el usuario logueado)
$user_id_session = $_SESSION['user_id'];
$registration_message = '';

// ----------------------------------------------------------------------
// --- 1. OBTENER Y VALIDAR EL RANGO DE FECHAS O EL MES (FILTRO) ---
// ----------------------------------------------------------------------

$customStartDate = $_GET['start_date'] ?? '';
$customEndDate = $_GET['end_date'] ?? '';
$isCustomRange = false;

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $customStartDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $customEndDate)) {
    if (strtotime($customStartDate) > strtotime($customEndDate)) {
        $customStartDate = '';
        $customEndDate = '';
    } else {
        $startDate = $customStartDate;
        $endDate = $customEndDate;
        $selectedMonthYear = '';
        $isCustomRange = true;
    }
}

if (!$isCustomRange) {
    $selectedMonthYear = $_GET['month'] ?? date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonthYear)) {
        $selectedMonthYear = date('Y-m');
    }
    $startOfMonth = date('Y-m-01', strtotime($selectedMonthYear . '-01'));
    $endOfMonth = date('Y-m-t', strtotime($selectedMonthYear . '-01'));
    $startDate = $startOfMonth;
    $endDate = $endOfMonth;
}

// ----------------------------------------------------------------------
// --- 2. CÁLCULO DE MÉTRICAS (KPIs) PARA EL USUARIO LOGUEADO ---
// ----------------------------------------------------------------------

try {
    // Total de Días Registrados (basado en check_in no nulo)
    $stmt_total_checkins = $pdo->prepare("
        SELECT COUNT(id)
        FROM attendance
        WHERE DATE(check_in) BETWEEN ? AND ?
        AND user_id = ?
        AND check_in IS NOT NULL
    ");
    $stmt_total_checkins->execute([$startDate, $endDate, $user_id_session]);
    $total_days_registered = $stmt_total_checkins->fetchColumn() ?: 0;

    // Total de Registros 'A Tiempo'
    $stmt_on_time = $pdo->prepare("
        SELECT COUNT(id)
        FROM attendance
        WHERE DATE(check_in) BETWEEN ? AND ?
        AND TIME(check_in) <= ?
        AND user_id = ?
        AND check_in IS NOT NULL
    ");
    $stmt_on_time->execute([$startDate, $endDate, ON_TIME_LIMIT, $user_id_session]);
    $total_on_time = $stmt_on_time->fetchColumn() ?: 0;

    // Total de Días Completos (check_in, lunch_out, lunch_in, check_out no nulos)
    $stmt_full_days = $pdo->prepare("
        SELECT COUNT(id)
        FROM attendance
        WHERE DATE(check_in) BETWEEN ? AND ?
        AND user_id = ?
        AND check_in IS NOT NULL
        AND lunch_out IS NOT NULL
        AND lunch_in IS NOT NULL
        AND check_out IS NOT NULL
    ");
    $stmt_full_days->execute([$startDate, $endDate, $user_id_session]);
    $total_full_days = $stmt_full_days->fetchColumn() ?: 0;
    
} catch (PDOException $e) {
    $total_days_registered = 0;
    $total_on_time = 0;
    $total_full_days = 0;
    error_log("Error al calcular KPIs de asistencia: " . $e->getMessage());
}

$total_late = $total_days_registered - $total_on_time;
$punctuality_percentage = ($total_days_registered > 0) ? round(($total_on_time / $total_days_registered) * 100) : 0;
$punctuality_class = 'default-color';
if ($punctuality_percentage >= 90) {
    $punctuality_class = 'success-color';
} elseif ($punctuality_percentage >= 70) {
    $punctuality_class = 'warning-color';
} else {
    $punctuality_class = 'danger-color';
}

// -----------------------------------------------------------------------------
// --- 3. OBTENER DATOS DE ASISTENCIA PARA LA TABLA (USUARIO LOGUEADO) ---
// -----------------------------------------------------------------------------
$attendance_data_full = [];
try {
    $stmt_attendance_data = $pdo->prepare("
        SELECT
            id, 
            check_in, 
            lunch_out, 
            lunch_in, 
            check_out,
            TIME(check_in) > ? AS is_late
        FROM attendance
        WHERE DATE(check_in) BETWEEN ? AND ?
        AND user_id = ?
        ORDER BY check_in DESC;
    ");
    $stmt_attendance_data->execute([ON_TIME_LIMIT, $startDate, $endDate, $user_id_session]);
    $attendance_data_full = $stmt_attendance_data->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener datos de asistencia: " . $e->getMessage());
}


// --- 4. GENERACIÓN DE OPCIONES DE MESES PARA EL SELECTOR ---
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

// Variables para el encabezado
$stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
$stmt->execute();
$system_version = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Asistencia - Listto! ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" type="image/png" href="img/fav.png">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/attendance.css" rel="stylesheet">

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
   <a href="attendance.php" class="active">Registro de Asistencias</a>
  </nav>
  <div class="header-right">
   <span class="app-version"><?php echo htmlspecialchars($system_version); ?></span>
   <a href="../logout.php" class="btn-logout">Cerrar Sesi&oacute;n</a>
  </div>
 </header>

    <main class="container">

        <!-- 1. TÍTULO DE LA PÁGINA
        <div class="page-header-controls">
            <h1 class="page-title">Reporte de Asistencia</h1>
        </div> -->
        
        <!-- 2. TARJETAS DE KPIS -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <h3>Días Registrados</h3>
                <p class="value default-color"><?= number_format($total_days_registered, 0, ',', '.') ?></p>
            </div>
            <div class="kpi-card">
                <h3>Registros 'A Tiempo'</h3>
                <p class="value success-color"><?= number_format($total_on_time, 0, ',', '.') ?></p>
            </div>
            <div class="kpi-card">
                <h3>Días Completados</h3>
                <p class="value default-color"><?= number_format($total_full_days, 0, ',', '.') ?></p>
            </div>
            <div class="kpi-card">
                <h3>Mi Puntualidad (%)</h3>
                <p class="value <?= htmlspecialchars($punctuality_class) ?>"><?= $punctuality_percentage ?>%</p>
            </div>
        </div>

        <!-- 3. SECCIÓN DE CHECK-IN (KIOSCO) -->
        <div class="check-in-section">
            <h2>Registro de Asistencia (Quiosco)</h2>
            <p>Ingresa tu RUT y PIN para registrar tu evento de hoy (<?= date('d/m/Y') ?>)</p>
            
            <!-- Mensaje de estado (para RUT y éxito final) -->
            <div id="check-in-message" class="alert"></div>
            
            <form id="rut-form">
                <input type="text" id="rut-input" placeholder="Ej: 12.345.678-9" required>
                <button type="submit" id="rut-submit-btn">
                    <span class="loader"></span>
                    <span>Siguiente</span>
                </button>
            </form>
        </div>

        <!-- 4. TABLA DE DETALLE (Ahora incluye los filtros) -->
        <div class="content-card">
            
            <!-- CONTROLES DE FILTRO (MOVIDOS AQUÍ) -->
            <div class="filter-controls-container">
                <div class="filter-controls-group">

                    <div class="month-selector-container">
                        <label for="month-selector">Filtrar por Mes:</label>
                        <select id="month-selector" onchange="window.location.href = 'attendance.php?month=' + this.value">
                            <option value="">Seleccione Mes</option>
                            <?php
                            foreach ($monthOptions as $value => $label) {
                                $selected = (!$isCustomRange && $value === $selectedMonthYear) ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($value) . "\" {$selected}>" . htmlspecialchars($label) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <form id="range-filter-form" class="range-selector-styled" action="attendance.php" method="GET">
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
                        <button type="submit" class="btn-filter">Filtrar</button>
                        <?php if ($isCustomRange): ?>
                            <button type="button" class="btn-reset" onclick="window.location.href = 'attendance.php'">
                                Reset
                            </button>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Toast de Resumen de Filtro -->
                <p class="filter-toast">
                    <?php
                    if ($isCustomRange) {
                        echo "Filtrando: <strong>" . date('d/m/Y', strtotime($startDate)) . "</strong> a <strong>" . date('d/m/Y', strtotime($endDate)) . "</strong> (Rango Personalizado)";
                    } elseif ($selectedMonthYear) {
                        // El reporte de KPIs y tabla siempre muestra los datos del usuario logueado
                        echo "Mostrando tu historial para: <strong>" . htmlspecialchars($monthOptions[$selectedMonthYear] ?? 'Mes Actual') . "</strong>";
                    }
                    ?>
                </p>
            </div>
            <!-- FIN DE CONTROLES DE FILTRO -->

            <div class="table-header-controls">
                <h2>Detalle de Asistencia (Mi Historial)</h2>
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
                <table class="sales-table attendance-table">
                    <thead>
                        <tr>
                            <th data-sort-column="check_in" class="sort-desc">Fecha</th>
                            <th>Entrada</th>
                            <th>Salida Colación</th>
                            <th>Entrada Jornada</th>
                            <th>Salida Turno</th>
                            <th>Duración Almuerzo</th>
                            <th>Horas Netas</th>
                            <th>Puntualidad</th>
                        </tr>
                    </thead>
                    <tbody id="attendance-table-body">
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2rem;">Cargando registros...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- ================================================================== -->
    <!-- --- MODAL DE INGRESO DE PIN Y SELECCIÓN DE EVENTO --- -->
    <!-- ================================================================== -->
    <div id="pin-modal-overlay">
        <div id="pin-modal">
            <p id="pin-user-greeting">Hola,</p>
            <h3 id="pin-user-name">Nombre de Usuario</h3>

            <!-- VISTA 1: Ingreso de PIN -->
            <div id="pin-input-view" class="modal-view active">
                <p>Ingresa tu clave PIN de 4 dígitos:</p>
                
                <div id="pin-display"></div>
                <input type="hidden" id="pin-input" maxlength="4">
                
                <div id="pin-message"></div>

                <div id="keyboard-container">
                    <button class="keyboard-btn" data-key="1">1</button>
                    <button class="keyboard-btn" data-key="2">2</button>
                    <button class="keyboard-btn" data-key="3">3</button>
                    <button class="keyboard-btn" data-key="4">4</button>
                    <button class="keyboard-btn" data-key="5">5</button>
                    <button class="keyboard-btn" data-key="6">6</button>
                    <button class="keyboard-btn" data-key="7">7</button>
                    <button class="keyboard-btn" data-key="8">8</button>
                    <button class="keyboard-btn" data-key="9">9</button>
                    <button class="keyboard-btn action-key" data-key="clear">Limpiar</button>
                    <button class="keyboard-btn" data-key="0">0</button>
                    <button class="keyboard-btn action-key" data-key="back">⌫</button>
                </div>

                <div id="pin-actions">
                    <button type="button" id="pin-cancel-btn">Cancelar</button>
                    <button type="button" id="pin-validate-btn">
                        <span class="loader"></span>
                        <span>Validar PIN</span>
                    </button>
                </div>
            </div>

            <!-- VISTA 2: Selección de Evento -->
            <div id="event-selector-view" class="modal-view">
                <div id="event-message-container"></div>
                <p>Selecciona la acción a registrar ahora:</p>
                
                <!-- Lista de estatus del día -->
                <div id="event-status-display" class="event-status-list"></div>

                <div id="event-buttons-grid">
                    <button class="event-btn" data-event-type="check_in" disabled>
                        Entrada
                    </button>
                    <button class="event-btn" data-event-type="lunch_out" disabled>
                        Salida a Colación
                    </button>
                    <button class="event-btn" data-event-type="lunch_in" disabled>
                        Entrada a Jornada
                    </button>
                    <button class="event-btn" data-event-type="check_out" disabled>
                        Salida de Turno
                    </button>
                </div>
                <button type="button" id="event-cancel-btn" class="btn-logout" style="margin-top: 15px; width: 100%; background-color: #f3f4f6; color: var(--text-secondary);">
                    Volver a RUT
                </button>
            </div>
        </div>
    </div>


    <script>
    // --- LÓGICA DE TABLA Y FILTROS (EXISTENTE Y MODIFICADA) ---
    const attendanceData = <?= json_encode($attendance_data_full); ?>;
    const onTimeLimit = '<?= ON_TIME_LIMIT ?>';
    
    let currentSortColumn = 'check_in';
    let currentSortDirection = 'desc';

    /**
     * Calcula la diferencia de tiempo entre dos timestamps en formato HH:MM
     * @param {string} start - Hora de inicio (e.g., '08:00:00' o '2025-01-01 08:00:00')
     * @param {string} end - Hora de fin (e.g., '09:00:00' o '2025-01-01 09:00:00')
     * @returns {string} Duración en formato "Xh Ym" o '-'
     */
    const calculateDuration = (start, end) => {
        if (!start || !end) return '-';

        let startDate = new Date(start.replace(' ', 'T'));
        let endDate = new Date(end.replace(' ', 'T'));
        
        // Manejar solo los campos de tiempo si son horas simples (no DATETIME)
        if (start.length <= 8) {
            const today = new Date().toISOString().slice(0, 10);
            startDate = new Date(`${today}T${start}`);
            endDate = new Date(`${today}T${end}`);
        }

        const diffMs = endDate - startDate;
        if (diffMs < 0) return 'Error';

        const diffSec = Math.floor(diffMs / 1000);
        const hours = Math.floor(diffSec / 3600);
        const minutes = Math.floor((diffSec % 3600) / 60);

        return `${hours}h ${minutes}m`;
    };

    /**
     * Calcula las horas netas trabajadas (Total - Almuerzo)
     * @param {object} record - Registro de asistencia con 4 timestamps
     * @returns {string} Duración neta en formato "Xh Ym" o '-'
     */
    const calculateNetHours = (record) => {
        const { check_in, lunch_out, lunch_in, check_out } = record;

        if (!check_in || !check_out) return '-';

        let totalMs = new Date(check_out.replace(' ', 'T')) - new Date(check_in.replace(' ', 'T'));
        
        if (lunch_out && lunch_in) {
            let lunchMs = new Date(lunch_in.replace(' ', 'T')) - new Date(lunch_out.replace(' ', 'T'));
            totalMs -= lunchMs;
        }

        if (totalMs < 0) return 'Error';

        const totalSec = Math.floor(totalMs / 1000);
        const hours = Math.floor(totalSec / 3600);
        const minutes = Math.floor((totalSec % 3600) / 60);

        return `${hours}h ${minutes}m`;
    };

    const sortData = (data, column, direction) => {
        return data.sort((a, b) => {
            let aVal = a[column];
            let bVal = b[column];
            
            // Convertir a Date objetos para comparación
            const dateA = aVal ? new Date(aVal.replace(' ', 'T')) : (column === 'check_in' ? new Date(0) : null);
            const dateB = bVal ? new Date(bVal.replace(' ', 'T')) : (column === 'check_in' ? new Date(0) : null);
            
            let comparison = 0;

            if (dateA && dateB) {
                comparison = dateA - dateB;
            } else if (dateA) {
                comparison = 1; // Nulls last
            } else if (dateB) {
                comparison = -1; // Nulls last
            }
            
            // Para el check_in (columna por defecto), los nulos deberían ir al final si es ASC, o al inicio si es DESC
            if (column === 'check_in') {
                if (aVal === null) return direction === 'desc' ? 1 : -1;
                if (bVal === null) return direction === 'desc' ? -1 : 1;
            }

            return direction === 'asc' ? comparison : -comparison;
        });
    };

    const updateTable = () => {
        const limit = document.getElementById('limit').value;
        const tableBody = document.getElementById('attendance-table-body');
        tableBody.innerHTML = '';
        let sortedData = sortData([...attendanceData], currentSortColumn, currentSortDirection);
        let finalData = (limit === 'all') ? sortedData : sortedData.slice(0, parseInt(limit));

        if (!finalData || finalData.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 2rem; color: #6b7280;">No hay registros de asistencia para este rango.</td></tr>`;
            return;
        }

        finalData.forEach(record => {
            const row = document.createElement('tr');
            
            const checkInTime = record.check_in ? new Date(record.check_in.replace(' ', 'T')).toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' }) : '-';
            const lunchOutTime = record.lunch_out ? new Date(record.lunch_out.replace(' ', 'T')).toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' }) : '-';
            const lunchInTime = record.lunch_in ? new Date(record.lunch_in.replace(' ', 'T')).toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' }) : '-';
            const checkOutTime = record.check_out ? new Date(record.check_out.replace(' ', 'T')).toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' }) : '-';

            const formattedDate = record.check_in ? new Date(record.check_in.replace(' ', 'T')).toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' }) : 'N/A';
            const punctualityTime = record.check_in ? record.check_in.split(' ')[1] : '99:99:99';
            const isLate = punctualityTime > onTimeLimit;
            const statusClass = isLate ? 'late-color' : 'on-time-color';
            const statusText = isLate ? 'Tarde' : 'A Tiempo';

            const lunchDuration = calculateDuration(record.lunch_out, record.lunch_in);
            const netHours = calculateNetHours(record);

            row.innerHTML = `
                <td>${formattedDate}</td>
                <td>${checkInTime}</td>
                <td>${lunchOutTime}</td>
                <td>${lunchInTime}</td>
                <td>${checkOutTime}</td>
                <td><span class="time-badge">${lunchDuration}</span></td>
                <td><span class="time-badge">${netHours}</span></td>
                <td><span class="${statusClass}">${statusText}</span></td>
            `;
            tableBody.appendChild(row);
        });

        const headers = document.querySelectorAll('.attendance-table th');
        headers.forEach(header => header.classList.remove('sort-asc', 'sort-desc'));
        const currentHeader = document.querySelector(`.attendance-table th[data-sort-column="${currentSortColumn}"]`);
        if (currentHeader) currentHeader.classList.add(`sort-${currentSortDirection}`);
    };

    const setupTableHeaders = () => {
        const headers = document.querySelectorAll('.attendance-table th[data-sort-column]');
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', function() {
                const column = this.getAttribute('data-sort-column');
                if (currentSortColumn === column) {
                    currentSortDirection = (currentSortDirection === 'asc') ? 'desc' : 'asc';
                } else {
                    currentSortColumn = column;
                    currentSortDirection = (column === 'check_in') ? 'desc' : 'asc';
                }
                updateTable();
            });
        });
    };
    
    const handleRangeFormSubmission = (event) => {
        const start = document.getElementById('start_date').value;
        const end = document.getElementById('end_date').value;
        if ((start && !end) || (!start && end)) {
            event.preventDefault();
            console.error('Ambas fechas (inicio y fin) son requeridas.');
        }
        if (start && end && new Date(start) > new Date(end)) {
            event.preventDefault();
            console.error('La fecha de inicio no puede ser posterior a la fecha de fin.');
        }
        if (start && end) {
            const form = event.target;
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.delete('month');
            form.action = currentUrl.pathname;
        }
    };

    // --- LÓGICA DEL KIOSCO DE PIN (NUEVA) ---

    let currentUserId = null;
    let currentAttendanceId = null;
    let currentAttendanceStatus = {};
    const MAX_PIN_LENGTH = 4; 

    const rutForm = document.getElementById('rut-form');
    const rutInput = document.getElementById('rut-input');
    const rutSubmitBtn = document.getElementById('rut-submit-btn');
    const checkInMessage = document.getElementById('check-in-message');
    
    const pinModalOverlay = document.getElementById('pin-modal-overlay');
    const pinUserName = document.getElementById('pin-user-name');
    const pinDisplay = document.getElementById('pin-display');
    const pinInput = document.getElementById('pin-input');
    const pinMessage = document.getElementById('pin-message');
    const keyboardContainer = document.getElementById('keyboard-container');
    const pinCancelBtn = document.getElementById('pin-cancel-btn');
    const pinValidateBtn = document.getElementById('pin-validate-btn');
    const eventCancelBtn = document.getElementById('event-cancel-btn');
    const eventMessageContainer = document.getElementById('event-message-container');

    const pinInputView = document.getElementById('pin-input-view');
    const eventSelectorView = document.getElementById('event-selector-view');
    const eventStatusDisplay = document.getElementById('event-status-display');
    const eventButtons = document.querySelectorAll('.event-btn');

    const EVENTS_MAP = {
        'check_in': { label: 'Entrada', next: 'lunch_out', color: 'success' },
        'lunch_out': { label: 'Salida a Colación', next: 'lunch_in', color: 'warning' },
        'lunch_in': { label: 'Entrada a Jornada', next: 'check_out', color: 'info' },
        'check_out': { label: 'Salida de Turno', next: null, color: 'danger' }
    };


    const formatRut = (rut) => {
        rut = rut.replace(/[^0-9kK]/g, '');
        let dv = rut.slice(-1);
        let cuerpo = rut.slice(0, -1);
        
        let rutFormateado = '';
        if (cuerpo.length > 0) {
            cuerpo = cuerpo.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }
        if (rut.length > 0) {
            rutFormateado = cuerpo + '-' + dv;
        }
        return rutFormateado;
    };

    rutInput.addEventListener('keyup', () => {
        rutInput.value = formatRut(rutInput.value);
    });

    /**
     * Muestra una vista específica del modal
     */
    const showModalView = (viewId) => {
        document.querySelectorAll('.modal-view').forEach(view => {
            view.classList.remove('active');
        });
        document.getElementById(viewId).classList.add('active');
    };

    /**
     * Muestra el modal de PIN y lo inicializa
     */
    const showPinModal = (userId, name) => {
        currentUserId = userId;
        pinUserName.textContent = name;
        pinModalOverlay.style.display = 'flex';
        clearPin();
        showModalView('pin-input-view');
    };

    /**
     * Oculta el modal y reinicia la vista principal
     */
    const hidePinModal = () => {
        pinModalOverlay.style.display = 'none';
        currentUserId = null;
        currentAttendanceId = null;
        currentAttendanceStatus = {};
        checkInMessage.classList.remove('show');
        rutInput.value = '';
    };

    /**
     * Limpia el PIN ingresado
     */
    const clearPin = () => {
        pinInput.value = '';
        updatePinDisplay();
        pinMessage.textContent = '';
    };

    /**
     * Actualiza los puntos en el display del PIN
     */
    const updatePinDisplay = () => {
        pinDisplay.innerHTML = '';
        for (let i = 0; i < pinInput.value.length; i++) {
            const dot = document.createElement('div');
            dot.className = 'pin-dot';
            pinDisplay.appendChild(dot);
        }
    };

    /**
     * Maneja los clics en el teclado numérico
     */
    const handlePinKey = (key) => {
        if (pinInputView.classList.contains('active')) {
            pinMessage.textContent = ''; 
            
            if (key === 'clear') {
                clearPin();
            } else if (key === 'back') {
                pinInput.value = pinInput.value.slice(0, -1);
                updatePinDisplay();
            } else if (/\d/.test(key) && pinInput.value.length < MAX_PIN_LENGTH) {
                pinInput.value += key;
                updatePinDisplay();
            }
        }
    };

    /**
     * Muestra un spinner en un botón
     */
    const showLoader = (button, show = true) => {
        const loader = button.querySelector('.loader');
        const text = button.querySelector('span:not(.loader)');
        if (loader) loader.style.display = show ? 'inline-block' : 'none';
        if (text) text.style.display = show ? 'none' : 'inline-block';
        button.disabled = show;
    };

    /**
     * Muestra un mensaje de estado en el área principal
     */
    const showCheckInMessage = (message, type = 'danger') => {
        checkInMessage.textContent = message;
        checkInMessage.className = `alert ${type}-color show`;
    };

    // --- MANEJADORES DE ESTADO DEL KIOSCO ---

    /**
     * Maneja el envío del RUT (Paso 1)
     */
    const handleRutSubmit = async (e) => {
        e.preventDefault();
        showLoader(rutSubmitBtn, true);
        checkInMessage.classList.remove('show');
        
        const rut = rutInput.value;
        const formData = new URLSearchParams({ action: 'check_rut', rut: rut });

        try {
            const res = await fetch('attendance.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.status === 'success_rut') {
                showPinModal(data.user_id, data.name);
            } else {
                showCheckInMessage(data.message || 'Error desconocido', 'danger');
            }
        } catch (error) {
            console.error('Error fetch RUT:', error);
            showCheckInMessage('Error de conexión. Intenta de nuevo.', 'danger');
        } finally {
            showLoader(rutSubmitBtn, false);
        }
    };

    /**
     * Maneja la validación del PIN y carga el estatus (Paso 2)
     */
    const handlePinValidation = async () => {
        if (pinInput.value.length < MAX_PIN_LENGTH) {
            pinMessage.textContent = `El PIN debe tener ${MAX_PIN_LENGTH} dígitos.`;
            return;
        }

        showLoader(pinValidateBtn, true);
        pinMessage.textContent = '';

        const formData = new URLSearchParams({
            action: 'check_pin_and_status',
            user_id: currentUserId,
            pin: pinInput.value
        });

        try {
            const res = await fetch('attendance.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.status === 'pin_ok') {
                currentAttendanceId = data.attendance_id;
                currentAttendanceStatus = data.attendance_status;
                showEventSelector();
            } else {
                pinMessage.textContent = data.message || 'Error desconocido';
                clearPin();
            }
        } catch (error) {
            console.error('Error fetch PIN/Status:', error);
            pinMessage.textContent = 'Error de conexión. Intenta de nuevo.';
        } finally {
            showLoader(pinValidateBtn, false);
        }
    };
    
    /**
     * Muestra la vista de selección de evento y configura los botones
     */
    const showEventSelector = () => {
        showModalView('event-selector-view');
        eventMessageContainer.innerHTML = '';
        
        let nextEvent = 'check_in';
        let statusHtml = '';
        let allCompleted = true;

        // Determinar el siguiente evento lógico y construir el display de estado
        for (const key in EVENTS_MAP) {
            const status = currentAttendanceStatus[key];
            const eventInfo = EVENTS_MAP[key];
            
            const isCompleted = status !== null;
            
            statusHtml += `<p class="${isCompleted ? 'completed' : 'pending'}">${eventInfo.label}: <span>${status || 'PENDIENTE'}</span></p>`;
            
            if (!isCompleted) {
                nextEvent = key;
                allCompleted = false;
                break;
            }
        }

        eventStatusDisplay.innerHTML = statusHtml;

        // Habilitar y deshabilitar botones
        eventButtons.forEach(btn => {
            const eventType = btn.dataset.eventType;
            const eventLabel = EVENTS_MAP[eventType].label;
            
            if (allCompleted) {
                eventMessageContainer.innerHTML = `<div class="alert success-color show" style="margin-bottom: 0;">¡Jornada Completa! Gracias por tu trabajo.</div>`;
                btn.disabled = true;
                btn.textContent = eventLabel; // Mostrar el texto por defecto
            } else if (eventType === nextEvent) {
                btn.disabled = false;
                btn.innerHTML = `Registrar ${eventLabel} <br> <small>(Ahora)</small>`;
            } else {
                btn.disabled = true;
                btn.textContent = eventLabel;
            }
        });

        if (allCompleted) {
            // Ocultar la cuadrícula si está completo
            document.getElementById('event-buttons-grid').style.display = 'none';
        } else {
            document.getElementById('event-buttons-grid').style.display = 'grid';
        }
    };

    /**
     * Maneja el registro del evento (Paso 3)
     */
    const handleEventRegistration = async (e) => {
        const button = e.target.closest('.event-btn');
        if (!button || button.disabled) return;

        const eventType = button.dataset.eventType;
        const initialButtonText = button.innerHTML;
        
        showLoader(button, true);
        eventMessageContainer.innerHTML = ''; // Limpiar mensajes

        const formData = new URLSearchParams({
            action: 'register_event',
            user_id: currentUserId,
            attendance_id: currentAttendanceId,
            event_type: eventType
        });

        try {
            const res = await fetch('attendance.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.status === 'success_register') {
                eventMessageContainer.innerHTML = `<div class="alert success-color show" style="margin-bottom: 0;">${data.message}</div>`;
                // Recargar la página después de 2 segundos para ver los cambios en KPIs y tabla
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                eventMessageContainer.innerHTML = `<div class="alert danger-color show" style="margin-bottom: 0;">${data.message || 'Error al registrar el evento.'}</div>`;
                // Recargar el estatus para ver si se debe a un error de secuencia
                // For simplicity, we just keep the modal open and re-enable the button
                button.innerHTML = initialButtonText;
                button.disabled = false;
            }
        } catch (error) {
            console.error('Error fetch Register:', error);
            eventMessageContainer.innerHTML = `<div class="alert danger-color show" style="margin-bottom: 0;">Error de conexión. Intenta de nuevo.</div>`;
            button.innerHTML = initialButtonText;
            button.disabled = false;
        } finally {
             // El loader se queda hasta la recarga si fue exitoso
             if (button.disabled) {
                showLoader(button, false);
             }
        }
    };


    // --- INICIALIZACIÓN DE EVENTOS ---

    document.addEventListener('DOMContentLoaded', function() {
        // Lógica de la tabla (existente)
        setupTableHeaders();
        updateTable();
        document.getElementById('limit').addEventListener('change', updateTable);
        const rangeForm = document.getElementById('range-filter-form');
        if (rangeForm) {
            rangeForm.addEventListener('submit', handleRangeFormSubmission);
        }

        // Lógica del Kiosco (nueva)
        if(rutForm) {
            rutForm.addEventListener('submit', handleRutSubmit);
        }
        if(pinCancelBtn) {
            pinCancelBtn.addEventListener('click', hidePinModal);
        }
        if(eventCancelBtn) {
            eventCancelBtn.addEventListener('click', hidePinModal);
        }
        if(pinValidateBtn) {
            pinValidateBtn.addEventListener('click', handlePinValidation);
        }
        
        eventButtons.forEach(button => {
            button.addEventListener('click', handleEventRegistration);
        });

        if(keyboardContainer) {
            keyboardContainer.addEventListener('click', (e) => {
                if (e.target.matches('.keyboard-btn')) {
                    const key = e.target.dataset.key;
                    if (key) handlePinKey(key);
                }
            });
        }
    });
    </script>
</body>

</html>
