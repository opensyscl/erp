<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "config.php";

// Incluir PHPMailer y TCPDF (mantener la lógica de procesamiento)
require_once __DIR__ . '/PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';
require_once(__DIR__ . '/tcpdf/tcpdf.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Redireccionar si el carrito está vacío o no hay sesión
if (empty($_SESSION['cart'])) {
    // header("Location: index.php"); 
    // exit;
}

if (!isset($_SESSION['customer_id'])) {
    $customer_name = null;
    $customer_email = null;
} else {
    $customer_id = $_SESSION['customer_id'];
    // Obtener datos cliente
    $stmt = $pdo->prepare("SELECT name, email FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    $customer_name  = $customer['name'] ?? '';
    $customer_email = $customer['email'] ?? '';
}


// Contar items para el botón del header
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $qty) {
        if (is_numeric($qty)) {
            $cart_count += $qty;
        }
    }
}

$success = $error = "";
$pdf_file_path = "";

// Procesar pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['customer_id'])) {
        header("Location: login.php");
        exit;
    }
    
    $payment_method  = $_POST['payment_method'] ?? 'efectivo';
    if (is_array($payment_method)) { $payment_method = reset($payment_method); }
    $delivery_method = $_POST['delivery_method'] ?? 'retiro';
    if (is_array($delivery_method)) { $delivery_method = reset($delivery_method); }

    $total     = 0;
    $cart_pids = array_keys($_SESSION['cart']);
    $placeholders = rtrim(str_repeat('?,', count($cart_pids)), ',');
    $products = [];

    if (!empty($cart_pids)) {
        $stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id IN ($placeholders)");
        $stmt->execute($cart_pids);
        $products_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products_data as $p) { $products[$p['id']] = $p; }

        $total = 0;
        foreach ($_SESSION['cart'] as $pid => $qty) {
            if (is_array($qty)) { $qty = reset($qty); }
            if (isset($products[$pid]) && is_numeric($products[$pid]['price']) && is_numeric($qty)) {
                $total += $products[$pid]['price'] * $qty;
            } else {
                error_log("Error en producto $pid: precio o cantidad inválidos");
            }
        }
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO orders
            (customer_id, total, payment_method, delivery_method, status, created_at)
            VALUES (?, ?, ?, ?, 'Pendiente', NOW())
        ");
        $stmt->execute([$customer_id, $total, $payment_method, $delivery_method]);
        $order_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, price)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($_SESSION['cart'] as $pid => $qty) {
            if (is_array($qty)) { $qty = reset($qty); }
            $stmt->execute([$order_id, $pid, $qty, $products[$pid]['price']]);
        }

        $updateStockStmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        foreach ($_SESSION['cart'] as $pid => $qty) {
            if (is_array($qty)) { $qty = reset($qty); }
            $updateStockStmt->execute([$qty, $pid]);
        }

        $pdo->commit();

        $pdf_file_path = generateOrderPDF($order_id, $customer_name, $products, $_SESSION['cart'], $total, $payment_method, $delivery_method);
        sendOrderEmail($customer_email, $customer_name, $order_id, $products, $_SESSION['cart'], $total, $payment_method, $delivery_method, $pdf_file_path);

        $_SESSION['cart'] = [];
        $success = "✅ ¡Gracias por tu compra! Tu número de orden es <strong>#{$order_id}</strong>. Revisa tu correo 📧 para el detalle.";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "❌ Error al procesar la orden: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Checkout - Listto</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
/* --- RAÍZ Y VARIABLES GLOBALES (ESTILO APLICADO DE CART.PHP) --- */
:root {
    --background-start: #f4f4f5;
    --background-end: #e9e9ed;
    --card-background: rgba(255, 255, 255, 0.65);
    --card-border: rgba(255, 255, 255, 0.9);
    --card-shadow: rgba(0, 0, 0, 0.07);
    --text-primary: #1a202c;
    --text-secondary: #4a5568;
    --accent-color: #e81c1c;
    --accent-color-dark: #c01515;
}

/* --- RESET & BASE --- */
* { box-sizing: border-box; margin:0; padding:0; }

body {
    font-family: 'Inter', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Apple Color Emoji', sans-serif;
    background-color: var(--background-start);
    background-image: linear-gradient(135deg, var(--background-start) 0%, var(--background-end) 100%);
    color: var(--text-primary);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* --- HEADER (CON EFECTO GLASS) --- */
header {
    background: var(--card-background);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    padding: 12px 24px;
    border-bottom: 1px solid var(--card-border);
    box-shadow: 0 4px 30px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 100;
}

header img { height: 40px; }

.header-right {
    display: flex;
    align-items: center;
    gap: 15px;
}

.header-right span {
    font-weight: 500;
    color: var(--text-secondary);
    white-space: nowrap;
}

/* --- BOTONES MODERNIZADOS --- */
.btn, .btn-secondary, .btn-logout, .btn-login, .btn-cart {
    background-image: linear-gradient(145deg, var(--accent-color), var(--accent-color-dark));
    color: #fff;
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    display: inline-block;
}

.btn:hover, .btn-login:hover, .btn-cart:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(232, 28, 28, 0.3);
}

.btn-logout {
    background-image: linear-gradient(145deg, #555, #333);
}

.btn-logout:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
}

.btn-secondary {
    background: #777;
    background-image: none;
    box-shadow: none;
}
.btn-secondary:hover {
    background: #555;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

/* --- CONTENEDOR DE FORMULARIO (Glassmorphism) --- */
.container {
    flex: 1;
    max-width: 700px;
    margin: 30px auto;
    padding: 30px;
    
    background: var(--card-background);
    border: 1px solid var(--card-border);
    border-radius: 18px;
    box-shadow: 0 8px 32px 0 var(--card-shadow);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}

h2 {
    font-size: 28px;
    margin-bottom: 25px;
    font-weight: 700;
    text-align: center;
    color: var(--text-primary);
}

/* --- ESTILOS DE FORMULARIO --- */
label { font-weight: 600; display:block; margin-bottom:6px; color: var(--text-primary); }

/* ✨ GLASSMORPHISM PARA SELECT ✨ */
select {
    width:100%; 
    padding:12px; 
    border-radius:10px;
    font-size:16px;
    
    /* Efecto Glassmorphism */
    background: rgba(255, 255, 255, 0.4); /* Fondo más transparente */
    border: 1px solid rgba(255, 255, 255, 0.6); /* Borde más sutil */
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); /* Sombra suave */
    
    color: var(--text-primary);
    transition: all 0.3s;
    appearance: none; /* Oculta el estilo nativo del select (flecha) */
    
    /* Flecha personalizada para select (solo por si acaso) */
    background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%23e81c1c' d='M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 20px;
}
select:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 0 3px rgba(232, 28, 28, 0.2); /* Sombra de enfoque roja */
    outline: none;
}
/* Estilo para las opciones dentro del select (necesitan fondo sólido para leerse) */
select option {
    background: #ffffff; 
    color: var(--text-primary);
}


form > div { margin-bottom: 20px; }

/* Botón de Submit Principal (Estilo .btn) */
button[type=submit] {
    width:100%;
    background-image: linear-gradient(145deg, var(--accent-color), var(--accent-color-dark));
    color: #fff;
    padding: 12px 25px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    margin-top: 15px;
}
button[type=submit]:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(232, 28, 28, 0.3);
}

/* --- Mensajes de Éxito/Error/Info --- */
.success, .error {
    padding: 18px;
    border-radius: 10px;
    margin-bottom: 25px;
    font-weight: 600;
}
.success { background:#e6ffed; color:#0e7f3d; border:1px solid #b3ffc9; }
.error { background:#ffe6e6; color:#9c1f1f; border:1px solid #ffb3b3; }

.payment-info {
    background: #fff3cd;
    border: 1px solid #ffeeba;
    color: #856404;
    padding: 15px;
    border-radius: 10px;
    margin-top: 15px;
    font-weight: 500;
    display: none;
}

.btn-group {
    margin-top:25px; 
    display:flex; 
    gap:15px; 
    flex-wrap:wrap; 
    justify-content:center; 
}


/* --- ESTILOS MÓVILES MEJORADOS --- */
@media (max-width: 768px) {
    /* CORRECCIÓN DE ENCABEZADO: Apilar botones a la derecha */
    header {
        padding: 10px 15px;
        flex-wrap: wrap; 
        justify-content: space-between; 
    }
    .header-right {
        flex-direction: column;
        align-items: flex-end;
        gap: 5px;
        flex-grow: 1; 
        width: auto; 
    }
    .header-right span {
        display: none; /* Ocultar el saludo */
    }
    .header-right .btn-login, 
    .header-right .btn-logout, 
    .header-right .btn-cart {
        padding: 8px 14px;
        font-size: 13px;
        width: 100%;
        max-width: 115px;
    }

    /* Contenedor principal para móviles */
    .container { 
        width: 95%; 
        margin: 15px auto; 
        padding: 20px; 
        max-width: 100%;
    }
    h2 { 
        font-size: 24px; 
        margin-bottom: 20px;
    }
    
    .btn-group {
        flex-direction: column;
        align-items: stretch;
    }
    .btn-group .btn, .btn-group .btn-secondary {
        text-align: center;
    }
}
    </style>
</head>
<body>

<header>
    <a href="index.php"><img src="https://tiendaslistto.cl/erp/img/Logo1.png" alt="Listto"></a>
    <div class="header-right">
        <?php if ($customer_name): ?>
            <span>👋 Hola, <?= htmlspecialchars($customer_name) ?></span>
            <a href="logout.php" class="btn-logout">Cerrar sesión</a>
        <?php else: ?>
            <a href="login.php" class="btn-login"><i class="fas fa-sign-in-alt"></i> Iniciar sesión</a>
        <?php endif; ?>
        
        <a href="cart.php" class="btn-cart">
            <i class="fas fa-shopping-cart"></i> Carrito (<?= $cart_count ?>)
        </a>
    </div>
</header>

<main class="container">
    <h2>📝 Finalizar Compra</h2>

    <?php if ($success): ?>
        <div class="success"><?= $success ?></div>
        <div class="btn-group">
            <a href="index.php" class="btn-secondary">⬅ Volver a la tienda</a>
            <?php if ($pdf_file_path && file_exists($pdf_file_path)): ?>
                <a href="download_order.php?file=<?= urlencode(basename($pdf_file_path)) ?>" target="_blank" class="btn">📄 Descargar pedido (PDF)</a>
            <?php endif; ?>
        </div>

    <?php elseif ($error): ?>
        <div class="error"><?= $error ?></div>

    <?php else: ?>
        <form method="POST" action="">
            <div>
                <label for="payment_method">💳 Método de pago:</label>
                <select name="payment_method" id="payment_method" required>
                    <option value="efectivo">Efectivo</option>
                    <option value="tarjeta">Tarjeta</option>
                </select>
            </div>
            <div>
                <label for="delivery_method">🚚 Método de entrega:</label>
                <select name="delivery_method" id="delivery_method" required>
                    <option value="retiro">Retiro en tienda</option>
                    <option value="envio">Envío a domicilio</option>
                </select>
            </div>
            <div id="payment-info" class="payment-info">
                💡 Al momento de la entrega podrá realizar el pago con su tarjeta de débito o crédito.
            </div>
            <button type="submit">✅ Confirmar pedido</button>
        </form>
    <?php endif; ?>
</main>

<script>
    const paymentSelect = document.getElementById('payment_method');
    const deliverySelect = document.getElementById('delivery_method');
    const paymentInfo = document.getElementById('payment-info');

    function updatePaymentInfo() {
        // Muestra la info si es 'envio' Y 'tarjeta'
        if (deliverySelect.value === 'envio' && paymentSelect.value === 'tarjeta') {
            paymentInfo.style.display = 'block';
        } else {
            paymentInfo.style.display = 'none';
        }
    }

    paymentSelect.addEventListener('change', updatePaymentInfo);
    deliverySelect.addEventListener('change', updatePaymentInfo);
    
    // Ejecutar al cargar por si los valores por defecto ya cumplen la condición
    document.addEventListener('DOMContentLoaded', updatePaymentInfo); 
</script>

</body>
</html>

<?php
// ===============================
// 📄 FUNCIONES DE PEDIDO (PDF y Email)
// ===============================

function generateOrderPDF($order_id, $customer_name, $products, $cart, $total, $payment_method, $delivery_method) {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Listto!');
    $pdf->SetAuthor('Listto!');
    $pdf->SetTitle("Pedido #$order_id");
    $pdf->SetMargins(15, 30, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(15);
    $pdf->SetAutoPageBreak(true, 25);
    $pdf->AddPage();

    $logo = __DIR__ . '/logo_listto.png';
    if (!file_exists($logo)) {
        @file_put_contents($logo, @file_get_contents('https://tiendaslistto.cl/erp/img/Logo1.png'));
    }
    $pdf->Image($logo, 15, 10, 40);
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(232, 28, 28);
    $pdf->Cell(0, 15, "Listto! - Pedido #$order_id", 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', '', 12);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 6, "Cliente: $customer_name", 0, 1);
    $pdf->Cell(0, 6, "Método de pago: $payment_method", 0, 1);
    $pdf->Cell(0, 6, "Método de entrega: $delivery_method", 0, 1);
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetFillColor(232, 28, 28);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(90, 8, "Producto", 1, 0, 'C', true);
    $pdf->Cell(30, 8, "Cantidad", 1, 0, 'C', true);
    $pdf->Cell(30, 8, "Precio", 1, 0, 'C', true);
    $pdf->Cell(30, 8, "Subtotal", 1, 1, 'C', true);

    $pdf->SetFont('helvetica', '', 12);
    $fill = false;
    foreach ($cart as $pid => $qty) {
        $name      = $products[$pid]['name'];
        $price     = $products[$pid]['price'];
        $subtotal = $price * $qty;

        $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
        $pdf->SetTextColor(0, 0, 0);

        $pdf->Cell(90, 8, $name, 1, 0, 'L', true);
        $pdf->Cell(30, 8, $qty, 1, 0, 'C', true);
        $pdf->Cell(30, 8, "$" . number_format($price, 0, ',', '.'), 1, 0, 'R', true);
        $pdf->Cell(30, 8, "$" . number_format($subtotal, 0, ',', '.'), 1, 1, 'R', true);
        $fill = !$fill;
    }

    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetFillColor(232, 28, 28);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(150, 10, "TOTAL", 1, 0, 'R', true);
    $pdf->Cell(30, 10, "$" . number_format($total, 0, ',', '.'), 1, 1, 'R', true);

    $pdf->Ln(10);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(50, 50, 50);
    $note = "Gracias por tu compra en Listto! Si tu método de pago es tarjeta y has elegido envío a domicilio, el repartidor llevará un POS para que puedas realizar el pago al momento de la entrega.\n\n¡Esperamos verte pronto de nuevo!";
    $pdf->MultiCell(0, 6, $note, 0, 'L');

    $dir = __DIR__ . "/orders";
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    $file_path = $dir . "/order_$order_id.pdf";
    $pdf->Output($file_path, 'F');
    return $file_path;
}

function sendOrderEmail($to, $name, $order_id, $products, $cart, $total, $payment_method, $delivery_method, $pdf_file) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host         = "mail.tiendaslistto.cl";
        $mail->SMTPAuth     = true;
        $mail->Username     = "contacto@tiendaslistto.cl";
        $mail->Password     = "TListto.2025";
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port         = 587;
        $mail->CharSet      = 'UTF-8';

        $mail->setFrom('contacto@tiendaslistto.cl', 'Listto!');
        $mail->addAddress($to, $name);
        $mail->Subject = "Listto! Hemos recibido tu pedido #$order_id";

        $body  = "<p><strong>Gracias por tu compra, $name</strong></p>";
        $body .= "<p>Ya estamos preparando tu pedido: <strong>#$order_id</strong> y en un momento te contactaremos.</p>";
        $body .= "<p><strong>Tu método de pago:</strong> $payment_method</p>";
        $body .= "<p><strong>Tu método de entrega:</strong> $delivery_method</p>";
        $body .= "<p><strong>Total:</strong> $" . number_format($total, 0, ',', '.') . "</p>";

        if ($delivery_method === 'envio' && $payment_method === 'tarjeta') {
            $body .= "<p>💡 El repartidor llevará un POS para realizar el pago al momento de la entrega.</p>";
        }

        $mail->isHTML(true);
        $mail->Body = $body;

        if ($pdf_file && file_exists($pdf_file)) {
            $mail->addAttachment($pdf_file);
        }

        $mail->send();
    } catch (Exception $e) {
        error_log("Error enviando email: {$mail->ErrorInfo}");
    }
}
?>