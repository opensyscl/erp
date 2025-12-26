<?php
// --- CONFIGURACIÓN Y CONEXIÓN A LA BASE DE DATOS ---
require 'config.php'; // Tu archivo de conexión a la BD (PDO)

// URL para el código QR
$qr_url = 'https://tiendaslistto.cl/app/index.php';

// --- INFORMACIÓN FIJA DE LA TIENDA ---
$store_info = [
    'name'    => 'Tiendas Listto, SpA.',
    'rut'     => '78.169.866-0',
    'address' => 'Av. Vicuña Mackenna 6617, LC7, La Florida',
    'email'   => 'contacto@tiendaslistto.cl',
    'phone'   => '+56 9 2021 0349',
    'website' => 'www.tiendaslistto.cl'
];

// --- OBTENER DATOS DE LA VENTA ---
$sale_id = $_GET['id'] ?? 0;
if (!$sale_id) {
    die("Error: No se ha especificado un ID de venta.");
}

$stmt_sale = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
$stmt_sale->execute([$sale_id]);
$sale = $stmt_sale->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    die("Error: Venta con ID $sale_id no encontrada.");
}

// --- OBTENER DATOS DE LOS ÍTEMS DE LA VENTA ---
$stmt_items = $pdo->prepare(
    "SELECT 
        CAST(si.quantity AS DECIMAL(10, 3)) as quantity, 
        CAST(si.price AS DECIMAL(10, 2)) as price, 
        p.name
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = ?"
);
$stmt_items->execute([$sale_id]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);


// ----------------------------------------------------------------
// ✅ NUEVA LÓGICA: DETECCIÓN DE PRODUCTOS A GRANEL EN LA VENTA
// ----------------------------------------------------------------
$has_bulk_product = false;
foreach ($items as $item) {
    if (strpos($item['name'], 'Granel') !== false) {
        $has_bulk_product = true;
        break; // Detenemos el bucle al encontrar el primer producto Granel
    }
}
// ----------------------------------------------------------------


// --- CÁLCULOS DE TOTALES ---
$total = (float)$sale['total']; // Aseguramos que sea float
$iva_rate = 0.19;
$neto = $total / (1 + $iva_rate);
$iva = $total - $neto;


// ----------------------------------------------------------------
// ✅ FUNCIÓN MODIFICADA: FORMATO DE MONEDA CONDICIONAL
// Ahora recibe el flag $has_bulk_product.
// ----------------------------------------------------------------
function format_currency($amount, $has_bulk) {
    // Si hay productos a granel, usamos 2 decimales; si no, usamos 0.
    $decimals = $has_bulk ? 2 : 0;
    return '$' . number_format($amount, $decimals, ',', '.');
}
// ----------------------------------------------------------------


// --- FUNCIÓN PARA FORMATO CONDICIONAL DE CANTIDAD (Entero vs. Granel con 'gr') ---
function format_quantity($quantity, $product_name) {
    if (strpos($product_name, 'Granel') !== false) {
        // Si es granel, usamos 0 decimales y agregamos el sufijo (gr)
        $formatted_qty = number_format($quantity, 0, ',', '.');
        return $formatted_qty . ' (gr)';
    } else {
        // Si es unidad, muestra la cantidad como entero (usando floor para 1.000 -> 1)
        return floor($quantity);
    }
}

// --- FUNCIÓN PARA GENERAR URL DEL QR (Usando API QRServer) ---
function generate_qr_api_url($data, $size = '112x112') { 
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . '&data=' . urlencode($data);
}

// Generar la URL del QR con el nuevo tamaño
$qr_image_url = generate_qr_api_url($qr_url);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket de Venta #<?php echo htmlspecialchars($sale['id']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            /* Usamos Roboto Mono para una apariencia clara y similar a impresora */
            font-family: 'Roboto Mono', monospace;
            font-size: 11px; /* Ajustamos ligeramente la fuente para más contenido */
            color: #000;
            margin: 0;
            padding: 10px;
            width: 75mm; /* Ancho estándar de ticket */
            box-sizing: border-box;
            line-height: 1.5;
        }
        .ticket { width: 100%; margin: 0 auto; }
        .header, .footer { text-align: center; }
        /* Usamos línea sólida o más espaciada para mejor visibilidad */
        .header hr, .content hr { border: 0.5px solid #000; margin: 12px 0; }
        .content { margin-top: 10px; }
        .footer { margin-top: 15px; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        
        /* Estilo para el Isotipo */
        .logo-img { max-width: 40px; height: auto; margin-bottom: 5px; }
        .logo-text { font-size: 16px; font-weight: 700; margin: 0 0 5px 0; }
        
        .ticket-info { font-size: 10px; line-height: 1.4; }
        .item-name { font-weight: 700; font-size: 12px; } /* Hacemos el nombre más visible */
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 0; }
        .items-table th {
            border-bottom: 1px solid #000;
            text-align: left;
            font-weight: 700;
            font-size: 11px;
        }
        .items-table td { font-size: 11px; }
        .items-table .col-qty { width: 15%; text-align: right; }
        .items-table .col-price { width: 25%; text-align: right; }
        .items-table .col-name { width: 60%; }
        
        /* Totales */
        .totals-table td:first-child { text-align: right; padding-right: 10px; font-weight: 400; }
        .totals-table td:last-child { text-align: right; font-weight: 700; }
        .totals-table .total-final td {
            font-size: 14px;
            font-weight: 900;
            padding-top: 8px;
            border-top: 2px solid #000; /* Borde sólido y más grueso */
        }

        /* Estilo para el QR */
        .qr-container { text-align: center; margin-top: 15px; }
        .qr-container p { font-size: 10px; margin-bottom: 5px; }

        /* Ocultar en impresión */
        @media print {
            .no-print { display: none; }
            body { margin: 0; padding: 0; }
        }
    </style>
</head>
<body onload="window.print();" onafterprint="redirectToPOS()">
    <div class="ticket">
        <header class="header">
            <img src="https://tiendaslistto.cl/erp/img/fav.png" alt="Listto Isotipo" class="logo-img">
            <p class="logo-text">Tiendas Listto!</p>
            
            <p class="ticket-info">
                <?php echo htmlspecialchars($store_info['name']); ?><br>
                RUT: <?php echo htmlspecialchars($store_info['rut']); ?><br>
                <?php echo htmlspecialchars($store_info['address']); ?><br>
                Teléfono: <?php echo htmlspecialchars($store_info['phone']); ?><br>
                <?php echo htmlspecialchars($store_info['email']); ?>
            </p>
            <hr>
        </header>

        <main class="content">
            <p class="text-left ticket-info">
                <strong>Ticket N°:</strong> <?php echo htmlspecialchars($sale['id']); ?><br>
                <strong>Fecha:</strong> <?php echo (new DateTime($sale['created_at']))->format('d/m/Y H:i'); ?> hrs<br>
                <strong>Pago:</strong> <?php echo htmlspecialchars($sale['method']); ?><br>
                <strong>Atendido por:</strong> Tiendas Listto 001
            </p>
            <hr>
            
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="col-name">Producto</th>
                        <th class="col-qty">Cant.</th>
                        <th class="col-price">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="col-name">
                            <span class="item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                            <br>
                            <span style="font-size: 10px;">(<?php echo format_quantity($item['quantity'], $item['name']); ?> x <?php echo format_currency($item['price'], $has_bulk_product); ?>)</span>
                        </td>
                        <td class="col-qty"><?php echo format_quantity($item['quantity'], $item['name']); ?></td>
                        <td class="col-price"><?php echo format_currency($item['price'] * $item['quantity'], $has_bulk_product); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <hr>

            <table class="totals-table">
                <tbody>
                    <tr>
                        <td>NETO:</td>
                        <td><?php echo format_currency($neto, $has_bulk_product); ?></td>
                    </tr>
                    <tr>
                        <td>IVA (19%):</td>
                        <td><?php echo format_currency($iva, $has_bulk_product); ?></td>
                    </tr>
                    <tr class="total-final">
                        <td>Total a pagar:</td>
                        <td><?php echo format_currency($total, $has_bulk_product); ?></td>
                    </tr>
                </tbody>
            </table>

            <div class="qr-container">
                <p>Conoce todos nuestros productos en nuestra web</p>
                <img src="<?php echo htmlspecialchars($qr_image_url); ?>" alt="Código QR App Web" width="112" height="112"> </div>
            </main>

        <footer class="footer">
            <hr>
            <p style="font-weight: 700; font-size: 12px;">¡Gracias por tu compra!</p>
            <p class="ticket-info">
                Visítanos en <?php echo htmlspecialchars($store_info['website']); ?><br>
                Síguenos en Instagram: @listtocl
            </p>
            <p style="font-size: 9px; line-height: 1.2;">
                Conserve su ticket para cambios o devoluciones.
            </p>
        </footer>
    </div>

    <script>
        /**
        * Función que se ejecuta automáticamente después de que se intenta
        * imprimir el documento. Redirige a pos.php.
        */
        function redirectToPOS() {
            // Un pequeño retraso asegura que el cuadro de diálogo de impresión se cierre antes de la redirección.
            setTimeout(function() {
                window.location.href = 'pos.php';
            }, 100);
        }
    </script>
</body>
</html>