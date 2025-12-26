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
// RUTA CONFIGURADA: Módulo de Consumo Interno
$module_path = '/erp/outputs/'; 

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

$current_user = $_SESSION['user_username'] ?? 'Sistema';

// --- 3. PROCESAMIENTO DEL FORMULARIO DE REGISTRO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['filter'])) {
    // Validar datos de entrada
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $notes = $_POST['notes'] ?? '';
    $notes = htmlspecialchars($notes, ENT_QUOTES, 'UTF-8');

    if ($product_id && $quantity > 0) {
        $pdo->beginTransaction();

        try {
            // 1. Obtener datos del producto y bloquear la fila (concurrencia)
            $stmt = $pdo->prepare("SELECT name, stock, cost_price, price FROM products WHERE id = ? FOR UPDATE");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            // 2. Validar stock
            if ($product && $product['stock'] >= $quantity) {
                // 3. Descontar el stock
                $stmt_update = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                $stmt_update->execute([$quantity, $product_id]);

                // 4. Registrar el movimiento
                $stmt_insert = $pdo->prepare("
                    INSERT INTO consumo_interno (product_id, quantity_removed, cost_price_at_time, sale_price_at_time, user_username, notes)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt_insert->execute([
                    $product_id,
                    $quantity,
                    $product['cost_price'],
                    $product['price'],
                    $current_user,
                    $notes
                ]);

                $pdo->commit();
                
                // === REDIRECCIÓN PRG EN CASO DE ÉXITO ===
                // Usamos una clave de sesión simple para el mensaje toast
                $_SESSION['toast_message'] = 'Consumo registrado y stock actualizado para el producto: ' . htmlspecialchars($product['name']) . '.';
                $_SESSION['toast_type'] = 'success';
                header('Location: outputs.php');
                exit();
                
            } else {
                $pdo->rollBack();
                $error_msg = $product ? 'No hay stock suficiente. Stock actual: ' . htmlspecialchars($product['stock']) : 'El producto no existe.';
                
                // Usamos la variable $message estándar para errores que impiden la redirección
                $message = '<div class="alert error">Error: ' . $error_msg . '</div>'; 
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = '<div class="alert error">Error al procesar la solicitud: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert error">Por favor, selecciona un producto y una cantidad válida.</div>';
    }
}


// --- 4. CONFIGURACIÓN Y OBTENCIÓN DE RANGO DE FECHAS (CORREGIDO) ---

// Intentar determinar el mes o rango de fechas seleccionado
$selected_month = null;
$selected_range = false;

if (isset($_GET['date_start']) && isset($_GET['date_end']) && !empty($_GET['date_start']) && !empty($_GET['date_end'])) {
    // Opción 1: Rango de fechas personalizado (Prioridad)
    $date_start = $_GET['date_start'];
    $date_end = $_GET['date_end'];
    $selected_range = true;
    // Opcional: Asegurar que el selector de mes esté vacío si hay rango
    $selected_month = ''; 
} else if (isset($_GET['month']) && !empty($_GET['month'])) {
    // Opción 2: Selector de Meses
    $selected_month = $_GET['month']; // Formato YYYY-MM
    $date_start = date('Y-m-01', strtotime($selected_month));
    $date_end = date('Y-m-t', strtotime($selected_month));
} else {
    // Opción 3: Por defecto (Mes actual)
    $date_start = date('Y-m-01');
    $date_end = date('Y-m-t');
    $selected_month = date('Y-m');
}

// Asegurarse de que las fechas tengan formato de inicio/fin de día para el SQL
$date_start_sql = $date_start . ' 00:00:00';
$date_end_sql = $date_end . ' 23:59:59';

// Función de utilidad para formatear dinero
function format_money($amount) {
    return number_format($amount, 0, ',', '.');
}

// Función para generar las opciones del selector de meses (CORREGIDA: Genera 12 meses fijos)
function generate_month_options($selected_month) {
    $output = '';
    
    // Generar el mes actual y los 11 meses anteriores (total 12)
    $months_to_show = [];
    $current_timestamp = strtotime(date('Y-m-01'));
    
    for ($i = 0; $i < 12; $i++) {
        $months_to_show[] = date('Y-m', strtotime("-$i months", $current_timestamp));
    }

    // Mapeo de nombres de meses para idioma español
    $month_names = [
        'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril', 
        'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto', 
        'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
    ];

    foreach ($months_to_show as $month_value) {
        $label = date('F Y', strtotime($month_value . '-01'));
        $label = strtr($label, $month_names);
        
        // Compara el valor del mes (YYYY-MM) con el mes seleccionado
        $selected = ($selected_month == $month_value) ? 'selected' : '';
        $output .= "<option value='$month_value' $selected>" . ucfirst($label) . "</option>";
    }
    return $output;
}

// --- 5. OBTENCIÓN DE DATOS PARA KPIS Y TABLA ---
try {
    // --- KPIs del periodo seleccionado ---
    $kpi_sql_base = "SELECT %s FROM consumo_interno WHERE removal_date BETWEEN ? AND ?";

    // KPI 1: Total de unidades retiradas
    $total_units_removed = $pdo->prepare(sprintf($kpi_sql_base, "SUM(quantity_removed)"));
    $total_units_removed->execute([$date_start_sql, $date_end_sql]);
    $kpi_total_units = $total_units_removed->fetchColumn() ?: 0;

    // KPI 2: Costo Neto Total (cost_price)
    $total_net_cost = $pdo->prepare(sprintf($kpi_sql_base, "SUM(quantity_removed * cost_price_at_time)"));
    $total_net_cost->execute([$date_start_sql, $date_end_sql]);
    $kpi_net_cost = $total_net_cost->fetchColumn() ?: 0;

    // KPI 3: Costo Bruto Total (Neto + 19% IVA)
    $kpi_gross_cost = $kpi_net_cost * 1.19;

    // KPI 4: Venta Potencial Perdida (price)
    $total_potential_sale = $pdo->prepare(sprintf($kpi_sql_base, "SUM(quantity_removed * sale_price_at_time)"));
    $total_potential_sale->execute([$date_start_sql, $date_end_sql]);
    $kpi_potential_loss = $total_potential_sale->fetchColumn() ?: 0;

    // KPI 5: Productos más retirados (Top 5)
    $top_products_stmt = $pdo->prepare("
        SELECT p.name, p.image_url, SUM(ci.quantity_removed) as total_removed
        FROM consumo_interno ci
        JOIN products p ON ci.product_id = p.id
        WHERE ci.removal_date BETWEEN ? AND ?
        GROUP BY ci.product_id, p.name, p.image_url
        ORDER BY total_removed DESC
        LIMIT 5
    ");
    $top_products_stmt->execute([$date_start_sql, $date_end_sql]);
    $kpi_top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Datos para la tabla de últimos registros (AHORA FILTRADO POR EL PERIODO Y LIMITADO) ---
    $recent_consumptions_stmt = $pdo->prepare("
        SELECT ci.*, p.name as product_name, p.barcode
        FROM consumo_interno ci
        JOIN products p ON ci.product_id = p.id
        WHERE ci.removal_date BETWEEN ? AND ?
        ORDER BY ci.removal_date DESC
        LIMIT 10
    ");
    // Usamos las fechas filtradas para la tabla de registros recientes
    $recent_consumptions_stmt->execute([$date_start_sql, $date_end_sql]);
    $recent_consumptions = $recent_consumptions_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message .= '<div class="alert error">Error al cargar datos: ' . $e->getMessage() . '</div>';
    // Inicializar variables para evitar errores en la vista
    $kpi_total_units = $kpi_net_cost = $kpi_gross_cost = $kpi_potential_loss = 0;
    $kpi_top_products = $recent_consumptions = [];
}

// --- 6. OBTENCIÓN DE VERSIÓN DEL SISTEMA ---
$system_version = '1.0';
try {
    $stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
    $stmt->execute();
    $version_result = $stmt->fetchColumn();
    if ($version_result) {
        $system_version = $version_result;
    }
} catch (PDOException $e) {
    // Silencio
}

// --- 7. RECUPERAR TOAST DE LA SESIÓN ---
$toast_message = $_SESSION['toast_message'] ?? '';
$toast_type = $_SESSION['toast_type'] ?? '';
unset($_SESSION['toast_message']);
unset($_SESSION['toast_type']);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consumo Interno - Mi Sistema</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" type="image/png" href="/erp/img/fav.png">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/outputs.css"> 
    
    <style>
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .toast {
            padding: 15px 20px;
            border-radius: 8px;
            color: #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            opacity: 0;
            transition: opacity 0.3s ease-in-out, transform 0.3s ease-in-out;
            transform: translateX(100%);
            min-width: 300px;
        }
        .toast.show {
            opacity: 1;
            transform: translateX(0);
        }
        .toast.success {
            background-color: #28a745; /* Verde para éxito */
        }
        .toast.error {
            background-color: #dc3545; /* Rojo para error/advertencia */
        }
    </style>
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
            <a href="outputs.php" class="active">Consumo Interno</a>
        </nav>
        <div class="header-right">
            <span class="app-version"><?php echo htmlspecialchars($system_version); ?></span>
            <a href="../logout.php" class="btn-logout">Cerrar Sesi&oacute;n</a>
        </div>
    </header>

    <main class="container">
        <?php echo $message; // Muestra mensajes de error que no usen PRG ?>

        <div class="kpi-grid">
            <div class="kpi-card"><h3>Unidades Retiradas</h3><p class="value"><?= number_format($kpi_total_units, 0, ',', '.') ?></p></div>
            <div class="kpi-card"><h3>Costos de Compras</h3><p class="value">$<?= format_money($kpi_net_cost) ?></p></div>
            <div class="kpi-card"><h3>Costos Brutos</h3><p class="value">$<?= format_money($kpi_gross_cost) ?></p></div>
            <div class="kpi-card"><h3>Venta Potencial</h3><p class="value">$<?= format_money($kpi_potential_loss) ?></p></div>
        </div>

<div class="content-card">
            <div class="top-products-header-controls">
                <h2>Top 5 Productos Retirados (Periodo Seleccionado) 🏆</h2>
                
                <form method="get" class="date-range-controls">
                    <div class="month-selector-container">
                        <label for="month_selector">Mes:</label>
                        <select id="month_selector" name="month" onchange="document.getElementById('date_start').value = ''; document.getElementById('date_end').value = ''; this.form.submit()">
                            <option value="" <?= $selected_range ? 'selected' : '' ?>>Rango personalizado</option> 
                            <?= generate_month_options($selected_month) ?>
                        </select>
                    </div>

                    <label for="date_start">Desde:</label>
                    <input type="date" id="date_start" name="date_start" 
                        value="<?= htmlspecialchars($date_start) ?>" 
                        onchange="document.getElementById('month_selector').value = ''; this.form.submit()">

                    <label for="date_end">Hasta:</label>
                    <input type="date" id="date_end" name="date_end" 
                        value="<?= htmlspecialchars($date_end) ?>" 
                        onchange="document.getElementById('month_selector').value = ''; this.form.submit()">
                </form>
            </div>
            
            <?php if (empty($kpi_top_products)): ?>
                <p>No hay datos de consumo en el periodo **<?= htmlspecialchars(date('d/m/Y', strtotime($date_start))) ?>** al **<?= htmlspecialchars(date('d/m/Y', strtotime($date_end))) ?>**.</p>
            <?php else: ?>
                <div class="top-products-grid">
                    <?php foreach ($kpi_top_products as $p): ?>
                        <div class="product-item" title="<?= htmlspecialchars($p['name']) ?>">
                            <?php
                                // Usa la URL de la imagen si existe, sino usa un placeholder
                                $image_url = !empty($p['image_url']) ? htmlspecialchars($p['image_url']) : '/erp/img/placeholder.png'; // Reemplaza /erp/img/placeholder.png por tu ruta de placeholder
                            ?>
                            <img src="<?= $image_url ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="product-image">
                            <span class="product-name"><?= htmlspecialchars($p['name']) ?></span>
                            <span class="product-quantity"><?= $p['total_removed'] ?> unidades</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

<div class="content-card">
            <h2>Últimos Consumos Registrados (Periodo Seleccionado)</h2>
            <div class="table-container">
                <table class="sales-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Costo Neto</th>
                            <th>Venta Perdida</th>
                            <th>Usuario</th>
                            <th>Notas</th>
                            <th>Acciones</th> </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_consumptions)): ?>
                            <tr><td colspan="8" style="text-align: center;">No hay registros de consumo en este periodo.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent_consumptions as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars(date("d-m-Y H:i", strtotime($row['removal_date']))) ?></td>
                                <td><?= htmlspecialchars($row['product_name']) ?></td>
                                <td id="qty-<?= $row['id'] ?>"><?= htmlspecialchars($row['quantity_removed']) ?></td>
                                <td>$<?= format_money($row['cost_price_at_time'] * $row['quantity_removed']) ?></td>
                                <td>$<?= format_money($row['sale_price_at_time'] * $row['quantity_removed']) ?></td>
                                <td><?= htmlspecialchars($row['user_username']) ?></td>
                                <td id="notes-<?= $row['id'] ?>"><?= htmlspecialchars($row['notes']) ?></td>

                                <td class="action-buttons-cell">
                                    <button
                                           class="btn-action edit-btn action-edit"
                                           data-id="<?= $row['id'] ?>"
                                           data-product-id="<?= $row['product_id'] ?>"
                                           data-quantity="<?= $row['quantity_removed'] ?>"
                                           data-notes="<?= htmlspecialchars($row['notes']) ?>"
                                           title="Editar">✏️</button>

                                    <button
                                           class="btn-action delete-btn action-delete"
                                           data-id="<?= $row['id'] ?>"
                                           data-product-name="<?= htmlspecialchars($row['product_name']) ?>"
                                           title="Eliminar">🗑️</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
<div class="content-card">
            <h2>Registrar Salida por Consumo Interno</h2>
           <form action="outputs.php" method="POST" id="consumption-form">
                <div class="form-grid">

                    <div class="form-group">
                        <label for="product_search">Buscar Producto (por Nombre o Código de Barras)</label>
                        <input type="text" id="product_search" name="product_search"
                                placeholder="Escribe o escanea aquí..." autocomplete="off" required>

                        <div id="search_results_container"></div>
                    </div>

                    <input type="hidden" id="product_id" name="product_id">

                    <div class="form-group">
                        <label for="quantity">Cantidad</label>
                        <input type="number" id="quantity" name="quantity" required min="1" value="1">
                    </div>

                    <div class="form-group">
                        <label for="notes">Notas (Opcional)</label>
                        <input type="text" id="notes" name="notes" placeholder="Ej: Para limpieza de baño">
                    </div>

                    <button type="submit" class="btn-submit">Registrar Salida</button>
                </div>
            </form>
        </div>


    </main>

    <div id="deleteConfirmationModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3 class="modal-title">Confirmar Eliminación</h3>
            <p class="modal-message">
                ¿Estás seguro de **ELIMINAR** el consumo <span id="modal-record-id">#</span> de "<strong id="modal-product-name">Producto</strong>"?
                <br>
                La cantidad consumida se **REVERTIRÁ** al stock del producto.
            </p>
            <div class="modal-actions">
                <button id="confirmDeleteBtn" class="btn-modal-confirm">Aceptar y Eliminar</button>
                <button id="cancelDeleteBtn" class="btn-modal-cancel">Cancelar</button>
            </div>
        </div>
    </div>
    
    <div id="toast-container" class="toast-container"></div>


    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('product_search');
        const resultsContainer = document.getElementById('search_results_container');
        const hiddenProductIdInput = document.getElementById('product_id');
        const form = document.getElementById('consumption-form');
        const quantityInput = document.getElementById('quantity');
        const tableBody = document.querySelector('.sales-table tbody');
        
        // Contenedor del Toast
        const toastContainer = document.getElementById('toast-container');
        
        // Referencias del Modal de Eliminación
        const deleteModal = document.getElementById('deleteConfirmationModal');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
        const modalRecordId = document.getElementById('modal-record-id');
        const modalProductName = document.getElementById('modal-product-name');
        let currentDeleteId = null; // ID del registro a eliminar

        let debounceTimeout;
        let currentEditRow = null; // Para el modo edición

        // -----------------------------------------------------------
        // FUNCIÓN TOAST DE NOTIFICACIÓN (REEMPLAZA ALERT)
        // -----------------------------------------------------------
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.classList.add('toast', type);
            toast.innerHTML = message;
            
            toastContainer.appendChild(toast);

            // Forzar reflow para la transición
            void toast.offsetWidth; 
            
            // Mostrar
            toast.classList.add('show');

            // Ocultar y eliminar después de 5 segundos
            setTimeout(() => {
                toast.classList.remove('show');
                toast.addEventListener('transitionend', () => {
                    toast.remove();
                }, { once: true });
            }, 5000);
        }

        // Mostrar Toast si viene de una redirección PRG
        const prgMessage = '<?php echo $toast_message; ?>';
        const prgType = '<?php echo $toast_type; ?>';
        if (prgMessage) {
            showToast(prgMessage, prgType);
        }
        
        // -----------------------------------------------------------
        // FUNCIONES DE BÚSQUEDA Y ESCANEO
        // -----------------------------------------------------------
        const performSearch = (query) => {
            if (query.length < 2) {
                resultsContainer.innerHTML = '';
                hiddenProductIdInput.value = '';
                return;
            }

            // RUTA CORRECTA: Desde outputs/ a search_products.php (que está un nivel arriba)
            fetch(`../search_products.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(products => {
                    resultsContainer.innerHTML = '';

                    if (products.length === 1 && (query === products[0].barcode || query.length >= 5)) {
                        selectProduct(products[0]);
                        quantityInput.focus();
                    } else if (products.length > 0) {
                        products.forEach(product => {
                            const item = createResultItem(product);
                            item.addEventListener('click', () => selectProduct(product));
                            resultsContainer.appendChild(item);
                        });
                    } else {
                        resultsContainer.innerHTML = '<div class="search-item"><em>No se encontraron productos</em></div>';
                        hiddenProductIdInput.value = '';
                    }
                })
                .catch(error => {
                    console.error('Error en la búsqueda:', error);
                    resultsContainer.innerHTML = '<div class="search-item"><em>Error al buscar</em></div>';
                });
        };

        const createResultItem = (product) => {
            const item = document.createElement('div');
            item.classList.add('search-item');
            item.innerHTML = `
                <span class="item-name">${product.name}</span>
                <span class="item-details">Código: ${product.barcode || 'N/A'} | Stock: ${product.stock}</span>
            `;
            return item;
        };

        const selectProduct = (product) => {
            searchInput.value = product.name;
            hiddenProductIdInput.value = product.id;
            resultsContainer.innerHTML = '';
            quantityInput.value = 1;
        }

        // -----------------------------------------------------------
        // MANEJADORES DE EVENTOS DE FORMULARIO
        // -----------------------------------------------------------
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimeout);
            const query = this.value.trim();
            if (query.length === 0) {
                resultsContainer.innerHTML = '';
                hiddenProductIdInput.value = '';
                return;
            }
            debounceTimeout = setTimeout(() => { performSearch(query); }, 300);
        });

        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(debounceTimeout);
                const query = this.value.trim();
                if (query.length > 5) {
                    performSearch(query);
                }
            }
        });

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
                resultsContainer.innerHTML = '';
            }
        });

        form.addEventListener('submit', function(e) {
            if (!hiddenProductIdInput.value) {
                e.preventDefault();
                showToast('Por favor, busca y selecciona un producto de la lista antes de registrar la salida.', 'error');
                searchInput.focus();
            }
            if (parseInt(quantityInput.value) <= 0) {
                e.preventDefault();
                showToast('La cantidad debe ser un número positivo.', 'error');
                quantityInput.focus();
            }
        });

        // -----------------------------------------------------------
        // MANEJADORES DE ACCIONES (EDITAR/ELIMINAR)
        // -----------------------------------------------------------
        tableBody.addEventListener('click', function(e) {
            if (e.target.classList.contains('delete-btn')) {
                handleDelete(e.target);
            } else if (e.target.classList.contains('edit-btn')) {
                handleEdit(e.target);
            }
        });

        // --- FUNCIÓN ELIMINAR (MODAL) ---
        function handleDelete(button) {
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-product-name');

            currentDeleteId = id;
            modalRecordId.textContent = `#${id}`;
            modalProductName.textContent = name;

            deleteModal.style.display = 'flex';
        }

        // --- Manejar el clic en 'Aceptar' dentro del modal ---
        confirmDeleteBtn.onclick = function() {
            deleteModal.style.display = 'none';

            if (!currentDeleteId) return;

            const id = currentDeleteId;
            currentDeleteId = null; // Resetear ID

            // Llama al script de eliminación
            fetch('delete_consumption.php', { 
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    window.location.reload(); // Recargar para actualizar tabla y KPIs
                } else {
                    showToast('Error al eliminar: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error de conexión o servidor al intentar eliminar el registro: ' + error.message, 'error');
            });
        }

        // --- Manejar el clic en 'Cancelar' dentro del modal ---
        cancelDeleteBtn.onclick = function() {
            deleteModal.style.display = 'none';
            currentDeleteId = null;
        }

        // --- Cerrar si se hace clic fuera del modal ---
        window.onclick = function(event) {
            if (event.target == deleteModal) {
                deleteModal.style.display = 'none';
                currentDeleteId = null;
            }
        }

        // --- FUNCIÓN EDITAR ---
        function handleEdit(button) {
            if (currentEditRow) {
                showToast('Ya estás editando otro registro. Finaliza esa edición o cancela primero.', 'error');
                return;
            }

            const id = button.getAttribute('data-id');
            const oldQuantity = button.getAttribute('data-quantity');
            const oldNotes = button.getAttribute('data-notes');
            const row = button.closest('tr');

            // Poner la fila en modo edición visual
            row.classList.add('editing-row');
            currentEditRow = row;

            // Reemplazar la cantidad con un input
            const quantityCell = document.getElementById(`qty-${id}`);
            quantityCell.innerHTML = `<input type="number" id="edit-qty-${id}" value="${oldQuantity}" min="1" required>`;

            // Reemplazar las notas con un input
            const notesCell = document.getElementById(`notes-${id}`);
            notesCell.innerHTML = `<input type="text" id="edit-notes-${id}" value="${oldNotes}">`;

            // Reemplazar los botones de acción con GUARDAR y CANCELAR
            const actionsCell = button.closest('td');
            actionsCell.innerHTML = `
                <button class="btn-action save-edit-btn" data-id="${id}">💾 Guardar</button>
                <button class="btn-action cancel-edit-btn" data-id="${id}">❌ Cancelar</button>
            `;

            // --- MANEJADORES DE GUARDAR/CANCELAR ---

            // Cancelar Edición
            actionsCell.querySelector('.cancel-edit-btn').addEventListener('click', () => {
                currentEditRow = null;
                window.location.reload();
            });

            // Guardar Edición
            actionsCell.querySelector('.save-edit-btn').addEventListener('click', () => {
                const newQtyInput = document.getElementById(`edit-qty-${id}`);
                const newNoteInput = document.getElementById(`edit-notes-${id}`);
                
                const newQty = newQtyInput.value;
                const newNote = newNoteInput.value;

                if (newQty <= 0) {
                    showToast('La cantidad debe ser mayor a cero.', 'error');
                    newQtyInput.focus();
                    return;
                }
                
                // Deshabilitar botones para evitar doble clic
                actionsCell.querySelectorAll('button').forEach(btn => btn.disabled = true);

                // Llama al script de edición
                fetch('edit_consumption.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${id}&quantity=${newQty}&notes=${encodeURIComponent(newNote)}&old_quantity=${oldQuantity}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        currentEditRow = null;
                        window.location.reload(); 
                    } else {
                        showToast('Error al editar: ' + data.message, 'error');
                        actionsCell.querySelectorAll('button').forEach(btn => btn.disabled = false);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error de conexión o servidor al intentar guardar la edición: ' + error.message, 'error');
                    actionsCell.querySelectorAll('button').forEach(btn => btn.disabled = false);
                });
            });
        }
    });
    </script>
</body>
</html>