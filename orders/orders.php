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
// RUTA CONFIGURADA: Módulo de Ordenes y Pedidos
$module_path = '/erp/orders/'; 

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


// Variables para el encabezado (versión del sistema)
try {
 $stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
 $stmt->execute();
 $system_version = $stmt->fetchColumn() ?? 'v1.0';
} catch (PDOException $e) {
 $system_version = 'v1.0 (DB Error)';
 error_log("Database error fetching version: " . $e->getMessage());
}

// --- CONSTANTES Y FUNCIONES DE CÁLCULO DE PRECIOS DE COMPRA ---
define('IVA_RATE', 0.19);

/**
* Convierte un valor a entero (para precios sin decimales).
*/
function to_integer_price($value) {
 return (int) round($value);
}

/**
* Calcula el Costo Bruto (con IVA) a partir del Costo Neto.
*/
function calculate_gross_cost($net_cost) {
 return $net_cost * (1 + IVA_RATE);
}

// --- LÓGICA DE PROVEEDORES Y DATOS ---

$suppliers = [];
$selected_supplier_id = null;
$supplier_products = [];
$supplier_orders = [];
$selected_supplier_name = '';

try {
 // 1. Obtener la lista de todos los proveedores para el sidebar
 $stmt_suppliers = $pdo->prepare("SELECT id, name FROM suppliers ORDER BY name ASC");
 $stmt_suppliers->execute();
 $suppliers = $stmt_suppliers->fetchAll(PDO::FETCH_ASSOC);

 // 2. Determinar el proveedor seleccionado (por defecto, el primero si existe)
 if (isset($_GET['supplier_id']) && is_numeric($_GET['supplier_id'])) {
  $selected_supplier_id = (int)$_GET['supplier_id'];
 } elseif (!empty($suppliers)) {
  // Aseguramos que el ID del primer proveedor sea un entero.
  $selected_supplier_id = (int)$suppliers[0]['id'];
 }

 // 3. Obtener los productos y ÓRDENES del proveedor seleccionado (si hay uno)
 if ($selected_supplier_id) {
  // Nombre del proveedor seleccionado
  $stmt_name = $pdo->prepare("SELECT name FROM suppliers WHERE id = ?");
  $stmt_name->execute([$selected_supplier_id]);
  $selected_supplier_name = $stmt_name->fetchColumn() ?? 'Proveedor Desconocido';

  // Productos del proveedor seleccionado
// 💡 CAMBIO CLAVE: Se agregó la columna 'image_url' a la selección
$stmt_products = $pdo->prepare("
 SELECT id, barcode AS code, name, stock, cost_price, price, image_url
 FROM products
 WHERE supplier_id = ?
 ORDER BY name ASC
");
  $stmt_products->execute([$selected_supplier_id]);
  $raw_products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

  // Procesar productos para costos actuales
  foreach ($raw_products as $product) {
   $net_cost = (float)$product['cost_price'];
   $gross_cost = calculate_gross_cost($net_cost);

   $supplier_products[] = [
    'id' => (int)$product['id'],
    'code' => htmlspecialchars($product['code']),
    'name' => htmlspecialchars($product['name']),
    'stock' => (int)$product['stock'],
    // Costos de compra actuales
    'cost_price_net' => to_integer_price($net_cost),
    'cost_price_gross' => to_integer_price($gross_cost),
    'current_sale_price' => to_integer_price((float)$product['price']),
        // 💡 CLAVE: Incluir la URL de la imagen en el JSON para JavaScript
    'image_url' => htmlspecialchars($product['image_url'] ?? ''),
   ];
  }

  // Órdenes de Compra del proveedor seleccionado
$stmt_orders = $pdo->prepare("
  SELECT po.id,
     po.order_number,
     po.date,
     po.created_at,
     po.total_amount,
     s.name AS supplier_name,
     u.username AS creator_username
  FROM purchase_orders po
  LEFT JOIN suppliers s ON po.supplier_id = s.id -- 👈 Cambiado a LEFT JOIN
  LEFT JOIN users u ON po.created_by = u.id
  WHERE po.supplier_id = ?
  ORDER BY po.created_at DESC
");
$stmt_orders->execute([$selected_supplier_id]);
$supplier_orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);
 }
} catch (PDOException $e) {
 error_log("Database operation failed: " . $e->getMessage());
 $db_error = true;
}


// Codificar datos de productos para usar en JavaScript
$products_json = json_encode($supplier_products);
$orders_json = json_encode($supplier_orders);

// Definir página actual para el header
$current_page = 'orders.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Generación de Pedidos - Listto!</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="icon" type="image/png" href="/erp/img/fav.png">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/orders.css">
<script src="https://unpkg.com/@phosphor-icons/web@2.0.3/dist/assets/inline.js"></script>
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
<a href="orders.php" class="active">Órdenes de Compra</a>
</nav>
<div class="header-right">
<span class="app-version"><?php echo htmlspecialchars($system_version); ?></span>
<a href="../logout.php" class="btn-logout">Cerrar Sesión <i class="ph ph-sign-out"></i></a>
</div>
</header>

<div id="status-message" class="status-message" style="display: none;"></div>

<div class="main-wrapper">
    <aside class="sidebar">
        
        <div class="sidebar-content">
            
            <h2 class="sidebar-title">Proveedores</h2>
            
            <ul class="supplier-list">
                <?php if (empty($suppliers)): ?>
                <li class="no-suppliers">No hay proveedores registrados.</li>
                <?php else: ?>
                <?php foreach ($suppliers as $supplier): ?>
                <li class="supplier-item">
                    <a href="?supplier_id=<?= $supplier['id'] ?>" class="<?= $supplier['id'] == $selected_supplier_id ? 'active' : '' ?>">
                        <i class="ph ph-truck-trailer"></i> <?= htmlspecialchars($supplier['name']) ?>
                    </a>
                </li>
                <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        
        </div>
        </aside>

<main class="container main-content">
<?php if (!$selected_supplier_id): ?>
<div class="content-card info-message">
<p><i class="ph ph-info-bold"></i> Por favor, selecciona un proveedor de la lista de la izquierda para comenzar a generar órdenes de compra.</p>
</div>
<?php else: ?>
<h1 class="page-title">Generar Orden de Compra: <?= htmlspecialchars($selected_supplier_name) ?></h1>

<div class="content-card invoice-form-card">
<h2>Productos en el Pedido</h2>

<div class="product-controls">
<button id="add-product-btn" class="btn-secondary" data-modal-target="select-product-modal"><i class="ph ph-plus-circle"></i> Agregar Producto Existente</button>
<button id="add-new-product-btn" class="btn-primary" data-modal-target="add-new-product-modal"><i class="ph ph-magic-wand"></i> Agregar Nuevo Producto</button>
</div>

<form id="invoice-main-form">
<div class="table-container">
<table class="supplier-products-table">
<thead>
<tr>
<th rowspan="2" class="col-code">Código</th>
<th rowspan="2" class="col-product">Producto</th>

<th colspan="2" class="current-metrics-header">Costos Registrados</th>
<th colspan="3" class="invoice-data-header">Datos del Pedido</th>
<th rowspan="2">Acción</th>
</tr>
<tr>
<th>Costo Neto Act.</th>
<th>Costo Bruto Act.</th>

<th>Costo Neto Pedido <span class="required">*</span></th>
<th>Costo Bruto Pedido</th>
<th>Cantidad a Pedir <span class="required">*</span></th>
</tr>
</thead>
<tbody id="invoice-items-body">
<tr id="no-products-row"><td colspan="8" style="text-align: center; padding: 1rem; color: #666;">Para empezar, agrega un prodcuto nuevo o existente </td></tr>
</tbody>
</table>
</div>

<div class="invoice-totals">
<p>Subtotal (Neto): <span id="subtotal-net" class="total-value">$0</span></p>
<p>IVA (<?= (IVA_RATE * 100) ?>%): <span id="iva-amount" class="total-value">$0</span></p>
<p class="total-line">Total Orden (Bruto): <span id="total-gross" class="total-value highlighted">$0</span></p>
</div>

<div class="form-actions">
<button id="clear-invoice-btn" class="btn-secondary" type="button"><i class="ph ph-trash"></i> Limpiar Pedido</button>
<button id="register-invoice-btn" class="btn-success" disabled><i class="ph ph-note-pencil"></i> Generar Orden</button>
</div>
</form>
</div>
<div class="content-card history-card">
  <h2>Órdenes de Compra Generadas</h2>
  <div class="table-container">
    <table class="invoices-table">
      <thead>
        <tr>
          <th>Nº Orden</th>
          <th>Proveedor</th>
          <th>Fecha de Pedido</th>
          <th>Monto</th>
          <th>Creada por</th>
          <th>Acción</th>
        </tr>
      </thead>
<tbody id="orders-list-body">
  <?php if (empty($supplier_orders)): ?>
    <tr><td colspan="6" style="text-align: center; padding: 1rem;">No hay órdenes registradas para este proveedor.</td></tr>
  <?php else: ?>
    <?php foreach ($supplier_orders as $order):
     
      // 🛑 CLAVE 1: Leer Proveedor y Usuario de los JOINS 🛑
      $supplier_name = htmlspecialchars($order['supplier_name'] ?? 'Error de Proveedor');
      $creator_username = htmlspecialchars($order['creator_username'] ?? $_SESSION['user_username'] ?? 'Usuario Desconocido');
     
      // 🛑 CLAVE 2: Formato del Monto (Evita el N/A y los warnings) 🛑
      $total_amount = $order['total_amount'] ?? 0;
      $total_amount_formatted = ($total_amount > 0)
        ? '$' . number_format($total_amount, 0, ',', '.')
        : 'N/A';
    ?>
      <tr>
        <td><?= htmlspecialchars($order['order_number']) ?></td>
        <td><?= $supplier_name ?></td>
        <td><?= date('d-m-Y', strtotime($order['date'])) ?></td>
        <td><?= $total_amount_formatted ?></td>
        <td><?= $creator_username ?></td>
        <td>
          <a href="ver_oc.php?id=<?= $order['id'] ?>" target="_blank" class="btn-view-invoice">
            <i class="ph ph-magnifying-glass"></i> Ver Detalle
          </a>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php endif; ?>
</tbody>
    </table>
  </div>
</div>

<div id="register-modal" class="modal">
 <div class="modal-content">
  <span class="close-button" data-modal-target="register-modal">&times;</span>
  <h3>Confirmar y Generar Orden de Compra</h3>
  <p class="modal-summary">Estás a punto de generar la orden de compra para este proveedor.</p>
  <form id="final-invoice-form">
   <p class="total-summary">Total Orden a Registrar (Bruto): <strong id="modal-total-display">$0</strong></p>
   <input type="hidden" id="final-supplier-id" value="<?= $selected_supplier_id ?>">

   <div class="form-group">
    <label for="order_date">Fecha de Emisión:</label>
    <input type="date" id="order_date" name="order_date" value="<?= date('Y-m-d') ?>" required>
   </div>
   <button type="submit" class="btn-success btn-full-width"><i class="ph ph-floppy-disk"></i> Generar y Guardar Orden</button>
  </form>
 </div>
</div>

<div id="select-product-modal" class="modal">
<div class="modal-content large-modal-content"> 
    <span class="close-button" data-modal-target="select-product-modal">&times;</span>
    <h3>Seleccionar Producto Existente</h3>
    <p>Haz clic en las tarjetas de producto que deseas añadir a tu orden de compra. Los ya agregados están desactivados.</p>
    <div id="product-grid-container" class="product-grid-container">
        <p class="search-info">Cargando productos...</p>
    </div>
</div>
</div>

<div id="add-new-product-modal" class="modal">
<div class="modal-content">
<span class="close-button" data-modal-target="add-new-product-modal">&times;</span>
<h3>Crear y Añadir Nuevo Producto</h3>
<p>Acá podrás añadir los datos básicos del producto</p>
<form id="new-product-form">
<input type="hidden" id="new-product-supplier-id" value="<?= $selected_supplier_id ?>">

<div class="form-group">
<label for="new_product_code">Código de barras:</label>
<input type="text" id="new_product_code" name="new_product_code" required>
</div>
<div class="form-group">
<label for="new_product_name">Nombre del Producto:</label>
<input type="text" id="new_product_name" name="new_product_name" required>
</div>
<div class="form-group">
<label for="new_product_quantity">Cantidad a Pedir:</label>
<input type="number" id="new_product_quantity" name="new_product_quantity" value="1" min="1" step="1" required>
</div>
<hr class="form-divider">
<h4>Definición de Costo de Compra</h4>
<div class="form-group price-input-group">
<label for="new_product_cost_net">Costo Neto (Inicial):</label>
<input type="number" step="1" id="new_product_cost_net" name="new_product_cost_net" value="1000" min="0" required>
<p class="help-text">Costo Bruto estimado: <span id="new_product_cost_gross_display"></span></p>
</div>
<button type="submit" class="btn-success btn-full-width"><i class="ph ph-plus-square"></i> Crear y añadir producto</button>
</form>
</div>
</div>


<?php endif; ?>
</main>
</div>



<script>
// --- INYECCIÓN DE VARIABLES JAVASCRIPT ---
const SUPPLIER_ID = <?= $selected_supplier_id ?? 0; ?>;
const ALL_SUPPLIER_PRODUCTS = <?= $products_json; ?>;
const INVOICE_ITEMS = []; // Mantenemos el nombre para reutilizar la lógica de arrays
const IVA_RATE = <?= IVA_RATE; ?>;

// Formateador de moneda (peso chileno)
const currencyFormatter = new Intl.NumberFormat('es-CL', {
style: 'currency',
currency: 'CLP',
minimumFractionDigits: 0
});
</script>
<script src="js/orders.js"></script>
<div id="status-message" class="status-message" aria-live="polite" aria-atomic="true"></div>
</body>
</html>