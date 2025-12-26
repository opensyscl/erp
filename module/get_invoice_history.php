<?php
// =========================================================
// get_invoice_history.php
// Script llamado por AJAX (fetch) para actualizar el historial.
// =========================================================

// 1. Configuración inicial y conexión a la base de datos
require_once 'config/db.php'; // Asegúrate de incluir tu archivo de conexión
// header('Content-Type: text/html; charset=utf-8'); 
// Opcional: Esto asegura que el navegador interprete la respuesta como HTML

// 2. Obtener el ID del proveedor desde la URL (parámetro GET)
$supplier_id = $_GET['supplier_id'] ?? null;

// Validar que el ID sea un número entero
if (!is_numeric($supplier_id) || $supplier_id <= 0) {
    // Si no hay ID válido, puedes devolver un mensaje de error HTML o una tabla vacía
    echo '<p class="error-message">Error: ID de proveedor no proporcionado o inválido.</p>';
    exit;
}

// 3. Consulta a la base de datos
// *ADAPTA ESTA CONSULTA A TU ESTRUCTURA DE TABLAS*
try {
    $conn = getDbConnection(); // Asume que tienes una función para obtener la conexión
    
    // Consulta para obtener las facturas registradas para ese proveedor (las más recientes primero)
    $sql = "SELECT invoice_number, invoice_date, total_amount, created_at 
            FROM invoices 
            WHERE supplier_id = :supplier_id 
            ORDER BY invoice_date DESC, created_at DESC 
            LIMIT 10"; // Limitar a las últimas 10, por ejemplo

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':supplier_id', $supplier_id, PDO::PARAM_INT);
    $stmt->execute();
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Manejo de errores de base de datos
    error_log("Error de DB en get_invoice_history.php: " . $e->getMessage());
    echo '<p class="error-message">Error al conectar o consultar la base de datos.</p>';
    exit;
}

// 4. Generar el HTML de la tabla
ob_start(); // Inicia el almacenamiento en búfer de salida (para capturar el HTML)
?>

<table class="data-table">
    <thead>
        <tr>
            <th># Factura</th>
            <th>Fecha</th>
            <th>Monto Total (Bruto)</th>
            <th>Fecha Registro</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($invoices)): ?>
            <tr>
                <td colspan="4" style="text-align: center;">No hay facturas registradas para este proveedor.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($invoices as $invoice): ?>
                <tr>
                    <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                    <td><?php echo date('d-m-Y', strtotime($invoice['invoice_date'])); ?></td>
                    <td><?php echo '$' . number_format($invoice['total_amount'], 0, ',', '.'); ?></td>
                    <td><?php echo date('d-m-Y H:i', strtotime($invoice['created_at'])); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php
// 5. Devolver el HTML generado
$html_output = ob_get_clean(); // Obtiene el contenido del búfer y lo limpia
echo $html_output; // Envía el HTML como respuesta a la solicitud AJAX

// Nota CLAVE: Este archivo solo debe emitir el HTML de la tabla.
// No debe haber etiquetas <html>, <body>, <head>, etc.
?>