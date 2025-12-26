<?php
/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/

// NOTA IMPORTANTE: Se asume que 'config.php' existe y establece la conexión PDO ($pdo).
require '../config.php';
session_start();


// Descomentar para activar la protección de sesión
if (!isset($_SESSION['user_username'])) {
header('Location: ../login.php');
exit();
}


// Variables para el encabezado
$current_page = 'labels.php';
// Simulación de variables de sesión
$_SESSION['user_username'] = $_SESSION['user_username'] ?? 'Usuario Demo';

// Obtener la versión del sistema (se asume que existe la tabla config)
try {
$stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
$stmt->execute();
$system_version = $stmt->fetchColumn() ?: 'v1.0.0';
} catch (Exception $e) {
$system_version = 'v1.0.0'; // Fallback si no hay DB
}

// ------------------------------------------------------------------
// 🔍 OBTENER CATEGORÍAS Y PROVEEDORES PARA FILTROS
// ------------------------------------------------------------------
$categories_data = [];
$suppliers_data = [];

try {
  // Obtener Categorías
  $stmt_cat = $pdo->prepare("SELECT id, name FROM categories ORDER BY name ASC");
  $stmt_cat->execute();
  $categories_data = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

  // Obtener Proveedores
  $stmt_sup = $pdo->prepare("SELECT id, name FROM suppliers ORDER BY name ASC");
  $stmt_sup->execute();
  $suppliers_data = $stmt_sup->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  // Ignorar si falla la carga de filtros, para no bloquear la app
}
// ------------------------------------------------------------------


// --- 1. OBTENER DATOS DE PRODUCTOS PARA EL LISTADO (MÁS RECIENTES, LÍMITE 5000) ---
$products_data = [];
$error_message = null;

try {
// MODIFICACIÓN: AGREGADO 'image_url', 'category_id' y 'supplier_id' a la selección
$stmt_products = $pdo->prepare("
 SELECT id, barcode, name, price, stock, image_url, category_id, supplier_id
 FROM products
 ORDER BY id DESC /* Los productos con ID más alto (más recientes) primero */
 LIMIT 5000
");
$stmt_products->execute();
$products_data = $stmt_products->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
$error_message = "Error: No se pudo cargar el listado de productos. Asegúrate de que la tabla 'products' exista y que la conexión esté activa. Detalle: " . $e->getMessage();
}

// --- 2. CÁLCULO DE KPIs MEJORADOS ---
$total_products_loaded = count($products_data);
// Se eliminó el cálculo de $total_stock

// Nuevo KPI 1: Productos sin Código de Barra (Calidad de Datos)
$products_no_barcode = count(array_filter($products_data, function($p) {
// Consideramos '0', nulo o cadena vacía como sin código.
return empty($p['barcode']) || $p['barcode'] == '0';
}));

// Nuevo KPI 2: Porcentaje de Productos con Imagen (Usabilidad)
$products_with_image = count(array_filter($products_data, function($p) {
return !empty($p['image_url']) && filter_var($p['image_url'], FILTER_VALIDATE_URL);
}));
$percentage_with_image = $total_products_loaded > 0 ? round(($products_with_image / $total_products_loaded) * 100) : 0;

// KPI 3: Productos Sin Stock (Mantenido, es útil para re-etiquetar ofertas)
$products_out_of_stock = count(array_filter($products_data, function($p) {
return (int)($p['stock'] ?? 0) <= 0;
}));

// KPI 4: Precio Promedio (Mantenido)
$average_price = $total_products_loaded > 0 ? array_sum(array_column($products_data, 'price')) / $total_products_loaded : 0;


// Helper para formato de moneda
function format_currency($amount) {
return number_format($amount, 0, ',', '.');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Centro de Etiquetas - Listto!</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="icon" type="image/png" href="/erp/img/fav.png">
 <link rel="stylesheet" href="css/labels.css">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
 /* ======================================= */
 /* ESTILOS CSS PARA ALINEACIÓN DE FILTROS */
 /* ======================================= */
 .table-header-controls {
  display: flex;
  justify-content: space-between; /* Separa el título y los controles */
  align-items: center; /* Centra verticalmente */
  margin-bottom: 20px; /* Espacio para separar de la tabla */
 }
 .table-controls {
  display: flex;
  gap: 10px;
  align-items: center;
 }
 .filter-select {
  padding: 8px 12px;
  border: 1px solid #ccc;
  border-radius: 6px;
  font-size: 14px;
  min-width: 150px;
 }
 
 /* ======================================= */
 /* ESTILOS CSS PARA EL ORDENAMIENTO (Mantenidos) */
 /* ======================================= */
 .sortable {
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.25rem;
  user-select: none;
 }
 .sort-icon {
  width: 10px;
  height: 10px;
  position: relative;
  opacity: 0.3; /* Icono gris por defecto */
  transition: opacity 0.2s;
  color: var(--text-primary); /* Usa el color del texto principal */
 }
 .sort-icon:before, .sort-icon:after {
  content: '';
  position: absolute;
  width: 0;
  height: 0;
  border-left: 5px solid transparent;
  border-right: 5px solid transparent;
  left: 0;
 }
 .sort-icon.none {
  opacity: 0.3;
 }
 .sort-icon.active {
  opacity: 1; /* Icono activo visible */
  color: var(--color-primary); /* Color primario para el icono activo */
 }
 /* Triángulo ASC (flecha hacia arriba) */
 .sort-icon:after {
  top: 0;
  border-bottom: 5px solid currentColor;
 }
 /* Triángulo DESC (flecha hacia abajo) */
 .sort-icon:before {
  bottom: 0;
  border-top: 5px solid currentColor;
 }
 /* Mostrar solo el indicador activo */
 .sort-icon.asc:before {
  border-top-color: transparent; /* Oculta el triángulo de abajo */
 }
 .sort-icon.desc:after {
  border-bottom-color: transparent; /* Oculta el triángulo de arriba */
 }

</style>
</head>

<body>
 <div id="notification-area"></div>

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
 <span>Hola, <strong><?php echo htmlspecialchars($_SESSION['user_username']); ?></strong></span>
 </div>

 <nav class="header-nav">
 <a href="labels.php" class="active">Centro de Etiquetas</a>
 </nav>

 <div class="header-right">
 <span class="app-version"><?php echo htmlspecialchars($system_version); ?></span>
 <a href="../logout.php" class="btn-logout">Cerrar Sesi&oacute;n</a>
 </div>
</header>

<main class="container">
 <div class="page-header-controls">
 <h1 class="page-title">Generador de Etiquetas</h1>
 <button class="btn-primary" id="btn-print-selected">
  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-2 inline-block"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
  Imprimir Seleccionadas
 </button>
 </div>

 <div class="kpi-grid">
 <div class="kpi-card" style="border-left-color: var(--kpi-green);">
  <h3>Productos Cargados</h3>
  <p class="value"><?= number_format($total_products_loaded, 0, ',', '.') ?></p>
 </div>
   <div class="kpi-card" style="border-left-color: var(--kpi-orange);">
  <h3>Sin Cód. Barra</h3>
  <p class="value"><?= number_format($products_no_barcode, 0, ',', '.') ?></p>
 </div>
   <div class="kpi-card" style="border-left-color: var(--kpi-purple);">
  <h3>Con Imagen</h3>
  <p class="value"><?= $percentage_with_image ?>%</p>
 </div>
   <div class="kpi-card" style="border-left-color: var(--kpi-red);">
  <h3>Sin Stock</h3>
  <p class="value projection" style="color: var(--kpi-red);"><?= number_format($products_out_of_stock, 0, ',', '.') ?></p>
 </div>
   <div class="kpi-card" style="border-left-color: var(--kpi-blue);">
  <h3>Precio Promedio</h3>
  <p class="value">$<?= format_currency($average_price) ?></p>
 </div>
 </div>

 <div class="content-card">
  <div class="table-header-controls">
    <h2>Listado de Productos (Mostrando: 30 m&aacute;s recientes)</h2>
  
   <div class="table-controls">
        <select id="category-filter" class="filter-select">
    <option value="all">Todas las Categorías</option>
    <?php foreach ($categories_data as $cat): ?>
     <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
    <?php endforeach; ?>
    </select>
    
    <select id="supplier-filter" class="filter-select">
    <option value="all">Todos los Proveedores</option>
    <option value="0">Sin Proveedor Asignado</option>
    <?php foreach ($suppliers_data as $sup): ?>
     <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?></option>
    <?php endforeach; ?>
    </select>
       
   <input type="text" id="search-input" placeholder="Escanear Código o Buscar Nombre..." class="search-input">
   </div>
 </div>
 
 <?php if ($error_message): ?>
  <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
 <?php else: ?>
  <div class="table-container">
  <table class="sales-table">
   <thead>
   <tr>
    <th><input type="checkbox" id="select-all"></th>
    <th>IMAGEN</th>        <th data-sort="id"><div class="sortable" data-sort="id">ID <span class="sort-icon desc active" data-sort-icon="id"></span></div></th>
    <th data-sort="barcode"><div class="sortable" data-sort="barcode">CÓDIGO DE BARRA <span class="sort-icon none" data-sort-icon="barcode"></span></div></th>
    <th data-sort="name"><div class="sortable" data-sort="name">Nombre <span class="sort-icon none" data-sort-icon="name"></span></div></th>
    <th data-sort="price"><div class="sortable" data-sort="price">Precio <span class="sort-icon none" data-sort-icon="price"></span></div></th>
    <th data-sort="stock"><div class="sortable" data-sort="stock">Stock <span class="sort-icon none" data-sort-icon="stock"></span></div></th>
    <th>Acción</th>
   </tr>
   </thead>
   <tbody id="products-table-body">
      </tbody>
  </table>
  </div>
 <?php endif; ?>
 </div>
</main>

 <script>
 // Datos PHP pasados a JS (ya ordenados por ID DESC)
 const productsData = <?= json_encode($products_data); ?>;
 const tableBody = document.getElementById('products-table-body');
 const searchInput = document.getElementById('search-input');
 const selectAllCheckbox = document.getElementById('select-all');
 const btnPrintSelected = document.getElementById('btn-print-selected');
 // Seleccionamos directamente los TH que tienen el atributo data-sort
 const sortableHeaders = document.querySelectorAll('th[data-sort]');

 // **NUEVAS REFERENCIAS DE FILTRO**
 const categoryFilter = document.getElementById('category-filter');
 const supplierFilter = document.getElementById('supplier-filter');

 // Estado de ordenamiento inicial: ID descendente (los más recientes primero)
 let currentSort = { column: 'id', direction: 'desc' };
 const INITIAL_LIMIT = 30;


 // =========================================================================
 // 1. FUNCIÓN DE NOTIFICACIÓN (REEMPLAZO DE alert())
 // =========================================================================
 const showNotification = (message, type = 'success', duration = 3000) => {
  const container = document.getElementById('notification-area');
  if (!container) return;

  const toast = document.createElement('div');
  toast.className = 'notification-toast';
  if (type === 'error') {
   toast.classList.add('error');
  }
  toast.textContent = message;
  container.appendChild(toast);

  setTimeout(() => {
   toast.classList.add('show');
  }, 10);

  setTimeout(() => {
   toast.classList.remove('show');
   setTimeout(() => {
    container.removeChild(toast);
   }, 300);
  }, duration);
 };


 // =========================================================================
 // 2. LÓGICA DE IMPRESIÓN
 // =========================================================================

 const formatCurrency = (amount) => {
 // Formato Chileno (CLP) sin decimales
 return parseFloat(amount).toLocaleString('es-CL', {
  style: 'currency',
  currency: 'CLP',
  minimumFractionDigits: 0
 });
 };

 // Función para abrir la ventana de impresión (asumiendo print_label.php)
 const handlePrintLabel = (product) => {
 const priceCLP = formatCurrency(product.price).replace('$', '').trim();
 const printUrl = `print_label.php?name=${encodeURIComponent(product.name)}&barcode=${encodeURIComponent(product.barcode)}&price=${encodeURIComponent(priceCLP)}`;
 // Abrimos en nueva ventana para simular la impresión
 window.open(printUrl, '_blank', 'width=400,height=300');
 };

 const handlePrintClick = (e) => {
 const productDataAttr = e.currentTarget.getAttribute('data-product');
 try {
  // Se utiliza el atributo data-product tal cual, debe ser parseable.
  const product = JSON.parse(productDataAttr);
  handlePrintLabel(product);
  showNotification(`Imprimiendo etiqueta: ${product.name}`, 'success', 2000);
 } catch (error) {
  console.error("Error al parsear datos del producto:", error);
  // Esto ocurre si el JSON está mal formado (ej. por comillas sin escapar)
  showNotification("Error: No se pudieron cargar los datos del producto para imprimir.", 'error');
 }
 };

 const handlePrintSelected = () => {
  const selectedCheckboxes = document.querySelectorAll('#products-table-body .product-checkbox:checked');
  const selectedProducts = [];
 
  if (selectedCheckboxes.length === 0) {
   showNotification('Por favor, selecciona al menos un producto para imprimir.', 'error', 4000);
   return;
  }

  selectedCheckboxes.forEach(checkbox => {
   // El data-id está en el checkbox, lo usamos directamente.
   const productId = parseInt(checkbox.getAttribute('data-id'));
   const product = productsData.find(p => p.id === productId);
   if (product) {
    selectedProducts.push(product);
   }
  });

  let delay = 0;
  const delayIncrement = 300;
 
  selectedProducts.forEach((product, index) => {
   setTimeout(() => {
    handlePrintLabel(product);
    if (index === selectedProducts.length - 1) {
     setTimeout(() => {
      showNotification(`Impresión de ${selectedProducts.length} etiquetas iniciada. Revisa las ventanas emergentes.`, 'success', 5000);
     }, delayIncrement);
    }
   }, delay);
   delay += delayIncrement;
  });

  if (selectAllCheckbox) selectAllCheckbox.checked = false;
  document.querySelectorAll('#products-table-body .product-checkbox').forEach(checkbox => checkbox.checked = false);
 };

 // =========================================================================
 // 3. LÓGICA DE ORDENAMIENTO Y TABLA
 // =========================================================================

 // Función de comparación para el ordenamiento
 const compare = (a, b, column, direction) => {
  let aVal = a[column];
  let bVal = b[column];

  // Manejo de valores numéricos
  if (column === 'id' || column === 'price' || column === 'stock') {
   aVal = parseFloat(aVal) || 0;
   bVal = parseFloat(bVal) || 0;
  } else {
   // Manejo de strings (case-insensitive)
   aVal = String(aVal).toLowerCase();
   bVal = String(bVal).toLowerCase();
  }

  if (aVal < bVal) return direction === 'asc' ? -1 : 1;
  if (aVal > bVal) return direction === 'asc' ? 1 : -1;
  return 0;
 };

 // Función principal de ordenamiento
 const sortProducts = (data, column, direction) => {
  // Clonar la data para evitar mutar el array original
  const sortedData = [...data];
  return sortedData.sort((a, b) => compare(a, b, column, direction));
 };


 // Función para renderizar la tabla
 const renderTable = (data) => {
 tableBody.innerHTML = '';

 if (data.length === 0) {
  tableBody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem;">No se encontraron productos que coincidan con la búsqueda o los filtros.</td></tr>';
  return;
 }

 data.forEach(product => {
  const stockColor = product.stock > 0 ? '#10b981' : '#ef4444';
 
  // Lógica para usar image_url (si existe) o placeholder
  const imageUrl = product.image_url && product.image_url.trim() !== ''
   ? (product.image_url.startsWith('http') ? product.image_url : `../${product.image_url}`)
   : `https://placehold.co/40x40/f0f0f0/999?text=ID${product.id}`;
 
  const product_json = JSON.stringify({
  'id': product.id,
  'name': product.name,
  'barcode': product.barcode,
  'price': product.price
  });
   
    // CORRECCIÓN: Escapar comillas simples dentro del JSON
    const safe_product_json = product_json.replace(/'/g, '&apos;');


  const row = document.createElement('tr');
  row.innerHTML = `
  <td><input type="checkbox" class="product-checkbox" data-id="${product.id}"></td>
     <td style="text-align: center; vertical-align: middle;">
   <img
    src="${imageUrl}"
    alt="Producto ${product.id}"
    class="product-image"
    style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; box-shadow: 0 0 3px rgba(0,0,0,0.1);"
    onerror="this.style.display='none'; this.after(document.createTextNode('No Img'));"
   >
  </td>
  <td>${product.id}</td>
  <td>${product.barcode}</td>
  <td>${product.name}</td>
  <td style="text-align: right;">${formatCurrency(product.price)}</td>
  <td style="text-align: center; color: ${stockColor}; font-weight: 600;">${product.stock.toLocaleString('es-CL')}</td>
  <td>
   <button class="btn-print-single btn-primary-mini" data-product='${safe_product_json}'>
   Imprimir
   </button>
  </td>
  `;
  tableBody.appendChild(row);
 });

 attachPrintListeners();
 };

 // Función para adjuntar Event Listeners a los botones de impresión
 const attachPrintListeners = () => {
 document.querySelectorAll('.btn-print-single').forEach(button => {
  // Aseguramos que solo haya un listener
  button.removeEventListener('click', handlePrintClick);
  button.addEventListener('click', handlePrintClick);
 });
 };

 // Función principal de filtrado y visualización (MODIFICADA para filtros)
 const filterAndRender = () => {
 const searchTerm = searchInput.value.toLowerCase();
 
 // Obtener valores de los nuevos filtros
 const selectedCategory = categoryFilter.value;
 const selectedSupplier = supplierFilter.value;
 
 let dataToRender = productsData;
 let titleElement = document.querySelector('.content-card h2');

 // 1. APLICAR FILTROS DE CATEGORÍA Y PROVEEDOR (sobre los 5000 productos)
 dataToRender = dataToRender.filter(product => {
  // Filtrar por Categoría
  const categoryMatch = selectedCategory === 'all' || 
              product.category_id == selectedCategory;
  
  // Filtrar por Proveedor
  let supplierMatch = selectedSupplier === 'all';
  if (!supplierMatch) {
   if (selectedSupplier === '0') {
    // Productos sin proveedor (null o 0)
    supplierMatch = product.supplier_id == null || product.supplier_id == 0;
   } else {
    // Proveedor específico
    supplierMatch = product.supplier_id == selectedSupplier;
   }
  }

  return categoryMatch && supplierMatch;
 });


 // 2. APLICAR FILTRO DE BÚSQUEDA (sobre los resultados filtrados)
 if (searchTerm) {
  dataToRender = dataToRender.filter(product =>
   (product.name && String(product.name).toLowerCase().includes(searchTerm)) ||
   (product.barcode && String(product.barcode).toLowerCase().includes(searchTerm))
  );
 }
 
 // 3. ORDENAR el conjunto de datos
 dataToRender = sortProducts(dataToRender, currentSort.column, currentSort.direction);

 // 4. APLICAR LÍMITE DE VISUALIZACIÓN o actualizar el título
 const isFiltered = searchTerm || selectedCategory !== 'all' || selectedSupplier !== 'all';

 if (isFiltered) {
  // Si hay filtros o búsqueda, mostramos todos los resultados
  if (titleElement) {
   titleElement.textContent = `Resultados Encontrados (${dataToRender.length} productos)`;
  }
 } else {
  // Vista inicial sin filtros/búsqueda: aplicamos el límite inicial
  dataToRender = dataToRender.slice(0, INITIAL_LIMIT);
  if (titleElement) {
   titleElement.textContent = `Listado de Productos (Mostrando: ${INITIAL_LIMIT} más recientes)`;
  }
 }
 
 renderTable(dataToRender);
 };

 // Manejador de evento para ordenamiento
 const handleSortClick = (e) => {
  // El target podría ser el TH o el DIV.sortable dentro. Buscamos el TH contenedor.
  const thElement = e.currentTarget.closest('th[data-sort]');
  if (!thElement) return;

  const column = thElement.getAttribute('data-sort');
  const sortableDiv = thElement.querySelector('.sortable');
 
  // Determinar la nueva dirección
  let newDirection;
  if (currentSort.column === column) {
   // Si es la misma columna, alternar dirección
   newDirection = currentSort.direction === 'asc' ? 'desc' : 'asc';
  } else {
   // Si es una nueva columna, usar 'asc' por defecto (o 'desc' si es ID/Stock/Price)
   newDirection = (column === 'id' || column === 'stock' || column === 'price') ? 'desc' : 'asc';
  }

  currentSort = { column: column, direction: newDirection };

  // Actualizar iconos visuales
  document.querySelectorAll('.sort-icon').forEach(icon => {
   icon.classList.remove('active', 'asc', 'desc', 'none');
   icon.classList.add('none');
  });

  const activeIcon = document.querySelector(`[data-sort-icon="${column}"]`);
  if (activeIcon) {
   activeIcon.classList.remove('none');
   activeIcon.classList.add('active', newDirection);
  }

  // Re-renderizar la tabla con el nuevo orden
  filterAndRender();
 };


 // =========================================================================
 // 4. INICIO Y EVENT LISTENERS
 // =========================================================================

 document.addEventListener('DOMContentLoaded', function() {

 if (selectAllCheckbox) {
  selectAllCheckbox.addEventListener('change', (e) => {
  document.querySelectorAll('#products-table-body .product-checkbox').forEach(checkbox => {
   checkbox.checked = e.target.checked;
  });
  });
 }

 if (btnPrintSelected) {
  btnPrintSelected.addEventListener('click', handlePrintSelected);
 }

 // Event listener para ordenamiento: lo adjuntamos al TH que contiene data-sort
 sortableHeaders.forEach(header => {
  header.addEventListener('click', handleSortClick);
 });
 
 // **NUEVOS EVENT LISTENERS PARA FILTROS (Llaman a filterAndRender)**
 if (categoryFilter) {
  categoryFilter.addEventListener('change', filterAndRender);
 }
 if (supplierFilter) {
  supplierFilter.addEventListener('change', filterAndRender);
 }
 // FIN NUEVOS EVENT LISTENERS

 // Búsqueda y filtrado
 if (searchInput) {
  searchInput.addEventListener('keyup', filterAndRender);
 
  // Simulación de escáner al presionar Enter
  searchInput.addEventListener('keydown', (e) => {
  if (e.key === 'Enter') {
   e.preventDefault();
  
   const searchTerm = searchInput.value;
   // Buscar por código de barra (escaneo)
   const exactMatch = productsData.find(p => p.barcode === searchTerm);
  
   if (exactMatch) {
   handlePrintLabel(exactMatch);
   showNotification(`Código de barra [${exactMatch.barcode}] encontrado e impresión iniciada.`, 'success', 3000);
   searchInput.value = '';
   filterAndRender();
   } else {
   // Si no encuentra, igual filtra por el término (para búsqueda por nombre/ID)
   filterAndRender();
   }
  }
  });
 }

 // Renderizar la tabla inicial (los 30 más recientes, ordenados por ID DESC)
 if (productsData.length > 0) {
  // Inicializar el ícono de ordenamiento para 'id' descendente
  const initialIcon = document.querySelector('[data-sort-icon="id"]');
  if (initialIcon) {
  initialIcon.classList.remove('none');
  initialIcon.classList.add('active', 'desc');
  }
 
  filterAndRender();
 } else if (!'<?= $error_message ?>') {
  tableBody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem;">No se encontraron productos en la base de datos para cargar.</td></tr>';
 }
 });
</script>
</body>

</html>