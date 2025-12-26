<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Es crucial que 'config.php' exista y configure correctamente la variable $pdo.
require 'config.php';


// Configura la expiración de la sesión a 2 horas (7200 segundos) de INACTIVIDAD.
$lifetime = 7200; 

// Establece el tiempo de vida máximo para la cookie de sesión.
session_set_cookie_params($lifetime);

// Establece el tiempo de vida máximo para el archivo de sesión en el servidor.
ini_set('session.gc_maxlifetime', $lifetime);

session_start();

// ====== MANEJO DE ACCIONES AJAX PARA EL CARRITO (SE MANTIENE IGUAL) ======
if (isset($_POST['action']) && $_POST['action'] !== 'fetch_sale' && $_POST['action'] !== 'refund') {
    header('Content-Type: application/json');
    $productId = $_POST['id'] ?? null;
    $product = null;

if ($productId) {
    // CORRECCIÓN: Agregamos is_offer y el filtro archived = 0
    $stmt = $pdo->prepare("SELECT id, name, price, stock, is_offer FROM products WHERE id = ? AND archived = 0");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
   }
    
    switch ($_POST['action']) {
        case 'add':
            if ($product) {
                // Validación de stock (funcionalidad no solicitada pero importante)
                if (isset($_SESSION['cart'][$productId]) && $_SESSION['cart'][$productId]['quantity'] >= $product['stock']) {
                    http_response_code(400); // Bad Request
                    echo json_encode(['error' => 'Stock insuficiente']);
                    exit();
                }
                
                if (!isset($_SESSION['cart'][$productId])) {
                    $_SESSION['cart'][$productId] = array_merge($product, ['quantity' => 1]);
                } else {
                    $_SESSION['cart'][$productId]['quantity']++;
                }
            }
            break;
        case 'update':
            // Se usa max(0,...) para manejar devoluciones parciales y evitar negativos
            $quantity = max(0, (float)($_POST['quantity'] ?? 0)); 
            
            if ($productId && isset($_SESSION['cart'][$productId])) {
                // Re-validación de stock al actualizar
                if ($quantity > $product['stock']) {
                    // Si se excede el stock, ajustamos a la cantidad máxima
                    $_SESSION['cart'][$productId]['quantity'] = $product['stock'];
                } else if ($quantity > 0) {
                    $_SESSION['cart'][$productId]['quantity'] = $quantity;
                } else {
                    unset($_SESSION['cart'][$productId]);
                }
            }
            break;
        case 'clear':
            $_SESSION['cart'] = [];
            break;
    }
    
    echo json_encode($_SESSION['cart'] ?? []);
    exit();
}


// =================================================================================
// ====== LÓGICA CORREGIDA: BUSCAR VENTA POR ID PRIMARIO (COLUMNA 'id')======
// ====== AHORA INCLUYE si.id (ID de sale_items) PARA DEVOLUCIÓN PARCIAL ===========
// =================================================================================
if (isset($_POST['action']) && $_POST['action'] === 'fetch_sale') {
    header('Content-Type: application/json');
    
    // Ahora $saleIdFromInput será el ID primario de la tabla 'sales'.
    $saleIdFromInput = (int)($_POST['sale_id'] ?? 0); 

    if ($saleIdFromInput <= 0) {
        echo json_encode(['error' => 'ID de venta inválido.']);
        exit();
    }

    // 1. Obtener la venta principal BUSCANDO POR EL ID PRIMARIO
    $stmt_sale = $pdo->prepare("SELECT id, total, receipt_number, created_at FROM sales WHERE id = ?");
    $stmt_sale->execute([$saleIdFromInput]);
    $sale = $stmt_sale->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        echo json_encode(['error' => 'Venta no encontrada.']);
        exit();
    }
    
    $saleId = $sale['id']; 

    // 2. Obtener los items de la venta. INCLUYE si.id
    $stmt_items = $pdo->prepare("SELECT si.id, si.product_id, si.quantity, si.price, p.name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?");
    $stmt_items->execute([$saleId]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    $sale['items'] = $items;
    echo json_encode($sale);
    exit();
}


// =================================================================================
// ====== LÓGICA PARA FINALIZAR LA VENTA (MANTIENE LA CORRECCIÓN DE PACKS)======
// =================================================================================
if (isset($_POST['finalize'])) {
    if (empty($_SESSION['cart'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'El carrito está vacío.']);
        exit();
    }

    $total = 0;
// Cantidad debe ser (float) para Granel.
    foreach ($_SESSION['cart'] as $item) {
        $price = is_numeric($item['price']) ? (float)$item['price'] : 0;
        $quantity = is_numeric($item['quantity']) ? (float)$item['quantity'] : 0; // ¡Cambiado a FLOAT!
        $total += $price * $quantity;
    }

    $method = $_POST['method'] ?? 'efectivo';
    $paid = isset($_POST['paid_amount']) ? floatval($_POST['paid_amount']) : $total;
    $change = max(0, $paid - $total);

    $pdo->beginTransaction();
    try {
        // 1. Aumentar el contador del recibo
        $stmt_counter = $pdo->prepare("UPDATE counters SET value = value + 1 WHERE name = 'receipt_number'");
        $stmt_counter->execute();
        
        $stmt_get_counter = $pdo->prepare("SELECT value FROM counters WHERE name = 'receipt_number'");
        $stmt_get_counter->execute();
        $receipt_number = $stmt_get_counter->fetchColumn();

        // 2. Insertar la venta
        // Nota: Agregado 'status' por defecto (asumiendo que existe en tu tabla)
        $stmt = $pdo->prepare("INSERT INTO sales (total, paid, `change`, method, receipt_number, status, created_at) VALUES (?, ?, ?, ?, ?, 'completed', NOW())");
        $stmt->execute([$total, $paid, $change, $method, $receipt_number]);
        $sale_id = $pdo->lastInsertId();

        // 3. Preparar consultas
        $stmt_item = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        $stmt_stock_update = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");

        // Consulta para verificar si es un PACK/OFERTA
        $stmt_check_offer = $pdo->prepare("SELECT id, is_offer FROM products WHERE id = ?");
        
        // Consulta para obtener los productos individuales de una oferta.
        $stmt_offer_details = $pdo->prepare("SELECT product_id, quantity FROM offer_products WHERE offer_id = ?");


        // 4. Procesar items, insertar y actualizar stock
        foreach ($_SESSION['cart'] as $item) {
            $product_id = $item['id'];
            $quantity_sold = (float)$item['quantity'];
            $price_unit = (float)$item['price'];

            // A. Insertar el item en sale_items (siempre)
            $stmt_item->execute([$sale_id, $product_id, $quantity_sold, $price_unit]);
            
            // B. Verificar si el item vendido es un PACK/OFERTA
            $stmt_check_offer->execute([$product_id]);
            $product_row = $stmt_check_offer->fetch(PDO::FETCH_ASSOC);
            $is_pack = $product_row && $product_row['is_offer'] == 1;


            if ($is_pack) {
                // ES UN PACK/OFERTA.
                
                // B.1. Descontar el stock del PACK (el producto en sí mismo)
                $stmt_stock_update->execute([$quantity_sold, $product_id]);

                // B.2. Descontar el stock de los productos INDIVIDUALES (los componentes)
                $pack_id = $product_id; 
                $stmt_offer_details->execute([$pack_id]);
                $offer_components = $stmt_offer_details->fetchAll(PDO::FETCH_ASSOC);

                foreach ($offer_components as $component) {
                    $component_id = $component['product_id'];
                    $component_qty_per_pack = (float)$component['quantity'];
                    
                    // Cantidad total a descontar: (Unidades del componente en 1 pack) * (Packs vendidos)
                    $total_qty_to_deduct = $component_qty_per_pack * $quantity_sold;

                    // Descontamos del stock individual.
                    $stmt_stock_update->execute([$total_qty_to_deduct, $component_id]);
                }

            } else {
                // Es un producto REGULAR. Simplemente descontamos su stock.
                $stmt_stock_update->execute([$quantity_sold, $product_id]);
            }
        }

        $pdo->commit();
        $_SESSION['cart'] = [];

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'sale_id' => $sale_id]);
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        error_log("Error en la venta: " . $e->getMessage()); // Loguea el error
        echo json_encode(['error' => 'Error al guardar venta y actualizar stock. Mensaje: ' . $e->getMessage()]);
        exit();
    }
}


// =================================================================================
// ====== LÓGICA PARA DEVOLUCIÓN / ANULACIÓN DE VENTA (Parcial o Completa) ======
// =================================================================================
if (isset($_POST['action']) && $_POST['action'] === 'refund') {
    header('Content-Type: application/json');

    $saleId = $_POST['sale_id'] ?? null; 
    // Recibe un JSON de ítems a devolver {sale_item_id: quantity_to_refund, ...}
    $refundItemsJson = $_POST['refund_items'] ?? '[]';
    $refundItems = json_decode($refundItemsJson, true);

    if (!$saleId || !is_array($refundItems)) {
        echo json_encode(['error' => 'Datos de anulación inválidos.']);
        http_response_code(400);
        exit();
    }
    if (empty($refundItems)) {
        echo json_encode(['error' => 'No se seleccionó ningún producto para devolver.']);
        http_response_code(400);
        exit();
    }

    $pdo->beginTransaction();
    $totalRefundedAmount = 0;
    $itemsRefundedCount = 0;
    
    try {
        // Preparar consultas
        $stmt_stock_update = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        $stmt_check_offer = $pdo->prepare("SELECT id, is_offer FROM products WHERE id = ?");
        $stmt_offer_details = $pdo->prepare("SELECT product_id, quantity FROM offer_products WHERE offer_id = ?");

        // 1. Obtener la venta principal
        $stmt_sale = $pdo->prepare("SELECT total FROM sales WHERE id = ?");
        $stmt_sale->execute([$saleId]);
        $saleData = $stmt_sale->fetch(PDO::FETCH_ASSOC);

        if (!$saleData) {
            $pdo->rollBack();
            echo json_encode(['error' => 'Venta principal no encontrada.']);
            http_response_code(404);
            exit();
        }

        // 2. Procesar la devolución de cada ítem
        foreach ($refundItems as $saleItemId => $quantityToRefund) {
            $quantityToRefund = (float)$quantityToRefund;
            if ($quantityToRefund <= 0) continue;

            // Obtener el detalle del item vendido (sale_items.id)
            $stmt_item_detail = $pdo->prepare("SELECT product_id, price, quantity FROM sale_items WHERE id = ? AND sale_id = ?");
            $stmt_item_detail->execute([$saleItemId, $saleId]);
            $itemDetail = $stmt_item_detail->fetch(PDO::FETCH_ASSOC);

            if (!$itemDetail) continue;

            $product_id = $itemDetail['product_id'];
            $price_unit = (float)$itemDetail['price'];
            $original_quantity = (float)$itemDetail['quantity'];

            // Validar que no se intente devolver más de lo que se vendió
            if ($quantityToRefund > $original_quantity) {
                 // Puedes lanzar un error o ajustar la cantidad, aquí ajustaremos
                 $quantityToRefund = $original_quantity; 
            }

            // Calcular el monto a descontar del total de la venta
            $refundAmount = $price_unit * $quantityToRefund;
            $totalRefundedAmount += $refundAmount;

            // A. Reponer stock
            $stmt_stock_update->execute([$quantityToRefund, $product_id]);

            // B. Reponer stock de componentes si es un PACK/OFERTA
            $stmt_check_offer->execute([$product_id]);
            $product_row = $stmt_check_offer->fetch(PDO::FETCH_ASSOC);
            $is_pack = $product_row && $product_row['is_offer'] == 1;

            if ($is_pack) {
                // Reponer el stock de sus componentes individuales
                $stmt_offer_details->execute([$product_id]);
                $offer_components = $stmt_offer_details->fetchAll(PDO::FETCH_ASSOC);

                foreach ($offer_components as $component) {
                    $component_id = $component['product_id'];
                    $component_qty_per_pack = (float)$component['quantity'];
                    $total_qty_to_revert = $component_qty_per_pack * $quantityToRefund;

                    $stmt_stock_update->execute([$total_qty_to_revert, $component_id]);
                }
            }

            // C. Actualizar o Eliminar la fila de sale_items
            if (abs($quantityToRefund - $original_quantity) < 0.001) { // Usar tolerancia para floats
                // Si la cantidad a devolver es la cantidad total vendida, eliminamos la fila
                $stmt_delete_item = $pdo->prepare("DELETE FROM sale_items WHERE id = ?");
                $stmt_delete_item->execute([$saleItemId]);
            } else {
                // Si es parcial, actualizamos la cantidad restante
                $new_quantity = $original_quantity - $quantityToRefund;
                $stmt_update_item = $pdo->prepare("UPDATE sale_items SET quantity = ? WHERE id = ?");
                $stmt_update_item->execute([$new_quantity, $saleItemId]);
            }

            $itemsRefundedCount++;
        }

        // 3. Recalcular y actualizar el total de la tabla sales
        $newTotal = (float)$saleData['total'] - $totalRefundedAmount;

        // Si existe la columna 'status', actualizamos
        $stmt_update_sale = $pdo->prepare("UPDATE sales SET total = ?, status = 'partial_refund' WHERE id = ?");
        $stmt_update_sale->execute([max(0, $newTotal), $saleId]);
        
        // Verificamos si aún quedan ítems después de la devolución
        $stmt_remaining_items = $pdo->prepare("SELECT COUNT(*) FROM sale_items WHERE sale_id = ?");
        $stmt_remaining_items->execute([$saleId]);
        $remainingItems = $stmt_remaining_items->fetchColumn();

        if ($remainingItems == 0) {
            // Si no quedan ítems, marcamos como "Anulado Completo"
            $stmt_update_sale_status = $pdo->prepare("UPDATE sales SET status = 'complete_refund', total = 0 WHERE id = ?");
            $stmt_update_sale_status->execute([$saleId]);
        }


        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'message' => "Se devolvieron {$itemsRefundedCount} ítems. Total devuelto: " . number_format($totalRefundedAmount, 2) . ". El nuevo total de la venta es: " . number_format(max(0, $newTotal), 2),
            'new_total' => max(0, $newTotal)
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error en la anulación parcial: " . $e->getMessage());
        echo json_encode(['error' => 'Error al procesar la devolución. Mensaje: ' . $e->getMessage()]);
        http_response_code(500);
    }
    exit();
}


// ====== CARGA DE DATOS PARA LA PÁGINA (SE MANTIENE IGUAL) ======
if (!isset($_SESSION['user_username'])) {
    header("Location: login.php");
    exit();
}
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$stmt_categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
$all_categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);

// ** CORRECCIÓN: Filtrar productos con p.archived = 0 **
$stmt_products = $pdo->query("SELECT
    p.id,
    p.name,
    p.price,
    p.stock,
    p.image_url,
    p.category_id,
    p.barcode,
    p.is_offer,
    p.archived
FROM products p
WHERE p.archived = 0
ORDER BY p.created_at DESC");

$all_products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

$current_page = basename($_SERVER['PHP_SELF']);
$stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
$stmt->execute();
$system_version = $stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>POS - Listto! Profesional</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="icon" type="image/png" href="img/fav.png">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/pos.css">
<style>
    /* Asegura que el contenedor principal de la aplicación tenga altura completa */
    body {
        margin: 0;
        height: 100vh;
        display: flex;
        flex-direction: column;
    }
    #pos-container {
        flex: 1; /* Ocupa el espacio restante */
        display: flex;
        overflow: hidden; /* Evita scrollbars duplicados en el body */
    }
    #right-column {
        flex: 1; /* Ocupa el espacio restante */
        display: flex;
        flex-direction: column;
        min-width: 50%; /* Asegura que no se comprima demasiado */
        overflow: hidden; /* Importante para que #products-grid controle su propio scroll */
    }
    /* VITAL: Define una altura y scroll para la cuadrícula de productos */
    #products-grid {
        flex: 1; /* Ocupa toda la altura disponible en #right-column */
        overflow-y: scroll; /* Habilita el scroll vertical */
        padding: 15px; /* Ajusta el padding si es necesario */
        /* Asegúrate de que las demás propiedades (grid-template-columns, gap, etc.) estén en pos.css */
    }
    .refund-table th, .refund-table td {
        padding: 8px;
        text-align: left;
    }
    .refund-table thead {
        background-color: #f4f4f4;
    }
    .refund-qty-input {
        text-align: center;
        padding: 5px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
    }
</style>
</head>
<body>
<header class="main-header">
    <div class="header-left">
        <a href="launcher.php" class="launcher-icon" title="Ir al Lanzador de Aplicaciones">
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
        <a href="pos.php" class="active">Punto de Venta</a>
        </nav>
    <div class="header-right">
        <span class="app-version"><?php echo htmlspecialchars($system_version); ?></span>
        <a href="logout.php" class="btn-logout">Cerrar Sesi&oacute;n</a>
    </div>
</header>

<div id="pos-container">
    <div id="left-column">
        <div id="cart-section" class="glass-card">
            <div id="search-container">
                <input type="text" id="barcode" placeholder="Escanear o buscar producto..." autocomplete="off">
                <div id="suggestions"></div>
            </div>
            <h2>Pedido Actual</h2>
            <div class="cart-table-container">
                <table class="cart-table" id="cart-table">
                    <thead><tr><th>Producto</th><th>Cant.</th><th>Subtotal</th><th>Acción</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <div id="payment-section" class="glass-card">
            <div class="summary">
                <p><strong>Subtotal:</strong> <span id="subtotal">$0</span></p>
                <p><strong>IVA (19%):</strong> <span id="iva">$0</span></p>
                <p><strong>Total a Pagar:</strong> <span id="total">$0</span></p>
            </div>
            <div id="payment-method">
                <button type="button" data-method="efectivo" class="pay-btn active">💵 Efectivo</button>
                <button type="button" data-method="debito" class="pay-btn">💳 Débito</button>
                <button type="button" data-method="credito" class="pay-btn">💳 Crédito</button>
                <button type="button" data-method="transferencia" class="pay-btn">🔄 Transferencia</button>
            </div>
            <div class="action-buttons">
                <button id="finalize">✅ Finalizar</button>
                <button id="clear-cart">🗑️ Limpiar</button>
                <button id="refund-btn">↩️ Devolución</button>
            </div>
        </div>
    </div>
    
    <div id="right-column">
        <div id="products-grid"></div>
    </div>

    <div id="sidebar-right-toggle">
        <button id="toggle-sidebar" class="sidebar-right-btn">Categorías</button>
    </div>
</div>

<div id="categories-sidebar">
    <h2>Categorías</h2>
    <div id="categories-nav">
        </div>
</div>
<div id="sidebar-overlay" style="display: none;"></div>

<div id="payment-modal-overlay" class="modal-overlay" style="display:none;">
    <div id="payment-modal-content" class="modal-content glass-card">
        <h2>Pago en Efectivo</h2>
        <p>Total a pagar: <strong id="modal-total">$0</strong></p>
        <label for="paid-amount">Monto pagado:</label>
        <input type="number" id="paid-amount" placeholder="0" autofocus>
        <p>Vuelto: <span id="change-display">$0</span></p>
        <div class="modal-buttons">
            <button id="modal-finalize-btn" class="modal-finalize-btn">Finalizar Venta</button>
            <button id="modal-cancel-btn" class="modal-cancel-btn">Cancelar</button>
        </div>
    </div>
</div>

<div id="refund-modal-overlay" class="modal-overlay" style="display:none;">
    <div id="refund-modal-content" class="modal-content glass-card" style="max-width: 500px;">
        <h2>Anulación de Venta ↩️</h2>
        <label for="sale-id-input">Ticket Nro:</label>
        <input type="number" id="sale-id-input" placeholder="Ej: 5491" autofocus min="1">
        
        <div id="sale-details" style="margin-top: 15px; border: 1px solid #ddd; padding: 10px; border-radius: 5px; display: none;">
            <h3>Ticket: <span id="detail-sale-id"></span> (ID: <span id="detail-receipt-number"></span>)</h3>
            <p>Total Original: <strong id="detail-total-original">$0</strong></p>
            <p>Fecha: <span id="detail-date"></span></p>
            
            <p style="font-weight: bold; margin-top: 15px;">Seleccione Items a Devolver:</p>
            
            <table class="refund-table" style="width: 100%;">
                <thead>
                    <tr><th style="width: 50%;">Producto</th><th style="text-align: center;">Vendidos</th><th>Devolver</th><th>Precio Unit.</th></tr>
                </thead>
                <tbody id="detail-items-list">
                    </tbody>
            </table>

            <div style="margin-top: 15px; font-size: 1em; padding-top: 10px; border-top: 1px solid #eee;">
                Monto Total a Devolver (Estimado): <strong id="refund-total-display">$0</strong>
            </div>

            <strong style="color: red; margin-top: 10px; display: block;">NOTA: Los ítems devueltos se eliminarán de la venta (o se reducirá su cantidad) y se repondrá el stock.</strong>
        </div>

        <div class="modal-buttons" style="margin-top: 20px;">
            <button id="modal-refund-partial-btn" class="modal-finalize-btn" disabled data-sale-id="">Finalizar Devolución Parcial</button>
            <button id="modal-refund-cancel-btn" class="modal-cancel-btn">Cancelar</button>
        </div>
    </div>
</div>
<div id="message-box" style="display: none;"></div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const AJAX_URL = '<?php echo basename($_SERVER['PHP_SELF']); ?>';
    let cart = <?php echo json_encode($_SESSION['cart'] ?? []); ?>;
    const allProducts = <?php echo json_encode($all_products); ?>;
    const allCategories = <?php echo json_encode($all_categories); ?>;

    let selectedPaymentMethod = 'efectivo';
    const cartTbody = document.querySelector('#cart-table tbody');
    const productsGrid = document.getElementById('products-grid');
    const categoriesNav = document.getElementById('categories-nav');
    const barcodeInput = document.getElementById('barcode');
    const suggestions = document.getElementById('suggestions');
    const toggleSidebarBtn = document.getElementById('toggle-sidebar');
    const categoriesSidebar = document.getElementById('categories-sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    const paymentModalOverlay = document.getElementById('payment-modal-overlay');
    const modalTotalDisplay = document.getElementById('modal-total');
    const paidInput = document.getElementById('paid-amount');
    const changeDisplay = document.getElementById('change-display');
    const modalFinalizeBtn = document.getElementById('modal-finalize-btn');
    const modalCancelBtn = document.getElementById('modal-cancel-btn');
    const messageBox = document.getElementById('message-box');

    // Variables para Devolución
    const refundBtn = document.getElementById('refund-btn');
    const refundModalOverlay = document.getElementById('refund-modal-overlay');
    const saleIdInput = document.getElementById('sale-id-input');
    const saleDetailsDiv = document.getElementById('sale-details');
    const modalRefundPartialBtn = document.getElementById('modal-refund-partial-btn'); // Renombrado
    const modalRefundCancelBtn = document.getElementById('modal-refund-cancel-btn');
    const detailItemsList = document.getElementById('detail-items-list');


    const INITIAL_LOAD_LIMIT = 50;
    const LOAD_BATCH_SIZE = 50;
    let currentProductIndex = 0;
    let currentFilterProducts = allProducts;

    // 🟢 Mantiene el orden en que se agregan los productos al carrito
    let cartOrder = [];

    // === Funciones utilitarias ===
    const formatCLP = (value) => new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value);

    const parseFormattedCLP = (formattedText) => {
        const cleanedText = formattedText.replace('$', '').trim().replace(/\./g, '').replace(',', '.');
        return parseFloat(cleanedText) || 0;
    };

    const safeParseInt = (v, def = 0) => {
        const n = parseInt(v, 10);
        return isNaN(n) ? def : n;
    };
    
    // Nueva función para parsear Float con seguridad (para granel)
    const safeParseFloat = (v, def = 0) => {
        const n = parseFloat(v);
        return isNaN(n) ? def : n;
    };


    const debounce = (fn, wait) => {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    };

    function showMessage(message) {
        messageBox.innerText = message;
        messageBox.style.display = 'block';
        messageBox.style.opacity = '1';
        setTimeout(() => {
            messageBox.style.opacity = '0';
            setTimeout(() => {
                messageBox.style.display = 'none';
            }, 400);
        }, 3000);
    }

    // === Sincronizar orden del carrito ===
    function syncCartOrder() {
        for (const id in cart) {
            if (!cartOrder.includes(id)) cartOrder.push(id);
        }
        cartOrder = cartOrder.filter(id => cart[id]);
    }

    // === Comunicación con el servidor ===
    function updateCartOnServer(action, id, quantity = null) {
        const formData = new FormData();
        formData.append('action', action);
        if (id) formData.append('id', id);
        if (quantity !== null) formData.append('quantity', quantity);

        fetch(AJAX_URL, { method: 'POST', body: formData })
            .then(res => {
                if (res.status === 400) {
                    const productInList = allProducts.find(p => p.id == id);
                    if (productInList) {
                        // Simular el decremento para reflejar el stock actual en el grid
                        productInList.stock = safeParseFloat(productInList.stock) - 1; 
                        renderProductsGrid(currentFilterProducts);
                    }
                    showMessage('⚠️ Stock insuficiente');
                    return res.json();
                }
                return res.json();
            })
            .then(data => {
                cart = data;

                if (action === 'add' && id && !cartOrder.includes(id.toString())) {
                    cartOrder.push(id.toString());
                } else if (action === 'update' && quantity === 0) {
                    cartOrder = cartOrder.filter(x => x !== id.toString());
                } else if (action === 'clear') {
                    cartOrder = [];
                } else {
                    syncCartOrder();
                }

                renderCart();
            })
            .catch(err => console.error('Error al actualizar carrito:', err));
    }

    // === Agregar producto al carrito ===
    function addToCart(id) {
        const product = allProducts.find(p => p.id == id);
        if (!product) {
            showMessage("❌ Error: el producto no existe.");
            return;
        }

        const stock = safeParseFloat(product.stock);
        const itemInCart = cart[id];
        const currentQty = safeParseFloat(itemInCart?.quantity);

        // Sin stock o superando el límite
        if (stock <= 0 || currentQty >= stock) {
            showMessage(`⚠️ No hay más unidades disponibles de "${product.name}".`);
            return;
        }

        updateCartOnServer('add', id);
    }

    // === Finalizar venta ===
    function finalizeSale(paidAmount = null) {
        const formData = new FormData();
        formData.append('finalize', '1');
        formData.append('method', selectedPaymentMethod);
        if (paidAmount !== null) formData.append('paid_amount', paidAmount);

        fetch(AJAX_URL, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = `print_ticket.php?id=${data.sale_id}`;
                } else {
                    showMessage('❌ Error al finalizar venta: ' + (data.error || 'Ocurrió un problema desconocido.'));
                }
            })
            .catch(err => console.error('Error al finalizar venta:', err));
    }

    // === Lógica de Devolución (Anulación) (CORREGIDA para Parcial) ===
    function updateRefundSummary() {
        let currentRefundTotal = 0;
        let itemsSelected = false;

        document.querySelectorAll('.refund-qty-input').forEach(input => {
            let qty = safeParseFloat(input.value);
            const price = safeParseFloat(input.dataset.price);
            const max = safeParseFloat(input.dataset.max);

            // Ajustar el valor al máximo permitido
            if (qty > max) {
                qty = max;
                input.value = max;
            }
            // Asegurar que no sea negativo
            if (qty < 0) {
                qty = 0;
                input.value = 0;
            }

            if (qty > 0) {
                currentRefundTotal += qty * price;
                itemsSelected = true;
            }
        });

        document.getElementById('refund-total-display').innerText = formatCLP(currentRefundTotal);
        
        // Habilitar el botón solo si hay items seleccionados para devolver
        modalRefundPartialBtn.disabled = !itemsSelected;
    }


    function fetchSaleDetails(saleId) {
        saleDetailsDiv.style.display = 'none';
        modalRefundPartialBtn.disabled = true;

        if (!saleId || saleId <= 0) return;

        const formData = new FormData();
        formData.append('action', 'fetch_sale');
        formData.append('sale_id', saleId); 

        fetch(AJAX_URL, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    showMessage('❌ Error: ' + data.error);
                    saleDetailsDiv.style.display = 'none';
                    document.getElementById('refund-total-display').innerText = formatCLP(0);
                } else if (data.id) {
                    // Renderizar los detalles de la venta
                    document.getElementById('detail-sale-id').innerText = data.id;
                    document.getElementById('detail-receipt-number').innerText = data.receipt_number; 
                    document.getElementById('detail-total-original').innerText = formatCLP(data.total);
                    document.getElementById('detail-date').innerText = data.created_at;
                    
                    modalRefundPartialBtn.dataset.saleId = data.id;

                    detailItemsList.innerHTML = '';
                    
data.items.forEach(item => {
    // item.id es el ID de la fila en sale_items (si.id)
    const saleItemId = item.id; 
    const priceUnit = safeParseFloat(item.price);
    const maxQty = safeParseFloat(item.quantity);

    // ********** CORRECCIÓN DEFINITIVA PARA FORZAR ENTERO **********
    
    // Eliminamos la lógica de 'isGranel' y definimos valores enteros.
    const initialValue = '0'; // Siempre comienza en 0.
    const stepValue = '1';    // Siempre en pasos de 1 (entero).
    const minValue = '0';     // Mínimo 0 para permitir no devolver nada.
    
    // Truncamos la cantidad vendida a un entero para la visualización y el máximo permitido.
    const maxQtyInt = Math.floor(maxQty);
    const displayQtySold = maxQtyInt.toFixed(0); 

    // **************************************************************

    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>${item.name}</td>
        <td style="text-align: center;">${displayQtySold}</td>
        <td>
            <input type="number" 
                class="refund-qty-input" 
                data-item-id="${saleItemId}" 
                data-price="${priceUnit}"
                data-max="${maxQtyInt}"
                
                value="${initialValue}" 
                min="${minValue}" 
                max="${maxQtyInt}" 
                step="${stepValue}"
                
                style="width: 80px;">
        </td>
        <td>${formatCLP(priceUnit)}</td>
    `;
    detailItemsList.appendChild(tr);
});
                    
                    // Evento para actualizar el total a devolver
                    detailItemsList.querySelectorAll('.refund-qty-input').forEach(input => {
                        input.addEventListener('input', updateRefundSummary);
                    });
                    
                    // Actualizar el resumen después de cargar los inputs
                    updateRefundSummary();

                    saleDetailsDiv.style.display = 'block';
                } else {
                    showMessage('⚠️ Venta no encontrada.');
                    saleDetailsDiv.style.display = 'none';
                }
            })
            .catch(err => console.error('Error al buscar venta:', err));
    }

    // === Render del carrito ===
    function renderCart() {
        cartTbody.innerHTML = '';
        let total = 0;

        syncCartOrder();

        cartOrder.forEach(id => {
            const item = cart[id];

            // 🛑 Evita renderizar productos incompletos o vacíos
            if (!item || !item.name || typeof item.price === "undefined") return;

            const itemPrice = safeParseFloat(item.price);
            const itemQuantity = safeParseFloat(item.quantity); // Usar parseFloat para granel
            const subtotal = itemPrice * itemQuantity;
            total += subtotal;

            const tr = document.createElement('tr');
            tr.dataset.id = id;
            tr.innerHTML = `
                <td><strong>${item.name}</strong><br><small>${formatCLP(itemPrice)} c/u</small></td>
                <td><input type="number" min="0.001" step="any" value="${item.quantity}" data-id="${id}" class="qty"></td>
                <td><strong>${formatCLP(subtotal)}</strong></td>
                <td><button data-id="${id}" data-action="delete" class="cart-btn remove-all" title="Borrar">×</button></td>
            `;
            cartTbody.appendChild(tr);
        });

        const totalFinal = total;
        // Cálculos de IVA asumidos para un 19%
        const subtotalNeto = totalFinal / 1.19; 
        const iva = totalFinal - subtotalNeto;
        document.getElementById('subtotal').innerText = formatCLP(subtotalNeto);
        document.getElementById('iva').innerText = formatCLP(iva);
        document.getElementById('total').innerText = formatCLP(totalFinal);
    }

    // === Render de productos ===
    function createProductCard(p) {
        const currentProductData = allProducts.find(prod => prod.id === p.id);
        const stock = currentProductData ? safeParseFloat(currentProductData.stock) : safeParseFloat(p.stock);
        const isAgotado = stock <= 0;
        const card = document.createElement('div');
        card.className = `glass-card product-card ${isAgotado ? 'agotado' : ''}`;
        card.dataset.id = p.id;
        card.innerHTML = `
            <img src="${p.image_url || 'https://placehold.co/150x90?text=Sin+Imagen'}" alt="${p.name}" loading="lazy">
            <div>
                <h3>${p.name}</h3>
                <p>${formatCLP(p.price)}</p>
                <span class="stock-info ${isAgotado ? 'agotado' : 'disponible'}">
                    ${isAgotado ? 'AGOTADO' : `Stock: ${stock.toFixed(0)}`}
                </span>
            </div>`;
        return card;
    }

    function loadMoreProducts() {
        const totalProducts = currentFilterProducts.length;
        if (currentProductIndex >= totalProducts) {
            productsGrid.removeEventListener('scroll', lazyLoadProducts);
            return;
        }
        const nextIndex = Math.min(currentProductIndex + LOAD_BATCH_SIZE, totalProducts);
        const productsToLoad = currentFilterProducts.slice(currentProductIndex, nextIndex);
        productsToLoad.forEach(p => productsGrid.appendChild(createProductCard(p)));
        currentProductIndex = nextIndex;
    }

    const lazyLoadProducts = debounce(() => {
        const scrollBottom = productsGrid.scrollTop + productsGrid.clientHeight;
        const scrollHeight = productsGrid.scrollHeight;
        if (scrollHeight > productsGrid.clientHeight && scrollBottom >= scrollHeight - 150) {
            loadMoreProducts();
        }
    }, 200);

    function renderProductsGrid(products) {
        productsGrid.innerHTML = '';
        currentFilterProducts = products;
        currentProductIndex = 0;
        productsGrid.removeEventListener('scroll', lazyLoadProducts);
        loadMoreProducts();
        if (currentProductIndex < currentFilterProducts.length) {
            productsGrid.addEventListener('scroll', lazyLoadProducts);
        }
    }

    // === Categorías ===
    function renderCategories() {
        categoriesNav.innerHTML = '';
        const iconsMap = { "Alimentos": "🥫", "Bebidas": "🥤", "Default": "📦" };

        const filterProducts = (id, btn) => {
            document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const filtered = id ? allProducts.filter(p => p.category_id == id) : allProducts;
            renderProductsGrid(filtered);
            if (window.innerWidth >= 992) closeSidebar();
        };

        const createBtn = (name, id, icon) => {
            const btn = document.createElement('button');
            btn.className = 'category-btn';
            btn.innerHTML = `${icon} <span>${name}</span>`;
            btn.onclick = () => filterProducts(id, btn);
            return btn;
        };

        const allBtn = createBtn('Todos', null, '🍽️');
        allBtn.classList.add('active');
        categoriesNav.appendChild(allBtn);
        allCategories.forEach(cat => categoriesNav.appendChild(createBtn(cat.name, cat.id, iconsMap[cat.name] || iconsMap.Default)));
    }

    // === Búsqueda por nombre o código ===
    const handleSearch = () => {
        const q = barcodeInput.value.trim().toLowerCase();
        if (!q) { suggestions.innerHTML = ''; return; }
        const results = allProducts.filter(p =>
            p.name.toLowerCase().includes(q) ||
            (p.barcode && p.barcode.toString().toLowerCase().includes(q))
        ).slice(0, 10);

        suggestions.innerHTML = '';
        results.forEach(p => {
            const div = document.createElement('div');
            div.className = 'item';
            const stock = safeParseFloat(p.stock);
            const stockInfo = stock > 0 ? `Stock: ${stock.toFixed(0)}` : 'AGOTADO';
            div.innerHTML = `
                <img src="${p.image_url || 'https://placehold.co/60x60?text=No+Img'}" alt="${p.name}">
                <div class="info">
                    <strong>${p.name}</strong>
                    <small>${p.barcode || 'Sin código'}</small>
                    <small class="stock-suggestion">${stockInfo}</small>
                </div>
                <span class="price">${formatCLP(p.price)}</span>`;
            div.onclick = () => {
                addToCart(p.id);
                barcodeInput.value = '';
                suggestions.innerHTML = '';
                barcodeInput.focus();
            };
            suggestions.appendChild(div);
        });
    };

    // === Sidebar ===
    function openSidebar() { categoriesSidebar.classList.add('open'); sidebarOverlay.style.display = 'block'; }
    function closeSidebar() { categoriesSidebar.classList.remove('open'); sidebarOverlay.style.display = 'none'; }

    toggleSidebarBtn.addEventListener('click', () => {
        if (categoriesSidebar.classList.contains('open')) closeSidebar();
        else openSidebar();
    });
    sidebarOverlay.addEventListener('click', closeSidebar);

    // === Eventos ===
    barcodeInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const q = barcodeInput.value.trim().toLowerCase();
            if (!q) return;
            const product = allProducts.find(p => p.barcode && p.barcode.toString().toLowerCase() === q);
            if (product) {
                addToCart(product.id);
                barcodeInput.value = '';
                suggestions.innerHTML = '';
                barcodeInput.focus();
            } else {
                showMessage('Producto no encontrado para código: ' + q);
            }
        }
    });

    cartTbody.addEventListener('click', e => {
        if (e.target.closest('[data-action="delete"]')) {
            updateCartOnServer('update', e.target.closest('[data-id]').dataset.id, 0);
        }
    });

    cartTbody.addEventListener('change', e => {
        if (e.target.classList.contains('qty')) {
            // Usar parseFloat para manejar valores granel
            const qty = safeParseFloat(e.target.value); 
            const itemId = e.target.dataset.id;
            updateCartOnServer('update', itemId, qty);
        }
    });

    productsGrid.addEventListener('click', e => {
        const card = e.target.closest('.product-card');
        if (card && !card.classList.contains('agotado')) {
            addToCart(card.dataset.id);
        }
    });

    barcodeInput.addEventListener('input', debounce(handleSearch, 250));
    document.addEventListener('click', e => {
        if (!e.target.closest('#search-container')) suggestions.innerHTML = '';
    });

    document.querySelectorAll('.pay-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelector('.pay-btn.active').classList.remove('active');
            btn.classList.add('active');
            selectedPaymentMethod = btn.dataset.method;
        });
    });

    document.getElementById('clear-cart').addEventListener('click', () => {
        if (confirm('¿Estás seguro de limpiar el carrito? Esta acción no se puede deshacer.')) {
            updateCartOnServer('clear');
        }
    });

    document.getElementById('finalize').addEventListener('click', () => {
        if (Object.keys(cart).length === 0) {
            showMessage('El carrito está vacío. Agrega productos para finalizar la venta.');
            return;
        }

        if (selectedPaymentMethod === 'efectivo') {
            const totalText = document.getElementById('total').innerText;
            modalTotalDisplay.innerText = totalText;
            changeDisplay.innerText = formatCLP(0);
            paidInput.value = '';
            paymentModalOverlay.style.display = 'flex';
            paidInput.focus();
        } else {
            finalizeSale();
        }
    });

    paidInput.addEventListener('input', () => {
        const total = parseFormattedCLP(document.getElementById('total').innerText);
        const paid = safeParseFloat(paidInput.value);
        const change = paid - total;
        changeDisplay.innerText = formatCLP(change);
        if (change < 0) {
            changeDisplay.style.backgroundColor = '#f8d7da';
            changeDisplay.style.color = '#721c24';
            modalFinalizeBtn.disabled = true;
        } else {
            changeDisplay.style.backgroundColor = '#e2f0e3';
            changeDisplay.style.color = '#155724';
            modalFinalizeBtn.disabled = false;
        }
    });

    paidInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (!modalFinalizeBtn.disabled) {
                modalFinalizeBtn.click();
            }
        }
    });

    modalFinalizeBtn.addEventListener('click', () => {
        const total = parseFormattedCLP(document.getElementById('total').innerText);
        const paid = safeParseFloat(paidInput.value);
        if (paid < total) {
            showMessage('El monto pagado es insuficiente.');
            return;
        }
        paymentModalOverlay.style.display = 'none';
        finalizeSale(paid);
    });

    modalCancelBtn.addEventListener('click', () => {
        paymentModalOverlay.style.display = 'none';
    });


    // === Eventos de Devolución (ACTUALIZADOS) ===
    refundBtn.addEventListener('click', () => {
        saleIdInput.value = '';
        saleDetailsDiv.style.display = 'none';
        modalRefundPartialBtn.disabled = true;
        modalRefundPartialBtn.dataset.saleId = ''; 
        document.getElementById('refund-total-display').innerText = formatCLP(0); // Limpiar total
        refundModalOverlay.style.display = 'flex';
        saleIdInput.focus();
    });

    modalRefundCancelBtn.addEventListener('click', () => {
        refundModalOverlay.style.display = 'none';
    });

    saleIdInput.addEventListener('input', debounce(() => {
        fetchSaleDetails(safeParseInt(saleIdInput.value)); 
    }, 500));
    
// Este evento llama a la nueva lógica de devolución parcial
modalRefundPartialBtn.addEventListener('click', () => {
    const saleId = modalRefundPartialBtn.dataset.saleId;
    const enteredId = safeParseInt(saleIdInput.value); 
    
    const refundItems = {};
    let totalRefundAmount = 0;

    // 1. Recolectar items y cantidades a devolver
    let itemsSelected = false;
    document.querySelectorAll('.refund-qty-input').forEach(input => {
        // ✅ CORRECCIÓN 3: Aplicar Math.floor() para forzar el entero final
        const qty = Math.floor(safeParseFloat(input.value)); 
        const itemId = input.dataset.itemId;
        const price = safeParseFloat(input.dataset.price);

        if (qty > 0) {
            refundItems[itemId] = qty;
            totalRefundAmount += qty * price;
            itemsSelected = true;
        }
    });

    if (!itemsSelected) {
        showMessage('⚠️ Selecciona al menos un ítem para devolver con una cantidad mayor a 0.');
        return;
    }
    
    const confirmationMsg = `¿Confirmas la devolución de ítems por un monto total de ${formatCLP(totalRefundAmount)} de la Venta ID #${enteredId}?`;

    if (saleId && confirm(confirmationMsg)) {
        
        const formData = new FormData();
        formData.append('action', 'refund');
        formData.append('sale_id', saleId); 
        // 2. Enviar la lista de ítems a devolver como JSON (qty ya es entero)
        formData.append('refund_items', JSON.stringify(refundItems));

        fetch(AJAX_URL, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                refundModalOverlay.style.display = 'none';
                if (data.success) {
                    showMessage('✅ ' + data.message);
                    // Recargar la página para reflejar el stock repuesto y el nuevo total de venta
                    window.location.reload(); 
                } else {
                    showMessage('❌ ' + (data.error || 'Error desconocido al anular.'));
                }
            })
            .catch(err => {
                showMessage('❌ Error de red al anular venta.');
                console.error(err);
            });
    }
});


    // === Inicialización ===
    renderCart();
    renderCategories();
    renderProductsGrid(allProducts);
    barcodeInput.focus();
});
</script>

</body>
</html>