<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir el archivo de configuración de la base de datos
// ASUMIDO: config.php está en el directorio superior (../)
require '../config.php';

// Función para formatear moneda al estilo chileno
function formatCurrency($amount) {
  // Utilizamos max(0, $amount) para evitar formatos de números negativos
  return '$' . number_format(max(0, $amount), 0, ',', '.');
}

// 1. Obtener y validar el ID del producto
$product_id = $_GET['product_id'] ?? null;

if (!$product_id || !is_numeric($product_id)) {
  die("Error: ID de producto no válido.");
}

// 2. Consulta a la base de datos para obtener el producto
try {
    $stmt = $pdo->prepare("
        SELECT id, barcode, name, price, stock, cost_price, sale_price, category_id, image_url
        FROM products
        WHERE id = ?
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Para depuración: echo "Error de base de datos: " . $e->getMessage(); exit;
    die("Error de base de datos al buscar producto."); 
}

if (!$product) {
  die("Error: Producto no encontrado con el ID: " . htmlspecialchars($product_id));
}

// 3. LÓGICA DE PRECIOS ADAPTADA A PROMOCIÓN 2X
$product_name = htmlspecialchars(mb_strtoupper($product['name']));
$product_barcode = htmlspecialchars($product['barcode']);
$product_stock = (int)$product['stock'];
$product_image_url = htmlspecialchars($product['image_url'] ?? ''); // Columna corregida

$price_val_1 = (float)$product['price'];
$price_val_2 = (float)$product['sale_price'];

// Determinar el precio unitario normal (más alto) y el de oferta (más bajo)
if ($price_val_1 > $price_val_2) {
    $unit_normal_price = $price_val_1;
    $unit_offer_price = $price_val_2;
} elseif ($price_val_2 > $price_val_1) {
    $unit_normal_price = $price_val_2;
    $unit_offer_price = $price_val_1;
} else {
    // Si son iguales, no hay descuento real.
    $unit_normal_price = $price_val_1; 
    $unit_offer_price = $price_val_1;
}

// 4. Calcular los valores para la promoción 2x
$promo_quantity = 2; // Cantidad fija para esta promoción
$show_offer_price = true;

// Precio de Referencia (2x el precio normal unitario)
$reference_price_val = $unit_normal_price * $promo_quantity;
$reference_price = formatCurrency($reference_price_val);

// Precio de la Oferta (2x el precio de oferta unitario)
$offer_price_val = $unit_offer_price * $promo_quantity;
$offer_price = formatCurrency($offer_price_val);
$offer_price_display = $promo_quantity . 'X' . $offer_price; 

// 5. Calcular el ahorro y si mostrar el precio de referencia
$save_val = $reference_price_val - $offer_price_val;
$save_amount = formatCurrency($save_val);

$show_reference = ($save_val > 0);

// Manejo de stock cero
$stock_text = $product_stock > 0 ? $product_stock . ' unidades' : 'AGOTADO';

// Si el precio de oferta es $0, ajustamos la visualización
if ($offer_price_val <= 0) {
    $offer_price_display = "CONSULTAR PRECIO";
    $show_reference = false;
    $show_offer_price = false;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Oferta de Producto A5 Horizontal - <?= $product_name ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <link rel="stylesheet" href="css/offers.css">
  
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&family=Roboto:wght@400;700;900&display=swap" rel="stylesheet">
</head>
<body onload="window.print()">
  <div class="offer-container">
        
        <div class="image-column">
            <?php if ($product_image_url): ?>
                <img src="<?= $product_image_url ?>" alt="<?= $product_name ?>" class="product-image">
            <?php else: ?>
                <div class="image-placeholder">SIN IMAGEN</div>
            <?php endif; ?>
        </div>

        <div class="info-and-footer-column">
            <div class="content-column">
          <div class="offer-header">
            <div class="promo-brand">PROMO</div>         <h1 class="product-name"><?= $product_name ?></h1>
          </div>

          <div class="offer-body">
            <?php if ($show_offer_price): ?>
              <div class="main-price-box">
                <span class="new-price-label">LLEVA</span>
                <span class="new-price"><?= $offer_price_display ?></span>
              </div>
            <?php else: ?>
              <div class="no-price-box">
                <span class="no-price-text"><?= $offer_price_display ?></span>
              </div>
            <?php endif; ?>

            <?php if ($show_reference): ?>
              <div class="reference-section">
                <p class="reference-price-label">PRECIO REFERENCIA</p>
                <p class="reference-price"><?= $reference_price ?></p>
                <div class="save-box">
                  <p class="save-text">AHORRA: <?= $save_amount ?></p>
                </div>
              </div>
            <?php endif; ?>

            <div class="details-footer">
              <p class="stock-info">Stock Disponible: <strong><?= $stock_text ?></strong></p>
              <p class="barcode-info">CÓDIGO: <?= $product_barcode ?></p>
            </div>
          </div>
            </div>       <div class="offer-footer">
            <p>*Válido hasta agotar stock o fecha de término (Consultar en caja). Listto! ERP.</p>
          </div>
        </div>   </div>
</body>
</html>