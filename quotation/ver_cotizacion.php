<?php
// =========================================================
// LÓGICA PHP PARA VISUALIZAR COTIZACIÓN
// =========================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "../config.php";

// Tasa de IVA (asumiendo que es constante en el sistema)
define('IVA_RATE', 0.19);

$quotation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($quotation_id <= 0) die("Cotización no válida.");

// 1. Obtener datos de la Cotización (quotations)
$stmt = $pdo->prepare("
    SELECT q.*, s.name AS supplier_name
    FROM quotations q
    INNER JOIN suppliers s ON q.supplier_id = s.id
    WHERE q.id = ?
");
$stmt->execute([$quotation_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC); // Usamos 'invoice' por conveniencia en el HTML
if (!$invoice) die("Cotización no encontrada.");

// 2. Obtener productos de la Cotización (quotation_items)
// Unimos con la tabla products solo para obtener el barcode/name de productos existentes.
// Para productos temporales (product_id IS NULL), usamos los campos code/name de quotation_items.
$stmt = $pdo->prepare("
    SELECT 
        qi.quantity, 
        qi.cost_net AS cost_price, 
        COALESCE(p.name, qi.product_name) AS final_product_name,
        COALESCE(p.barcode, qi.product_code) AS final_product_code,
        qi.product_id -- Para determinar si es un producto existente o temporal
    FROM quotation_items qi
    LEFT JOIN products p ON qi.product_id = p.id
    WHERE qi.quotation_id = ?
");
$stmt->execute([$quotation_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Recalcular Totales (usando los datos guardados en la tabla o recalculando si es necesario)
// Aunque el total está en $invoice['total_amount'], es buena práctica verificar los subtotales.
$subtotal = 0;
foreach ($items as $item) {
    // Usamos el 'cost_price' guardado (que es el costo NETO en quotation_items)
    $subtotal += $item['quantity'] * $item['cost_price'];
}
$iva = round($subtotal * IVA_RATE);
$total = round($subtotal + $iva);

// 4. URL para QR (verificación online)
// NOTA: Esta URL es un placeholder; debe apuntar a una página pública real.
$verification_url = "https://tiendaslistto.cl/erp/module/ver_cotizacion.php?id=" . $invoice['id'];
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($verification_url);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Solicitud de Cotización #<?= htmlspecialchars($invoice['quotation_number']) ?></title> 
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
        <h1>Solicitud de Cotización #<?= htmlspecialchars($invoice['quotation_number']) ?></h1> 
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
            <p><strong>Fecha de Cotización:</strong> <?= date("d/m/Y", strtotime($invoice['date'])) ?></p>
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
                    <th>Valor Neto (Unitario)</th>
                    <th>Total Neto</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($items as $index => $item): ?>
                <tr style="page-break-inside: avoid !important; break-inside: avoid !important;">
                    <td><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($item['final_product_code']) ?></td>
                    <td>
                        <?= htmlspecialchars($item['final_product_name']) ?>
                        <?php if ($item['product_id'] === null): ?>
                            <span class="badge bg-secondary ms-2" style="font-size: 0.7em;">(Producto Nuevo)</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $item['quantity'] ?></td>
                    <td>$<?= number_format($item['cost_price'], 0, ',', '.') ?></td>
                    <td>$<?= number_format($item['quantity'] * $item['cost_price'], 0, ',', '.') ?></td>
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
                Escanee este código para validar esta cotización en línea:<br>
                <a href="<?= $verification_url ?>" target="_blank"><?= $verification_url ?></a>
            </p>
        </div>
    </div>

    <div class="col-md-6">
        <div class="totals float-md-end">
            <table>
                <tr>
                    <td class="label">Subtotal Neto:</td>
                    <td class="amount">$<?= number_format($subtotal, 0, ',', '.') ?></td>
                </tr>
                <tr>
                    <td class="label">IVA (<?= (IVA_RATE * 100) ?>%):</td>
                    <td class="amount">$<?= number_format($iva, 0, ',', '.') ?></td>
                </tr>
                <tr>
                    <td class="label">Total Cotización:</td>
                    <td class="amount fw-bold text-success">$<?= number_format($total, 0, ',', '.') ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>


    <div class="invoice-footer">
        <p>Esta cotización fue generada automáticamente por <strong>Listto! ERP</strong>.</p> 
        <p>Para más información, contáctenos a <a href="mailto:contacto@tiendaslistto.cl">contacto@tiendaslistto.cl</a></p>
        <p>Teléfono: +56920210349 - tiendaslistto.cl</a></p>
        
    </div>
</div>

<script>
function exportPDF() {
    const element = document.getElementById('invoice');
    const opt = {
        margin:     0.5,
        // Nomenclatura ajustada en el nombre del archivo
        filename:   'COTIZACION_<?= $invoice['quotation_number'] ?>.pdf', 
        image:      { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF:      { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    html2pdf().from(element).set(opt).save();
}
</script>
</body>
</html>