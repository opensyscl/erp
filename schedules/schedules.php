<?php
/* error_reporting(E_ALL);
ini_set('display_errors', 1); */

// Ajusta la ruta a tu config.php. Asumimos que está en el directorio padre de donde ejecutas schedules.php
require '../config.php';
session_start();

// ----------------------------------------------------------------------
// 🚨 CORRECCIÓN CLAVE 1: RECUPERAR Y LIMPIAR EL MENSAJE DE SESIÓN 
// ----------------------------------------------------------------------
$toast_message = $_SESSION['toast_message'] ?? null;
$toast_type = $_SESSION['toast_type'] ?? null;

// Limpiar las variables de sesión para que el toast no aparezca de nuevo al recargar
unset($_SESSION['toast_message']);
unset($_SESSION['toast_type']);
// ----------------------------------------------------------------------

// Redireccionar si el usuario no está logueado
if (!isset($_SESSION['user_username'])) {
  header('Location: ../login.php');
  exit();
}

if (!isset($pdo)) {
  die('Error: PDO connection not established.');
}

// --- 1. OBTENER Y VALIDAR EL MES SELECCIONADO ---
$selectedMonthYear = $_GET['month'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonthYear)) {
  $selectedMonthYear = date('Y-m');
}

$startOfMonth = date('Y-m-01', strtotime($selectedMonthYear . '-01'));
$endOfMonth = date('Y-m-t', strtotime($selectedMonthYear . '-01'));

// --- 2. OBTENER EMPLEADOS Y TURNOS DISPONIBLES ---

// Empleados Activos
$stmt_employees = $pdo->prepare("
  SELECT id, name, is_active
  FROM employees
  WHERE is_active = 1
  ORDER BY name ASC
");
$stmt_employees->execute();
$employees = $stmt_employees->fetchAll(PDO::FETCH_ASSOC);
$employee_ids = array_column($employees, 'id');

// Turnos Fijos
$stmt_shifts = $pdo->prepare("
  SELECT id, name, start_time, end_time, color_code
  FROM shifts
  ORDER BY start_time ASC
");
$stmt_shifts->execute();
$shifts = $stmt_shifts->fetchAll(PDO::FETCH_ASSOC);

// Mapeo de Turnos por ID para JS
$shifts_map = [];
foreach ($shifts as $shift) {
  $shifts_map[$shift['id']] = $shift;
}


// --- 3. OBTENER DATOS DE HORARIOS DEL MES SELECCIONADO ---
$schedules_data = [];
if (!empty($employee_ids)) {
  $stmt_schedules = $pdo->prepare("
    SELECT
      s.id AS schedule_id, s.employee_id, s.schedule_date, s.is_day_off, s.notes, s.shift_id,
      COALESCE(sh.name, 'Personalizado') AS shift_name,
      COALESCE(s.custom_start, sh.start_time) AS start_time_raw,
      COALESCE(s.custom_end, sh.end_time) AS end_time_raw,  
      COALESCE(TIME_FORMAT(s.custom_start, '%H:%i'), TIME_FORMAT(sh.start_time, '%H:%i')) AS start_time,
      COALESCE(TIME_FORMAT(s.custom_end, '%H:%i'), TIME_FORMAT(sh.end_time, '%H:%i')) AS end_time,
      COALESCE(sh.color_code, '#9ca3af') AS color
    FROM schedules s
    LEFT JOIN shifts sh ON s.shift_id = sh.id
    WHERE s.schedule_date BETWEEN ? AND ?
    AND s.employee_id IN (" . implode(',', array_fill(0, count($employee_ids), '?')) . ")
    ORDER BY s.schedule_date ASC, start_time ASC
  ");
  $stmt_schedules->execute(array_merge([$startOfMonth, $endOfMonth], $employee_ids));
  $raw_schedules = $stmt_schedules->fetchAll(PDO::FETCH_ASSOC);

  // Reorganizar los datos para que JS los consuma fácilmente (por fecha/empleado)
  foreach ($raw_schedules as $schedule) {
    $employee_id = $schedule['employee_id'];
    $date = $schedule['schedule_date'];
    
    if (!isset($schedules_data[$employee_id])) {
      $schedules_data[$employee_id] = [];
    }
    
    // La clave ahora almacena TODOS los datos relevantes para el modal
    if ($schedule['is_day_off']) {
      $schedules_data[$employee_id][$date] = [
        'id' => $schedule['schedule_id'],
        'is_day_off' => true,
        'notes' => $schedule['notes']
      ];
    } else {
      $schedules_data[$employee_id][$date] = [
        'id' => $schedule['schedule_id'],
        'is_day_off' => false,
        'name' => $schedule['shift_name'],
        'shift_id' => $schedule['shift_id'],
        'start' => $schedule['start_time'],
        'end' => $schedule['end_time'],
        'start_raw' => $schedule['start_time_raw'],
        'end_raw' => $schedule['end_time_raw'],
        'color' => $schedule['color'],
        'notes' => $schedule['notes']
      ];
    }
  }
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
  // Fallback si la extensión Intl no está disponible
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
$current_page = 'schedules.php';
$stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
$stmt->execute();
$system_version = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestión de Horarios y Turnos - Listto! ERP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="icon" type="image/png" href="/erp/img/fav.png">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/schedules.css">
  
  <style>
    /* Estilos para el contenedor de toasts */
    #toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    /* Estilos para el toast individual */
    .toast {
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        color: white;
        opacity: 0;
        transition: opacity 0.3s, transform 0.3s;
        transform: translateX(100%);
        min-width: 250px;
    }

    .toast.show {
        opacity: 1;
        transform: translateX(0);
    }

    /* Tipos de toasts */
    .toast.success {
        background-color: #10b981; /* Verde */
        border-left: 5px solid #059669;
    }

    .toast.error {
        background-color: #ef4444; /* Rojo */
        border-left: 5px solid #dc2626;
    }

    .toast.warning {
        background-color: #f59e0b; /* Amarillo */
        border-left: 5px solid #d97706;
    }
  </style>
  
</head>

<body>
  <div id="toast-container"></div>

  <header class="main-header">
    <div class="header-left">
      <a href="../launcher.php" class="launcher-icon" title="Ir al Lanzador de Aplicaciones">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
          <circle cx="5" cy="5" r="3"/><circle cx="12" cy="5" r="3"/><circle cx="19" cy="5" r="3"/>
          <circle cx="5" cy="12" r="3"/><circle cx="12" cy="12" r="3"/><circle cx="19" cy="12" r="3"/>
          <circle cx="5" cy="19" r="3"/><circle cx="12" cy="19" r="3"/><circle cx="19" cy="19" r="3"/>
        </svg>
      </a>
      <span>Hola, <strong><?php echo htmlspecialchars($_SESSION['user_username'] ?? 'Usuario'); ?></strong></span>
    </div>
    <nav class="header-nav">
      <a href="schedules.php" class="active">Horarios y Turnos</a>
    </nav>
    <div class="header-right">
      <span class="app-version"><?php echo htmlspecialchars($system_version); ?></span>
      <a href="../logout.php" class="btn-logout">Cerrar Sesi&oacute;n</a>
    </div>
  </header>

  <main class="container">
    <div class="page-header-controls">
      <h1 class="page-title">Gestión de Horarios y Turnos</h1>
      <div class="controls-group">
        <button id="manage-employees-btn" class="btn-primary">Gestionar Empleados</button>
        <button id="manage-shifts-btn" class="btn-secondary">Definir Turnos</button> </div>
      <div class="month-selector-container">
        <label for="month-selector">Ver Mes:</label>
        <select id="month-selector" onchange="window.location.href = 'schedules.php?month=' + this.value">
          <?php
          foreach ($monthOptions as $value => $label) {
            $selected = ($value === $selectedMonthYear) ? 'selected' : '';
            echo "<option value=\"" . htmlspecialchars($value) . "\" {$selected}>" . htmlspecialchars($label) . "</option>";
          }
          ?>
        </select>
      </div>
    </div>
    
<div class="content-card full-width">
  <h2>Calendario de Horarios (<?= htmlspecialchars($monthOptions[$selectedMonthYear] ?? 'Mes Actual') ?>)</h2>
  
  <div id="schedule-calendar-weeks">
    <p id="loading-msg" style="text-align: center; padding: 2rem;">Cargando calendario de horarios...</p>
  </div>
  </div>
  </main>

  <div id="schedule-modal" class="modal" style="display:none;">
    <div class="modal-content">
      <span class="close-btn" data-modal="schedule-modal">&times;</span>
      <h3 id="modal-title">Asignar Horario</h3>
      <form id="schedule-form">
        <input type="hidden" id="modal-schedule-id" name="id">
        <input type="hidden" id="modal-employee-id" name="employee_id">
        <input type="hidden" id="modal-schedule-date" name="schedule_date">
        <p>Empleado: <strong id="modal-employee-name"></strong></p>
        <p>Fecha: <strong id="modal-date-display"></strong></p>

        <div class="form-group">
          <label>Tipo de Asignación:</label>
          <div class="radio-group">
            <input type="radio" id="type-shift" name="schedule_type" value="shift" checked>
            <label for="type-shift">Turno Fijo</label>
            <input type="radio" id="type-custom" name="schedule_type" value="custom">
            <label for="type-custom">Horario Continuado/Personalizado</label>
            <input type="radio" id="type-dayoff" name="schedule_type" value="dayoff">
            <label for="type-dayoff">Día Libre 🌴</label>
          </div>
        </div>

        <div id="shift-controls" class="form-section">
          <label for="shift_id">Seleccionar Turno:</label>
          <select id="shift_id" name="shift_id">
            <option value="">-- Seleccione un Turno --</option>
            <?php foreach ($shifts as $shift): ?>
              <option value="<?= $shift['id'] ?>">
                <?= htmlspecialchars($shift['name']) ?> (<?= date('H:i', strtotime($shift['start_time'])) ?> - <?= date('H:i', strtotime($shift['end_time'])) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div id="custom-controls" class="form-section" style="display: none;">
          <div class="input-group">
            <label for="custom_start">Inicio:</label>
            <input type="time" id="custom_start" name="custom_start" value="09:00">
          </div>
          <div class="input-group">
            <label for="custom_end">Fin:</label>
            <input type="time" id="custom_end" name="custom_end" value="17:00">
          </div>
        </div>
        
        <div class="form-group notes-group">
          <label for="notes">Notas (Opcional):</label>
          <textarea id="notes" name="notes" rows="2"></textarea>
        </div>

        <div class="modal-actions">
          <button type="submit" class="btn-primary" id="save-schedule-btn">Guardar Horario</button>
          <button type="button" class="btn-danger" id="delete-schedule-btn" style="display:none;">Eliminar</button>
        </div>
      </form>
    </div>
  </div>
  
  <div id="employees-modal" class="modal" style="display:none;">
    <div class="modal-content large-modal">
      <span class="close-btn" data-modal="employees-modal">&times;</span>
      <h3 id="employees-modal-title">Gestionar Empleados</h3>
      
      <div id="employees-list-container">
        <p style="text-align: center; padding: 1rem;">Cargando empleados...</p>
      </div>

      <button id="add-employee-btn" class="btn-primary" style="margin-top: 20px;">+ Agregar Nuevo Empleado</button>

      <form id="employee-form" style="display:none; margin-top: 20px; padding: 15px; border: 1px solid #ccc; border-radius: 8px; background-color: #f8f8f8;">
        <h4><span id="form-action-text">Crear</span> Empleado</h4>
        <input type="hidden" id="employee-id" name="id">

        <div class="form-group">
          <label for="employee-name">Nombre:</label>
          <input type="text" id="employee-name" name="name" required>
        </div>

        <div class="form-group radio-group-stacked">
          <label>Estado:</label>
          <div>
            
            <div class="option-wrapper">
              <input type="radio" id="is-active-yes" name="is_active" value="1" checked>
              <label for="is-active-yes">Activo</label>
            </div>
            
            <div class="option-wrapper">
              <input type="radio" id="is-active-no" name="is_active" value="0">
              <label for="is-active-no">Inactivo</label>
            </div>
            
          </div>
        </div>
        <div class="modal-actions" id="employee-actions">
          <button type="button" class="btn-secondary" id="cancel-employee-form-btn">Cancelar</button>
          <button type="button" class="btn-danger" id="delete-employee-btn" style="display:none;">Eliminar Empleado</button>
          <button type="submit" class="btn-primary" id="save-employee-btn">Crear</button>
        </div>
        </form>

    </div>
  </div>
  
  <div id="shifts-modal" class="modal" style="display:none;">
    <div class="modal-content large-modal">
      <span class="close-btn" data-modal="shifts-modal">&times;</span>
      <h3 id="shifts-modal-title">Definir Turnos Fijos</h3>
      
      <div id="shifts-list-container">
        <p style="text-align: center; padding: 1rem;">Cargando turnos...</p>
      </div>

      <button id="add-shift-btn" class="btn-primary" style="margin-top: 20px;">+ Agregar Nuevo Turno</button>

      <form id="shift-form" style="display:none; margin-top: 20px; padding: 15px; border: 1px solid #ccc; border-radius: 8px; background-color: #f8f8f8;">
        <h4><span id="shift-form-action-text">Crear</span> Turno</h4>
        <input type="hidden" id="shift-id" name="id">

        <div class="form-group">
          <label for="shift-name">Nombre:</label>
          <input type="text" id="shift-name" name="name" required>
        </div>

        <div class="input-group">
          <label for="shift-start-time">Inicio:</label>
          <input type="time" id="shift-start-time" name="start_time" value="09:00" required>
        </div>

        <div class="input-group">
          <label for="shift-end-time">Fin:</label>
          <input type="time" id="shift-end-time" name="end_time" value="17:00" required>
        </div>
        
        <div class="form-group">
          <label for="shift-color">Color:</label>
          <input type="color" id="shift-color" name="color_code" value="#3b82f6" required>
        </div>

        <div class="modal-actions" id="shift-actions">
          <button type="button" class="btn-secondary" id="cancel-shift-form-btn">Cancelar</button>
          <button type="button" class="btn-danger" id="delete-shift-btn" style="display:none;">Eliminar Turno</button>
          <button type="submit" class="btn-primary" id="save-shift-btn">Crear</button>
        </div>
      </form>
    </div>
  </div>

<script>
 const employeesData = <?= json_encode($employees); ?>;
 const shiftsData = <?= json_encode($shifts); ?>;
 const schedulesData = <?= json_encode($schedules_data); ?>;
 const selectedMonthYear = '<?= $selectedMonthYear ?>';

 // Rutas actualizadas para la carpeta 'api'
 const schedulesApiUrl = 'api/schedules_api.php';
 const employeesApiUrl = 'api/employees_api.php';
 const shiftsApiUrl = 'api/shifts_api.php'; // Nueva ruta para gestión de turnos

 /**
 * Muestra un mensaje temporal como un 'toast' en la esquina superior derecha.
 * @param {string} message - El mensaje a mostrar.
 * @param {string} type - El tipo de toast ('success', 'error', 'warning').
 */
 const showToast = (message, type = 'success') => {
   const container = document.getElementById('toast-container');
   if (!container) return; // Salir si el contenedor no existe

   const toast = document.createElement('div');
   toast.className = `toast ${type}`;
   toast.textContent = message;

   container.appendChild(toast);

   // Muestra el toast
   setTimeout(() => {
     toast.classList.add('show');
   }, 10); // Pequeño retraso para que la transición funcione

   // Oculta y elimina el toast después de 4 segundos
   setTimeout(() => {
     toast.classList.remove('show');
     // Espera a que la transición termine antes de eliminar
     setTimeout(() => {
       // Verifica si el elemento aún es hijo del contenedor antes de intentar removerlo
       if (container.contains(toast)) {
         container.removeChild(toast);
       }
     }, 300);
   }, 4000);
 };

 const getDaysInMonth = (year, month) => {
  return new Date(year, month, 0).getDate();
 };

 /**
 * Controla la visibilidad de los campos del formulario según el tipo de asignación
 */
 const setupModalListeners = () => {
  const shiftControls = document.getElementById('shift-controls');
  const customControls = document.getElementById('custom-controls');
  const radioButtons = document.querySelectorAll('input[name="schedule_type"]');
  const shiftSelect = document.getElementById('shift_id');
  const customStart = document.getElementById('custom_start');
  const customEnd = document.getElementById('custom_end');

  const toggleFormSections = (type) => {
   shiftControls.style.display = 'none';
   customControls.style.display = 'none';
  
   // Limpiar requerimientos
   shiftSelect.removeAttribute('required');
   customStart.removeAttribute('required');
   customEnd.removeAttribute('required');

   if (type === 'shift') {
    shiftControls.style.display = 'block';
    shiftSelect.setAttribute('required', 'required');
   } else if (type === 'custom') {
    customControls.style.display = 'flex';
    customStart.setAttribute('required', 'required');
    customEnd.setAttribute('required', 'required');
   }
  };

  radioButtons.forEach(radio => {
   radio.addEventListener('change', (e) => toggleFormSections(e.target.value));
  });

  // Inicializar al cargar (usando el valor que esté checked)
  const checkedRadio = document.querySelector('input[name="schedule_type"]:checked');
  if (checkedRadio) {
    toggleFormSections(checkedRadio.value);
  }
 };


/**
* Función principal para dibujar el calendario, dividiendo por semanas
*/
const renderCalendar = () => {
 const [year, month] = selectedMonthYear.split('-').map(Number);
 const daysInMonth = getDaysInMonth(year, month);
 const weeksContainer = document.getElementById('schedule-calendar-weeks');
 weeksContainer.innerHTML = '';

 if (employeesData.length === 0) {
  weeksContainer.innerHTML = '<p style="text-align: center; padding: 2rem;">No hay empleados activos para mostrar horarios. Por favor, gestione empleados.</p>';
  return;
 }

 let currentWeekDays = []; // Almacena los días de la semana actual
 const days = [];

 // 1. Crear un array con todos los días del mes para el loop
 for (let d = 1; d <= daysInMonth; d++) {
  days.push(d);
 }

 // 2. Iterar sobre todos los días para generar las tablas semanales
 let html = '';

 days.forEach((d, index) => {
  const date = new Date(year, month - 1, d);
  // getDay() devuelve 0 para Domingo, 1 para Lunes, etc.
  const dayOfWeek = date.getDay();
 
  // Si es el primer día del mes O si es Lunes (día 1) Y NO es el primer día,
  // iniciamos una nueva tabla.
  if (index === 0 || dayOfWeek === 1) {
   if (currentWeekDays.length > 0) {
    // Cierra la tabla anterior si no es la primera iteración
    html += renderWeeklyTable(currentWeekDays, year, month);
   }
   currentWeekDays = []; // Reinicia los días para la nueva semana
  }
 
  currentWeekDays.push(d);

  // Si es el último día del mes, renderiza la última tabla
  if (index === days.length - 1) {
   html += renderWeeklyTable(currentWeekDays, year, month);
  }
 });

 weeksContainer.innerHTML = html;
};


/**
* Función auxiliar para generar el HTML de una única tabla (Semana/Quincena)
*/
const renderWeeklyTable = (daysArray, year, month) => {
 if (daysArray.length === 0) return '';

 const startDay = daysArray[0];
 const endDay = daysArray[daysArray.length - 1];

 let html = `<div class="weekly-schedule-card">`;
 html += `<h3>Días del ${startDay} al ${endDay}</h3>`;
 html += '<table class="data-table"><thead><tr><th class="employee-name-header">Empleado</th>';

 // Encabezados de Días (Día y nombre del día)
 daysArray.forEach(d => {
  const date = new Date(year, month - 1, d);
  const dayOfWeek = date.toLocaleDateString('es-ES', { weekday: 'short' });
  html += `<th>${d}<br><span>${dayOfWeek}</span></th>`;
 });
 html += '</tr></thead><tbody>';

 // Filas por Empleado
 employeesData.forEach(employee => {
  html += `<tr><td class="employee-name-cell" data-employee-id="${employee.id}">${employee.name}</td>`;
 
  daysArray.forEach(d => {
   const dateKey = `${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
   const schedule = schedulesData[employee.id] ? schedulesData[employee.id][dateKey] : null;
  
   let cellContent = '';
   let cellClass = 'schedule-cell';

   if (schedule) {
    if (schedule.is_day_off) {
     cellContent = 'Día Libre 🌴';
     cellClass += ' day-off-cell';
    } else {
     // Muestra el turno/horario
     cellContent = `<span style="background-color: ${schedule.color};" class="shift-name">${schedule.name}</span><br>${schedule.start} - ${schedule.end}`;
     cellClass += ' assigned-shift-cell';
    }
   } else {
    cellContent = '<span class="no-schedule-text">Libre</span>';
    cellClass += ' no-schedule-cell';
   }

   // Celda para la interacción
   html += `<td class="${cellClass}" data-employee-id="${employee.id}" data-date="${dateKey}" onclick="openScheduleModal(${employee.id}, '${employee.name.replace(/'/g, "\\'")}', '${dateKey}')">${cellContent}</td>`;
  });
 
  html += '</tr>';
 });

 html += '</tbody></table></div>';
 return html;
};

 /**
 * Función para abrir el modal y cargar datos existentes
 */
 const openScheduleModal = (employeeId, employeeName, dateKey) => {
  const modal = document.getElementById('schedule-modal');
  const form = document.getElementById('schedule-form');
  // Acceso al schedule data. Es crucial que el schedule se pase desde PHP
  const schedule = schedulesData[employeeId] ? schedulesData[employeeId][dateKey] : null;
 
  // Limpiar y restablecer
  form.reset();
  document.getElementById('modal-schedule-id').value = '';
  document.getElementById('delete-schedule-btn').style.display = 'none';
  document.getElementById('type-shift').checked = true; // Valor por defecto

  // 1. Configurar datos base
  document.getElementById('modal-employee-id').value = employeeId;
  document.getElementById('modal-schedule-date').value = dateKey;
  document.getElementById('modal-employee-name').textContent = employeeName;
  document.getElementById('modal-date-display').textContent = new Date(dateKey + 'T00:00:00').toLocaleDateString('es-ES', { day: 'numeric', month: 'long', year: 'numeric' });
 
  // 2. Cargar datos existentes (Modo Editar/Eliminar)
  if (schedule) {
   document.getElementById('modal-schedule-id').value = schedule.id;
   document.getElementById('modal-title').textContent = 'Editar/Eliminar Horario';
   document.getElementById('delete-schedule-btn').style.display = 'inline-block';
   document.getElementById('notes').value = schedule.notes || '';

   if (schedule.is_day_off) {
    document.getElementById('type-dayoff').checked = true;
   } else if (schedule.shift_id === null) {
    // Personalizado (Activamos esta opción que estaba comentada en el HTML anterior)
    document.getElementById('type-custom').checked = true;
    // Asegurar que las horas se muestran en formato HH:MM (slice(0, 5))
    document.getElementById('custom_start').value = schedule.start_raw ? schedule.start_raw.slice(0, 5) : '09:00';
    document.getElementById('custom_end').value = schedule.end_raw ? schedule.end_raw.slice(0, 5) : '17:00';
   } else {
    // Turno fijo
    document.getElementById('type-shift').checked = true;
    document.getElementById('shift_id').value = schedule.shift_id;
   }
  } else {
   document.getElementById('modal-title').textContent = 'Asignar Horario';
  }

  // 3. (Re) Inicializar listeners para aplicar la visibilidad correcta
  setupModalListeners();

  // 4. Mostrar modal
  modal.style.display = 'block';
 };
 // Hacemos la función global para el onclick en la tabla
 window.openScheduleModal = openScheduleModal;


 // Función de utilidad para cerrar modales
 const closeModal = (modalId) => {
  document.getElementById(modalId).style.display = "none";
 }

 // Función de utilidad para ocultar el formulario de empleado y volver a la lista
 const hideEmployeeForm = () => {
  document.getElementById('employee-form').style.display = 'none';
  document.getElementById('employees-list-container').style.display = 'block';
  document.getElementById('add-employee-btn').style.display = 'block';
 };
 window.hideEmployeeForm = hideEmployeeForm;


 // Función para obtener y renderizar la lista de empleados
 const fetchAndRenderEmployees = async () => {
  const listContainer = document.getElementById('employees-list-container');
  listContainer.innerHTML = '<p style="text-align: center; padding: 1rem;">Cargando empleados...</p>';
 
  try {
   const response = await fetch(employeesApiUrl, { method: 'GET' });
   const result = await response.json();

   if (response.status === 200 && result.success) {
    let html = '<table class="data-table"><thead><tr><th>ID</th><th>Nombre</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
   
    if (result.data.length === 0) {
     html += '<tr><td colspan="4">No hay empleados registrados.</td></tr>';
    } else {
     result.data.forEach(employee => {
      const statusText = employee.is_active == 1 ? '<span style="color: #22c55e;">Activo</span>' : '<span style="color: #f97316;">Inactivo</span>';
      html += `
       <tr>
        <td>${employee.id}</td>
        <td>${employee.name}</td>
        <td>${statusText}</td>
        <td>
         <button class="btn-secondary btn-small" onclick="editEmployee(${employee.id}, '${employee.name.replace(/'/g, "\\'")}', ${employee.is_active})">Editar</button>
        </td>
       </tr>
      `;
     });
    }
   
    html += '</tbody></table>';
    listContainer.innerHTML = html;
   } else {
    listContainer.innerHTML = `<p style="color: var(--danger-color, red);">Error al cargar empleados: ${result.message || 'Error desconocido'}</p>`;
   }
  } catch (error) {
   listContainer.innerHTML = `<p style="color: var(--danger-color, red);">Error de conexión: ${error.message}</p>`;
  }
 };

 // Función para inicializar el modal de edición/creación de Empleados
 const setupEmployeeForm = (action = 'create', employee = {}) => {
  document.getElementById('employees-list-container').style.display = 'none';
  document.getElementById('add-employee-btn').style.display = 'none';
  const form = document.getElementById('employee-form');
  form.style.display = 'block';

  const deleteBtn = document.getElementById('delete-employee-btn');

  if (action === 'create') {
   document.getElementById('employee-id').value = '';
   document.getElementById('employee-name').value = '';
   document.getElementById('is-active-yes').checked = true;
   document.getElementById('form-action-text').textContent = 'Crear';
   deleteBtn.style.display = 'none';
   document.getElementById('save-employee-btn').textContent = 'Crear';
  } else { // Edit
   document.getElementById('employee-id').value = employee.id;
   document.getElementById('employee-name').value = employee.name;
   // Asegurarse de que el valor de is_active sea un número (0 o 1) para la comparación
   const isActive = parseInt(employee.is_active, 10) === 1;
   document.getElementById(isActive ? 'is-active-yes' : 'is-active-no').checked = true;
   document.getElementById('form-action-text').textContent = 'Editar';
   deleteBtn.style.display = 'inline-block';
   document.getElementById('save-employee-btn').textContent = 'Actualizar';
  }
 };

 // Llama a setupEmployeeForm para editar un empleado existente (usada en el HTML de la tabla)
 const editEmployee = (id, name, is_active) => {
  setupEmployeeForm('edit', { id, name, is_active });
 };

 // Hacemos editEmployee global para que el onclick en el HTML funcione
 window.editEmployee = editEmployee;


 /* ------------------------------------------------------------------
  * Funciones de Gestión de Turnos (Shift)
  * ------------------------------------------------------------------ */

 // Función para ocultar el formulario de turno y volver a la lista
 const hideShiftForm = () => {
  const form = document.getElementById('shift-form');
  const listContainer = document.getElementById('shifts-list-container');
  const addBtn = document.getElementById('add-shift-btn');
 
  if (form) form.style.display = 'none';
  if (listContainer) listContainer.style.display = 'block';
  if (addBtn) addBtn.style.display = 'block';
 };
 window.hideShiftForm = hideShiftForm;

 // Función para inicializar el modal de edición/creación de Turnos
 const setupShiftForm = (action = 'create', shift = {}) => {
  document.getElementById('shifts-list-container').style.display = 'none';
  document.getElementById('add-shift-btn').style.display = 'none';
  const form = document.getElementById('shift-form');
  form.style.display = 'block';

  const deleteBtn = document.getElementById('delete-shift-btn');

  if (action === 'create') {
   document.getElementById('shift-id').value = '';
   document.getElementById('shift-name').value = '';
   document.getElementById('shift-start-time').value = '09:00';
   document.getElementById('shift-end-time').value = '17:00';
   document.getElementById('shift-color').value = '#3b82f6';
   document.getElementById('shift-form-action-text').textContent = 'Crear';
   deleteBtn.style.display = 'none';
   document.getElementById('save-shift-btn').textContent = 'Crear';
  } else { // Edit
   document.getElementById('shift-id').value = shift.id;
   document.getElementById('shift-name').value = shift.name;
   document.getElementById('shift-start-time').value = shift.start_time ? shift.start_time.slice(0, 5) : '09:00';
   document.getElementById('shift-end-time').value = shift.end_time ? shift.end_time.slice(0, 5) : '17:00';
   document.getElementById('shift-color').value = shift.color_code;
   document.getElementById('shift-form-action-text').textContent = 'Editar';
   deleteBtn.style.display = 'inline-block';
   document.getElementById('save-shift-btn').textContent = 'Actualizar';
  }
 };

 // Función para obtener y renderizar la lista de turnos (debería llamar a shiftsApiUrl)
 const fetchAndRenderShifts = async () => {
  const listContainer = document.getElementById('shifts-list-container');
  listContainer.innerHTML = '<p style="text-align: center; padding: 1rem;">Cargando turnos...</p>';
 
  // Aquí es donde harías la llamada a la API en un entorno real:
  // const response = await fetch(shiftsApiUrl, { method: 'GET' });
  // const result = await response.json();
 
  // Usamos los datos iniciales de PHP para la demo/estructura
  const data = shiftsData;

  if (data.length > 0) {
    let html = '<table class="data-table"><thead><tr><th>ID</th><th>Nombre</th><th>Horario</th><th>Color</th><th>Acciones</th></tr></thead><tbody>';
   
    data.forEach(shift => {
      html += `
        <tr>
          <td>${shift.id}</td>
          <td>${shift.name}</td>
          <td>${shift.start_time.slice(0, 5)} - ${shift.end_time.slice(0, 5)}</td>
          <td><span style="background-color: ${shift.color_code}; padding: 4px; border-radius: 4px; color: white;">${shift.color_code}</span></td>
          <td>
            <button class="btn-secondary btn-small" onclick="editShift('${shift.id}', '${shift.name.replace(/'/g, "\\'")}', '${shift.start_time}', '${shift.end_time}', '${shift.color_code}')">Editar</button>
          </td>
        </tr>
      `;
    });
   
    html += '</tbody></table>';
    listContainer.innerHTML = html;
  } else {
    listContainer.innerHTML = '<p style="text-align: center; padding: 1rem;">No hay turnos fijos definidos.</p>';
  }
 };
 window.fetchAndRenderShifts = fetchAndRenderShifts; // Global

 // Llama a setupShiftForm para editar un turno existente
 const editShift = (id, name, start_time, end_time, color_code) => {
  setupShiftForm('edit', { id, name, start_time, end_time, color_code });
 };
 window.editShift = editShift;

 /* ------------------------------------------------------------------
  * Fin Funciones de Gestión de Turnos
  * ------------------------------------------------------------------ */


 document.addEventListener('DOMContentLoaded', function() {
  renderCalendar();
  setupModalListeners(); // Inicializar listeners del modal de horarios

  // Configuración general de cierre de modales
  document.querySelectorAll('.modal .close-btn').forEach(btn => {
   btn.onclick = function() {
    closeModal(this.getAttribute('data-modal'));
   }
  });
  window.onclick = function(event) {
   if (event.target.classList.contains('modal')) {
    closeModal(event.target.id);
   }
  }

  // ----------------------------------------------------
  // 1. IMPLEMENTACIÓN DE GESTIÓN DE EMPLEADOS (CRUD)
  // ----------------------------------------------------
  const employeesModal = document.getElementById('employees-modal');
 
  document.getElementById('manage-employees-btn').addEventListener('click', () => {
   employeesModal.style.display = 'block';
   hideEmployeeForm(); // Mostrar la lista por defecto
   fetchAndRenderEmployees(); // Cargar la lista al abrir
  });

  document.getElementById('add-employee-btn').addEventListener('click', () => {
   setupEmployeeForm('create');
  });
 
  // Listener para el botón Cancelar de empleado
  document.getElementById('cancel-employee-form-btn').addEventListener('click', hideEmployeeForm);

  // Envío del formulario de Empleados (Crear/Actualizar)
  document.getElementById('employee-form').addEventListener('submit', async function(e) {
   e.preventDefault();
   const data = {
    id: document.getElementById('employee-id').value || null,
    name: document.getElementById('employee-name').value,
    is_active: document.querySelector('input[name="is_active"]:checked').value
   };
  
   try {
    const response = await fetch(employeesApiUrl, {
     method: 'POST',
     headers: { 'Content-Type': 'application/json' },
     body: JSON.stringify(data),
    });
    const result = await response.json();
   
    if (response.status === 200 && result.success) {
     // 🚨 CORRECCIÓN 1: No mostrar toast aquí, sino usar la sesión PHP.
            // Necesitamos pasar el mensaje a la sesión antes de recargar.
            // *Esta lógica debe ser movida al archivo API/PHP (employees_api.php)*
            // Asumiendo que el API lo maneja, solo recargamos.
     window.location.reload();
    } else {
     showToast('Error: ' + result.message, 'error'); // <-- Mostrar error, ya que no recargaremos.
    }
   } catch (error) {
    console.error('Error de conexión:', error);
    showToast('Ocurrió un error de conexión con la API de empleados.', 'error');
   }
  });
 
  // Eliminación de Empleados
  document.getElementById('delete-employee-btn').addEventListener('click', async function() {
   const id = document.getElementById('employee-id').value;
   if (!confirm('¿Está seguro de que desea ELIMINAR este empleado? Esto solo es posible si no tiene horarios asignados. De lo contrario, desactívelo.')) return;
  
   try {
    const response = await fetch(employeesApiUrl, {
     method: 'DELETE',
     headers: { 'Content-Type': 'application/json' },
     body: JSON.stringify({ id: id }),
    });
    const result = await response.json();
   
    if (response.status === 200 && result.success) {
     // 🚨 CORRECCIÓN 2: No mostrar toast aquí, sino usar la sesión PHP.
            // *Esta lógica debe ser movida al archivo API/PHP (employees_api.php)*
     window.location.reload();
    } else {
     showToast('Error al eliminar: ' + (result.message || 'Error desconocido.'), 'error');
    }
   } catch (error) {
    console.error('Error de conexión:', error);
    showToast('Ocurrió un error de conexión al eliminar el empleado.', 'error');
   }
  });


  // ----------------------------------------------------
  // 2. IMPLEMENTACIÓN DE GESTIÓN DE TURNOS
  // ----------------------------------------------------
  const shiftsModal = document.getElementById('shifts-modal');

  document.getElementById('manage-shifts-btn').addEventListener('click', () => {
   shiftsModal.style.display = 'block';
   hideShiftForm();
   fetchAndRenderShifts();
  });
 
  document.getElementById('add-shift-btn').addEventListener('click', () => {
   setupShiftForm('create');
  });

  document.getElementById('cancel-shift-form-btn').addEventListener('click', hideShiftForm);

  // Envío del formulario de Turnos (Crear/Actualizar)
  document.getElementById('shift-form').addEventListener('submit', async function(e) {
   e.preventDefault();
   const formData = new FormData(this);
   const data = {};
   formData.forEach((value, key) => data[key] = value);

   // Asegurar que las horas están en formato HH:MM:SS para el backend
   data.start_time = (data.start_time || '00:00') + ':00';
   data.end_time = (data.end_time || '00:00') + ':00';
   data.id = data.id || null; // Si no hay ID, es una creación

   const actionText = data.id ? 'Turno Actualizado' : 'Turno Creado';
  
   try {
    const response = await fetch(shiftsApiUrl, {
     method: 'POST',
     headers: { 'Content-Type': 'application/json' },
     body: JSON.stringify(data),
    });
    const result = await response.json();
   
    if (response.status === 200 && result.success) {
     // 🚨 CORRECCIÓN 3: No mostrar toast aquí, sino usar la sesión PHP.
            // *Esta lógica debe ser movida al archivo API/PHP (shifts_api.php)*
     window.location.reload();
    } else {
     showToast('Error al guardar el turno: ' + (result.message || 'Error desconocido.'), 'error');
    }
   } catch (error) {
    console.error('Error de conexión:', error);
    showToast('Ocurrió un error de conexión con la API de turnos.', 'error');
   }
  });

  // Eliminación de Turnos
  document.getElementById('delete-shift-btn').addEventListener('click', async function() {
   const id = document.getElementById('shift-id').value;
   if (!id) return;

   if (!confirm('¿Está seguro de que desea ELIMINAR este turno?')) return;

   try {
    const response = await fetch(shiftsApiUrl, {
     method: 'DELETE',
     headers: { 'Content-Type': 'application/json' },
     body: JSON.stringify({ id: id }),
    });
   
    const result = await response.json();

    if (response.status === 200 && result.success) {
     // 🚨 CORRECCIÓN 4: No mostrar toast aquí, sino usar la sesión PHP.
            // *Esta lógica debe ser movida al archivo API/PHP (shifts_api.php)*
     window.location.reload();
    } else {
     showToast('Error al eliminar: ' + (result.message || 'Error desconocido.'), 'error');
    }
   } catch (error) {
    console.error('Error de conexión:', error);
    showToast('Ocurrió un error de conexión al eliminar el turno.', 'error');
   }
  });


  // ----------------------------------------------------
  // 3. IMPLEMENTACIÓN DE ENVÍO DE FORMULARIO DE HORARIOS
  // ----------------------------------------------------
  document.getElementById('schedule-form').addEventListener('submit', async function(e) {
   e.preventDefault();
   const formData = new FormData(this);
   const data = {};
   formData.forEach((value, key) => data[key] = value);

   const isUpdate = !!data.id;
   data.action = isUpdate ? 'update' : 'create';
  
   // Lógica para limpiar datos según el tipo
   if (data.schedule_type === 'shift') {
    data.custom_start = null;
    data.custom_end = null;
    data.is_day_off = 0;
    if (!data.shift_id) {
     showToast('Debe seleccionar un turno fijo.', 'warning');
     return;
    }
   } else if (data.schedule_type === 'custom') {
    data.shift_id = null;
    data.is_day_off = 0;
    // Asegurar que las horas están en formato HH:MM:SS para el backend
    data.custom_start = (data.custom_start || '00:00') + ':00';
    data.custom_end = (data.custom_end || '00:00') + ':00';
    if (!data.custom_start || !data.custom_end) {
     showToast('Debe ingresar horas de inicio y fin.', 'warning');
     return;
    }
   } else if (data.schedule_type === 'dayoff') {
    data.shift_id = null;
    data.custom_start = null;
    data.custom_end = null;
    data.is_day_off = 1;
   }

   try {
    const response = await fetch(schedulesApiUrl, {
     method: 'POST',
     headers: { 'Content-Type': 'application/json' },
     body: JSON.stringify(data),
    });
    const result = await response.json();

    if (result.success) {
     // 🚨 CORRECCIÓN 5 (Horarios): No mostrar toast aquí, sino usar la sesión PHP.
            // *Esta lógica debe ser movida al archivo API/PHP (schedules_api.php)*
     window.location.reload();
    } else {
     showToast('Error al guardar horario: ' + result.message, 'error');
    }
   } catch (error) {
    console.error('Error de conexión:', error);
    showToast('Ocurrió un error de conexión al guardar el horario.', 'error');
   }
  });
 
  // Implementación de Eliminación de Horario
  document.getElementById('delete-schedule-btn').addEventListener('click', async function() {
   const id = document.getElementById('modal-schedule-id').value;
   if (!id) {
    showToast('Error: ID de horario no encontrado para eliminar.', 'error');
    return;
   }

   if (!confirm('¿Está seguro de que desea ELIMINAR este horario?')) return;

   try {
    const response = await fetch(schedulesApiUrl, {
     method: 'DELETE',
     headers: { 'Content-Type': 'application/json' },
     body: JSON.stringify({ id: id }),
    });
    const result = await response.json();

    if (response.status === 200 && result.success) {
     // 🚨 CORRECCIÓN 6 (Horarios): No mostrar toast aquí, sino usar la sesión PHP.
            // *Esta lógica debe ser movida al archivo API/PHP (schedules_api.php)*
     window.location.reload();
    } else {
     showToast('Error al eliminar: ' + (result.message || 'Error desconocido.'), 'error');
    }
   } catch (error) {
    console.error('Error de conexión:', error);
    showToast('Ocurrió un error de conexión al eliminar el horario.', 'error');
   }
  });
    
    // ----------------------------------------------------------------------
    // 🔑 CORRECCIÓN CLAVE 7: LÓGICA DE PERSISTENCIA DEL TOAST DESDE PHP
    // ----------------------------------------------------------------------
    // Estas variables son inyectadas desde la parte PHP de schedules.php (Primer Fragmento)
    const toastMessage = "<?php echo $toast_message ? addslashes($toast_message) : ''; ?>";
    const toastType = "<?php echo $toast_type ? addslashes($toast_type) : ''; ?>";

    if (toastMessage && toastType) {
        // Muestra el toast ÚNICAMENTE si existe un mensaje de sesión, justo al terminar la recarga.
        showToast(toastMessage, toastType);
    }
    // ----------------------------------------------------------------------

 }); // Fin DOMContentLoaded
 </script>

</body>
</html>

</body>
</html>