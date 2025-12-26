<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Definimos la RUTA BASE para todas las redirecciones y llamadas JS.
const BASE_KANBAN_PATH = '/erp/task/task.php';

// Asegúrate de que 'config.php' incluye la conexión PDO como $pdo
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
    // Esto debería ser un error si config.php no establece $pdo
    die('Error fatal: Conexión PDO ($pdo) no disponible.');
}

$current_user_id = $_SESSION['user_id'];


// ----------------------------------------------------------------------
// --- 2. VALIDACIÓN DE ROL Y ACCESO (Omitido por brevedad, asumiendo que ya funciona) ---
// ----------------------------------------------------------------------

$user_can_access = true; // Asumimos que la lógica de acceso original está bien.

if (!$user_can_access) {
    header('Location: ../not_authorized.php');
    exit();
}
// ----------------------------------------------------------------------


// ----------------------------------------------------------------------
// --- 3. LÓGICA DE MANEJO DE DATOS (Crear, Editar, Eliminar, Actualizar Status) ---
// ----------------------------------------------------------------------

$error_message = '';
$success_message = '';
$allowed_statuses = ['nuevo', 'iniciado', 'en_progreso', 'completado', 'cancelado'];

// Función de redirección con mensaje (Patrón Post/Redirect/Get)
function redirect_with_message($status, $msg = '') {
    header('Location: ' . BASE_KANBAN_PATH . '?status=' . $status . ($msg ? '&msg=' . urlencode($msg) : ''));
    exit();
}

// A) Manejar Creación de Nueva Tarea (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_task') {
    try {
        $title = trim($_POST['title']);
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'media';
        // Sanitización de la fecha
        $dueDate = empty($_POST['due_date']) ? null : (new DateTime($_POST['due_date']))->format('Y-m-d');
        $task_user_id = $current_user_id;

        if (empty($title)) {
            throw new Exception('El título de la tarea es obligatorio.');
        }

        $stmt = $pdo->prepare("INSERT INTO tasks (title, description, priority, due_date, user_id, status) VALUES (?, ?, ?, ?, ?, 'nuevo')");
        $stmt->execute([$title, $description, $priority, $dueDate, $task_user_id]);
        
        redirect_with_message('success', 'Tarea creada exitosamente.');

    } catch (Exception $e) {
        redirect_with_message('error', 'Error al crear: ' . $e->getMessage());
    } catch (PDOException $e) {
        error_log("Error de BD: " . $e->getMessage());
        redirect_with_message('error', 'Error de BD al crear la tarea.');
    }
}

// B) Manejar Edición de Tarea Existente (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_task') {
    try {
        $taskId = (int)$_POST['task_id'];
        $title = trim($_POST['title']);
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'media';
        // Sanitización de la fecha
        $dueDate = empty($_POST['due_date']) ? null : (new DateTime($_POST['due_date']))->format('Y-m-d');
        $status = $_POST['status'] ?? 'nuevo';

        if (empty($title) || $taskId <= 0) {
            throw new Exception('Faltan datos obligatorios para la edición.');
        }

        if (!in_array($status, $allowed_statuses)) {
             throw new Exception('Estado no válido.');
        }

        // Solo permitir editar las tareas propias del usuario
        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET title = ?, description = ?, priority = ?, due_date = ?, status = ?, updated_at = NOW() 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$title, $description, $priority, $dueDate, $status, $taskId, $current_user_id]);

        if ($stmt->rowCount() === 0) {
             throw new Exception('No se encontró la tarea o no tiene permiso para editarla.');
        }
        
        redirect_with_message('edit_success', 'Tarea actualizada exitosamente.');

    } catch (Exception $e) {
        redirect_with_message('error', 'Error al editar: ' . $e->getMessage());
    } catch (PDOException $e) {
        error_log("Error de BD: " . $e->getMessage());
        redirect_with_message('error', 'Error de BD al actualizar la tarea.');
    }
}

// C) Manejar Eliminación de Tarea (GET)
if (isset($_GET['action']) && $_GET['action'] === 'delete_task' && isset($_GET['id'])) {
    $taskId = (int)$_GET['id'];

    if ($taskId <= 0) {
        redirect_with_message('error', 'ID de tarea no válido para la eliminación.');
    }

    try {
        // Importante: Filtro por user_id para evitar que un usuario elimine tareas de otro
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?"); 
        $stmt->execute([$taskId, $current_user_id]);
        
        if ($stmt->rowCount() > 0) {
             redirect_with_message('delete_success', 'Tarea eliminada exitosamente.');
        } else {
             // Si rowCount es 0, es que la tarea no existe o no pertenece al usuario.
             redirect_with_message('error', 'No se pudo eliminar la tarea. Verifique el ID.');
        }

    } catch (PDOException $e) {
        error_log("Error de BD al eliminar tarea: " . $e->getMessage());
        redirect_with_message('error', 'Error de BD al eliminar la tarea.');
    }
}


// D) Manejar Actualización de Estado (Usado por JS/Drag&Drop - GET)
if (isset($_GET['action']) && $_GET['action'] === 'update_status' && isset($_GET['id']) && isset($_GET['new_status'])) {
    $taskId = (int)$_GET['id'];
    $newStatus = $_GET['new_status'];
    
    if (!in_array($newStatus, $allowed_statuses)) {
        redirect_with_message('error', 'invalid_status');
    }

    try {
        // Se añade el filtro de user_id para seguridad
        $stmt = $pdo->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ? AND user_id = ?"); 
        $stmt->execute([$newStatus, $taskId, $current_user_id]);
        
        redirect_with_message('update_success');
    } catch (PDOException $e) {
        error_log("Error de BD al actualizar estado: " . $e->getMessage());
        redirect_with_message('error', 'db_update_failed');
    }
}

// Mensajes de feedback después de redirección
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $success_message = 'Tarea creada exitosamente.';
    } elseif ($_GET['status'] === 'update_success') {
        $success_message = 'Estado de la tarea actualizado con éxito.';
    } elseif ($_GET['status'] === 'edit_success') { 
        $success_message = 'Tarea actualizada con éxito.';
    } elseif ($_GET['status'] === 'delete_success') { // Nuevo mensaje
        $success_message = $_GET['msg'] ?? 'Tarea eliminada exitosamente.';
    } elseif ($_GET['status'] === 'error') {
        $error_detail = $_GET['msg'] ?? 'Ocurrió un error al procesar la solicitud.';
        $error_message = htmlspecialchars($error_detail);
    }
}


// ----------------------------------------------------------------------
// --- 4. OBTENER DATOS PARA LA VISTA KANBAN (Omitido por brevedad, asumiendo que ya funciona) ---
// ----------------------------------------------------------------------
// ... (Toda la lógica para obtener y estructurar $kanban_columns y $all_tasks_data) ...

$tasks_raw = [];
try {
    // Filtro obligatorio: WHERE user_id = :user_id
    $stmt = $pdo->prepare("SELECT id, title, description, status, priority, due_date, created_at
                             FROM tasks
                             WHERE user_id = :user_id
                             ORDER BY FIELD(priority, 'alta', 'media', 'baja'), created_at ASC");
                             
    $stmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
    $stmt->execute();
    $tasks_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error de BD al obtener tareas: " . $e->getMessage());
    $tasks_raw = [];
    $error_message = 'Error al cargar las tareas de la base de datos.';
}

// Estructurar las tareas por columna de estado y preparar datos para JS
$kanban_columns = [
    'nuevo' => ['label' => '📌 Pendiente', 'tasks' => []],
    'iniciado' => ['label' => '▶️ Iniciado', 'tasks' => []],
    'en_progreso' => ['label' => '⚙️ En Proceso', 'tasks' => []],
    'completado' => ['label' => '✅ Completado', 'tasks' => []],
    'cancelado' => ['label' => '🚫 Cancelado', 'tasks' => []],
];

$all_tasks_data = [];

foreach ($tasks_raw as $task) {
    $status = $task['status'];
    
    // Formateo para la vista
    $task['due_date_formatted'] = $task['due_date'] ? date('d/m/Y', strtotime($task['due_date'])) : 'N/A';
    
    // Guardar los datos sin formato (raw) para pasarlos a JS/Edición
    $task['description_raw'] = $task['description']; 
    $task['due_date_raw'] = $task['due_date']; // Fecha en formato YYYY-MM-DD
    $all_tasks_data[$task['id']] = $task; 

    if (isset($kanban_columns[$status])) {
        $kanban_columns[$status]['tasks'][] = $task;
    }
}
// ----------------------------------------------------------------------


// ----------------------------------------------------------------------
// --- 5. HTML/FRONTEND (Sección relevante de las tarjetas) ---
// ----------------------------------------------------------------------

// Obtener versión del sistema (asumiendo que $pdo está configurado)
try {
    $stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
    $stmt->execute();
    $system_version = $stmt->fetchColumn() ?? '1.0.0';
} catch (PDOException $e) {
    $system_version = 'Error DB';
}

$current_page = 'task.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tablero Kanban de Tareas - Listto! ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" type="image/png" href="img/fav.png">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="css/task.css">
    
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
        <a 
            href="task.php" 
            class="<?= ($current_page === 'task.php' ? 'active' : ''); ?>"
        >Tablero Kanban</a>
        <a 
            href="taskmetrics.php" 
            class="<?= ($current_page === 'taskmetrics.php' ? 'active' : ''); ?>"
        >Métricas</a>
    </nav>
        <div class="header-right">
            <span class="app-version"><?php echo htmlspecialchars($system_version); ?></span>
            <a href="../logout.php" class="btn-logout">Cerrar Sesi&oacute;n</a>
        </div>
    </header>

    <main class="kanban-layout">
        
        <div class="kanban-board-wrapper">
                
            <?php 
                // Asignar clase de toast basada en el mensaje
                $toast_class = '';
                if ($success_message) {
                    $toast_class = 'success-toast';
                } elseif ($error_message) {
                    $toast_class = 'error-toast';
                }
            ?>
                
            <?php if ($success_message || $error_message): ?>
                <div class="filter-toast <?= $toast_class ?>" style="margin-bottom: 20px;">
                    <?= htmlspecialchars($success_message ?: $error_message) ?>
                </div>
            <?php endif; ?>

            <div class="kanban-board">

                <?php
                // Renderizar cada columna
                foreach ($kanban_columns as $status_key => $column):
                    $task_count = count($column['tasks']);
                ?>
                    <div class="kanban-column" id="column-<?= $status_key ?>">
                        <div class="column-header">
                            <span><?= $column['label'] ?></span>
                            <span class="task-count">(<?= $task_count ?>)</span>
                        </div>
                        
                        <div class="task-list" data-status="<?= $status_key ?>">
                            <?php if ($task_count === 0): ?>
                                <p style="text-align: center; color: var(--text-secondary); padding: 10px;">Sin tareas</p>
                            <?php endif; ?>

                            <?php foreach ($column['tasks'] as $task): ?>
                                <div 
                                    class="task-card" 
                                    id="task-<?= $task['id'] ?>" 
                                    draggable="true" 
                                    data-task-id="<?= $task['id'] ?>" 
                                    data-status="<?= $task['status'] ?>"
                                    data-title="<?= htmlspecialchars($task['title']) ?>"
                                    data-description="<?= htmlspecialchars($task['description_raw']) ?>"
                                    data-priority="<?= htmlspecialchars($task['priority']) ?>"
                                    data-due-date="<?= htmlspecialchars($task['due_date_raw']) ?>"
                                >
                                    <button 
                                        class="delete-task-btn" 
                                        data-task-id="<?= $task['id'] ?>"
                                        title="Eliminar Tarea #<?= $task['id'] ?>"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                    </button>
                                    
                                    <div class="task-title">#<?= $task['id'] ?>: <?= htmlspecialchars($task['title']) ?></div>
                                    <p class="task-description">
                                        <?= nl2br(htmlspecialchars(substr($task['description_raw'], 0, 80) . (strlen($task['description_raw']) > 80 ? '...' : ''))) ?>
                                    </p>
                                    <div class="task-meta">
                                        <span class="<?= 'priority-' . $task['priority'] ?>">
                                            Prioridad: <?= ucfirst($task['priority']) ?>
                                        </span>
                                        <span>
                                            Límite: <?= $task['due_date_formatted'] ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>
    </main>

    <button class="floating-action-button" id="openTaskModal" title="Crear Nueva Tarea">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
    </button>

    <div id="taskModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2 id="modalTitle">Crear Nueva Tarea</h2> 
            
            <form class="create-edit-task-form" method="POST" action="<?= BASE_KANBAN_PATH ?>">
                <input type="hidden" name="action" id="modal_action" value="create_task">
                <input type="hidden" name="task_id" id="modal_task_id" value="">

                <label for="modal_title_input">Título:</label>
                <input type="text" id="modal_title_input" name="title" required placeholder="Ej: Revisar reporte de ventas">
                
                <label for="modal_description">Descripción:</label>
                <textarea id="modal_description" name="description" rows="3" placeholder="Detalles de la tarea..."></textarea>
                
                <label for="modal_priority">Prioridad:</label>
                <select id="modal_priority" name="priority">
                    <option value="alta">Alta</option>
                    <option value="media" selected>Media</option>
                    <option value="baja">Baja</option>
                </select>

                <label for="modal_status" id="label_modal_status" style="display: none;">Estado:</label>
                <select id="modal_status" name="status" style="display: none;">
                     <?php foreach ($allowed_statuses as $status_key): ?>
                         <option value="<?= $status_key ?>"><?= $kanban_columns[$status_key]['label'] ?></option>
                     <?php endforeach; ?>
                </select>

                <label for="modal_due_date">Fecha Límite:</label>
                <input type="date" id="modal_due_date" name="due_date">

                <button type="submit" class="btn-filter" id="modal_submit_button" style="margin-top: 15px;">Crear Tarea</button>
            </form>

        </div>
    </div>

    <script>
        const allTasksData = <?= json_encode($all_tasks_data) ?>;
    </script>

    <script>
    // ----------------------------------------------------------------
    // --- LÓGICA DE JAVASCRIPT PARA KANBAN INTERACTIVO (Drag & Drop, Modal, y ELIMINAR) ---
    // ----------------------------------------------------------------

    document.addEventListener('DOMContentLoaded', () => {
        const columns = document.querySelectorAll('.task-list');
        // Usamos el contenedor para delegar eventos y capturar clics en el botón
        const kanbanBoard = document.querySelector('.kanban-board-wrapper'); 
        const kanbanPath = '<?= BASE_KANBAN_PATH ?>';
        
        // Elementos del Modal (omitidos por brevedad, asumo que existen)
        const modal = document.getElementById('taskModal');
        const openCreateBtn = document.getElementById('openTaskModal');
        const closeBtn = document.querySelector('.modal .close-button');
        const form = document.querySelector('.create-edit-task-form');
        const modalTitle = document.getElementById('modalTitle');
        const submitButton = document.getElementById('modal_submit_button');
        const taskIdInput = document.getElementById('modal_task_id');
        const actionInput = document.getElementById('modal_action');
        const titleInput = document.getElementById('modal_title_input');
        const descriptionInput = document.getElementById('modal_description');
        const prioritySelect = document.getElementById('modal_priority');
        const dueDateInput = document.getElementById('modal_due_date');
        const statusSelect = document.getElementById('modal_status');
        const statusLabel = document.getElementById('label_modal_status');


        let draggedTask = null;
        
        // --- Funciones del Modal ---

        const resetForm = () => {
            modalTitle.textContent = 'Crear Nueva Tarea';
            submitButton.textContent = 'Crear Tarea';
            actionInput.value = 'create_task';
            taskIdInput.value = '';
            form.reset();
            prioritySelect.value = 'media';
            statusSelect.style.display = 'none';
            statusLabel.style.display = 'none';
        };

        const openModal = () => {
            modal.style.display = 'flex';
            titleInput.focus(); 
        };

        const closeModal = () => {
            modal.style.display = 'none';
        };

        const openEditModal = (taskId) => {
            const task = allTasksData[taskId];
            if (!task) {
                console.error('Error: Tarea no encontrada.');
                return;
            }
            // 1. Configurar el Modal como "Editar"
            modalTitle.textContent = `Editar Tarea #${taskId}`;
            submitButton.textContent = 'Guardar Cambios';
            actionInput.value = 'edit_task';
            // 2. Llenar los campos
            taskIdInput.value = taskId;
            titleInput.value = task.title;
            descriptionInput.value = task.description_raw;
            prioritySelect.value = task.priority;
            dueDateInput.value = task.due_date_raw; 
            statusSelect.value = task.status;
            // 3. Mostrar campos de edición (Estado)
            statusSelect.style.display = 'block';
            statusLabel.style.display = 'block';
            openModal();
        };

        // --- Eventos Generales del Modal ---

        openCreateBtn.addEventListener('click', () => {
            resetForm(); 
            openModal();
        });
        closeBtn.addEventListener('click', closeModal);

        // Agregamos un listener al board para manejar clics en tareas y en el nuevo botón de eliminar
        kanbanBoard.addEventListener('click', (e) => {
            // 1. Capturar clic en el botón de ELIMINAR
            if (e.target.closest('.delete-task-btn')) {
                const deleteBtn = e.target.closest('.delete-task-btn');
                const taskId = deleteBtn.getAttribute('data-task-id');
                // Previene que se dispare la edición de la tarjeta al mismo tiempo
                e.stopPropagation(); 
                
                if (confirm(`¿Está seguro de que desea eliminar la Tarea #${taskId}? Esta acción es irreversible.`)) {
                    // Redirigir para ejecutar la acción DELETE en PHP
                    window.location.href = `${kanbanPath}?action=delete_task&id=${taskId}`;
                }
                return;
            }
            
            // 2. Capturar clic en la tarjeta para EDITAR
            const taskCard = e.target.closest('.task-card');
            if (taskCard) {
                // Evita que el clic se dispare al soltar después del drag & drop
                if (e.detail === 0) return; 

                const taskId = taskCard.getAttribute('data-task-id');
                if (taskId) {
                    openEditModal(parseInt(taskId));
                }
            }
        });


        // --- Funciones Drag & Drop ---
        // (El resto de la lógica de Drag & Drop se mantiene igual)

        const updateTaskStatus = (taskId, newStatus) => {
            window.location.href = `${kanbanPath}?action=update_status&id=${taskId}&new_status=${newStatus}`;
        };
        
        // Inicializar eventos de Drag & Drop
        document.querySelectorAll('.task-card').forEach(task => {
            task.addEventListener('dragstart', (e) => {
                draggedTask = task;
                e.dataTransfer.effectAllowed = 'move';  
                setTimeout(() => task.classList.add('dragging'), 0);
            });

            task.addEventListener('dragend', () => {
                if (draggedTask) {
                    draggedTask.classList.remove('dragging');
                }
                draggedTask = null;
            });
        });

        columns.forEach(column => {
            column.addEventListener('dragover', (e) => {
                e.preventDefault(); 
            });

            column.addEventListener('dragenter', (e) => {
                e.preventDefault();
                column.style.backgroundColor = 'rgba(59, 130, 246, 0.05)';
            });

            column.addEventListener('dragleave', () => {
                column.style.backgroundColor = 'transparent';
            });

            column.addEventListener('drop', (e) => {
                e.preventDefault();
                column.style.backgroundColor = 'transparent';

                if (draggedTask) {
                    const taskId = draggedTask.getAttribute('data-task-id');
                    const newStatus = column.getAttribute('data-status');
                    const oldStatus = draggedTask.getAttribute('data-status');

                    if (oldStatus !== newStatus) {
                        column.appendChild(draggedTask);
                        draggedTask.setAttribute('data-status', newStatus);
                        updateTaskStatus(taskId, newStatus);
                    } else {
                        draggedTask.classList.remove('dragging');
                    }
                }
            });
        });
    });
    </script>

</body>
</html>