<?php
// =========================================================
// 1. CONFIGURACIÓN, SEGURIDAD Y CONEXIÓN
// =========================================================
// Muestra errores para depuración
/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/

require '../config.php';
// Inicia sesión
session_start();

// Descomentar cuando la seguridad sea necesaria
if (!isset($_SESSION['user_username'])) {
    header('Location: ../../login.php');
    exit();
}


// Variables para el encabezado (versión del sistema)
$system_version = 'v1.0';
try {
    $stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
    $stmt->execute();
    $system_version = $stmt->fetchColumn() ?? 'v1.0';
} catch (PDOException $e) {
    // Manejo de error si la tabla 'config' no existe o la conexión falla
    $system_version = 'v1.0 (DB Error)';
    error_log("Database error fetching version: " . $e->getMessage());
}

// Bandera de error de DB para el renderizado
$db_error = false;

// --- FUNCIONES DE CÁLCULO DE PRECIOS Y MÁRGENES ---
// Iva utilizado en Chile
define('IVA_RATE', 0.19);

/**
 * Convierte un valor a entero (para precios sin decimales).
 * @param float $value Valor a convertir.
 * @return int Valor redondeado y convertido a entero.
 */
function to_integer_price($value) {
    // Redondea a la unidad más cercana antes de convertir a entero
    return (int) round($value);
}

/**
 * Calcula el Costo Bruto (con IVA) a partir del Costo Neto.
 * @param float $net_cost Costo neto (sin IVA).
 * @return float Costo bruto (con IVA).
 */
function calculate_gross_cost($net_cost) {
    return $net_cost * (1 + IVA_RATE);
}

/**
 * Calcula el Costo Neto (sin IVA) a partir del Costo Bruto.
 * @param float $gross_cost Costo bruto (con IVA).
 * @return float Costo neto (sin IVA).
 */
function calculate_net_cost($gross_cost) {
    if ((1 + IVA_RATE) == 0) return 0;
    return $gross_cost / (1 + IVA_RATE);
}

/**
 * Calcula el Porcentaje de Margen Bruto (markup) basado en el costo neto y el precio de venta.
 * Margen = ((Precio Venta - Costo Neto) / Precio Venta) * 100
 * @param float $net_cost Costo neto (cost_price).
 * @param float $sale_price Precio de venta (price).
 * @return float Porcentaje de margen.
 */
function calculate_margin_percentage($net_cost, $sale_price) {
    if ($sale_price <= 0 || $sale_price < $net_cost) return 0;
    return (($sale_price - $net_cost) / $sale_price) * 100;
}

/**
 * Aplica el redondeo a la centena superior y resta 10.
 * @param float $price Precio.
 * @return int Nuevo precio redondeado.
 */
function round_to_nearest_hundred_minus_ten($price) {
    // Redondea hacia arriba a la centena más cercana
    $rounded_up = ceil($price / 100) * 100;
    return to_integer_price($rounded_up - 10); // Usamos la nueva función para asegurar entero
}


// --- LÓGICA DE PROVEEDORES ---

$suppliers = [];
$selected_supplier_id = null;
$supplier_products = [];
$supplier_invoices = [];
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
        $selected_supplier_id = $suppliers[0]['id'];
    }

    // 3. Obtener los productos y facturas del proveedor seleccionado (si hay uno)
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

        // Procesar productos para mostrar métricas actuales y asegurar valores enteros en precios
        foreach ($raw_products as $product) {
            $net_cost = (float)$product['cost_price'];
            $current_sale_price = (float)$product['price'];

            $gross_cost = calculate_gross_cost($net_cost);
            $margin = calculate_margin_percentage($net_cost, $current_sale_price);

            $supplier_products[] = [
                'id' => (int)$product['id'],
                'code' => htmlspecialchars($product['code']),
                'name' => htmlspecialchars($product['name']),
                'stock' => (int)$product['stock'],
                // Aplicamos to_integer_price a los costos/precios que son CLP
                'cost_price_net' => to_integer_price($net_cost),
                'cost_price_gross' => to_integer_price($gross_cost),
                'current_margin' => round($margin, 2),
                'current_sale_price' => to_integer_price($current_sale_price),
                // 💡 CLAVE: Incluir la URL de la imagen en el JSON para JavaScript
                'image_url' => htmlspecialchars($product['image_url'] ?? ''),
            ];
        }

        // Facturas del proveedor seleccionado
        $stmt_invoices = $pdo->prepare("
            SELECT id, invoice_number, total_amount AS total, created_at
            FROM purchase_invoices
            WHERE supplier_id = ?
            ORDER BY created_at DESC
        ");
        $stmt_invoices->execute([$selected_supplier_id]);
        $supplier_invoices = $stmt_invoices->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Manejo de errores de base de datos general para evitar que la aplicación se caiga
    error_log("Database operation failed: " . $e->getMessage());
    $db_error = true;
}


// Codificar datos de productos para usar en JavaScript
$products_json = json_encode($supplier_products);
$invoices_json = json_encode($supplier_invoices);

// Definir página actual para el header
$current_page = 'suppliers.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ingreso de Facturas - Listto!</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="icon" type="image/png" href="/erp/img/fav.png">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/suppliers.css">
<script src="https://unpkg.com/@phosphor-icons/web@2.0.3/dist/assets/inline.js"></script>
</head>

<body>

<header class="main-header">
    <div class="header-left">
        <a href="../../launcher.php" class="launcher-icon" title="Ir al Lanzador de Aplicaciones">
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
        <a href="suppliers.php" class="active">Facturas de Proveedores</a>
    </nav>
    <div class="header-right">
        <span class="app-version"><?php echo htmlspecialchars($system_version); ?></span>
        <a href="logout.php" class="btn-logout">Cerrar Sesión <i class="ph ph-sign-out"></i></a>
    </div>
</header>

<div id="status-message" class="status-message" aria-live="polite" aria-atomic="true"></div>

<div class="main-wrapper">
    <aside class="sidebar">
        <h2 class="sidebar-title">Proveedores</h2>
        <ul class="supplier-list">
            <?php if ($db_error): ?>
                <li class="no-suppliers" style="color: red;">Error al cargar proveedores.</li>
            <?php elseif (empty($suppliers)): ?>
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
    </aside>

    <main class="container main-content">
        <?php if (!$selected_supplier_id): ?>
            <div class="content-card info-message">
                <p><i class="ph ph-info-bold"></i> Por favor, selecciona un proveedor de la lista de la izquierda para comenzar a ingresar facturas.</p>
            </div>
        <?php else: ?>
            <h1 class="page-title">Factura de Compra: <?= htmlspecialchars($selected_supplier_name) ?></h1>

            <div class="content-card invoice-form-card">
                <h2>Productos en la Factura</h2>

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
                                    <th rowspan="2" class="col-stock">Stock Act.</th>
                                    <th colspan="4" class="current-metrics-header">Métricas Actuales (Antes)</th>
                                    <th colspan="6" class="invoice-data-header">Datos de Factura y Precios Nuevos</th>
                                    <th rowspan="2">Acción</th>
                                </tr>
                                <tr>
                                    <th>Costo Neto Ant.</th>
                                    <th>Costo Bruto Ant.</th>
                                    <th>Margen Act. (%)</th>
                                    <th>P. Venta Act.</th>

                                    <th>Costo Neto Nuevo <span class="required">*</span></th>
                                    <th>Costo Bruto Nuevo</th>
                                    <th>Margen % Nuevo</th>
                                    <th>P. Venta Bruto Sug.</th>
                                    <th>Nuevo P. Venta Final <span class="required">*</span></th>
                                    <th>Cantidad <span class="required">*</span></th>
                                </tr>
                            </thead>
                            <tbody id="invoice-items-body">
                                <tr id="no-products-row"><td colspan="13" style="text-align: center; padding: 1rem; color: #666;">Usa el botón "Agregar Producto Existente" para empezar.</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="invoice-totals">
                        <p>Subtotal (Neto): <span id="subtotal-net" class="total-value">$0</span></p>
                        <p>IVA (<?= (IVA_RATE * 100) ?>%): <span id="iva-amount" class="total-value">$0</span></p>
                        <p class="total-line">Total Factura (Bruto): <span id="total-gross" class="total-value highlighted">$0</span></p>
                    </div>

                    <div class="form-actions">
                        <button id="clear-invoice-btn" class="btn-secondary" type="button"><i class="ph ph-trash"></i> Limpiar Factura</button>
                        <button id="register-invoice-btn" class="btn-success" disabled><i class="ph ph-receipt"></i> Registrar Factura</button>
                    </div>
                </form>
            </div> 
            
            <div class="content-card history-card">
                <h2>Facturas de Proveedor Registradas</h2>
                <div class="table-container">
                    <table class="invoices-table">
                        <thead>
                            <tr>
                                <th>Nº Factura</th>
                                <th>Monto Total</th>
                                <th>Fecha</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($supplier_invoices)): ?>
                                <tr><td colspan="4" style="text-align: center; padding: 1rem;">No hay facturas registradas para este proveedor.</td></tr>
                            <?php else: ?>
                                <?php foreach ($supplier_invoices as $invoice): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                                        <td class="text-right">$<?= number_format($invoice['total'], 0, ',', '.') ?></td>
                                        <td><?= date('d-m-Y', strtotime($invoice['created_at'])) ?></td>
                                        <td>
                                            <a href="ver_factura.php?id=<?= $invoice['id'] ?>" target="_blank" class="btn-view-invoice">
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
                    <h3>Confirmar y Registrar Factura</h3>
                    <p class="modal-summary">Estás a punto de ingresar los productos y actualizar los precios de venta.</p>
                    <form id="final-invoice-form">
                        <p class="total-summary">Total Factura a Registrar (Bruto): <strong id="modal-total-display">$0</strong></p>
                        <input type="hidden" id="final-supplier-id" value="<?= $selected_supplier_id ?>">

                        <div class="form-group">
                            <label for="invoice_number">Número de Factura:</label>
                            <input type="text" id="invoice_number" required placeholder="Ej: 1234567">
                        </div>
                        <div class="form-group">
                            <label for="invoice_date">Fecha de Emisión:</label>
                            <input type="date" id="invoice_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <button type="submit" class="btn-success btn-full-width"><i class="ph ph-floppy-disk"></i> Guardar Factura y Actualizar Productos</button>
                    </form>
                </div>
            </div>

<div id="select-product-modal" class="modal">
    <div class="modal-content large-modal-content"> 
        <span class="close-button" data-modal-target="select-product-modal">&times;</span>
        <h3>Seleccionar Producto Existente</h3>
        <p>Haz clic en las tarjetas de producto que deseas añadir a tu orden de compra. Los ya agregados están desactivados.</p>
        
        <input type="text" id="product-search" placeholder="Buscar producto por código o nombre..." class="search-input">
        
        <div id="product-grid-container" class="product-grid-container">
            <?php if (!empty($supplier_products)): ?>
                <?php foreach ($supplier_products as $product): 
                    // Se usa el 'cost_price_net' que ya has procesado en PHP
                    $costo_neto_actual = number_format($product['cost_price_net'], 0, ',', '.');
                    // URL de la imagen. Usa una imagen de placeholder si no existe.
                    $image_url = !empty($product['image_url']) ? htmlspecialchars($product['image_url']) : '../img/placeholder-product.png';
                ?>
                    <div 
                        class="product-card" 
                        data-product-id="<?= $product['id'] ?>"
                        data-product-name="<?= $product['name'] ?>" 
                        data-search-text="<?= htmlspecialchars($product['name'] . ' ' . $product['code']) ?>"
                    >
                        <div class="product-image-container">
                            <img src="<?= $image_url ?>" alt="<?= $product['name'] ?>">
                        </div>
                        <div class="product-info">
                            <h4 class="product-name"><?= htmlspecialchars($product['name']) ?></h4>
                            <p class="product-stock">Stock: <strong><?= $product['stock'] ?></strong> | Cód: <span><?= $product['code'] ?></span></p>
                            <p class="product-cost">Costo Act. Neto: <strong>$<?= $costo_neto_actual ?></strong></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p id="product-search-results" class="search-info">No hay productos existentes para este proveedor.</p>
            <?php endif; ?>
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
                <input type="text" id="new_product_code" required>
            </div>
            <div class="form-group">
                <label for="new_product_name">Nombre del Producto:</label>
                <input type="text" id="new_product_name" required>
            </div>
            <div class="form-group">
                <label for="new_product_quantity">Cantidad:</label>
                <input type="number" id="new_product_quantity" value="1" min="1" step="1" required>
            </div>
            <hr class="form-divider">
            <h4>Definición de Precios</h4>
            <div class="form-group price-input-group">
                <label for="new_product_cost_net">Costo Neto:</label>
                <input type="number" step="0.01" id="new_product_cost_net" value="1000" min="0" required>
            </div>
            <div class="form-group price-input-group">
                <label for="new_product_margin_pct">Margen de Venta (%):</label>
                <input type="number" step="0.01" id="new_product_margin_pct" value="30.00" min="0" required>
            </div>
            <div class="form-group price-input-group">
                <label for="new_product_price_final">Precio Venta Final Sug.:</label>
                <input type="number" **step="0.01"** id="new_product_price_final" value="0" readonly class="highlighted-read-only"> 
            </div>

            <button type="submit" class="btn-success btn-full-width"><i class="ph ph-plus-square"></i> Crear y añadir producto</button>
        </form>
    </div>
</div>


        <?php endif; ?>
    </main>
</div>

    <script>
    // --- INYECCIÓN DE VARIABLES JAVASCRIPT CORREGIDA ---
    // Aseguramos que el ID del proveedor siempre sea un número (0 si no está seleccionado)
    const SUPPLIER_ID = <?= $selected_supplier_id ?? 0; ?>;
    
    // Inyección de la lista de productos del proveedor seleccionado
    // CLAVE: Ahora incluye la columna 'image_url' para el modal
    const ALL_SUPPLIER_PRODUCTS = <?= $products_json; ?>;
    
    // La lista de ítems de factura está vacía inicialmente, se llena por el usuario
    const INVOICE_ITEMS = [];
    
    // Inyectamos la tasa de IVA desde la constante PHP
    const IVA_RATE = <?= IVA_RATE; ?>;

    // Formateador de moneda (peso chileno, sin decimales por la naturaleza del negocio)
    const currencyFormatter = new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 0
    });
</script>
<script src="js/suppliers.js"></script>

</body>
</html>