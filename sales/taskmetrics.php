<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Definimos la RUTA BASE
const BASE_METRICS_PATH = '/erp/task/taskmetrics.php';

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
    die('Error fatal: Conexión PDO ($pdo) no disponible.');
}

$current_user_id = $_SESSION['user_id'];
$current_username = htmlspecialchars($_SESSION['user_username'] ?? 'Usuario');


// ----------------------------------------------------------------------
// --- 2. VALIDACIÓN DE ROL ESPECÍFICO (Mismo acceso que Kanban) ---
// ----------------------------------------------------------------------

$user_can_access = false;
$module_path = '/erp/task/'; 

try {
    $stmt_role = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_role->execute([$current_user_id]);
    $user_role = $stmt_role->fetchColumn();

    // LÓGICA DE NEGOCIO: Solo POS1 puede acceder (y asumimos ADMIN).
    if ($user_role === 'POS1' || $user_role === 'ADMIN') {
        $user_can_access = true;
    }

} catch (PDOException $e) {
    error_log("Error de BD al verificar rol del usuario: " . $e->getMessage());
    header('Location: ../not_authorized.php');
    exit();
}

// ----------------------------------------------------------------------
// --- 3. VALIDACIÓN DE MÓDULO ---
// ----------------------------------------------------------------------
if ($user_can_access) {
    @include '../includes/module_check.php';
    if (function_exists('is_module_enabled') && !is_module_enabled($module_path)) {
        $user_can_access = false;
    }
}


// ----------------------------------------------------------------------
// --- 4. REDIRECCIÓN FINAL (Si no hay acceso) ---
// ----------------------------------------------------------------------
if (!$user_can_access) {
    header('Location: ../not_authorized.php');
    exit();
}
// ----------------------------------------------------------------------


// ----------------------------------------------------------------------
// --- 5. LÓGICA DE OBTENCIÓN DE MÉTRICAS AVANZADAS ---
// ----------------------------------------------------------------------

$metrics = [
    'total_tasks' => 0,
    'completed_tasks' => 0,
    'tasks_per_status' => [],
    'avg_cycle_time_days' => 'N/A', 
    'tasks_by_priority' => ['alta' => 0, 'media' => 0, 'baja' => 0],
    'completion_rate' => '0%',
    'avg_cycle_time_by_priority' => ['alta' => 'N/A', 'media' => 'N/A', 'baja' => 'N/A'],
    'throughput_data' => ['labels' => [], 'data' => []] // Datos para el gráfico de tasa de finalización diaria
];
$error_message = '';

try {
    // 5.1. Métricas Base (Conteo por estado y prioridad)
    $stmt_base = $pdo->prepare("
        SELECT status, priority, COUNT(id) AS count
        FROM tasks
        WHERE user_id = :user_id
        GROUP BY status, priority
    ");
    $stmt_base->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
    $stmt_base->execute();
    $base_counts = $stmt_base->fetchAll(PDO::FETCH_ASSOC);

    $status_counts = [];
    foreach ($base_counts as $row) {
        $status_counts[$row['status']] = ($status_counts[$row['status']] ?? 0) + $row['count'];
        if (isset($metrics['tasks_by_priority'][$row['priority']])) {
            $metrics['tasks_by_priority'][$row['priority']] += $row['count'];
        }
    }

    $total_tasks = array_sum($status_counts);
    $metrics['total_tasks'] = $total_tasks;
    $metrics['completed_tasks'] = $status_counts['completed'] ?? 0;
    
    // Asegurar estructura completa de estados
    $all_statuses = ['new', 'started', 'in_progress', 'completed', 'cancelled'];
    foreach ($all_statuses as $status) {
        $metrics['tasks_per_status'][$status] = $status_counts[$status] ?? 0;
    }

    if ($total_tasks > 0) {
        $rate = ($metrics['completed_tasks'] / $total_tasks) * 100;
        $metrics['completion_rate'] = number_format($rate, 1) . '%';
    }

    // 5.2. Cálculo de Tiempos de Ciclo
    
    // Tiempo de Ciclo Promedio General
    $stmt_cycle_general = $pdo->prepare("
        SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) / 24 AS avg_days
        FROM tasks
        WHERE user_id = :user_id AND status = 'completed' AND updated_at IS NOT NULL
    ");
    $stmt_cycle_general->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
    $stmt_cycle_general->execute();
    $avg_days = $stmt_cycle_general->fetchColumn();

    if ($avg_days !== null && $avg_days > 0) {
        $metrics['avg_cycle_time_days'] = number_format($avg_days, 1) . ' días';
    }
    
    // Tiempo de Ciclo Promedio por Prioridad
    $stmt_cycle_priority = $pdo->prepare("
        SELECT priority, AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) / 24 AS avg_days
        FROM tasks
        WHERE user_id = :user_id AND status = 'completed' AND updated_at IS NOT NULL
        GROUP BY priority
    ");
    $stmt_cycle_priority->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
    $stmt_cycle_priority->execute();
    $cycle_priority_counts = $stmt_cycle_priority->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($cycle_priority_counts as $priority => $avg) {
        if ($avg !== null && $avg > 0) {
             $metrics['avg_cycle_time_by_priority'][$priority] = number_format($avg, 1) . ' días';
        }
    }


    // 5.3. Throughput Diario (Cantidad de tareas completadas por día en el último mes)
    $start_date = date('Y-m-d', strtotime('-30 days'));
    
    $stmt_throughput = $pdo->prepare("
        SELECT DATE(updated_at) AS completion_date, COUNT(id) AS count
        FROM tasks
        WHERE user_id = :user_id AND status = 'completed' 
        AND updated_at >= :start_date
        GROUP BY completion_date
        ORDER BY completion_date ASC
    ");
    $stmt_throughput->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
    $stmt_throughput->bindParam(':start_date', $start_date);
    $stmt_throughput->execute();
    $throughput_results = $stmt_throughput->fetchAll(PDO::FETCH_ASSOC);

    // Generar labels para los últimos 30 días
    $date_range = [];
    $current = strtotime($start_date);
    $end = strtotime(date('Y-m-d'));
    while ($current <= $end) {
        $date_range[date('Y-m-d', $current)] = 0;
        $current = strtotime('+1 day', $current);
    }
    
    // Merge de los resultados de la BD con el rango completo
    foreach ($throughput_results as $row) {
        if (isset($date_range[$row['completion_date']])) {
            $date_range[$row['completion_date']] = (int)$row['count'];
        }
    }
    
    // Formatear para Chart.js
    foreach ($date_range as $date => $count) {
        $metrics['throughput_data']['labels'][] = date('d/M', strtotime($date));
        $metrics['throughput_data']['data'][] = $count;
    }


} catch (PDOException $e) {
    error_log("Error de BD al obtener métricas avanzadas: " . $e->getMessage());
    $error_message = 'Error al cargar las métricas avanzadas de la base de datos.';
}

// ----------------------------------------------------------------------
// --- 6. VARIABLES DE CONFIGURACIÓN Y VISUALIZACIÓN ---
// ----------------------------------------------------------------------

$current_page = 'taskmetrics.php';
$stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
$stmt->execute();
$system_version = $stmt->fetchColumn();

// Mapeo de estados para la visualización
$status_labels = [
    'new' => 'Pendiente',
    'started' => 'Iniciado',
    'in_progress' => 'En Proceso',
    'completed' => 'Completado',
    'cancelled' => 'Cancelado'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Métricas de Kanban - Listto! ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" type="image/png" href="img/fav.png">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/taskmetrics.css"> 

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
            <span>Hola, <strong><?= $current_username ?></strong></span>
        </div>

        <nav class="header-nav">
            <a href="task.php">Tablero Kanban</a>
            <a href="taskmetrics.php" class="<?= ($current_page === 'taskmetrics.php' ? 'active' : ''); ?>">
                Métricas
            </a>
        </nav>
        <div class="header-right">
            <span class="app-version"><?= htmlspecialchars($system_version); ?></span>
            <a href="../logout.php" class="btn-logout">Cerrar Sesi&oacute;n</a>
        </div>
    </header>

    <main class="metrics-layout container">
        <?php if ($error_message): ?>
            <div class="filter-toast error-toast">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <h2>Principales Indicadores de Desempeño (KPIs)</h2>
        
                <div style="height: 300px; width: 100%;">
                    <canvas id="throughputChart"></canvas>
                </div>
            </div>
            
            <div class="chart-container">
                <h2>Distribución de Tareas por Prioridad</h2>
                <div style="height: 300px; width: 100%;">
                    <canvas id="priorityChart"></canvas>
                </div>
                <div class="legend-priority">
                    <div class="legend-item"><span class="legend-color" style="background-color: var(--priority-high);"></span>Alta (<?= $metrics['tasks_by_priority']['alta'] ?? 0 ?>)</div>
                    <div class="legend-item"><span class="legend-color" style="background-color: var(--priority-medium);"></span>Media (<?= $metrics['tasks_by_priority']['media'] ?? 0 ?>)</div>
                    <div class="legend-item"><span class="legend-color" style="background-color: var(--priority-low);"></span>Baja (<?= $metrics['tasks_by_priority']['baja'] ?? 0 ?>)</div>
                </div>
            </div>
        </div>
        
        <hr/>

        <h2>Tiempos de Resolución y Desglose</h2>
        <div class="metrics-grid" style="grid-template-columns: 1fr 1fr;">
            
             <div class="chart-container">
                <h2>Tiempo de Ciclo Promedio por Prioridad (Días)</h2>
                <ul class="tasks-list-status">
                    <?php 
                    $priority_colors = [
                        'alta' => 'var(--danger-color)', // Usar las variables CSS
                        'media' => 'var(--warning-color)',
                        'baja' => 'var(--success-color)'
                    ];
                    foreach ($metrics['avg_cycle_time_by_priority'] as $priority => $time): 
                    ?>
                        <li>
                            <span>Prioridad <?= ucfirst($priority) ?></span>
                            <span style="color: <?= $priority_colors[$priority] ?>; font-size: 1.2rem;"><?= $time ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="chart-container">
                <h2>Desglose de Tareas Activas por Estado</h2>
                <ul class="tasks-list-status">
                    <?php 
                    $column_colors = [
                        'new' => '#3b82f6', 
                        'started' => '#10b981', 
                        'in_progress' => '#f59e0b', 
                        'completed' => '#1e40af', 
                        'cancelled' => '#6b7280' 
                    ];
                    foreach ($metrics['tasks_per_status'] as $status => $count): 
                    ?>
                        <li>
                            <span><?= $status_labels[$status] ?></span>
                            <span style="color: <?= $column_colors[$status] ?>; font-size: 1.2rem;"><?= $count ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- COLORES DEFINIDOS EN CSS (Para consistencia) ---
        // Nota: Chart.js necesita colores en formato JS, no variables CSS, 
        // por eso los definimos explícitamente aquí.
        const colors = {
            accent: '#3b82f6',
            success: '#10b981',
            danger: '#ef4444',
            warning: '#f59e0b',
            textPrimary: '#1a202c',
            priorityHigh: '#ef4444',
            priorityMedium: '#f59e0b',
            priorityLow: '#10b981',
            inactive: '#e0e0e0' // Gris para datos cero
        };
        
        
        // =========================================================
        // 1. GRÁFICO DE DISTRIBUCIÓN POR PRIORIDAD (DONUT CHART)
        // =========================================================
        const priorityData = {
            labels: ['Alta', 'Media', 'Baja'],
            datasets: [{
                data: [
                    <?= $metrics['tasks_by_priority']['alta'] ?? 0 ?>,
                    <?= $metrics['tasks_by_priority']['media'] ?? 0 ?>,
                    <?= $metrics['tasks_by_priority']['baja'] ?? 0 ?>
                ],
                backgroundColor: [
                    colors.priorityHigh,
                    colors.priorityMedium,
                    colors.priorityLow
                ].map((color, index) => {
                    // Usar color inactivo si el valor es cero para que no distorsione el gráfico
                    const value = [
                        <?= $metrics['tasks_by_priority']['alta'] ?? 0 ?>,
                        <?= $metrics['tasks_by_priority']['media'] ?? 0 ?>,
                        <?= $metrics['tasks_by_priority']['baja'] ?? 0 ?>
                    ][index];
                    return value > 0 ? color : colors.inactive;
                }),
                hoverOffset: 4
            }]
        };

        const priorityConfig = {
            type: 'doughnut',
            data: priorityData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (context.parsed !== null) {
                                    label += `: ${context.parsed}`;
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        };

        const priorityChartElement = document.getElementById('priorityChart');
        if (priorityChartElement) {
            new Chart(priorityChartElement, priorityConfig);
        }


        // =========================================================
        // 2. GRÁFICO DE THROUGHPUT (BAR CHART)
        // =========================================================
        const throughputData = {
            labels: <?= json_encode($metrics['throughput_data']['labels']) ?>,
            datasets: [{
                label: 'Tareas Completadas',
                data: <?= json_encode($metrics['throughput_data']['data']) ?>,
                backgroundColor: colors.success,
                borderColor: colors.success,
                borderWidth: 1,
                borderRadius: 5
            }]
        };

        const throughputConfig = {
            type: 'bar',
            data: throughputData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Cantidad de Tareas'
                        },
                        ticks: {
                            stepSize: 1 
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Día del Mes'
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    title: { display: false }
                }
            }
        };

        const throughputChartElement = document.getElementById('throughputChart');
        if (throughputChartElement) {
            new Chart(throughputChartElement, throughputConfig);
        }
    });
    </script>
</body>
</html>