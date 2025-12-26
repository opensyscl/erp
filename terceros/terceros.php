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
// --- 2. VALIDACIÓN DE ROL ESPECÍFICO (SOLO POS1) ---
// ----------------------------------------------------------------------

$user_can_access = false;
// ** MODIFICAR ESTA LÍNEA **: Ajustar la ruta de la carpeta del módulo
$module_path = '/erp/terceros/';
$current_tab = isset($_GET['tab']) && in_array($_GET['tab'], ['clients', 'suppliers']) ? $_GET['tab'] : 'clients';

try {
    // Obtenemos el rol del usuario logueado
    $stmt_role = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_role->execute([$current_user_id]);
    $user_role = $stmt_role->fetchColumn();

    // LÓGICA: Solo el rol 'POS1' tiene acceso a este módulo.
    if (in_array($user_role, ['POS1'])) {
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


// --- 5. PROCESAMIENTO DE FORMULARIOS (REGISTRO Y ACTUALIZACIÓN) ---
$message = ''; // Inicializa la variable para mensajes de éxito o error.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Detectar la acción y el tipo de entidad
    $action = '';
    $entity_type = ''; // 'client' o 'supplier'

    if (isset($_POST['register_client'])) { $action = 'register'; $entity_type = 'client'; }
    elseif (isset($_POST['update_client'])) { $action = 'update'; $entity_type = 'client'; }
    elseif (isset($_POST['register_supplier'])) { $action = 'register'; $entity_type = 'supplier'; }
    elseif (isset($_POST['update_supplier'])) { $action = 'update'; $entity_type = 'supplier'; }

    // Si hay una acción válida, procesar
    if ($action && $entity_type) {
        
        // Campos comunes para ambas entidades
        $name = trim($_POST['entity_name'] ?? '');
        $rut = trim($_POST['entity_rut'] ?? '');
        $address = trim($_POST['entity_address'] ?? '');
        $city = trim($_POST['entity_city'] ?? '');
        $email = trim($_POST['entity_email'] ?? '');
        $phone = trim($_POST['entity_phone'] ?? '');
        $web_page = trim($_POST['entity_web_page'] ?? '');
        $image_url = trim($_POST['entity_image_url'] ?? '');
        $entity_id = trim($_POST['entity_id'] ?? 0); // Para updates

        // Asignar el tab actual para mantenerlo después del POST
        $current_tab = ($entity_type === 'client') ? 'clients' : 'suppliers';
        $table_name = $current_tab; // 'clients' o 'suppliers'
        $friendly_name = ($entity_type === 'client') ? 'Cliente' : 'Proveedor';

        // Valores comunes para las consultas
        $db_values = [$name, $rut, $address, $city, $email, $phone, $web_page, $image_url];
        
        if (empty($name)) {
            $message = '<div class="alert warning">El nombre del ' . strtolower($friendly_name) . ' no puede estar vacío.</div>';
        } else {
            try {
                if ($action === 'register') {
                    // --- REGISTRAR ---
                    $db_fields = "(name, rut, address, city, email, phone, web_page, image_url)";
                    $db_placeholders = array_fill(0, count($db_values), '?');
                    $sql = "INSERT INTO $table_name $db_fields VALUES (" . implode(', ', $db_placeholders) . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($db_values);
                    $message = '<div class="alert success">' . $friendly_name . ' "' . htmlspecialchars($name) . '" registrado exitosamente.</div>';

                } elseif ($action === 'update') {
                    // --- ACTUALIZAR ---
                    if (empty($entity_id)) {
                        $message = '<div class="alert warning">ID de la entidad faltante para actualizar.</div>';
                    } else {
                        $db_fields = "name=?, rut=?, address=?, city=?, email=?, phone=?, web_page=?, image_url=?";
                        $update_values = array_merge($db_values, [$entity_id]);
                        $sql = "UPDATE $table_name SET $db_fields WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($update_values);
                        $message = '<div class="alert success">' . $friendly_name . ' "' . htmlspecialchars($name) . '" actualizado exitosamente.</div>';
                    }
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $message = '<div class="alert error">Error: El ' . strtolower($friendly_name) . ' ya existe (posiblemente por RUT duplicado).</div>';
                } else {
                    error_log("Error de BD: " . $e->getMessage());
                    $message = '<div class="alert error">Error al procesar el ' . strtolower($friendly_name) . '. Detalle: ' . $e->getMessage() . '</div>';
                }
            }
        }
    }


    // Redirigir para evitar re-envío del formulario
    if (!empty($message)) {
        // Usar strip_tags para asegurar que el mensaje es seguro en la URL
        $safe_msg = strip_tags($message);
        header("Location: terceros.php?tab=$current_tab&msg=" . urlencode($safe_msg));
        exit();
    }
}

// Manejar mensajes después de la redirección
if (isset($_GET['msg'])) {
    // Determinar la clase del alert basado en el contenido del mensaje
    $msg_text = htmlspecialchars($_GET['msg']);
    $alert_class = 'success'; // Default
    if (strpos(strtolower($msg_text), 'error') !== false) {
        $alert_class = 'error';
    } elseif (strpos(strtolower($msg_text), 'warning') !== false || strpos(strtolower($msg_text), 'vacío') !== false) {
        $alert_class = 'warning';
    }
    $message = '<div class="alert ' . $alert_class . '">' . $msg_text . '</div>';
}


// --- 6. OBTENCIÓN DE DATOS PARA LA VISTA ---
$clients = [];
$suppliers = [];

try {
    // Definimos los campos que necesitamos
    $base_fields = "id, name, rut, address, city, email, phone, web_page, image_url, DATE(created_at) AS registration_date";

    // Obtener Clientes (se mantienen ordenados por nombre)
    $clients_stmt = $pdo->query("SELECT $base_fields, next_invoice_number FROM clients ORDER BY name ASC");
    $clients = $clients_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener Proveedores (*** CAMBIO AQUÍ: Ordenado por fecha de creación descendente ***)
    $suppliers_stmt = $pdo->query("SELECT $base_fields, next_quotation_number FROM suppliers ORDER BY created_at DESC");
    $suppliers = $suppliers_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Manejo de error al cargar datos
    $clients = [];
    $suppliers = [];
    // Nota: El error de columna faltante es común al añadir nuevos campos (image_url, etc.)
    $message = '<div class="alert error">Error al cargar datos. Verifica las columnas de la BD (e.g., image_url, next_invoice_number). Detalle: ' . htmlspecialchars($e->getMessage()) . '</div>';
}


// --- 7. OBTENCIÓN DE DATOS ADICIONALES PARA LA VISTA (Header) ---
$system_version = '1.0';
try {
    $stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
    $stmt->execute();
    $version_result = $stmt->fetchColumn();
    if ($version_result) {
        $system_version = $version_result;
    }
} catch (PDOException $e) {
    // Mantiene el valor predeterminado si falla la consulta de la versión.
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Terceros - Mi Sistema</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" type="image/png" href="../img/fav.png">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/terceros.css">

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
            <a href="terceros.php" class="active">Gestión de Terceros</a>
        </nav>
        <div class="header-right">
            <span class="app-version"><?php echo htmlspecialchars($system_version); ?></span>
            <a href="../logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </header>

    <main class="container">
        <?php echo $message; // Muestra mensajes de éxito o error ?>

        <div class="tabs-container">
            <button class="tab-button <?= $current_tab === 'suppliers' ? 'active' : '' ?>" data-tab="suppliers">Proveedores</button>
            <button class="tab-button <?= $current_tab === 'clients' ? 'active' : '' ?>" data-tab="clients">Clientes</button>
            
        </div>

        <div class="main-content-split">
            
            <div class="side-panel">
                <div class="content-card">
                    
                    <div id="form-clients" class="entity-form <?= $current_tab === 'clients' ? 'active' : 'hidden' ?>">
                        <h2 id="form-clients-title">Registrar Cliente</h2>
                        <form action="terceros.php" method="POST">
                            <input type="hidden" name="action_type" value="register_client" id="client_form_action">
                            <input type="hidden" name="entity_id" id="client_id" value="">
                            
                            <div class="form-group"><label for="client_name">Nombre / Razón Social</label><input type="text" id="client_name" name="entity_name" required></div>
                            <div class="form-group"><label for="client_rut">RUT</label><input type="text" id="client_rut" name="entity_rut"></div>
                            <div class="form-group"><label for="client_address">Dirección</label><input type="text" id="client_address" name="entity_address"></div>
                            <div class="form-group"><label for="client_city">Comuna</label><input type="text" id="client_city" name="entity_city"></div>
                            <div class="form-group"><label for="client_email">Correo</label><input type="email" id="client_email" name="entity_email"></div>
                            <div class="form-group"><label for="client_phone">Teléfono</label><input type="tel" id="client_phone" name="entity_phone"></div>
                            <div class="form-group"><label for="client_web_page">Página Web</label><input type="url" id="client_web_page" name="entity_web_page"></div>
                            <div class="form-group"><label for="client_image_url">URL de Imagen/Logo</label><input type="url" id="client_image_url" name="entity_image_url"></div>
                            
                            <!-- El nombre del botón se establece en JS para 'register_client' o 'update_client' -->
                            <button type="submit" name="register_client" id="client_submit_button" class="btn-submit btn-client">Añadir Cliente</button>
                            <button type="button" id="client_cancel_button" class="btn-cancel hidden">Cancelar Edición</button>
                        </form>
                    </div>

                    <div id="form-suppliers" class="entity-form <?= $current_tab === 'suppliers' ? 'active' : 'hidden' ?>">
                        <h2 id="form-suppliers-title">Registrar Proveedor</h2>
                        <form action="terceros.php" method="POST">
                            <input type="hidden" name="action_type" value="register_supplier" id="supplier_form_action">
                            <input type="hidden" name="entity_id" id="supplier_id" value="">
                            
                            <div class="form-group"><label for="supplier_name">Nombre / Razón Social</label><input type="text" id="supplier_name" name="entity_name" required></div>
                            <div class="form-group"><label for="supplier_rut">RUT</label><input type="text" id="supplier_rut" name="entity_rut"></div>
                            <div class="form-group"><label for="supplier_address">Dirección</label><input type="text" id="supplier_address" name="entity_address"></div>
                            <div class="form-group"><label for="supplier_city">Comuna</label><input type="text" id="supplier_city" name="entity_city"></div>
                            <div class="form-group"><label for="supplier_email">Correo</label><input type="email" id="supplier_email" name="entity_email"></div>
                            <div class="form-group"><label for="supplier_phone">Teléfono</label><input type="tel" id="supplier_phone" name="entity_phone"></div>
                            <div class="form-group"><label for="supplier_web_page">Página Web</label><input type="url" id="supplier_web_page" name="entity_web_page"></div>
                            <div class="form-group"><label for="supplier_image_url">URL de Imagen/Logo</label><input type="url" id="supplier_image_url" name="entity_image_url"></div>
                            
                            <!-- El nombre del botón se establece en JS para 'register_supplier' o 'update_supplier' -->
                            <button type="submit" name="register_supplier" id="supplier_submit_button" class="btn-submit btn-supplier">Añadir Proveedor</button>
                            <button type="button" id="supplier_cancel_button" class="btn-cancel hidden">Cancelar Edición</button>
                        </form>
                    </div>

                </div>
            </div>

            <div class="main-panel">
                
                <div id="grid-clients" class="entity-grid-view <?= $current_tab === 'clients' ? 'active' : 'hidden' ?>">
                    <h2 class="grid-title">Clientes Registrados (<?= count($clients) ?>)</h2>
                    <div class="entity-grid">
                        <?php if (empty($clients)): ?>
                            <p class="empty-message">No hay clientes registrados.</p>
                        <?php else: ?>
                            <?php foreach ($clients as $client): ?>
                                <div class="entity-card client-card" 
                                    data-id="<?= htmlspecialchars($client['id']) ?>"
                                    data-name="<?= htmlspecialchars($client['name']) ?>"
                                    data-rut="<?= htmlspecialchars($client['rut'] ?? '') ?>"
                                    data-address="<?= htmlspecialchars($client['address'] ?? '') ?>"
                                    data-city="<?= htmlspecialchars($client['city'] ?? '') ?>"
                                    data-email="<?= htmlspecialchars($client['email'] ?? '') ?>"
                                    data-phone="<?= htmlspecialchars($client['phone'] ?? '') ?>"
                                    data-web_page="<?= htmlspecialchars($client['web_page'] ?? '') ?>"
                                    data-image_url="<?= htmlspecialchars($client['image_url'] ?? '') ?>">
                                    
                                    <div class="entity-image-container">
                                        <?php 
                                        // Placeholder azul para clientes
                                        $img_url = !empty($client['image_url']) ? htmlspecialchars($client['image_url']) : 'https://placehold.co/100x100/4F46E5/FFFFFF?text=CL'; 
                                        ?>
                                        <img src="<?= $img_url ?>" alt="Logo de <?= htmlspecialchars($client['name']) ?>" class="entity-image" onerror="this.onerror=null;this.src='https://placehold.co/100x100/4F46E5/FFFFFF?text=CL';">
                                    </div>
                                    <div class="entity-info">
                                        <strong class="entity-name"><?= htmlspecialchars($client['name']) ?></strong>
                                        <span class="entity-detail">RUT: <?= htmlspecialchars($client['rut'] ?? 'N/A') ?></span>
                                        <div class="entity-stock stock-client">
                                            Sig. Factura: <?= htmlspecialchars($client['next_invoice_number'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div id="grid-suppliers" class="entity-grid-view <?= $current_tab === 'suppliers' ? 'active' : 'hidden' ?>">
                    <h2 class="grid-title">Proveedores Registrados (<?= count($suppliers) ?>)</h2>
                    <div class="entity-grid">
                        <?php if (empty($suppliers)): ?>
                            <p class="empty-message">No hay proveedores registrados.</p>
                        <?php else: ?>
                            <?php foreach ($suppliers as $supplier): ?>
                                <div class="entity-card supplier-card" 
                                    data-id="<?= htmlspecialchars($supplier['id']) ?>"
                                    data-name="<?= htmlspecialchars($supplier['name']) ?>"
                                    data-rut="<?= htmlspecialchars($supplier['rut'] ?? '') ?>"
                                    data-address="<?= htmlspecialchars($supplier['address'] ?? '') ?>"
                                    data-city="<?= htmlspecialchars($supplier['city'] ?? '') ?>"
                                    data-email="<?= htmlspecialchars($supplier['email'] ?? '') ?>"
                                    data-phone="<?= htmlspecialchars($supplier['phone'] ?? '') ?>"
                                    data-web_page="<?= htmlspecialchars($supplier['web_page'] ?? '') ?>"
                                    data-image_url="<?= htmlspecialchars($supplier['image_url'] ?? '') ?>">
                                    
                                    <div class="entity-image-container">
                                        <?php 
                                        // Placeholder rojo para proveedores
                                        $img_url = !empty($supplier['image_url']) ? htmlspecialchars($supplier['image_url']) : 'https://placehold.co/100x100/EF4444/FFFFFF?text=PR'; 
                                        ?>
                                        <img src="<?= $img_url ?>" alt="Logo de <?= htmlspecialchars($supplier['name']) ?>" class="entity-image" onerror="this.onerror=null;this.src='https://placehold.co/100x100/EF4444/FFFFFF?text=PR';">
                                    </div>
                                    <div class="entity-info">
                                        <strong class="entity-name"><?= htmlspecialchars($supplier['name']) ?></strong>
                                        <span class="entity-detail">RUT: <?= htmlspecialchars($supplier['rut'] ?? 'N/A') ?></span>
                                        <div class="entity-stock stock-supplier">
                                            Sig. Cot.: <?= htmlspecialchars($supplier['next_quotation_number'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

    </main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tabButtons = document.querySelectorAll('.tab-button');
        const entityForms = document.querySelectorAll('.entity-form');
        const entityGrids = document.querySelectorAll('.entity-grid-view');
        const mainPanel = document.querySelector('.main-panel');
        const sidePanel = document.querySelector('.side-panel');

        // --- GESTIÓN DE PESTAÑAS ---

        function changeTab(tabName) {
            // Actualizar botones
            tabButtons.forEach(button => {
                button.classList.toggle('active', button.dataset.tab === tabName);
            });

            // Mostrar/Ocultar formularios
            entityForms.forEach(form => {
                const isActive = form.id === `form-${tabName}`;
                form.classList.toggle('active', isActive);
                form.classList.toggle('hidden', !isActive);
            });

            // Mostrar/Ocultar grillas
            entityGrids.forEach(grid => {
                const isActive = grid.id === `grid-${tabName}`;
                grid.classList.toggle('active', isActive);
                grid.classList.toggle('hidden', !isActive);
            });
            
            // Actualizar URL
            const newUrl = new URL(window.location.href);
            newUrl.searchParams.set('tab', tabName);
            // Limpiar 'msg' al cambiar de pestaña manualmente
            newUrl.searchParams.delete('msg'); 
            window.history.pushState({ path: newUrl.href }, '', newUrl.href);

            // Resetear formularios al cambiar de pestaña
            resetForm('clients');
            resetForm('suppliers');
        }

        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                changeTab(this.dataset.tab);
            });
        });

        // --- LÓGICA PARA POBLAR FORMULARIO AL HACER CLIC ---

        /**
         * Rellena el formulario con los datos de una tarjeta.
         * @param {string} type - 'clients' o 'suppliers'
         * @param {DOMStringMap} data - El objeto card.dataset
         */
        function populateForm(type, data) {
            const prefix = type.slice(0, -1); // 'client' o 'supplier'
            
            // Rellenar campos del formulario
            document.getElementById(`${prefix}_id`).value = data.id || '';
            document.getElementById(`${prefix}_name`).value = data.name || '';
            document.getElementById(`${prefix}_rut`).value = data.rut || '';
            document.getElementById(`${prefix}_address`).value = data.address || '';
            document.getElementById(`${prefix}_city`).value = data.city || '';
            document.getElementById(`${prefix}_email`).value = data.email || '';
            document.getElementById(`${prefix}_phone`).value = data.phone || '';
            document.getElementById(`${prefix}_web_page`).value = data.web_page || '';
            document.getElementById(`${prefix}_image_url`).value = data.image_url || '';

            // Cambiar título
            document.getElementById(`form-${type}-title`).textContent = `Editar ${prefix === 'client' ? 'Cliente' : 'Proveedor'} (ID: ${data.id})`;

            // Cambiar botón de envío (cambia el atributo 'name' para que PHP sepa la acción)
            const submitButton = document.getElementById(`${prefix}_submit_button`);
            submitButton.textContent = `Actualizar ${prefix === 'client' ? 'Cliente' : 'Proveedor'}`;
            // Se debe actualizar el atributo 'name' para que PHP reciba el valor correcto
            submitButton.setAttribute('name', `update_${prefix}`); 

            // Mostrar botón de cancelar
            document.getElementById(`${prefix}_cancel_button`).classList.remove('hidden');

            // Asegurarse de que la pestaña correcta esté visible
            if (!document.getElementById(`form-${type}`).classList.contains('active')) {
                changeTab(type);
            }
            
            // Desplazarse al formulario
            sidePanel.scrollIntoView({ behavior: 'smooth' });
        }

        // Usamos delegación de eventos en el panel derecho para las tarjetas
        mainPanel.addEventListener('click', function(e) {
            const card = e.target.closest('.entity-card');
            if (!card) return; // No se hizo clic en una tarjeta

            let type;
            if (card.classList.contains('client-card')) {
                type = 'clients';
            } else if (card.classList.contains('supplier-card')) {
                type = 'suppliers';
            } else {
                return;
            }

            // Si la tarjeta está seleccionada, deseleccionarla y resetear el form
            if (card.classList.contains('selected')) {
                resetForm(type);
                card.classList.remove('selected');
            } else {
                 // Primero, remover 'selected' de todas las tarjetas
                document.querySelectorAll('.entity-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');

                // Llenar el formulario
                populateForm(type, card.dataset);
            }
        });

        // --- LÓGICA PARA RESETEAR FORMULARIO ---

        /**
         * Resetea un formulario a su estado de "Registrar".
         * @param {string} type - 'clients' o 'suppliers'
         */
        function resetForm(type) {
            const prefix = type.slice(0, -1); // 'client' o 'supplier'
            const form = document.getElementById(`form-${type}`).querySelector('form');
            if (!form) return;

            form.reset(); // Limpia todos los campos
            
            // Limpiar ID oculto
            document.getElementById(`${prefix}_id`).value = '';

            // Resetear título
            document.getElementById(`form-${type}-title`).textContent = `Registrar ${prefix === 'client' ? 'Cliente' : 'Proveedor'}`;

            // Resetear botón de envío
            const submitButton = document.getElementById(`${prefix}_submit_button`);
            submitButton.textContent = `Añadir ${prefix === 'client' ? 'Cliente' : 'Proveedor'}`;
            // Resetear el atributo 'name' a 'register_client' o 'register_supplier'
            submitButton.setAttribute('name', `register_${prefix}`); 

            // Ocultar botón de cancelar
            document.getElementById(`${prefix}_cancel_button`).classList.add('hidden');
            
            // Deseleccionar cualquier tarjeta
            document.querySelectorAll('.entity-card').forEach(c => c.classList.remove('selected'));
        }

        // Asignar eventos a los botones de cancelar
        document.getElementById('client_cancel_button').addEventListener('click', () => resetForm('clients'));
        document.getElementById('supplier_cancel_button').addEventListener('click', () => resetForm('suppliers'));


        // --- GESTIÓN DE URL AL CARGAR ---

        // Inicializar la pestaña correcta al cargar (basado en PHP)
        const initialTab = '<?= $current_tab ?>';
        if (!document.querySelector(`.tab-button[data-tab="${initialTab}"]`).classList.contains('active')) {
             changeTab(initialTab);
        }

        // Limpiar solo 'msg' de la URL después de verlo
        window.onload = function() {
            if (window.location.search.includes('msg=')) {
                const url = new URL(window.location);
                url.searchParams.delete('msg'); // Solo elimina 'msg'
                window.history.replaceState({}, document.title, url.toString());
            }
        }
    });
</script>
</body>
</html>