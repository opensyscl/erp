<?php
/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/

// Configuración y Seguridad
require 'config.php';
session_start();

if (!isset($_SESSION['user_username'])) {
  header('Location: ../login.php');
  exit();
}

// Variables para el encabezado
$current_page = 'inventario.php';
try {
  $stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
  $stmt->execute();
  $system_version = $stmt->fetchColumn() ?: 'N/A';
} catch (PDOException $e) {
  // Manejo de error de consulta de versión, si es necesario
  $system_version = 'Error DB';
}


// --- OBTENER DATOS DE INVENTARIO PARA KPIs ---
$total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$low_stock_products = $pdo->query("SELECT COUNT(*) FROM products WHERE stock < 5 AND stock > 0")->fetchColumn();
$out_of_stock_products = $pdo->query("SELECT COUNT(*) FROM products WHERE stock = 0")->fetchColumn();
$no_image_products = $pdo->query("SELECT COUNT(*) FROM products WHERE image_url IS NULL OR image_url = ''")->fetchColumn();

// Nuevos KPIs
$no_supplier_products = $pdo->query("SELECT COUNT(*) FROM products WHERE supplier_id IS NULL OR supplier_id = 0")->fetchColumn();
$negative_stock_products = $pdo->query("SELECT COUNT(*) FROM products WHERE stock < 0")->fetchColumn();


// --- OBTENER CATEGORÍAS Y PROVEEDORES ---
$stmt_categories = $pdo->prepare("SELECT id, name FROM categories ORDER BY name");
$stmt_categories->execute();
$categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);

$stmt_suppliers = $pdo->prepare("SELECT id, name FROM suppliers ORDER BY name");
$stmt_suppliers->execute();
$suppliers = $stmt_suppliers->fetchAll(PDO::FETCH_ASSOC);

// ------------------------------------------------------------------
// 🚀 LÓGICA DE FILTRADO Y ORDENAMIENTO (PHP)
// ------------------------------------------------------------------

// 1. Capturar todos los filtros y la búsqueda
$filtro_supplier = isset($_GET['supplier_id']) ? $_GET['supplier_id'] : 'all';
$filtro_sort = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'updated_at_desc';
$search_term = isset($_GET['search_query']) ? trim($_GET['search_query']) : '';

// 2. Definir variables por defecto y listas blancas (Whitelist) para seguridad
$order_by_default = 'updated_at';
$order_direction_default = 'DESC';
$order_by = $order_by_default;
$order_direction = $order_direction_default;

$allowed_columns = ['name', 'stock', 'created_at', 'updated_at', 'price']; // Columnas permitidas
$where_clauses = ['1=1']; // Inicializa las condiciones WHERE

// 3. 🛠️ TRADUCIR EL FILTRO DE ORDENAMIENTO A SQL
// El orden Inverso (DESC) y Directo (ASC) están definidos aquí.
switch ($filtro_sort) {
    // Ordenar por Nombre
    case 'name_asc': // Directo (A-Z)
        $order_by = 'name';
        $order_direction = 'ASC';
        break;
    case 'name_desc': // INVERSO (Z-A)
        $order_by = 'name';
        $order_direction = 'DESC';
        break;

    // Ordenar por Stock
    case 'stock_desc': // INVERSO (Mayor a Menor)
        $order_by = 'stock';
        $order_direction = 'DESC';
        break;
    case 'stock_asc': // Directo (Menor a Mayor)
        $order_by = 'stock';
        $order_direction = 'ASC';
        break;

    // Ordenar por Fechas de Creación
    case 'created_at_desc': // Directo (Más Reciente a Más Antiguo)
        $order_by = 'created_at';
        $order_direction = 'DESC';
        break;
    case 'created_at_asc': // INVERSO (Más Antiguo a Más Reciente)
        $order_by = 'created_at';
        $order_direction = 'ASC';
        break;

    // Ordenar por Fechas de Modificación (por defecto si no hay filtro)
    case 'updated_at_desc': // Directo (Más Reciente a Más Antiguo)
        $order_by = 'updated_at';
        $order_direction = 'DESC';
        break;
    case 'updated_at_asc': // INVERSO (Más Antiguo a Más Reciente)
        $order_by = 'updated_at';
        $order_direction = 'ASC';
        break;

    // Ordenar por Precios
    case 'price_desc': // Directo (Mayor a Menor)
        $order_by = 'price';
        $order_direction = 'DESC';
        break;
    case 'price_asc': // INVERSO (Menor a Mayor)
        $order_by = 'price';
        $order_direction = 'ASC';
        break;
        
    default:
        // Si no coincide, se mantiene el valor por defecto
        break;
}

// 4. 🔒 SANITIZACIÓN: Comprueba que la columna y dirección son válidas (Whitelist)
// Esto es CRUCIAL para evitar la inyección SQL en la cláusula ORDER BY.
if (!in_array($order_by, $allowed_columns) || !in_array($order_direction, ['ASC', 'DESC'])) {
    $order_by = $order_by_default;
    $order_direction = $order_direction_default;
}

// 5. 🔎 CONSTRUIR CLÁUSULAS WHERE BASADAS EN FILTROS Y BÚSQUEDA

// Lógica de Búsqueda por Texto
if (!empty($search_term)) {
    // Busca en nombre y SKU, usamos parámetros de PDO para seguridad
    $where_clauses[] = "(`name` LIKE :search_term OR `sku` LIKE :search_term)";
}

// Lógica de Filtro por Proveedor
if ($filtro_supplier !== 'all' && is_numeric($filtro_supplier)) {
    $where_clauses[] = "supplier_id = :supplier_id";
}


// 6. ⚙️ CONSTRUIR Y PREPARAR LA CONSULTA SQL FINAL
$sql = "SELECT * FROM products 
        WHERE " . implode(' AND ', $where_clauses) . "
        ORDER BY {$order_by} {$order_direction}"; // 👈 Aquí se inyectan las variables sanitizadas

// Usar consultas preparadas para la búsqueda y filtros WHERE
$stmt_products = $pdo->prepare($sql);

// 7.  EJECUCIÓN DE LA CONSULTA
$params = [];
if (!empty($search_term)) {
    // El valor para PDO debe incluir los comodines.
    $params[':search_term'] = "%" . $search_term . "%";
}
if ($filtro_supplier !== 'all' && is_numeric($filtro_supplier)) {
    $params[':supplier_id'] = $filtro_supplier;
}

$stmt_products->execute($params);
$products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

// ------------------------------------------------------------------
// FIN LÓGICA DE FILTRADO Y ORDENAMIENTO
// ------------------------------------------------------------------

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inventario - Listto! ERP</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="icon" type="../image/png" href="img/fav.png">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/inventario.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="launcher.php" class="launcher-icon" title="Ir al Lanzador de Aplicaciones">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="5" cy="5" r="3"/><circle cx="12" cy="5" r="3"/><circle cx="19" cy="5" r="3"/>
                    <circle cx="5" cy="12" r="3"/><circle cx="12" cy="12" r="3"/><circle cx="19" cy="12" r="3"/>
                    <circle cx="5" cy="19" r="3"/><circle cx="12" cy="19" r="3"/><circle cx="19" cy="19" r="3"/>
                </svg>
            </a>
            <span>Hola, <strong><?php echo htmlspecialchars($_SESSION['user_username'] ?? 'Usuario'); ?></strong></span>
        </div>
        <nav class="header-nav">
            <a href="inventario.php" class="active">Inventario</a>
            <a href="inventario/analisisinventario.php" >Análisis de Inventario</a>
            <a href="inventario/conteo_inventario.php" >Conteo Físico</a>
        </nav>
        <div class="header-right">
            <span class="app-version"><?= htmlspecialchars($system_version); ?></span>
            <a href="../logout.php" class="btn-logout">Cerrar Sesi&oacute;n</a>
        </div>
    </header>

<div class="container">
<aside class="sidebar">

    <div class="filter-group-header active" data-target="category-filters-container">
        <h2><i class="fas fa-tags"></i> Categor&iacute;as</h2>
        <i class="fas fa-chevron-up toggle-icon"></i>
    </div>
    <ul id="category-filters-container" class="filter-list active-list limited-height">
        <li><a href="#" class="sidebar-filter category-filter active" data-filter-id="all" data-filter-type="category">Todas</a></li>
        <?php foreach ($categories as $cat): ?>
            <li>
                <a href="#" class="sidebar-filter category-filter" data-filter-id="<?php echo $cat['id']; ?>" data-filter-type="category">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </a>
            </li>
        <?php endforeach; ?>
        <li id="category-view-more-container" class="view-more-container">
            <a href="#" id="category-view-more-btn" class="view-more-btn">Ver m&aacute;s categor&iacute;as...</a>
        </li>
    </ul>

    <hr class="sidebar-separator">

    <div class="filter-group-header" data-target="supplier-filters-container">
        <h2><i class="fas fa-truck-loading"></i> Proveedores</h2>
        <i class="fas fa-chevron-down toggle-icon"></i>
    </div>
    <ul id="supplier-filters-container" class="filter-list limited-height">
        <li><a href="#" class="sidebar-filter supplier-filter active" data-filter-id="all" data-filter-type="supplier">Todos</a></li>
        <?php foreach ($suppliers as $sup): ?>
            <li>
                <a href="#" class="sidebar-filter supplier-filter" data-filter-id="<?php echo $sup['id']; ?>" data-filter-type="supplier">
                    <?php echo htmlspecialchars($sup['name']); ?>
                </a>
            </li>
        <?php endforeach; ?>
        <li id="supplier-view-more-container" class="view-more-container">
            <a href="#" id="supplier-view-more-btn" class="view-more-btn">Ver todos los proveedores...</a>
        </li>
    </ul>

</aside>

 <div class="main-content">
<div class="kpi-grid">
    <div class="kpi-card" data-filter="all">
        <h3>Productos</h3>
        <div class="value"><?php echo number_format($total_products); ?></div>
    </div>
    <div class="kpi-card" data-filter="low-stock">
        <h3>Stock Bajo</h3>
        <div class="value"><?php echo number_format($low_stock_products); ?></div>
    </div>
    <div class="kpi-card" data-filter="out-of-stock">
        <h3>Sin Stock</h3>
        <div class="value"><?php echo number_format($out_of_stock_products); ?></div>
    </div>
    <div class="kpi-card" data-filter="no-image">
        <h3>Sin Imágenes</h3>
        <div class="value"><?php echo number_format($no_image_products); ?></div>
    </div>

    <div class="kpi-card" data-filter="no-supplier">
        <h3>Sin Proveedor</h3>
        <div class="value"><?php echo number_format($no_supplier_products); ?></div>
    </div>
    <div class="kpi-card" data-filter="negative-stock">
        <h3>Stock Negativo</h3>
        <div class="value"><?php echo number_format($negative_stock_products); ?></div>
    </div>
    
    <div class="kpi-card" id="kpi-archived" data-filter="show-archived">
        <h3>Archivados <i class="fas fa-box-archive"></i></h3>
        <div class="value" id="kpi-archived-count">0</div>
    </div>
</div>

<div class="top-bar">
     
    <div class="filter-and-search-container">
           
        <div class="search-container">
            <i class="fas fa-search"></i>
            <input type="text" id="product-search" placeholder="Buscar productos...">
        </div>
           
        <form method="GET" action="inventario.php" class="filter-dropdown-form">

            <div class="filter-group">
                <label for="sort_by_filter">Ordenar por:</label>
                <select name="sort_by" id="sort_by_filter" class="form-control">
                    
                    <option value="name_asc" <?php echo ($filtro_sort == 'name_asc') ? 'selected' : ''; ?>>Nombre (A-Z)</option>
                    <option value="name_desc" <?php echo ($filtro_sort == 'name_desc') ? 'selected' : ''; ?>>Nombre (Z-A)</option>

                    <option value="stock_desc" <?php echo ($filtro_sort == 'stock_desc') ? 'selected' : ''; ?>>Stock (Mayor a Menor)</option>
                    <option value="stock_asc" <?php echo ($filtro_sort == 'stock_asc') ? 'selected' : ''; ?>>Stock (Menor a Mayor)</option>

                    <option value="created_at_desc" <?php echo ($filtro_sort == 'created_at_desc') ? 'selected' : ''; ?>>Fecha de Creaci&oacute;n (Nuevo)</option>
                    <option value="created_at_asc" <?php echo ($filtro_sort == 'created_at_asc') ? 'selected' : ''; ?>>Fecha de  Creaci&oacute;n (Antiguo)</option>
                    
                    <option value="updated_at_desc" <?php echo ($filtro_sort == 'updated_at_desc') ? 'selected' : ''; ?>>Fecha de Modificaci&oacute;n (Reciente)</option>
                    <option value="updated_at_asc" <?php echo ($filtro_sort == 'updated_at_asc') ? 'selected' : ''; ?>>Fecha de Modificaci&oacute;n (Antigua)</option>
                    
                    <option value="price_desc" <?php echo ($filtro_sort == 'price_desc') ? 'selected' : ''; ?>>Precio (Mayor a Menor)</option>
                    <option value="price_asc" <?php echo ($filtro_sort == 'price_asc') ? 'selected' : ''; ?>>Precio (Menor a Mayor)</option>
                </select>
            </div>

            <button type="submit" class="btn-add btn-filter-submit" style="background-color: #3b82f6;">
                <i class="fas fa-filter"></i> Aplicar
            </button>

            <?php if ($filtro_supplier !== 'all' || $filtro_sort !== 'updated_at_desc'): ?>
                <a href="inventario.php" class="btn-add" style="background-color: #f43f5e; margin-left: 0.5rem; text-decoration: none;">
                    <i class="fas fa-eraser"></i> Limpiar
                </a>
            <?php endif; ?>
        </form>
    </div>
    
    <div class="buttons-container">
        <button id="add-product-btn" class="btn-add" style="background-color: #3b82f6;"><i class="fas fa-plus-circle"></i> Nuevo Producto</button>
        <button id="add-category-btn" class="btn-add" style="background-color: #3b82f6;"><i class="fas fa-list-alt"></i> Nueva Categor&iacute;a</button>
        <button id="add-supplier-btn" class="btn-add" style="background-color: #3b82f6;"> <i class="fas fa-truck"></i> Nuevo Proveedor</button>
        <button id="export-btn" class="btn-add btn-export-green" style="background-color: #10b981;">
            <i class="fas fa-file-export"></i> Exportar
        </button>
    </div>
</div>
<div class="product-grid" id="product-grid">
      </div>
    
    <div id="loading-indicator" style="text-align: center; padding: 2rem; font-size: 1.2rem; color: var(--text-secondary); display: none;">
      Cargando productos...
    </div>

  </div>
</div>


<div id="product-modal" class="modal">
  <div class="modal-content add-card-modal">
    <span class="close-btn">&times;</span>
    <h2 id="modal-title"></h2>
    <form id="product-form" class="modal-form">
      <input type="hidden" id="product-id" name="id">
      <div class="form-grid">
        <div>
          <label for="barcode">C&oacute;digo de Barras</label>
          <input type="text" id="barcode" name="barcode" required>
        </div>
        <div>
          <label for="name">Nombre del Producto</label>
          <input type="text" id="name" name="name" required>
        </div>
        
        <div>
          <label for="cost_price">Precio Costo</label>
          <input type="number" id="cost_price" name="cost_price" step="0.01">
        </div>
        <div>
          <label for="brute_price">Costo + IVA</label>
          <input type="number" id="brute_price" name="brute_price" step="0.01" readonly>
        </div>

        <div>
          <label for="margin">Utilidad sobre Venta (%)</label>
          <input type="number" id="margin" name="margin" step="0.01" min="0" max="100">
        </div>
        <div>
          <label for="suggested_price">Precio Venta Sugerido</label>
          <input type="text" id="suggested_price" readonly style="color: #666; background-color: #f0f0f0;">
        </div>
        
        <div>
          <label for="stock">Stock</label>
          <input type="number" id="stock" name="stock" required>
        </div>
        <div class="precio-venta">
          <label for="price">Precio Venta (Ajustado)</label>
          <input type="number" id="price" name="price" step="0.01">
        </div>

        <div>
          <label for="category_id">Categor&iacute;a</label>
          <select id="category_id" name="category_id">
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="supplier_id">Proveedor</label>
          <select id="supplier_id" name="supplier_id">
            <option value="0">-- Sin proveedor --</option>
            <?php foreach ($suppliers as $sup): ?>
              <option value="<?php echo $sup['id']; ?>"><?php echo htmlspecialchars($sup['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="image_url">URL de la Imagen</label>
          <input type="text" id="image_url" name="image_url">
        </div>
        <div class="full-width">
          <label for="product_image">Subir Imagen desde PC</label>
          <input type="file" id="product_image" name="product_image" accept="image/*">
        </div>
        
        <input type="hidden" name="is_offer" value="0">
        
      </div>
      <button type="submit" class="btn-submit" style="margin-top: 2rem;">Guardar Producto</button>
    </form>
  </div>
</div>

<div id="supplier-modal" class="modal">
  <div class="modal-content add-card-modal">
    <span class="close-btn">&times;</span>
    <h2>Agregar Nuevo Proveedor</h2>
    <form id="supplier-form" class="modal-form">
      <div class="form-group">
        <label for="supplier-name">Nombre del Proveedor</label>
        <input type="text" id="supplier-name" name="name" required>
      </div>
      <div class="form-group">
        <label for="supplier-contact">Contacto</label>
        <input type="text" id="supplier-contact" name="contact">
      </div>
      <button type="submit" class="btn-submit">Guardar Proveedor</button>
    </form>
  </div>
</div>

<div id="category-modal" class="modal">
  <div class="modal-content">
    <span class="close-btn">&times;</span>
    <h2>Crear Nueva Categor&iacute;a</h2>
    <form id="category-form" class="modal-form full-width">
      <div class="form-group">
        <label for="category-name">Nombre de la Categor&iacute;a</label>
        <input type="text" id="category-name" name="name" required>
      </div>
      <button type="submit" class="btn-submit">Crear Categor&iacute;a</button>
    </form>
  </div>
</div>

<div id="confirm-modal" class="modal">
  <div class="modal-content">
    <span class="close-btn">&times;</span>
    <h2 id="confirm-title"></h2>
    <p id="confirm-message" style="margin-bottom: 2rem;"></p>
    <div style="display: flex; justify-content: flex-end; gap: 1rem;">
      <button id="cancel-action-btn" class="btn-logout" style="background-color: #999; ">Cancelar</button>
      <button id="confirm-action-btn" class="btn-logout">Confirmar</button>
    </div>
  </div>
</div>

<div id="export-format-modal" class="modal"> 
  <div class="modal-content" style="max-width: 400px; text-align: center;">
    <span class="close-btn">&times;</span>
    <h2>Formato de Exportaci&oacute;n</h2>
    <p>Selecciona el formato para exportar el inventario:</p>
    <div style="display: flex; justify-content: space-around; gap: 1rem; margin-top: 1.5rem;">
        <button id="export-xls-btn" class="btn-add" style="background-color: #10b981; flex-grow: 1;">
            <i class="fas fa-file-excel"></i> Excel (.xls)
        </button>
        <button id="export-pdf-btn" class="btn-add" style="background-color: #ef4444; flex-grow: 1;">
            <i class="fas fa-file-pdf"></i> PDF
        </button>
    </div>
  </div>
</div>

<div id="toast-container"></div>


<script>
// ==========================================================
// 🚨 DEFINICIONES GLOBALES DE FUNCIONES (Accesibles en toda la página)
// ==========================================================

// --- 1. FUNCIÓN TOAST ---
function showToast(message, type = 'success', duration = 3000) {
    const container = document.getElementById('toast-container');
    if (!container) return console.error('Toast container not found');

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    let icon = '';
    if (type === 'success') {
        icon = '<i class="fas fa-check-circle"></i>';
    } else if (type === 'error') {
        icon = '<i class="fas fa-times-circle"></i>';
    } else if (type === 'warning') {
        icon = '<i class="fas fa-exclamation-triangle"></i>';
    }

    toast.innerHTML = `${icon}<span>${message}</span>`;
    
    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('show');
    }, 10);

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            if(container.contains(toast)) {
                container.removeChild(toast);
            }
        }, 300);
    }, duration);
}
// ----------------------------------------------------

// 💡 FUNCIÓN PARA CARGAR EL KPI DE PRODUCTOS TOTALES (Activos)
async function loadTotalKpi() {
    try {
        const url = 'api/apiinventario.php?action=count_total';
        const res = await fetch(url);
        const data = await res.json();
        
        if (data.success) {
            // Asegúrate de que este selector apunte a tu KPI de Total
            const totalCountElement = document.querySelector('.kpi-card[data-filter="all"] .value'); 
            if (totalCountElement) {
                totalCountElement.textContent = data.count.toLocaleString('es-CL');
            }
        }
    } catch (error) {
        console.error("Fallo al cargar KPI de productos totales:", error);
    }
}

// 💡 FUNCIÓN PARA CARGAR EL KPI DE PRODUCTOS ARCHIVADOS
async function loadArchivedKpi() {
    try {
        const url = 'api/apiinventario.php?action=count_archived';
        const res = await fetch(url);
        
        if (!res.ok) {
            throw new Error(`Error en la respuesta de la API: ${res.status}`);
        }
        
        const data = await res.json();
        
        if (data.success) {
            const archivedCountElement = document.getElementById('kpi-archived-count');
            
            if (archivedCountElement) {
                 archivedCountElement.textContent = data.count.toLocaleString('es-CL'); 
            }
        } else {
            console.error("Error al obtener el conteo de archivados:", data.message);
        }
    } catch (error) {
        console.error("Fallo al cargar KPI de productos archivados:", error);
        const archivedCountElement = document.getElementById('kpi-archived-count');
        if (archivedCountElement) {
            archivedCountElement.textContent = 'Err';
        }
    }
}

// 🛑 LÓGICA DE ARCHIVADO 
async function archiveProduct(productId) {
    // Aquí puedes incluir tu lógica de preparación de solicitud si es necesario
    try {
        const res = await fetch(`api/apiinventario.php?action=archive_product&id=${productId}`, { method: 'POST' });
        const data = await res.json();
        
        if (data.success) {
            showToast('Producto archivado correctamente.', 'success');
            // Corregido: Usar la función de reinicio de la cuadrícula
            resetAndLoadProducts(); 
            // 🚀 ACTUALIZA AMBOS CONTADORES 🚀
            loadTotalKpi(); 
            loadArchivedKpi(); 
        } else {
            showToast('Error al archivar: ' + data.message, 'error');
        }
    } catch (error) {
        showToast('Error de conexión al archivar.', 'error');
    }
}

// 🛑 LÓGICA DE DESARCHIVADO 
async function unarchiveProduct(productId) {
    // Aquí puedes incluir tu lógica de preparación de solicitud si es necesario
    try {
        const res = await fetch(`api/apiinventario.php?action=unarchive_product&id=${productId}`, { method: 'POST' });
        const data = await res.json();
        
        if (data.success) {
            showToast('Producto desarchivado correctamente.', 'success');
            // Corregido: Usar la función de reinicio de la cuadrícula
            resetAndLoadProducts(); 
            // 🚀 ACTUALIZA AMBOS CONTADORES 🚀
            loadTotalKpi(); 
            loadArchivedKpi(); 
        } else {
            showToast('Error al desarchivar: ' + data.message, 'error');
        }
    } catch (error) {
        showToast('Error de conexión al desarchivar.', 'error');
    }
}


// ==========================================================
// 🏁 CÓDIGO DE INICIALIZACIÓN (Solo se ejecuta cuando el DOM está listo)
// ==========================================================
document.addEventListener('DOMContentLoaded', function() {
    // Las funciones globales ya están definidas arriba y son accesibles aquí.

    // --- VARIABLES DE ESTADO Y DOM ---
    const productGrid = document.getElementById('product-grid');
    const searchInput = document.getElementById('product-search');
    const productModal = document.getElementById('product-modal');
    const categoryModal = document.getElementById('category-modal');
    const supplierModal = document.getElementById('supplier-modal');
    const confirmModal = document.getElementById('confirm-modal');
    const exportFormatModal = document.getElementById('export-format-modal'); 
    const closeBtns = document.querySelectorAll('.close-btn');

    // Variables de estado
    let offset = 0;
    let isLoading = false;
    const limit = 24;
    const loadingIndicator = document.getElementById('loading-indicator');
    
    // CORRECCIÓN: Sincronizar estado inicial con parámetros de URL (PHP)
    const urlParams = new URLSearchParams(window.location.search);
    let currentCategory = 'all';
    let currentKpiFilter = 'all';
    let currentSupplier = urlParams.get('supplier_id') || 'all'; // Sincroniza proveedor
    searchInput.value = urlParams.get('search_query') || ''; // Sincroniza búsqueda

    // Variables para los campos del formulario de producto
    const costPriceInput = document.getElementById('cost_price');
    const brutePriceInput = document.getElementById('brute_price');
    const marginInput = document.getElementById('margin');
    const priceInput = document.getElementById('price');
    const suggestedPriceInput = document.getElementById('suggested_price');
    const productImageInput = document.getElementById('product_image');
    const barcodeInput = document.getElementById('barcode');
    const nameInput = document.getElementById('name');
    const stockInput = document.getElementById('stock');
    const supplierSelect = document.getElementById('supplier_id');
    const sortByFilter = document.getElementById('sort_by_filter'); 
    const imageUrlInput = document.getElementById('image_url'); // Asumo que tienes este campo para la URL de la imagen
    const imagePreview = document.getElementById('product-image-preview'); // Asumo que tienes este elemento para la vista previa
    
    // Variables para la funcionalidad "Ver Más"
    const categoryList = document.getElementById('category-filters-container');
    const categoryViewMoreBtn = document.getElementById('category-view-more-btn');
    const categoryViewMoreContainer = document.getElementById('category-view-more-container');
    const supplierList = document.getElementById('supplier-filters-container');
    const supplierViewMoreBtn = document.getElementById('supplier-view-more-btn');
    const supplierViewMoreContainer = document.getElementById('supplier-view-more-container');
    
    // Función auxiliar para abrir modales y centrar la vista
    function openModalAndScroll(modalElement) {
        modalElement.style.display = 'flex';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // --- Cierre de Modales Centralizado ---
    closeBtns.forEach(btn => btn.onclick = () => {
        productModal.style.display = 'none';
        categoryModal.style.display = 'none';
        supplierModal.style.display = 'none';
        confirmModal.style.display = 'none';
        if (exportFormatModal) exportFormatModal.style.display = 'none'; 
    });

window.onclick = (event) => {
    // La condición para productModal ha sido eliminada. Ahora solo se cierra con la X.
    if (event.target === categoryModal) categoryModal.style.display = 'none';
    if (event.target === supplierModal) supplierModal.style.display = 'none';
    if (event.target === confirmModal) confirmModal.style.display = 'none';
    if (exportFormatModal && event.target === exportFormatModal) exportFormatModal.style.display = 'none';
};

    // --- LÓGICA DE PROVEEDOR ---

    // FUNCIÓN AUXILIAR PARA AÑADIR EL PROVEEDOR AL DOM EN VIVO
    function addSupplierToDom(supplier) {
        // 1. Añadir al Dropdown del Modal de Producto
        const dropdown = document.getElementById('supplier_id');
        if (dropdown) {
            const newOption = document.createElement('option');
            newOption.value = supplier.id;
            newOption.innerText = supplier.name;
            dropdown.appendChild(newOption);
        }
        // 2. Añadir al Sidebar de Filtros
        const sidebarContainer = document.querySelector('.sidebar ul#supplier-filters-container');
        if (sidebarContainer) {
            const newListItem = document.createElement('li'); 
            const newLink = document.createElement('a');
            newLink.href = '#';
            newLink.className = 'sidebar-filter supplier-filter';
            newLink.dataset.filterId = supplier.id; 
            newLink.dataset.filterType = 'supplier'; 
            newLink.innerText = supplier.name;
            
            newListItem.appendChild(newLink);
            // Insertar antes del contenedor "Ver Más"
            sidebarContainer.insertBefore(newListItem, supplierViewMoreContainer); 
        }
        // 3. ACTUALIZAR ALTURA DE LISTA Y LISTENERS
        initSidebarFilterListeners(); // Re-inicializar listeners para incluir el nuevo proveedor
        checkSupplierListHeight();
    }

    document.getElementById('add-supplier-btn').addEventListener('click', () => {
        document.getElementById('supplier-form').reset();
        openModalAndScroll(supplierModal);
    });

    document.getElementById('supplier-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        try {
            const res = await fetch('api/apiinventario.php?action=add_supplier', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                showToast('Proveedor creado correctamente.', 'success');
                supplierModal.style.display = 'none';
                
                if (data.new_supplier) {
                    addSupplierToDom(data.new_supplier);
                }
            } else {
                showToast('Error: ' + data.message, 'error');
            }
        } catch (error) {
            showToast('Error al conectar con la API de proveedores.', 'error');
        }
    });

    // ----------------------------------------------------
    // 🏁 INICIALIZACIÓN DE KPIs AL CARGAR LA PÁGINA 
    // ----------------------------------------------------
    loadTotalKpi();
    loadArchivedKpi();
    
    // --- LÓGICA DE CATEGORÍA ---
    // FUNCIÓN AUXILIAR PARA AÑADIR LA CATEGORÍA AL DOM Y AÑADIR EL EVENT LISTENER
    function addCategoryToDom(category) {
        // 1. Añadir al Sidebar de Filtros
        const sidebarContainer = document.querySelector('.sidebar ul#category-filters-container');
        if (sidebarContainer) {
            const newListItem = document.createElement('li'); 
            const newLink = document.createElement('a');
            newLink.href = '#';
            newLink.className = 'sidebar-filter category-filter';
            newLink.dataset.filterId = category.id; 
            newLink.dataset.filterType = 'category'; 
            newLink.innerText = category.name;
            
            newListItem.appendChild(newLink);
            // Insertar antes del contenedor "Ver Más"
            sidebarContainer.insertBefore(newListItem, categoryViewMoreContainer); 
        }

        // 2. Añadir al Dropdown del Modal de Producto
        const dropdown = document.getElementById('category_id');
        if (dropdown) {
            const newOption = document.createElement('option');
            newOption.value = category.id;
            newOption.innerText = category.name;
            dropdown.appendChild(newOption);
        }
        // 3. ACTUALIZAR ALTURA DE LISTA Y LISTENERS
        initSidebarFilterListeners(); // Re-inicializar listeners para incluir la nueva categoría
        checkCategoryListHeight();
    }
    
    document.getElementById('add-category-btn').addEventListener('click', () => {
        document.getElementById('category-form').reset();
        openModalAndScroll(categoryModal);
    });

    document.getElementById('category-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        try {
            const res = await fetch('api/apiinventario.php?action=add_category', { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.success) {
                showToast('Categoría creada correctamente.', 'success');
                categoryModal.style.display = 'none';
                
                if (data.new_category) {
                    addCategoryToDom(data.new_category);
                }
                
            } else {
                showToast('Error: ' + data.message, 'error');
            }
        } catch (error) {
            showToast('Error de conexión al crear la categoría.', 'error');
        }
    });

    // --- LÓGICA DE CÁLCULOS AUTOMÁTICOS ---
    const IVA_RATE = 1.19;

    function calculateBrutePrice() {
        const cost = parseFloat(costPriceInput.value);
        if (!isNaN(cost) && cost >= 0) {
            const brutePrice = cost * IVA_RATE;
            brutePriceInput.value = brutePrice.toFixed(0);
        } else {
            brutePriceInput.value = '';
        }
    }

    function calculateSellingPrice() {
        const brutePrice = parseFloat(brutePriceInput.value);
        const margin = parseFloat(marginInput.value);
        
        // 1. OBTENER ESTADO DEL PRODUCTO
        const productName = nameInput.value.trim().toLowerCase();
        const isGranelProduct = productName.includes('granel');

        if (!isNaN(brutePrice) && brutePrice >= 0 && !isNaN(margin) && margin >= 0) {
            const denominator = 1 - (margin / 100);
            
            if (denominator > 0) {
                let sellingPriceCalculated = brutePrice / denominator;

                // Mostrar el precio sugerido (exacto)
                if (suggestedPriceInput) suggestedPriceInput.value = sellingPriceCalculated.toFixed(2);

                let sellingPriceFinal;

                if (isGranelProduct) {
                    // Producto a Granel: Precisión de 2 decimales, sin redondeo a la centena.
                    sellingPriceFinal = sellingPriceCalculated;
                    priceInput.value = sellingPriceFinal.toFixed(2);
                    
                } else {
                    // Productos Normales: Redondeo a la centena - 10.
                    sellingPriceFinal = Math.ceil(sellingPriceCalculated / 100) * 100 - 10;
                    
                    // Mínimo: Precio bruto + 10
                    if (sellingPriceFinal < brutePrice) {
                        sellingPriceFinal = brutePrice + 10; 
                    }
                    
                    // Mostrar como entero
                    priceInput.value = sellingPriceFinal.toFixed(0);
                }
                
            } else {
                priceInput.value = ''; 
            }
        } else {
            priceInput.value = '';
        }
    }
    
    function calculateMargin() {
        const sellingPrice = parseFloat(priceInput.value);
        const brutePrice = parseFloat(brutePriceInput.value);
    
        if (!isNaN(sellingPrice) && sellingPrice > 0 && !isNaN(brutePrice) && brutePrice >= 0) {
            const margin = (1 - (brutePrice / sellingPrice)) * 100;
            marginInput.value = margin.toFixed(2);
        } else {
            marginInput.value = '';
        }
    }

    // --- CONSOLIDACIÓN DE LISTENERS DE CÁLCULO ---
    
    // Listener para el nombre: Recalcula el precio de venta cuando se cambia el nombre
    nameInput.addEventListener('input', calculateSellingPrice);

    // Listener para la URL de la imagen (solo si existe el campo y la previsualización)
    if (imageUrlInput && imagePreview) {
        imageUrlInput.addEventListener('input', (e) => {
            const url = e.target.value;
            // Solo muestra si la URL parece válida
            imagePreview.src = url || 'placeholder.png'; 
        });
    }

    costPriceInput.addEventListener('input', () => {
        calculateBrutePrice();
        calculateSellingPrice(); // Llama a precio de venta
        calculateMargin(); // Recalcula el margen si cambia el Costo (afecta Bruto)
    });
    
    brutePriceInput.addEventListener('input', () => {
        // Si el usuario edita el Bruto manualmente
        calculateSellingPrice();
        calculateMargin();
    });
    
    marginInput.addEventListener('input', calculateSellingPrice);
    priceInput.addEventListener('input', calculateMargin);
    // --------------------------------------------


    // --- LÓGICA DE FILTRADO Y CARGA ---
    function resetAndLoadProducts() {
        offset = 0;
        productGrid.innerHTML = '';
        loadingIndicator.innerText = 'Cargando productos...';
        loadMoreProducts();
    }

    async function loadMoreProducts() {
        if (isLoading) return;
        isLoading = true;
        loadingIndicator.style.display = 'block';

        let url = `api/load_products.php?offset=${offset}&limit=${limit}`;
        
        if (searchInput.value) {
            url += `&search=${encodeURIComponent(searchInput.value)}`;
        }
        if (currentCategory !== 'all') {
            url += `&category_id=${currentCategory}`;
        }
        if (currentSupplier !== 'all') {
             url += `&supplier_id=${currentSupplier}`;
        }
        if (currentKpiFilter !== 'all') {
            url += `&kpi_filter=${currentKpiFilter}`;
        }
        
        // Usar el filtro de ordenación de la URL
        const sortFilter = sortByFilter ? sortByFilter.value : urlParams.get('sort_by') || 'updated_at_desc'; 
        if (sortFilter !== 'updated_at_desc') {
             url += `&sort_by=${sortFilter}`;
        }

        try {
            const response = await fetch(url);
            const newProducts = await response.json();

            if (newProducts.length > 0) {
                newProducts.forEach(product => {
                    const productCard = createProductCard(product);
                    productGrid.appendChild(productCard);
                });
                offset += newProducts.length;
                observeLastProduct();
                loadingIndicator.style.display = 'none'; 
            } else if (offset === 0) {
                loadingIndicator.innerText = 'No se encontraron productos con los filtros aplicados.';
                observer.disconnect(); // Detener la observación si no hay resultados
            } else {
                loadingIndicator.innerText = 'Si buscas otro producto, prueba con otras palabras...';
                observer.disconnect();
            }
        } catch (error) {
            console.error('Error al cargar productos:', error);
            loadingIndicator.innerText = 'Error al cargar productos.';
        } finally {
            isLoading = false;
        }
    }

    // Lazy Load con Intersection Observer
    const observer = new IntersectionObserver(entries => {
        if (entries[0].isIntersecting && !isLoading) {
            loadMoreProducts();
        }
    }, { rootMargin: '200px' });

    function observeLastProduct() {
        const lastProduct = productGrid.lastElementChild;
        if (lastProduct) {
            observer.disconnect();
            observer.observe(lastProduct);
        }
    }

    // Búsqueda en tiempo real
    let searchTimeout;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            resetAndLoadProducts();
        }, 300);
    });
    

    // --- LÓGICA DE FILTRADO UNIFICADO Y SIDEBAR TOGGLE ---

    function initSidebarFilterListeners() {
        document.querySelectorAll('.sidebar-filter').forEach(link => {
            // Asegura que no se añadan múltiples listeners al re-inicializar
            link.removeEventListener('click', handleSidebarFilterClick); 
            link.addEventListener('click', handleSidebarFilterClick);
        });
    }
    
    function handleSidebarFilterClick(e) {
        e.preventDefault();
        const filterType = e.target.dataset.filterType; 
        const filterId = e.target.dataset.filterId; 

        // 1. Activar el filtro clicado y desactivar los demás del mismo tipo
        document.querySelectorAll(`.${filterType}-filter`).forEach(l => l.classList.remove('active'));
        e.target.classList.add('active');

        // 2. Actualizar variables de estado y desactivar el filtro opuesto
        if (filterType === 'category') {
            currentCategory = filterId;
            currentSupplier = 'all'; // Desactiva filtro de proveedor

            document.querySelectorAll('.supplier-filter').forEach(l => l.classList.remove('active'));
            const allSup = document.querySelector('.supplier-filter[data-filter-id="all"]');
            if (allSup) allSup.classList.add('active');

        } else if (filterType === 'supplier') {
            currentSupplier = filterId;
            currentCategory = 'all'; // Desactiva filtro de categoría
            
            document.querySelectorAll('.category-filter').forEach(l => l.classList.remove('active'));
            const allCat = document.querySelector('.category-filter[data-filter-id="all"]');
            if (allCat) allCat.classList.add('active');
        }
        
        // 3. Desactivar filtros KPI si hay un filtro de sidebar activo
        document.querySelectorAll('.kpi-card').forEach(c => c.classList.remove('active'));
        currentKpiFilter = 'all';

        // 4. Recargar productos
        resetAndLoadProducts();
    }
    
    // Listener para el filtro de ordenación (sort_by)
    if (sortByFilter) {
        sortByFilter.addEventListener('change', () => {
            // Se asume que el cambio de ordenación debe recargar con los mismos filtros
            resetAndLoadProducts();
        });
    }


    // Toggle del Sidebar Desplegable 
    document.querySelectorAll('.filter-group-header').forEach(header => {
        header.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const targetList = document.getElementById(targetId);

            this.classList.toggle('active');
            targetList.classList.toggle('active-list');
            
            // Si la lista de proveedor/categoría se cierra, la colapsamos visualmente
            if (!this.classList.contains('active')) {
                if (targetId === 'category-filters-container') {
                    categoryList.classList.add('limited-height');
                    categoryViewMoreBtn.textContent = 'Ver todas...';
                    categoryViewMoreContainer.style.position = 'absolute';
                } else if (targetId === 'supplier-filters-container') {
                     supplierList.classList.add('limited-height');
                     supplierViewMoreBtn.textContent = 'Ver todos...';
                     supplierViewMoreContainer.style.position = 'absolute';
                }
            }
        });
    });
    
    // Filtrado por KPI (Tarjetas)
    document.querySelectorAll('.kpi-card').forEach(card => {
        card.addEventListener('click', (e) => {
            const isActive = e.currentTarget.classList.contains('active');
            
            // 1. Desactivar todos los KPI
            document.querySelectorAll('.kpi-card').forEach(c => c.classList.remove('active'));
            
            // 2. Activar/Desactivar el KPI clicado y actualizar el estado
            if (!isActive) {
                e.currentTarget.classList.add('active');
                currentKpiFilter = e.currentTarget.dataset.filter;
            } else {
                currentKpiFilter = 'all';
            }

            // 3. Desactivar todos los filtros del sidebar (categoría y proveedor)
            document.querySelectorAll('.category-filter').forEach(l => l.classList.remove('active'));
            document.querySelectorAll('.supplier-filter').forEach(l => l.classList.remove('active'));
            
            const allCat = document.querySelector('.category-filter[data-filter-id="all"]');
            if (allCat) allCat.classList.add('active');
            const allSup = document.querySelector('.supplier-filter[data-filter-id="all"]');
            if (allSup) allSup.classList.add('active');
            
            currentCategory = 'all';
            currentSupplier = 'all';
            
            // 4. Recargar productos
            resetAndLoadProducts();
        });
    });

    // --- LÓGICA DE 'VER MÁS' EN CATEGORÍAS Y PROVEEDORES ---

    // Función "Ver Más" genérica
    function toggleViewMore(listElement, viewMoreBtn, viewMoreContainer, type) {
        const isLimited = listElement.classList.contains('limited-height');
        
        if (isLimited) {
            listElement.classList.remove('limited-height');
            listElement.style.maxHeight = 'none'; 
            viewMoreBtn.textContent = `Ver menos...`;
            viewMoreContainer.style.position = 'static'; 
        } else {
            listElement.classList.add('limited-height');
            listElement.style.maxHeight = ''; 
            viewMoreBtn.textContent = `Ver todas...`;
            viewMoreContainer.style.position = 'absolute'; 
        }
    }
    
    // Handlers de clic para Categorías y Proveedores
    if (categoryViewMoreBtn) {
        categoryViewMoreBtn.addEventListener('click', (e) => {
            e.preventDefault();
            toggleViewMore(categoryList, categoryViewMoreBtn, categoryViewMoreContainer, 'categorías');
        });
    }
    if (supplierViewMoreBtn) {
        supplierViewMoreBtn.addEventListener('click', (e) => {
            e.preventDefault();
            toggleViewMore(supplierList, supplierViewMoreBtn, supplierViewMoreContainer, 'proveedores');
        });
    }

    // Función para verificar si se necesita el botón 'Ver Más' para Categorías
    function checkCategoryListHeight() {
        if (!categoryList) return;
        setTimeout(() => {
            const listHeight = categoryList.scrollHeight;
            const containerHeight = 350; 

            if (listHeight > containerHeight + 50) { 
                categoryViewMoreContainer.style.display = 'block';
                // Asegurar que la lista esté limitada si el contenedor la excede
                categoryList.classList.add('limited-height');
                categoryViewMoreBtn.textContent = 'Ver todas...'; // Resetear texto
            } else {
                categoryViewMoreContainer.style.display = 'none';
                categoryList.classList.remove('limited-height');
                categoryList.style.maxHeight = 'none'; 
            }
        }, 50); 
    }

    // Función para verificar si se necesita el botón 'Ver Más' para Proveedores
    function checkSupplierListHeight() {
        if (!supplierList) return;
        setTimeout(() => {
            const listHeight = supplierList.scrollHeight;
            const containerHeight = 350; 

            if (listHeight > containerHeight + 50) { 
                supplierViewMoreContainer.style.display = 'block';
                // Asegurar que la lista esté limitada si el contenedor la excede
                supplierList.classList.add('limited-height');
                supplierViewMoreBtn.textContent = 'Ver todos...'; // Resetear texto
            } else {
                supplierViewMoreContainer.style.display = 'none';
                supplierList.classList.remove('limited-height');
                supplierList.style.maxHeight = 'none'; 
            }
        }, 50);
    }
    
    // --- FUNCIONES DE ACCIONES Y MODALES ---
    function createProductCard(product) {
        const card = document.createElement('div');
        card.className = 'product-card';
        card.dataset.productId = product.id;

        let stockClass = '';
        let stockIndicatorClass = '';
        if (product.stock < 5 && product.stock > 0) {
            stockClass = 'stock-low';
            stockIndicatorClass = 'orange';
        } else if (product.stock == 0) {
            stockClass = 'stock-zero';
            stockIndicatorClass = 'red';
        } else if (product.stock < 0) {
            stockClass = 'stock-negative';
            stockIndicatorClass = 'purple';
        } else {
            stockClass = 'stock-available';
            stockIndicatorClass = 'green';
        }
        
        const imageUrl = product.image_url || 'placeholder.png';
        const priceValue = (typeof product.price !== 'undefined' && product.price !== null) ? parseFloat(product.price) : 0;
        const costPriceValue = (typeof product.cost_price !== 'undefined' && product.cost_price !== null) ? parseFloat(product.cost_price) : 0;
        
        const priceFormatted = new Intl.NumberFormat('es-CL', { 
            style: 'currency', 
            currency: 'CLP', 
            minimumFractionDigits: (product.name && product.name.toLowerCase().includes('granel')) ? 2 : 0 // Muestra 2 decimales solo para granel
        }).format(priceValue);
        
        const brutePriceDisplay = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', minimumFractionDigits: 0 }).format(costPriceValue * 1.19);

        // Lógica para determinar el texto y el ícono del botón ARCHIVAR/RESTAURAR
        const isArchived = product.archived == 1;
        const archiveBtnClass = isArchived ? 'restore-btn' : 'archive-btn';
        const archiveBtnIcon = isArchived ? 'fas fa-box-open' : 'fas fa-archive';
        const archiveBtnTitle = isArchived ? 'Restaurar Producto' : 'Archivar Producto';

        card.innerHTML = `
            <div class="stock-indicator ${stockIndicatorClass}"></div>
            <img src="${imageUrl}" alt="${product.name}" class="product-card-image" onerror="this.onerror=null;this.src='placeholder.png'">
            <div class="product-info">
                <h3 class="product-name">${product.name}</h3>
                <p class="product-price">${priceFormatted}</p>
                <p class="product-details">
                    SKU: ${product.barcode || '—'}<br>
                    Costo bruto: ${brutePriceDisplay}
                </p>
                <div class="product-stock ${stockClass}">
                    Stock: ${product.stock}
                </div>
            </div>
            <div class="card-actions">
                <button class="action-btn edit-btn" data-id="${product.id}" title="Editar"><i class="fas fa-edit"></i></button>
                <button class="action-btn duplicate-btn" data-id="${product.id}" title="Duplicar"><i class="fas fa-copy"></i></button>
                <button class="action-btn ${archiveBtnClass}" data-id="${product.id}" title="${archiveBtnTitle}"><i class="${archiveBtnIcon}"></i></button>
                <button class="action-btn delete-btn" data-id="${product.id}" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
            </div>
        `;
        return card;
    }

    function showConfirmModal(title, message, callback) {
        document.getElementById('confirm-title').innerText = title;
        document.getElementById('confirm-message').innerText = message;
        openModalAndScroll(confirmModal);

        document.getElementById('confirm-action-btn').onclick = () => {
            callback();
            confirmModal.style.display = 'none';
        };
        document.getElementById('cancel-action-btn').onclick = () => {
            confirmModal.style.display = 'none';
        };
    }

    productGrid.addEventListener('click', async (e) => {
        const btn = e.target.closest('.action-btn');
        if (!btn) return;

        const productId = btn.dataset.id;
        const action = btn.classList[1];

        switch (action) {
            case 'edit-btn':
                try {
                    const response = await fetch(`api/apiinventario.php?action=get_product&id=${productId}`);

                    // Manejo de respuesta HTTP incorrecta (ej. 404, 500)
                    if (!response.ok) {
                        const errorText = await response.text();
                        console.error('API Error Response:', errorText); 
                        showToast(`Error de servidor: ${response.status}`, 'error');
                        return;
                    }

                    const product = await response.json();
                    if (product && product.id) {
                        // Cargar datos
                        document.getElementById('modal-title').innerText = 'Editar Producto';
                        document.getElementById('product-id').value = product.id;
                        document.getElementById('barcode').value = product.barcode;
                        document.getElementById('name').value = product.name;
                        document.getElementById('price').value = product.price; // Precio Final
                        document.getElementById('cost_price').value = product.cost_price;
                        document.getElementById('stock').value = product.stock;
                        document.getElementById('category_id').value = product.category_id;
                        document.getElementById('supplier_id').value = product.supplier_id || 0;
                        
                        // Cargar y previsualizar URL de imagen
                        if (imageUrlInput) imageUrlInput.value = product.image_url || '';
                        if (imagePreview) imagePreview.src = product.image_url || 'placeholder.png';


                        // LÓGICA DE CÁLCULO (Para rellenar el Precio Sugerido y el Margen)
                        calculateBrutePrice();
                        calculateMargin();
                        // Nota: calculateSellingPrice se llama para rellenar SuggestedPrice (no afecta Price)
                        calculateSellingPrice(); 
                        
                        openModalAndScroll(productModal);
                    } else {
                        showToast('Producto no encontrado o API devolvió respuesta vacía.', 'error');
                    }
                } catch (error) {
                    // Captura errores de red o errores al parsear el JSON
                    console.error('Fetch/JSON Parse Error:', error); 
                    showToast('Error al obtener datos del producto.', 'error');
                }
                break;

            case 'duplicate-btn':
                showConfirmModal('Duplicar', 'Seguro de que quieres duplicar este producto?', async () => {
                    try {
                        const res = await fetch(`api/apiinventario.php?action=duplicate_product&id=${productId}`, { method: 'POST' });
                        const data = await res.json();
                        if (data.success) {
                            showToast('Producto duplicado correctamente.', 'success');
                            resetAndLoadProducts();
                        } else {
                            showToast('Error al duplicar: ' + data.message, 'error');
                        }
                    } catch (error) {
                        showToast('Error de conexión al duplicar.', 'error');
                    }
                });
                break;
                
            case 'archive-btn':
            case 'restore-btn':
                const isArchiving = (action === 'archive-btn');
                const title = isArchiving ? 'Archivar' : 'Restaurar';
                const message = isArchiving 
                    ? 'Seguro de que quieres archivar este producto? Podrás restaurarlo después.'
                    : 'Seguro de que quieres restaurar este producto? Volverá a la lista activa.';

                showConfirmModal(title, message, async () => {
                    try {
                        // CORRECCIÓN CLAVE: Asegurarse de usar 'restore_product' (si esa es la acción esperada en PHP)
                        const apiAction = isArchiving ? 'archive_product' : 'restore_product';
                        const res = await fetch(`api/apiinventario.php?action=${apiAction}&id=${productId}`, { method: 'POST' });
                        const data = await res.json();
                        
                        if (data.success) {
                            showToast(`Producto ${isArchiving ? 'archivado' : 'restaurado'} correctamente.`, 'success');
                            // Recargar para que el producto desaparezca (si archivas) o reaparezca (si restauras)
                            resetAndLoadProducts(); 
                            loadTotalKpi(); // Actualizar KPI
                            loadArchivedKpi(); // Actualizar KPI
                        } else {
                            // Este es el mensaje que veías, devuelto por PHP: "Error al restaurar: Acción no válida."
                            showToast(`Error al ${isArchiving ? 'archivar' : 'restaurar'}: ` + data.message, 'error');
                        }
                    } catch (error) {
                        showToast(`Error de conexión al ${isArchiving ? 'archivar' : 'restaurar'}.`, 'error');
                    }
                });
                break;

            case 'delete-btn':
                showConfirmModal('Eliminar', 'Seguro que quieres eliminar este producto?', async () => {
                    try {
                        const res = await fetch(`api/apiinventario.php?action=delete_product&id=${productId}`, { method: 'POST' });
                        const data = await res.json();
                        if (data.success) {
                            showToast('Producto eliminado correctamente.', 'success');
                            const cardToRemove = btn.closest('.product-card');
                            if (cardToRemove) cardToRemove.remove();
                            loadTotalKpi(); // Actualizar KPI
                            loadArchivedKpi(); // Actualizar KPI
                        } else {
                            showToast('Error al eliminar: ' + data.message, 'error');
                        }
                    } catch (error) {
                        showToast('Error de conexión al eliminar.', 'error');
                    }
                });
                break;
        }
    });

    document.getElementById('add-product-btn').addEventListener('click', () => {
        document.getElementById('product-form').reset();
        document.getElementById('modal-title').innerText = 'Agregar Nuevo Producto';
        document.getElementById('product-id').value = '';
        
        // Limpiar campos calculados/auxiliares
        brutePriceInput.value = '';
        marginInput.value = '';
        if (suggestedPriceInput) suggestedPriceInput.value = ''; 
        if (imagePreview) imagePreview.src = 'placeholder.png'; // Resetear vista previa

        openModalAndScroll(productModal);
    });

    document.getElementById('product-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const productId = formData.get('id');

        let missingFields = [];
        if (!barcodeInput.value.trim()) missingFields.push("Código de Barras");
        if (!nameInput.value.trim()) missingFields.push("Nombre del Producto");
        if (!stockInput.value.trim()) missingFields.push("Stock");
        
        // Agregar validación para precios
        if (!costPriceInput.value.trim() && !priceInput.value.trim()) {
            missingFields.push("Precio de Costo o Precio Final");
        }

        if (missingFields.length > 0) {
            const message = `Por favor, ingrese todos los datos obligatorios: ${missingFields.join(', ')}.`;
            showToast(message, 'warning', 5000);
            return; 
        }

        // Manejar el archivo de imagen
        if (productImageInput.files.length > 0) {
            formData.set('product_image', productImageInput.files[0]);
        } else {
            // Si no se selecciona un archivo, pero hay una URL en el campo 'image_url' (para edición)
            // se debe asegurar que la API sepa si mantener la imagen o no, pero en FormData se borra
            // el campo file si está vacío. Asumimos que la API maneja 'image_url' por separado.
            formData.delete('product_image');
        }
        
        const url = productId ? `api/apiinventario.php?action=edit_product` : `api/apiinventario.php?action=add_product`;
        
        try {
            const res = await fetch(url, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                showToast('Producto guardado correctamente.', 'success');
                productModal.style.display = 'none';
                resetAndLoadProducts();
                loadTotalKpi(); // Actualizar KPI
            } else {
                showToast('Error: ' + data.message, 'error');
            }
        } catch (error) {
            showToast('Error de conexión al guardar el producto.', 'error');
        }
    });

// --- LÓGICA DE EXPORTACIÓN CORREGIDA ---
    function exportProducts(format) {
        let url = `api/apiinventario.php?action=export_products&format=${format}`;
        
        const searchValue = searchInput.value || '';
        
        if (searchValue) {
            url += `&search=${encodeURIComponent(searchValue)}`;
        }
        if (currentCategory !== 'all') {
            url += `&category_id=${currentCategory}`;
        }
        if (currentSupplier !== 'all') {
            url += `&supplier_id=${currentSupplier}`;
        }
        if (currentKpiFilter !== 'all') {
            url += `&kpi_filter=${currentKpiFilter}`;
        }
        
        // 1. Determinar el filtro de ordenación actual, tomando en cuenta el select
        // Si sortByFilter existe, toma su valor; si no, intenta con la URL (para inicialización)
        let sortFilter = 'updated_at_desc'; // Valor predeterminado
        
        if (sortByFilter) {
            sortFilter = sortByFilter.value;
        } else if (urlParams.get('sort_by')) {
            sortFilter = urlParams.get('sort_by');
        }

        // 2. Adjuntar el filtro de ordenación (si no es el predeterminado, pero es seguro incluirlo siempre)
        // **IMPORTANTE:** Siempre adjuntaremos el filtro para que PHP sepa qué orden aplicar.
        // La corrección verdadera DEBE estar en PHP para escapar el nombre de columna.
        if (sortFilter) {
             url += `&sort_by=${sortFilter}`;
        }

        window.open(url, '_blank');
        showToast(`Exportando productos a ${format.toUpperCase()}...`, 'success', 4000);
    }

    document.getElementById('export-btn').addEventListener('click', () => {
        if (exportFormatModal) {
            // openModalAndScroll es una función auxiliar que debe estar definida en el ámbito global
            openModalAndScroll(exportFormatModal); 
        } else {
            // Si no existe el modal, exporta por defecto
            exportProducts('xls');
        }
    });

    if (exportFormatModal) {
        const xlsButton = document.getElementById('export-xls-btn');
        const pdfButton = document.getElementById('export-pdf-btn');

        if (xlsButton) {
            xlsButton.onclick = () => {
                exportProducts('xls');
                exportFormatModal.style.display = 'none';
            };
        }
        if (pdfButton) {
            pdfButton.onclick = () => {
                exportProducts('pdf');
                exportFormatModal.style.display = 'none';
            };
        };
    }

    // --- INICIALIZACIÓN ---
    // Inicializar listeners para todos los filtros del sidebar (categorías y proveedores)
    initSidebarFilterListeners();
    
    // Llamar a las funciones de chequeo de altura al cargar
    checkCategoryListHeight();
    checkSupplierListHeight();

    // Carga inicial de productos
    resetAndLoadProducts();
});
</script>
</body>
</html>