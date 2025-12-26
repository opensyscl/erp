<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "../config.php";

$oc_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($oc_id <= 0) die("Orden de compra no válida.");

// Obtener orden de compra
$stmt = $pdo->prepare("
    SELECT po.*, s.name AS supplier_name
    FROM purchase_orders po
    INNER JOIN suppliers s ON po.supplier_id = s.id
    WHERE po.id = ?
");
$stmt->execute([$oc_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$invoice) die("Orden de compra no encontrada.");

// Obtener productos
$stmt = $pdo->prepare("
    SELECT poi.*, p.name AS product_name, p.barcode
    FROM purchase_order_items poi
    INNER JOIN products p ON poi.product_id = p.id
    WHERE poi.order_id = ?
");
$stmt->execute([$oc_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totales
$total_quantity = 0;
$subtotal = 0;
foreach ($items as $item) {
    $total_quantity += $item['quantity'];
    $subtotal += $item['quantity'] * $item['cost_price'];
}
$iva = $subtotal * 0.19;
$total = $subtotal + $iva;

// URL para QR (verificación online)
$verification_url = "https://tiendaslistto.cl/erp/module/ver_oc.php?id=" . $invoice['id'];
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($verification_url);

?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Orden de Compra #<?= htmlspecialchars($invoice['order_number']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
body {
    background: #f4f6f9;
    font-family: 'Roboto', Arial, sans-serif;
    padding: 30px;
}
.invoice-container {
    max-width: 900px;
    margin: auto;
    background: #fff;
    padding: 40px;
    border-radius: 10px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}
.invoice-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    border-bottom: 2px solid #eee;
    padding-bottom: 15px;
}
.invoice-header img {
    height: 60px;
}
.invoice-header h1 {
    font-size: 24px;
    color: #2c3e50;
    margin: 0;
}
.invoice-info-section {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
}
.invoice-info {
    font-size: 15px;
    color: #555;
}
.invoice-info p {
    margin: 3px 0;
}
.table {
    border: 1px solid #ddd;
    margin-bottom: 0;
}
.table thead {
    background: #2c3e50;
    color: #fff;
}
.table th, .table td {
    vertical-align: middle;
    text-align: left;
    font-size: 14px;
    padding: 10px;
}
.totals {
    margin-top: 30px;
    width: 100%;
    max-width: 400px;
    float: right;
}
.totals table {
    width: 100%;
}
.totals td {
    padding: 8px;
    font-size: 15px;
}
.totals .label {
    text-align: right;
    font-weight: bold;
}
.totals .amount {
    text-align: right;
}
.invoice-footer {
    margin-top: 60px;
    text-align: center;
    font-size: 13px;
    color: #777;
    border-top: 1px solid #eee;
    padding-top: 25px;
    clear: both;
}
.invoice-footer p {
    margin: 5px 0;
}
.btns {
    margin-top: 20px;
    text-align: right;
}
.btns button {
    margin-left: 10px;
}
.qr-verification {
    text-align: center;
    margin-top: 25px;
    margin-bottom: 30px;
}
.qr-verification img {
width: 140px;
    height: 140px;
    margin-bottom: 12px;
    
}
.qr-verification p {
    font-size: 14px;
    color: #444;
    max-width: 350px;
}
.qr-verification a {
    color: #007bff;
    text-decoration: none;
    word-break: break-all;
    font-weight: 500;
}
@media print {
    .no-print {
        display: none !important;
    }
    /* Regla CSS externa (además del estilo en línea) */
    .table tbody tr {
        page-break-inside: avoid !important;
        break-inside: avoid !important; 
    }
    /* Asegura que los totales también se mantengan juntos */
    .totals {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
}
</style>
</head>
<body>

<div class="btns no-print">
    <button class="btn btn-outline-primary" onclick="window.print()">🖨️ Imprimir</button>
    <button class="btn btn-outline-success" onclick="exportPDF()">📄 Exportar PDF</button>
</div>

<div class="invoice-container" id="invoice">
    <div class="invoice-header">
        <img src="../img/Logo1.png" alt="Listto">
        <h1>Orden de Compra #<?= htmlspecialchars($invoice['order_number']) ?></h1>
    </div>

    <div class="invoice-info-section">
        <div class="invoice-info">
            <h5>TIENDAS LISTTO, SpA</h5>
            <p>RUT: 78.169.866-0</p>
            <p>Vicuña Mackenna Ote. 6617, Local 7</p>
            <p>La Florida, Santiago de Chile</p>
        </div>
        <div class="invoice-info text-end">
            <p><strong>Proveedor:</strong> <?= htmlspecialchars($invoice['supplier_name']) ?></p>
            <p><strong>Fecha:</strong> <?= date("d/m/Y", strtotime($invoice['date'])) ?></p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Valor Neto</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($items as $index => $item): ?>
                <tr style="page-break-inside: avoid !important; break-inside: avoid !important;">
                    <td><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($item['barcode']) ?></td>
                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td>$<?= number_format($item['cost_price'], 2, ',', '.') ?></td>
                    <td>$<?= number_format($item['quantity'] * $item['cost_price'], 2, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="row align-items-start mt-4">
    <div class="col-md-6">
        <div class="qr-verification text-start">
            <img src="<?= $qr_url ?>" alt="QR Validación">
            <p class="mt-2">
                Escanee este código para validar esta orden en línea:<br>
                <a href="<?= $verification_url ?>" target="_blank"><?= $verification_url ?></a>
            </p>
        </div>
    </div>

    <div class="col-md-6">
        <div class="totals float-md-end">
            <table>
                <tr>
                    <td class="label">Subtotal:</td>
                    <td class="amount">$<?= number_format($subtotal, 2, ',', '.') ?></td>
                </tr>
                <tr>
                    <td class="label">IVA (19%):</td>
                    <td class="amount">$<?= number_format($iva, 2, ',', '.') ?></td>
                </tr>
                <tr>
                    <td class="label">Total:</td>
                    <td class="amount fw-bold text-success">$<?= number_format($total, 2, ',', '.') ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>




    <div class="invoice-footer">
        <p>Esta orden de compra fue generada automáticamente por <strong>Listto! ERP</strong>.</p>
        <p>Para más información, contáctenos a <a href="mailto:contacto@tiendaslistto.cl">contacto@tiendaslistto.cl</a></p>
        <p>Teléfono: +56920210349 - tiendaslistto.cl</a></p>
        
    </div>
</div>

<script>
function exportPDF() {
    const element = document.getElementById('invoice');
    const opt = {
        margin:      0.5,
        filename:    'OC_<?= $invoice['order_number'] ?>.pdf',
        image:       { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF:       { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    html2pdf().from(element).set(opt).save();
}
</script>
</body>
</html>