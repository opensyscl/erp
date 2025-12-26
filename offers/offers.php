<?php
// Reporte de Errores (Mantener comentado en producción)
/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/

// Incluye configuración de DB y Start Session
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

// 1.2 Asegurar que la conexión PDO esté lista (proviene de config.php)
if (!isset($pdo)) {
    // Si config.php falla, esto es el último recurso.
    die('Error fatal: Conexión PDO ($pdo) no disponible en config.php.');
}

$current_user_id = $_SESSION['user_id'];


// ----------------------------------------------------------------------
// --- 2. VALIDACIÓN DE ROL ESPECÍFICO (MÓDULO DE CAJA) ---
// ----------------------------------------------------------------------

$user_can_access = false;
// RUTA CONFIGURADA: Módulo de Packs y Promos
$module_path = '/erp/offers/'; 

try {
    // Obtenemos el rol del usuario logueado
    $stmt_role = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_role->execute([$current_user_id]);
    $user_role = $stmt_role->fetchColumn();

    // LÓGICA DE ACCESO: Roles permitidos para el módulo
    if (in_array($user_role, ['POS1', 'POS2', 'Admin', 'Manager'])) { 
        $user_can_access = true;
    }

} catch (PDOException $e) {
    error_log("Error de BD al verificar rol del usuario: " . $e->getMessage());
    header('Location: ../not_authorized.php');
    exit();
}


// ----------------------------------------------------------------------
// --- 3. VALIDACIÓN GLOBAL DE MÓDULO (GUARDIÁN) ---
// ----------------------------------------------------------------------

if ($user_can_access) {
    require '../includes/module_check.php';

    if (!is_module_enabled($module_path)) {
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

// --- ACCESO CONCEDIDO: COMIENZA EL CÓDIGO ESPECÍFICO DEL MÓDULO DE OFERTAS ---

$success_message = null;
$error_message = null;
$system_version = '2.3';

// ✅ CAPTURA DE MENSAJES DESPUÉS DE LA REDIRECCIÓN PRG (Post/Redirect/Get)
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars(urldecode($_GET['success']));
}
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars(urldecode($_GET['error']));
}

// --- LÓGICA DE CÁLCULO DE KPIS DE OFERTAS ---

$offer_kpis = [
    'available_offers' => 0,
    'total_stock' => 0,
    'total_retail_value' => 0,
];

try {
    $stmt_kpis = $pdo->prepare("
     SELECT
       COUNT(p.id) AS available_offers,
       SUM(p.stock) AS total_stock,
       SUM(p.price * p.stock) AS total_retail_value
     FROM products p
     WHERE p.is_offer = 1
    ");
    $stmt_kpis->execute();
    $results = $stmt_kpis->fetch(PDO::FETCH_ASSOC);

    if ($results) {
        $offer_kpis['available_offers'] = (int)$results['available_offers'];
        $offer_kpis['total_stock'] = (int)$results['total_stock'];
        // Mantener como float. El redondeo para visualización se hace en el HTML.
        $offer_kpis['total_retail_value'] = (float)$results['total_retail_value']; 
    }
} catch (Exception $e) {
    error_log("Error al calcular KPIs de ofertas: " . $e->getMessage());
}


// --- LÓGICA DE MANEJO DE PETICIONES (AJAX Y POST) ---

// 1. LÓGICA DE BÚSQUEDA AJAX (Productos individuales)
if (isset($_GET['action']) && $_GET['action'] === 'search_product' && isset($_GET['query'])) {
    header('Content-Type: application/json');
    $query_input = filter_input(INPUT_GET, 'query', FILTER_SANITIZE_STRING); // ✅ SANITIZACIÓN
    $query = '%' . $query_input . '%';

    $stmt = $pdo->prepare("
     SELECT id, name, barcode, price AS sale_price, stock, image_url, cost_price
     FROM products
     WHERE (name LIKE ? OR barcode LIKE ?) AND (is_offer IS NULL OR is_offer = 0)
     LIMIT 10
    ");
    $stmt->execute([$query, $query]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'products' => $products]);
    exit();
}

// 2. LÓGICA DE CARGAR DATOS DE OFERTA PARA EDICIÓN (AJAX)
if (isset($_GET['action']) && $_GET['action'] === 'load_offer_data' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $offerId = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT); // ✅ SANITIZACIÓN
    $offerId = (int)$offerId;

    if ($offerId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de oferta no válido.']);
        exit();
    }

    $pdo->beginTransaction();
    try {
        // A. Obtener detalles del Pack (Tabla products)
        $stmt_pack = $pdo->prepare("
             SELECT name, barcode, price AS final_price, stock
             FROM products
             WHERE id = ? AND is_offer = 1
         ");
        $stmt_pack->execute([$offerId]);
        $pack_data = $stmt_pack->fetch(PDO::FETCH_ASSOC);

        if (!$pack_data) {
            echo json_encode(['success' => false, 'message' => 'Oferta no encontrada o ID incorrecto.']);
            exit();
        }
        
        // B. Obtener los ítems que componen el Pack (Tabla offer_products)
        $stmt_items = $pdo->prepare("
             SELECT 
                 op.product_id AS id, 
                 op.quantity, 
                 op.original_price AS sale_price, 
                 op.offer_price AS final_price_item,
                 p.name, 
                 p.stock AS product_stock,
                 p.cost_price 
             FROM offer_products op
             JOIN products p ON op.product_id = p.id
             WHERE op.offer_id = ?
         ");
        $stmt_items->execute([$offerId]);
        $pack_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        // C. Calcular el descuento porcentual de cada ítem (lógica inversa)
        $items_with_discount = array_map(function($item) {
            $item_subtotal_original = $item['sale_price'] * $item['quantity'];
            $item_subtotal_final = $item['final_price_item'] * $item['quantity'];
            
            $discount = 0;
            if ($item_subtotal_original > 0) {
                $discount_amount = $item_subtotal_original - $item_subtotal_final;
                $discount = ($discount_amount / $item_subtotal_original) * 100;
            }
            
            $item['discount_percent'] = round($discount, 1);
            $item['subtotal'] = $item_subtotal_final;
            $item['stock'] = $item['product_stock']; 
            unset($item['product_stock']);
            return $item;
        }, $pack_items);

        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'pack_data' => $pack_data,
            'pack_items' => $items_with_discount,
            'offer_id' => $offerId
        ]);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error al cargar datos de oferta: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error en el servidor al cargar la oferta.']);
        exit();
    }
}


// 3. LÓGICA DE GUARDAR/ACTUALIZAR OFERTA (POST) - Implementación PRG
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_offer') {
    // ✅ SANITIZACIÓN DE POST
    $offerName = filter_input(INPUT_POST, 'offer_name', FILTER_SANITIZE_STRING);
    $offerBarcode = filter_input(INPUT_POST, 'offer_barcode', FILTER_SANITIZE_STRING);
    $finalSalePrice = filter_input(INPUT_POST, 'final_sale_price', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $stockPack = filter_input(INPUT_POST, 'stock_pack', FILTER_SANITIZE_NUMBER_INT);
    $offerIdToUpdate = filter_input(INPUT_POST, 'offer_id', FILTER_SANITIZE_NUMBER_INT);

    $offerItemsJson = $_POST['offer_items_json'] ?? '[]'; // Se confía en la generación JS

    $offerName = trim($offerName ?? '');
    $offerBarcode = trim($offerBarcode ?? '');
    $finalSalePrice = (float)($finalSalePrice ?? 0);
    $stockPack = (int)($stockPack ?? 0);
    $offerIdToUpdate = (int)($offerIdToUpdate ?? 0);

    $offerItems = json_decode($offerItemsJson, true);
    
    if (empty($offerBarcode)) {
        $offerBarcode = 'PACK' . time();
    }

    if (empty($offerName) || $finalSalePrice <= 0 || empty($offerItems)) {
        $errorMsgEncoded = urlencode("❌ Error: Faltan datos obligatorios, la oferta está vacía, o el precio final no es válido.");
        header("Location: offers.php?error={$errorMsgEncoded}");
        exit();
    } else {
        $pdo->beginTransaction();
        try {
            
            $newProductId = $offerIdToUpdate;
            
            // 1. Insertar o Actualizar el "producto" (el Pack) en la tabla 'products'
            if ($offerIdToUpdate > 0) {
                // ✅ MODO EDICIÓN: ACTUALIZAR PRODUCTO
                $stmt_product = $pdo->prepare("
                    UPDATE products 
                    SET name = ?, barcode = ?, price = ?, stock = ?, is_offer = 1, updated_at = NOW()
                    WHERE id = ? AND is_offer = 1
                ");
                $stmt_product->execute([$offerName, $offerBarcode, $finalSalePrice, $stockPack, $offerIdToUpdate]);
                
                // Eliminar ítems antiguos de offer_products antes de reinsertar
                $stmt_delete_items = $pdo->prepare("DELETE FROM offer_products WHERE offer_id = ?");
                $stmt_delete_items->execute([$offerIdToUpdate]);
                
            } else {
                // ✅ MODO CREACIÓN: INSERTAR NUEVO PRODUCTO
                // category_id=999 y cost_price=0 serán actualizados más adelante
                $stmt_product = $pdo->prepare("
                  INSERT INTO products (name, barcode, price, stock, category_id, cost_price, is_offer, created_at, updated_at)
                  VALUES (?, ?, ?, ?, 999, 0, 1, NOW(), NOW())
                ");
                $stmt_product->execute([$offerName, $offerBarcode, $finalSalePrice, $stockPack]);
                $newProductId = $pdo->lastInsertId();
            }

            // 2. Calcular y actualizar el cost_price del PACK en 'products'
            $totalCostPack = 0;
            foreach ($offerItems as $item) {
                // Se asume que 'cost_price' y 'quantity' vienen en el JSON correctamente.
                $totalCostPack += ($item['cost_price'] ?? 0) * ($item['quantity'] ?? 0);
            }
            
            $stmt_update_cost = $pdo->prepare("
              UPDATE products SET cost_price = ? WHERE id = ?
            ");
            $stmt_update_cost->execute([$totalCostPack, $newProductId]);

            // 3. Insertar los ítems del Pack en 'offer_products'
            $stmt_item = $pdo->prepare("
              INSERT INTO offer_products (offer_id, product_id, quantity, original_price, offer_price, created_at)
              VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            foreach ($offerItems as $item) {
                $unitOriginalPrice = $item['sale_price'];
                $unitOfferPrice = $item['final_price_item'];

                $stmt_item->execute([
                    $newProductId, 
                    $item['id'],  
                    $item['quantity'], 
                    $unitOriginalPrice,
                    $unitOfferPrice 
                ]);
            }
            
            $pdo->commit();
            
            $actionMessage = $offerIdToUpdate > 0 ? "actualizada" : "creada";
            $successMsgEncoded = urlencode("🎉 ¡Oferta **'{$offerName}'** {$actionMessage} exitosamente! Su ID de producto/pack es: **{$newProductId}**");
            header("Location: offers.php?success={$successMsgEncoded}");
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMsgEncoded = urlencode("❌ Error al guardar/actualizar la oferta. (Detalle: " . $e->getMessage() . ")");
            header("Location: offers.php?error={$errorMsgEncoded}");
            exit();
        }
    }
}


// 4. LÓGICA DE ELIMINAR OFERTA (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_offer') {
    $offerIdToDelete = filter_input(INPUT_POST, 'offer_id', FILTER_SANITIZE_NUMBER_INT); // ✅ SANITIZACIÓN
    $offerIdToDelete = (int)($offerIdToDelete ?? 0);

    if ($offerIdToDelete <= 0) {
        $errorMsgEncoded = urlencode("❌ Error: ID de oferta no válido para eliminar.");
        header("Location: offers.php?error={$errorMsgEncoded}");
        exit();
    }

    $pdo->beginTransaction();
    try {
        // 1. Eliminar los ítems de la tabla 'offer_products'
        $stmt_items = $pdo->prepare("DELETE FROM offer_products WHERE offer_id = ?");
        $stmt_items->execute([$offerIdToDelete]);
        
        // 2. Eliminar el producto/pack de la tabla 'products'
        $stmt_product = $pdo->prepare("DELETE FROM products WHERE id = ? AND is_offer = 1");
        $stmt_product->execute([$offerIdToDelete]);

        if ($stmt_product->rowCount() === 0) {
             throw new Exception("No se encontró la oferta con ID {$offerIdToDelete} o no es un pack.");
        }
        
        $pdo->commit();
        
        $successMsgEncoded = urlencode("🗑️ ¡Oferta (ID: {$offerIdToDelete}) eliminada exitosamente!");
        header("Location: offers.php?success={$successMsgEncoded}");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsgEncoded = urlencode("❌ Error al eliminar la oferta. (Detalle: " . $e->getMessage() . ")");
        header("Location: offers.php?error={$errorMsgEncoded}");
        exit();
    }
}


// 5. LÓGICA DE OBTENER LISTADO DE OFERTAS
$offers_list = [];
try {
    $stmt = $pdo->prepare("
     SELECT
       p.id,
       p.name,
       p.barcode,
       p.price AS final_price,
       p.stock,
       p.cost_price AS total_cost
     FROM products p
     WHERE p.is_offer = 1
     ORDER BY p.created_at DESC
     LIMIT 20
    ");
    $stmt->execute();
    $offers_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular el margen en PHP para el listado
    foreach ($offers_list as &$offer) {
        $offer['total_cost'] = (float)$offer['total_cost'];
        $offer['final_price'] = (float)$offer['final_price'];
        $margin_amount = $offer['final_price'] - $offer['total_cost'];

        if ($offer['final_price'] > 0) {
            $margin_percent = ($margin_amount / $offer['final_price']) * 100;
            $offer['margin_percent'] = round($margin_percent, 1);
        } else {
            $offer['margin_percent'] = 0;
        }
    }

    // ✅ Desenganchar la referencia para evitar la duplicación de la última fila.
    unset($offer);

} catch (Exception $e) {
    error_log("Error al cargar listado de ofertas: " . $e->getMessage());
}

// Obtener la versión del sistema para el encabezado
try {
    $stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
    $stmt->execute();
    $system_version = $stmt->fetchColumn() ?? '2.3';
} catch (Exception $e) {
    // No hacer nada, usar el valor por defecto
}

// Variables para el encabezado
$current_page = 'offers.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creación de Ofertas - Listto! ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" type="image/png" href="/erp/img/fav.png">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/offers.css">
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
      <a href="offers.php" class="active">Ofertas y Promociones</a>
     </nav>
     <div class="header-right">
      <span class="app-version"><?php echo htmlspecialchars($system_version); ?></span>
      <a href="../logout.php" class="btn-logout">Cerrar Sesión</a>
     </div>
    </header>

    <main class="container">
     <?php if (isset($success_message)): ?>
     <div class="alert success-alert"><?= $success_message ?></div>
     <?php endif; ?>
     <?php if (isset($error_message)): ?>
     <div class="alert error-alert"><?= $error_message ?></div>
     <?php endif; ?>

     <div class="kpi-grid">
     
      <div class="kpi-card">
       <h3>Ofertas Disponibles 🏷️</h3>
       <div class="value"><?= number_format($offer_kpis['available_offers'], 0, ',', '.') ?></div>
      </div>

      <div class="kpi-card">
       <h3>Stock Total de Packs 📦</h3>
       <div class="value"><?= number_format($offer_kpis['total_stock'], 0, ',', '.') ?></div>
      </div>
      
      <div class="kpi-card">
       <h3>Valor Total de Stock (Retail) 📈</h3>
       <div class="value projection">
        $<?= number_format($offer_kpis['total_retail_value'], 0, ',', '.') ?>
       </div>
      </div>
     </div>
     <div class="offers-grid">

      <div class="content-card search-card">
       <h2>1. Seleccionar Productos 🔎</h2>
       <div class="search-container">
        <input type="text" id="product-search" placeholder="Escribe para buscar productos..." class="form-control">
        <div id="search-results" class="search-results-dropdown">
        </div>
       </div>
      </div>

      <div class="content-card config-card">
       <h2 id="config-card-title">2. Configuración de la Oferta 🎁</h2>

       <form method="POST" id="offer-form">
            <input type="hidden" name="action" id="action-hidden" value="save_offer">
        <input type="hidden" name="offer_id" id="offer-id-hidden" value="0">
        <input type="hidden" name="offer_items_json" id="offer-items-json">

        <input type="hidden" name="offer_barcode" id="offer-barcode" value="">
        
        <div class="form-group-row">
         <div class="form-group">
          <label for="offer-name">Nombre de la Oferta:</label>
          <input type="text" name="offer_name" id="offer-name" required class="form-control" placeholder="Ej: Mega Pack Ahorro Invierno">
         </div>
         <div class="form-group">
          <label for="stock-pack">Stock del Pack (unidades):</label>
          <input type="number" name="stock_pack" id="stock-pack" required class="form-control" value="0" min="0">
         </div>
        </div>
        <h3 class="product-list-header">
         Productos del Pack:
         <span id="total-products-count" class="text-secondary">Total de Productos: 0</span>
        </h3>
        
        <div class="table-container">
         <table class="offers-table products-table">
         <thead>
         <tr>
          <th>Producto</th>
          <th>Cant. / Stock</th>
          <th class="numeric-cell">P. Original</th>
          <th>Dscto. (%)</th>
          <th class="numeric-cell">P. Oferta</th>
          <th class="numeric-cell">Margen (%)</th>
          <th>Acción</th>
         </tr>
         </thead>
          <tbody id="offer-items-body">
           <tr>
            <td colspan="7" style="text-align: center; padding: 1rem; color: #6b7280;">
             Usa el buscador para agregar productos.
            </td>
           </tr>
          </tbody>
         </table>
        </div>
        
        <div class="summary-box">
         <div class="summary-line">
          <span>Venta Bruta:</span>
          <span id="sum-original-price" class="value">$0</span>
         </div>
         <div class="summary-line total-cost">
          <span>Costo Total Oferta:</span>
          <span id="total-cost-pack" class="value">$0</span>
         </div>
         <div class="summary-line total-discount">
          <span>Descuento Total:</span>
          <span id="total-discount-amount" class="value danger-color">$0</span>
         </div>
        </div>

        <h3 class="kpi-header">KPIs de Rentabilidad del Pack:</h3>
        <div class="kpi-grid offer-kpi-grid">

         <div class="kpi-card">
          <h3>Ganancia Bruta 💰</h3>
          <div id="pack-margin-amount" class="value">$0</div>
         </div>

         <div class="kpi-card">
          <h3>Beneficio del Pack 📈</h3>
          <div id="pack-margin-percent" class="value">0%</div>
         </div>

         <div class="kpi-card">
          <h3>Ahorro / Cliente 🤑</h3>
          <div id="customer-savings-percent" class="value">0%</div>
          </div>

        </div>
        <div class="final-price-group">
         <label for="final-sale-price-display-wrapper">Precio Final de Venta del Pack 💰:</label>
         <div id="final-sale-price-display-wrapper" class="price-display-wrapper">
          <span class="currency-sign">$</span>
          <input type="text" id="final-sale-price-display" value="0" class="price-input-display" placeholder="0">
         </div>
         <input type="hidden" name="final_sale_price" id="final-sale-price-hidden">
        </div>
        
        <button type="submit" id="save-offer-btn" class="btn-primary" disabled>
          Guardar Oferta
        </button>
        <button type="button" id="clear-form-btn" class="btn-thrid" onclick="clearOfferForm()">
          Limpiar / Cancelar
        </button>
       </form>
      </div>
     </div>
    
     <div class="offers-list-container">
       <div class="content-card"> <h2>3. Ofertas Existentes (Packs) 📋</h2>
         <div class="table-container offers-table-wrapper">
           <table class="offers-table offers-list-table">
             <thead>
               <tr>
                 <th>ID</th>
                 <th>Nombre de la Oferta</th>
                 <th>Código de Barras</th>
                 <th class="numeric-cell">Stock</th>
                 <th class="numeric-cell">Costo Total</th>
                 <th class="numeric-cell">Precio Final</th>
                 <th class="numeric-cell">Margen (%)</th>
                 <th>Acciones</th>
               </tr>
             </thead>
             <tbody>
               <?php if (empty($offers_list)): ?>
                 <tr>
                   <td colspan="8" style="text-align: center; padding: 1rem; color: var(--text-secondary);">
                     No hay ofertas creadas aún.
                   </td>
                 </tr>
               <?php else: ?>
                 <?php foreach ($offers_list as $offer): ?>
                   <?php
                     $marginClass = 'info-color';
                     if ($offer['margin_percent'] < 10) $marginClass = 'danger-color';
                     if ($offer['margin_percent'] > 30) $marginClass = 'success-color';
                   ?>
                   <tr>
                     <td><?= htmlspecialchars($offer['id']) ?></td>
                     <td><?= htmlspecialchars($offer['name']) ?></td>
                     <td><?= htmlspecialchars($offer['barcode']) ?></td>
                     <td class="numeric-cell"><?= htmlspecialchars($offer['stock']) ?></td>
                     <td class="numeric-cell">$<?= number_format($offer['total_cost'], 0, ',', '.') ?></td>
                     <td class="numeric-cell">$<?= number_format($offer['final_price'], 0, ',', '.') ?></td>
                     <td class="numeric-cell"><span class="<?= $marginClass ?>"><?= htmlspecialchars($offer['margin_percent']) ?>%</span></td>
                     <td>
                       <button class="btn-small btn-secondary btn-edit-offer" data-id="<?= htmlspecialchars($offer['id']) ?>">Editar</button>
                       <button class="btn-small btn-danger btn-delete-offer" data-id="<?= htmlspecialchars($offer['id']) ?>">Eliminar</button>
                     </td>
                   </tr>
                 <?php endforeach; ?>
               <?php endif; ?>
             </tbody>
           </table>
         </div>
       </div> </div> </main>


<script>
  let offerItems = [];
  // Definiciones de Elementos del DOM
  const OFFER_ITEMS_BODY = document.getElementById('offer-items-body');
  const FINAL_SALE_PRICE_DISPLAY = document.getElementById('final-sale-price-display');
  const FINAL_SALE_PRICE_HIDDEN = document.getElementById('final-sale-price-hidden');
  const SAVE_OFFER_BTN = document.getElementById('save-offer-btn');
  const OFFER_ITEMS_JSON = document.getElementById('offer-items-json');
  const TOTAL_PRODUCTS_COUNT = document.getElementById('total-products-count');
  const SUM_ORIGINAL_PRICE = document.getElementById('sum-original-price');
  const TOTAL_DISCOUNT_AMOUNT = document.getElementById('total-discount-amount');
  const TOTAL_COST_PACK = document.getElementById('total-cost-pack');
    const OFFER_ID_HIDDEN = document.getElementById('offer-id-hidden');
    const CONFIG_CARD_TITLE = document.getElementById('config-card-title');
    const OFFER_NAME_INPUT = document.getElementById('offer-name');
    const STOCK_PACK_INPUT = document.getElementById('stock-pack');
    const OFFER_BARCODE_INPUT = document.getElementById('offer-barcode');
    const SEARCH_INPUT = document.getElementById('product-search');
    const SEARCH_RESULTS = document.getElementById('search-results');

  // Formateador de moneda (ajusta según tu moneda, por defecto CLP)
  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', minimumFractionDigits: 0 }).format(amount);
  };

  /**
  * Realiza una búsqueda AJAX de productos y muestra los resultados.
  */
  const searchProducts = async (query) => {
    if (query.length < 3) {
      SEARCH_RESULTS.innerHTML = '';
      return;
    }
   
    const response = await fetch(`offers.php?action=search_product&query=${encodeURIComponent(query)}`);
    const data = await response.json();
   
    SEARCH_RESULTS.innerHTML = '';
   
    const PLACEHOLDER_IMG_URL = 'https://placehold.co/40x40/f0f0f0/999999?text=IMG';

    if (data.success && data.products.length > 0) {
      data.products.forEach(product => {
        const isAdded = offerItems.some(item => item.id === product.id);
        const item = document.createElement('div');
        item.className = 'search-result-item' + (isAdded ? ' disabled' : '');
       
        const imageUrl = product.image_url && product.image_url.trim() !== '' ? product.image_url : PLACEHOLDER_IMG_URL;

        const itemHTML = `
          <div class="product-visual">
            <img src="${imageUrl}"
              alt="${product.name}"
              onerror="this.onerror=null; this.src='${PLACEHOLDER_IMG_URL}';">
          </div>
          <div class="product-text-details">
            <strong>${product.name}</strong>
            <span class="stock-info">(Stock: ${product.stock})</span>
          </div>
          <div class="actions-wrapper">
            <span class="price-info">${formatCurrency(product.sale_price)}</span>
            <button type="button" class="btn-add-product" ${isAdded ? 'disabled' : ''}
              onclick="addProductToOffer(${product.id},
                '${product.name.replace(/'/g, "\\'")}',
                ${product.sale_price},
                ${product.stock},
                ${product.cost_price})"
            >Añadir</button>
          </div>
        `;

        item.innerHTML = itemHTML;
        SEARCH_RESULTS.appendChild(item);
      });
    } else {
      SEARCH_RESULTS.innerHTML = '<div class="search-result-item search-info">No se encontraron productos.</div>';
    }
  };

  /**
  * Añade un producto al array de ítems de la oferta.
  */
  window.addProductToOffer = (id, name, sale_price, stock, cost_price = 0) => {
    if (offerItems.some(item => item.id === id)) {
      return;
    }

    offerItems.push({
      id: id,
      name: name,
      sale_price: sale_price,
      quantity: 1,
      discount_percent: 0,
      final_price_item: sale_price,
      subtotal: sale_price * 1,
      cost_price: cost_price,
      stock: stock
    });

    SEARCH_INPUT.value = '';
    SEARCH_RESULTS.innerHTML = '';

    updateOfferTable();
  };

  /**
  * Recalcula el precio final, subtotal, y margen de un ítem.
  */
  const calculateItemPrices = (item) => {
    const basePrice = item.sale_price * item.quantity;
    const discountAmount = basePrice * (item.discount_percent / 100);
    const finalPriceSubtotal = basePrice - discountAmount;
   
    const finalPriceItem = finalPriceSubtotal / item.quantity;

    item.final_price_item = parseFloat(finalPriceItem.toFixed(0));
    item.subtotal = parseFloat(finalPriceSubtotal.toFixed(0));
   
    const margin = item.final_price_item > 0
      ? ((item.final_price_item - item.cost_price) / item.final_price_item) * 100
      : 0;
    item.margin_percent = parseFloat(margin.toFixed(0));
  };

  /**
  * Actualiza solo los KPIs y el Resumen del Pack.
  */
  const updateOfferSummary = () => {
    let totalPackPriceWithDiscount = 0;
    let sumOriginalPrice = 0;
    let totalCostPack = 0;

    offerItems.forEach((item) => {
      calculateItemPrices(item);
      totalPackPriceWithDiscount += item.subtotal;
      sumOriginalPrice += (item.sale_price * item.quantity);
      totalCostPack += (item.cost_price * item.quantity);
    });

    const roundedTotalSuggested = Math.round(totalPackPriceWithDiscount);
    const roundedTotalCost = Math.round(totalCostPack);
    const totalOriginalPriceRounded = Math.round(sumOriginalPrice);
   
    // --- LÓGICA DE PRECIO FINAL DEL PACK ---

    let priceToCalculateKPI = parseFloat(FINAL_SALE_PRICE_HIDDEN.value) || 0;

    // Si el campo visible no está activo, actualizamos con el sugerido
    if (document.activeElement !== FINAL_SALE_PRICE_DISPLAY) {
      // Si el precio es 0 o coincide con el sugerido, usamos el sugerido
      if (priceToCalculateKPI === 0 || priceToCalculateKPI === roundedTotalSuggested) {
        priceToCalculateKPI = roundedTotalSuggested;
        FINAL_SALE_PRICE_HIDDEN.value = priceToCalculateKPI;
      }
      // Actualización del input visible con formato CL (solo el número)
      FINAL_SALE_PRICE_DISPLAY.value = priceToCalculateKPI.toLocaleString('es-CL');
    }

    // --- CÁLCULO DE KPIS ---
   
    const packMarginAmount = priceToCalculateKPI - roundedTotalCost;

    const packMarginPercent = priceToCalculateKPI > 0
      ? ((packMarginAmount / priceToCalculateKPI) * 100).toFixed(1)
      : 0;
     
    const totalDiscountAmount = totalOriginalPriceRounded - priceToCalculateKPI;
    const customerSavingsPercent = totalOriginalPriceRounded > 0
      ? ((totalDiscountAmount / totalOriginalPriceRounded) * 100).toFixed(1)
      : 0;


    // --- ACTUALIZACIÓN DE ELEMENTOS DEL RESUMEN ---
    SUM_ORIGINAL_PRICE.textContent = formatCurrency(totalOriginalPriceRounded);
    TOTAL_COST_PACK.textContent = formatCurrency(roundedTotalCost);
    TOTAL_DISCOUNT_AMOUNT.textContent = formatCurrency(Math.round(totalDiscountAmount));
   
    // ACTUALIZACIÓN DE NUEVOS KPIS
    document.getElementById('pack-margin-amount').textContent = formatCurrency(packMarginAmount);
   
    // Aplicar color al margen del pack según su valor
    const packMarginElement = document.getElementById('pack-margin-percent');
    packMarginElement.textContent = `${packMarginPercent}%`;
    packMarginElement.className = 'kpi-value value'; // Reset class
    if (packMarginPercent < 10) packMarginElement.classList.add('danger-color');
    else if (packMarginPercent > 30) packMarginElement.classList.add('success-color');
    else packMarginElement.classList.add('info-color');


    document.getElementById('customer-savings-percent').textContent = `${customerSavingsPercent}%`;


    // Habilitar/Deshabilitar el botón de guardar
    const isReady = offerItems.length > 0 &&
                document.getElementById('offer-name').value.trim() !== '' &&
                priceToCalculateKPI > 0;
   
    SAVE_OFFER_BTN.disabled = !isReady;
   
    // Actualizar el JSON para el envío al servidor
    OFFER_ITEMS_JSON.value = JSON.stringify(offerItems);
  };


  /**
  * Dibuja la tabla de ítems de la oferta y llama a la actualización de KPIs.
  */
  const updateOfferTable = () => {
    OFFER_ITEMS_BODY.innerHTML = '';
    TOTAL_PRODUCTS_COUNT.textContent = `Total de Productos: ${offerItems.length}`;

    if (offerItems.length === 0) {
      OFFER_ITEMS_BODY.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 1rem; color: var(--text-secondary);">
        Usa el buscador para agregar productos.
      </td></tr>`;
      updateOfferSummary();
      return;
    }

    offerItems.forEach((item, index) => {
      calculateItemPrices(item);
     
      let marginClass = 'info-color';
      if (item.margin_percent < 10) marginClass = 'danger-color';
      if (item.margin_percent > 30) marginClass = 'success-color';


      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${item.name}</td>
        <td>
          <input type="number" min="1" max="${item.stock}" value="${item.quantity}" data-index="${index}" data-field="quantity" class="input-small form-control input-quantity">
          <span class="stock-display">/ ${item.stock}</span>
        </td>
        <td class="numeric-cell">${formatCurrency(item.sale_price * item.quantity)}</td>
        <td>
          <input type="number" min="0" max="100" step="1" value="${item.discount_percent}" data-index="${index}" data-field="discount_percent" class="input-small form-control input-discount">
        </td>
        <td class="numeric-cell">${formatCurrency(item.final_price_item)}</td>
        <td class="numeric-cell"><span class="${marginClass}">${item.margin_percent}%</span></td>
        <td><button type="button" class="btn-remove" data-index="${index}">&#10005;</button></td>
      `;
      OFFER_ITEMS_BODY.appendChild(row);
    });

    updateOfferSummary();

    // Re-ejecutar la búsqueda para actualizar el estado "Añadir" en los resultados
    searchProducts(SEARCH_INPUT.value);
  };

    // ✅ NUEVA FUNCIONALIDAD: Cargar datos para edición
    const loadOfferForEdit = async (offerId) => {
        const response = await fetch(`offers.php?action=load_offer_data&id=${offerId}`);
        const data = await response.json();

        if (!data.success) {
            alert(`Error al cargar la oferta: ${data.message}`);
            return;
        }

        // 1. Cargar datos del Pack principal
        const packData = data.pack_data;
        OFFER_NAME_INPUT.value = packData.name;
        STOCK_PACK_INPUT.value = packData.stock;
        OFFER_BARCODE_INPUT.value = packData.barcode;
        
        // 2. Asignar el ID al campo oculto para que la petición POST lo use
        OFFER_ID_HIDDEN.value = data.offer_id;
        
        // 3. Establecer el precio final
        FINAL_SALE_PRICE_HIDDEN.value = packData.final_price;
        FINAL_SALE_PRICE_DISPLAY.value = packData.final_price.toLocaleString('es-CL');

        // 4. Cargar ítems y actualizar la tabla
        offerItems = data.pack_items;
        updateOfferTable(); 

        // 5. Cambiar el texto del botón y el título
        SAVE_OFFER_BTN.textContent = 'Actualizar Oferta';
        CONFIG_CARD_TITLE.textContent = `2. Configuración de la Oferta (Editando ID: ${offerId}) ✍️`;
        OFFER_NAME_INPUT.focus(); 
    };

    /**
     * Función para limpiar el formulario y volver al modo Creación.
     */
    window.clearOfferForm = () => {
        // Limpiar campos principales
        OFFER_NAME_INPUT.value = '';
        STOCK_PACK_INPUT.value = '0';
        OFFER_BARCODE_INPUT.value = '';
        
        // Limpiar campos de precio/ID
        OFFER_ID_HIDDEN.value = '0';
        FINAL_SALE_PRICE_HIDDEN.value = '0';
        FINAL_SALE_PRICE_DISPLAY.value = '0';
        
        // Limpiar ítems y redibujar
        offerItems = [];
        updateOfferTable(); 
        
        // Resetear textos
        SAVE_OFFER_BTN.textContent = 'Guardar Oferta';
        CONFIG_CARD_TITLE.textContent = '2. Configuración de la Oferta 🎁';
        SEARCH_INPUT.value = '';
        SEARCH_RESULTS.innerHTML = '';
        OFFER_NAME_INPUT.focus(); 
    };

    // ✅ NUEVA FUNCIONALIDAD: Lógica para Eliminar
    const deleteOffer = (offerId) => {
        if (!confirm(`⚠️ ¿Estás seguro de que quieres eliminar la oferta con ID ${offerId}? Esta acción es irreversible y eliminará el pack y sus componentes de la base de datos.`)) {
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'offers.php'; 

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_offer';
        form.appendChild(actionInput);

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'offer_id';
        idInput.value = offerId;
        form.appendChild(idInput);

        document.body.appendChild(form);
        form.submit();
    };


  // --- EVENT LISTENERS ---

  // Búsqueda de productos
  SEARCH_INPUT.addEventListener('input', (event) => {
    searchProducts(event.target.value);
  });
 
  // Modificación de inputs de Cantidad y Descuento
  OFFER_ITEMS_BODY.addEventListener('input', (event) => {
    const target = event.target;
    if (target.tagName === 'INPUT' && target.hasAttribute('data-index')) {
      const index = parseInt(target.getAttribute('data-index'));
      const field = target.getAttribute('data-field');

      let value = target.value;
     
      if (field === 'quantity') {
        value = value.replace(/[^0-9]/g, '');
        let numericValue = parseInt(value) || 0;

        if (numericValue < 1) numericValue = 1;
       
        const maxStock = parseInt(target.getAttribute('max'));
        if (numericValue > maxStock) {
          numericValue = maxStock;
        }

        offerItems[index][field] = numericValue;
        target.value = numericValue.toString();

        // Al modificar la cantidad, forzamos el redibujado de la tabla para actualizar el P. Original y margen
        updateOfferTable();

      } else if (field === 'discount_percent') {
        let numericValue = parseFloat(value) || 0;
        if (numericValue < 0) numericValue = 0;
        if (numericValue > 100) numericValue = 100;

        offerItems[index][field] = numericValue;
        target.value = numericValue.toString();

        updateOfferTable();
      }
    }
  });

  // Eliminación de ítems de la oferta
  OFFER_ITEMS_BODY.addEventListener('click', (event) => {
    const target = event.target;
    if (target.classList.contains('btn-remove')) {
      const index = parseInt(target.getAttribute('data-index'));
      offerItems.splice(index, 1);
      updateOfferTable();
    }
  });
 
  // Manejar la edición manual del precio final
  FINAL_SALE_PRICE_DISPLAY.addEventListener('input', (event) => {
    let value = event.target.value;
    let rawNumber = value.replace(/\./g, '');
    rawNumber = parseInt(rawNumber) || 0;
   
    FINAL_SALE_PRICE_HIDDEN.value = rawNumber;
   
    if (rawNumber > 0 || value.length > 0) {
      event.target.value = rawNumber.toLocaleString('es-CL');
    } else {
      event.target.value = '';
    }
   
    updateOfferSummary();
  });

  // Validación del formulario (habilita/deshabilita botón)
  document.getElementById('offer-form').addEventListener('input', (event) => {
    const target = event.target;
    // Solo actualiza si no es un campo que ya dispara el redibujado
    if (target.id !== 'final-sale-price-display' && !target.classList.contains('input-quantity') && !target.classList.contains('input-discount')) {
      updateOfferSummary();
    }
  });

    // Listener para los botones "Editar" y "Eliminar" en el listado
    document.addEventListener('click', (event) => {
        const target = event.target;
        
        // Lógica para el botón de ELIMINAR
        if (target.classList.contains('btn-delete-offer')) {
            const offerId = target.getAttribute('data-id');
            if (offerId) {
                deleteOffer(parseInt(offerId));
            }
        }
        
        // Lógica para el botón de EDITAR
        if (target.classList.contains('btn-edit-offer')) {
            const offerId = target.getAttribute('data-id');
            if (offerId) {
                loadOfferForEdit(parseInt(offerId));
            }
        }
    });

  // Inicializar al cargar
  document.addEventListener('DOMContentLoaded', updateOfferTable);
</script>
</body>
</html>