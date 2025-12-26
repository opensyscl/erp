<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "../config.php";

$invoice_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($invoice_id <= 0) die("Factura no válida.");

// Obtener factura (Factura de Compra)
$stmt = $pdo->prepare("
    SELECT pi.*, s.name AS supplier_name
    FROM purchase_invoices pi
    INNER JOIN suppliers s ON pi.supplier_id = s.id
    WHERE pi.id = ?
");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) die("Factura no encontrada.");

// Obtener productos (Items de la Factura de Compra)
$stmt = $pdo->prepare("
    SELECT pii.*, p.name AS product_name, p.barcode
    FROM purchase_invoice_items pii
    INNER JOIN products p ON pii.product_id = p.id
    WHERE pii.invoice_id = ?
");
$stmt->execute([$invoice_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Constante IVA
$IVA_FACTOR = 1.19;
$IVA_RATE = 0.19;

// Cálculos de totales
$total_quantity = 0;
$total_cost_neto_general = 0.0; 

// Pre-cálculo para la tabla de ítems
foreach ($items as &$item) {
    $total_quantity += $item['quantity'];

    // 1. Costo Bruto Anterior (Calculado a partir del Neto anterior en BD)
    $item['previous_gross_cost'] = (float)$item['previous_cost_price'] * $IVA_FACTOR;
    
    // 2. Costo Bruto Nuevo (Calculado a partir del Neto nuevo guardado en BD)
    // new_cost_price contiene el Costo Neto con decimales exactos
    $item['new_gross_cost'] = (float)$item['new_cost_price'] * $IVA_FACTOR;
    
    // =================================================================================
    // CÓDIGO CORREGIDO: Redondeo del Costo Bruto Unitario Nuevo antes de calcular el Subtotal Bruto
    // =================================================================================
    
    // 2a. Obtener el Costo Bruto Unitario Redondeado al entero superior (ceil)
    $item['new_gross_cost_rounded'] = ceil($item['new_gross_cost']);
    
    // 3. Subtotal Neto del ítem: Cantidad * Nuevo Costo Neto (Se mantiene el cálculo exacto para la contabilidad)
    $item['subtotal_neto'] = (float)$item['quantity'] * (float)$item['new_cost_price'];
    
    // 4. Subtotal Bruto del ítem: Cantidad * Costo Bruto Unitario REDONDEADO
    // Esto es lo que se usará en la columna "Subtotal Bruto"
    $item['subtotal_gross'] = (float)$item['quantity'] * $item['new_gross_cost_rounded'];
    
    // Acumular los totales generales de la factura (Neto)
    $total_cost_neto_general += $item['subtotal_neto'];
}
unset($item); 

// =================================================================
// CÁLCULOS FINALES DE TOTALES (Lógica de contabilidad: Sumar Netos)
// =================================================================

// Los totales finales (Subtotal, IVA, Total) siguen la lógica contable del NETO exacto.
// Si tu factura interna debe cuadrar con el total bruto de la tabla, debes sumar $item['subtotal_gross'] aquí
// y modificar la lógica de cálculo de IVA. Asumo que el TOTAL BRUTO de la factura (en BD) se calculó
// sobre el NETO exacto, por lo que mantengo la lógica contable estándar.

// 1. Subtotal (Neto) - Suma de todos los Subtotales Netos de los ítems
$subtotal = $total_cost_neto_general; 

// 2. IVA - Calculado a partir del Subtotal Neto
$iva = $subtotal * $IVA_RATE; 

// 3. Total Factura (Bruto) - Suma del Neto más el IVA
$total_bruto = $subtotal + $iva;

// URL para QR (verificación online)
$verification_url = "https://tiendaslistto.cl/erp/module/ver_factura_proveedor.php?id=" . $invoice['id'];
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($verification_url);

// Formateador de números para el DETALLE (Muestra 2 decimales)
function format_clp($number) {
    // Usamos round() para evitar problemas de precisión flotante antes de formatear
    return number_format(round($number, 2), 2, ',', '.');
}

// Formateador de números para los TOTALES (Redondeo a entero, como es común en la boleta/factura chilena)
function format_clp_total($number) {
    return number_format(round($number), 0, ',', '.');
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Factura de Proveedor #<?= htmlspecialchars($invoice['invoice_number']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
/* ... (Los estilos se mantienen sin cambios) ... */
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
    background: #34495e; 
    color: #fff;
}
.table th, .table td {
    vertical-align: middle;
    text-align: left;
    font-size: 14px;
    padding: 10px;
}
.totals {
    width: 100%;
    max-width: 400px;
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
.back-link {
    display: inline-block;
    margin-bottom: 20px;
    text-decoration: none;
    color: #0d6efd;
    font-weight: 500;
}
@media print {
    .no-print {
        display: none !important;
    }
}
</style>
</head>
<body>

<div class="btns no-print">
    <a href="proveedores.php?section=proveedores&supplier_id=<?= $invoice['supplier_id'] ?>" class="btn btn-outline-secondary me-auto">
        &larr; Volver
    </a>
    <button class="btn btn-outline-primary" onclick="window.print()">🖨️ Imprimir</button>
    <button class="btn btn-outline-success" onclick="exportPDF()">📄 Exportar PDF</button>
</div>

<div class="invoice-container" id="invoice">
    <div class="invoice-header">
        <img src="../img/Logo1.png" alt="Listto">
        <h1>Factura de Proveedor #<?= htmlspecialchars($invoice['invoice_number']) ?></h1>
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
            <p><strong>Fecha de Emisión:</strong> <?= date("d/m/Y", strtotime($invoice['date'])) ?></p>
            <p><strong>Fecha de Creación ERP:</strong> <?= date("d/m/Y H:i", strtotime($invoice['created_at'])) ?></p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Código</th>
                    <th>Producto</th>
                    <th class="text-center">Cantidad</th>
                    <th class="text-end">Costo Bruto Anterior</th>
                    <th class="text-end">Costo Bruto Nuevo</th>
                    <th class="text-end">Subtotal Bruto</th>
                    <th class="text-end">Subtotal Neto</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($items as $index => $item): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($item['barcode']) ?></td>
                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                    <td class="text-center"><?= $item['quantity'] ?></td>
                    <td class="text-end">$<?= format_clp($item['previous_gross_cost']) ?></td>
                    <td class="text-end">$<?= format_clp($item['new_gross_cost']) ?></td>
                    <td class="text-end">$<?= format_clp($item['subtotal_gross']) ?></td>
                    <td class="text-end">$<?= format_clp($item['subtotal_neto']) ?></td>
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
                    Escanee este código para verificar esta factura en línea:<br>
                    <a href="<?= $verification_url ?>" target="_blank"><?= $verification_url ?></a>
                </p>
            </div>
        </div>

        <div class="col-md-6">
            <div class="totals float-md-end">
                <table class="table table-borderless">
                    <tr>
                        <td class="label">Subtotal (Neto):</td>
                        <td class="amount">$<?= format_clp_total($subtotal) ?></td>
                    </tr>
                    <tr>
                        <td class="label">IVA (19%):</td>
                        <td class="amount">$<?= format_clp_total($iva) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Total Factura (Bruto):</td>
                        <td class="amount fw-bold text-success" style="font-size: 1.2rem;">$<?= format_clp_total($total_bruto) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>


    <div class="invoice-footer">
        <p>Esta factura de proveedor fue registrada y procesada por <strong>Listto! ERP</strong>.</p>
        <p>Para más información, contáctenos a <a href="mailto:contacto@tiendaslistto.cl">contacto@tiendaslistto.cl</a></p>
        <p>Teléfono: +56920210349 - tiendaslistto.cl</p>
    </div>
</div>

<script>
function exportPDF() {
    const element = document.getElementById('invoice');
    const opt = {
        margin:         0.5,
        filename:       'Factura_Proveedor_<?= $invoice['invoice_number'] ?>.pdf',
        image:          { type: 'jpeg', quality: 0.98 },
        html2canvas:    { scale: 2 },
        jsPDF:          { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    html2pdf().from(element).set(opt).save();
}
</script>
</body>
</html>