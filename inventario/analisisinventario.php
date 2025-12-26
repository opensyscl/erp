<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir archivos necesarios.
// NOTA: Asegúrate de que las rutas a estas librerías sean correctas en tu servidor.
require '../config.php';
session_start();

// --- 1. INCLUSIONES DE LIBRERÍAS ---
require '../PhpOffice/PHPMailer/src/Exception.php';
require '../PhpOffice/PHPMailer/src/PHPMailer.php';
require '../PhpOffice/PHPMailer/src/SMTP.php';
require '../PhpOffice/FPDF/fpdf.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
// ----------------------------------------------------------------------
// --- 2. VERIFICACIÓN DE LOGIN Y ROLES (Se mantiene) ---
// ----------------------------------------------------------------------

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if (!isset($pdo)) {
    die('Error fatal: Conexión PDO ($pdo) no disponible.');
}

$current_user_id = $_SESSION['user_id'];
$user_can_access = false;

try {
    $stmt_role = $pdo->prepare("SELECT role, username FROM users WHERE id = ?");
    $stmt_role->execute([$current_user_id]);
    $user_data = $stmt_role->fetch(PDO::FETCH_ASSOC);

    $user_role = $user_data['role'] ?? null;
    $current_username = $user_data['username'] ?? 'Usuario';

    if ($user_role === 'POS1' || $user_role === 'ADMIN') {
        $user_can_access = true;
    }

} catch (PDOException $e) {
    error_log("Error de BD al verificar rol del usuario: " . $e->getMessage());
    header('Location: ../not_authorized.php');
    exit();
}

if (!$user_can_access) {
    header('Location: ../not_authorized.php');
    exit();
}

// --- LÓGICA DE ACCIONES DE BOTONES ---
$is_send_email_action = false;
$is_export_pdf_action = false;

if (isset($_GET['action'])) {
    if ($_GET['action'] === 'send_email') {
        $is_send_email_action = true;
    } elseif ($_GET['action'] === 'export_pdf') {
        $is_export_pdf_action = true;
    }
}


// ----------------------------------------------------------------------
// --- 3. FUNCIONES DE PDF Y CORREO (Se mantienen) ---
// ----------------------------------------------------------------------

/**
 * Genera un PDF general con productos de stock crítico y bajo, ordenado por stock ascendente.
 * @param array $products_data Lista de productos para el reporte.
 * @return string Ruta temporal del archivo PDF.
 */
function generate_pdf_report(array $products_data): string {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    
    // CORRECCIÓN UTF8: Usamos mb_convert_encoding para acentos
    $pdf->Cell(0, 10, mb_convert_encoding('📄 Reporte de Stock Solicitado (Crítico y Bajo)', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C'); 
    
    $pdf->SetFont('Arial', '', 10);
    // CORRECCIÓN UTF8
    $pdf->Cell(0, 5, mb_convert_encoding('Fecha del Reporte: ', 'ISO-8859-1', 'UTF-8') . date('d/m/Y'), 0, 1, 'C'); 
    
    $pdf->Ln(10);

    // Agrupar por proveedor para el PDF solicitado
    $grouped_products = [];
    foreach ($products_data as $product) {
        // 1. Aseguramos que el stock es un número para ordenar, limpiando el formato
        $stock_clean = str_replace(',', '.', str_replace('.', '', $product['stock']));
        $product['stock_numeric'] = (float)$stock_clean; 
        
        $supplier = $product['supplier_name'];
        if ($product['alert_type'] === 'danger' || $product['alert_type'] === 'warning') {
            if (!isset($grouped_products[$supplier])) {
                $grouped_products[$supplier] = [];
            }
            $grouped_products[$supplier][] = $product;
        }
    }
    
    // Contenido del reporte agrupado
    foreach ($grouped_products as $supplier_name => $products) {
        
        // --- 🎯 ORDENAR: Ordenar los productos por 'stock_numeric' de menor a mayor (ASC) ---
        usort($products, function($a, $b) {
            return $a['stock_numeric'] <=> $b['stock_numeric']; 
        });
        // ----------------------------------------------------------------------------------
        
        // Título del Proveedor
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetFillColor(200, 220, 255);
        $pdf->Cell(0, 8, mb_convert_encoding('Proveedor: ' . $supplier_name, 'ISO-8859-1', 'UTF-8'), 1, 1, 'L', true);
        
        // Encabezados de la tabla
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(100, 7, mb_convert_encoding('Producto', 'ISO-8859-1', 'UTF-8'), 1, 0, 'L', true); 
        $pdf->Cell(30, 7, 'Stock', 1, 0, 'C', true);
        $pdf->Cell(30, 7, 'Nivel', 1, 0, 'C', true);
        $pdf->Cell(30, 7, mb_convert_encoding('Días Restantes', 'ISO-8859-1', 'UTF-8'), 1, 1, 'C', true);

        // Contenido de la tabla
        $pdf->SetFont('Arial', '', 10);
        foreach ($products as $product) {
            $level = ($product['alert_type'] === 'danger') ? 
                mb_convert_encoding('CRÍTICO', 'ISO-8859-1', 'UTF-8') : 
                mb_convert_encoding('BAJO', 'ISO-8859-1', 'UTF-8');
            
            $pdf->Cell(100, 6, mb_convert_encoding($product['product_name'], 'ISO-8859-1', 'UTF-8'), 1, 0, 'L');
            $pdf->Cell(30, 6, $product['stock'], 1, 0, 'C');
            $pdf->Cell(30, 6, $level, 1, 0, 'C');
            
            $days_text = $product['days_of_stock_value'] !== 'N/A' ? 
                $product['days_of_stock_value'] . mb_convert_encoding(' días', 'ISO-8859-1', 'UTF-8') : 
                'N/A';
            $pdf->Cell(30, 6, $days_text, 1, 1, 'C');
        }
        $pdf->Ln(5);
    }
    
    // Guardar PDF temporalmente
    $filename = tempnam(sys_get_temp_dir(), 'stock_report_solicitado_') . '.pdf';
    $pdf->Output('F', $filename);
    
    return $filename;
}


/**
 * Envía la notificación consolidada por correo.
 *
 * @param array $products_to_alert Array de productos en alerta (CRÍTICO).
 * @param string $pdf_path Ruta del PDF a adjuntar.
 * @param bool $is_manual_send Si fue disparado manualmente por el usuario.
 * @return bool True si el correo se envía correctamente.
 */
function send_consolidated_stock_notification(array $products_to_alert, string $pdf_path, bool $is_manual_send = false): bool {
    // Credenciales de SMTP proporcionadas por el usuario
    $smtp_host = 'mail.tiendaslistto.cl'; 
    $smtp_user = 'contacto@tiendaslistto.cl'; 
    $smtp_pass = 'TListto.2025'; 
    $recipient_email = 'contacto@tiendaslistto.cl'; 

    // Para el envío diario, solo enviamos si hay productos críticos.
    if (!$is_manual_send && empty($products_to_alert)) {
        return true; 
    }

    // Agrupar los productos por proveedor para el cuerpo del correo
    $grouped_products = [];
    foreach ($products_to_alert as $product) {
        $supplier = $product['supplier_name'];
        if (!isset($grouped_products[$supplier])) {
            $grouped_products[$supplier] = [];
        }
        $grouped_products[$supplier][] = $product;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 0; // Se desactiva la depuración para producción
        $mail->isSMTP();
        $mail->Host      = $smtp_host;
        $mail->SMTPAuth  = true;
        $mail->Username  = $smtp_user;
        $mail->Password  = $smtp_pass; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port      = 465;
        $mail->CharSet   = 'UTF-8';
        $mail->setFrom($smtp_user, 'Listto! ERP Alertas');
        $mail->addAddress($recipient_email, 'Encargado de Compras');

        $mail->isHTML(true);
        
        $subject = ($is_manual_send ? "SOLICITUD: " : "🚨 Reporte Diario: ") . count($products_to_alert) . " Productos con Stock CRÍTICO";
        $mail->Subject = $subject;
        
        // Generación del cuerpo HTML del correo AGRUPADO (Solo críticos)
        $body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .supplier-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 0.9em; margin-bottom: 20px;}
                    .supplier-table th, .supplier-table td { padding: 10px; border: 1px solid #e5e7eb; }
                    .supplier-header { background-color: #dc2626; color: white; text-align: left; font-size: 1.1em;}
                    .alert-danger { color: #b91c1c; font-weight: bold; }
                    .table-header { background-color: #f3f4f6; }
                </style>
            </head>
            <body>
                <h2>🚨 Reporte de Stock CRÍTICO " . ($is_manual_send ? 'Solicitado' : 'Diario') . "</h2>
                <p>Este informe consolida los productos con stock **CRÍTICO** (Stock &lt; 10), agrupados por proveedor.</p>
                <p>Por favor, priorizar la gestión de pedidos para estos artículos.</p>
                ";
        
        foreach ($grouped_products as $supplier_name => $products) {
            $body .= "
                <table class='supplier-table'>
                    <thead>
                        <tr class='supplier-header'>
                            <th colspan='2'>Proveedor: " . htmlspecialchars($supplier_name) . "</th>
                        </tr>
                        <tr class='table-header'>
                            <th>Producto</th>
                            <th style='text-align: center;'>Stock Actual</th>
                        </tr>
                    </thead>
                    <tbody>";
            
            foreach ($products as $product) {
                $body .= "
                    <tr>
                        <td>{$product['product_name']}</td>
                        <td style='text-align: center;' class='alert-danger'>{$product['stock']}</td>
                    </tr>";
            }
            $body .= "
                    </tbody>
                </table>";
        }

        $body .= "
                <p style='margin-top: 25px; font-size: 0.8em; color: #6b7280;'>Este reporte es generado " . ($is_manual_send ? 'bajo demanda' : 'diariamente de forma automática') . ".</p>
            </body>
            </html>";

        $mail->Body    = $body;
        $mail->AltBody = "Reporte de Stock CRÍTICO. Por favor, revise el cuerpo del correo o el PDF adjunto si aplica.";

        // Adjuntar el PDF (Solo si se generó y existe)
        if (!empty($pdf_path) && file_exists($pdf_path)) {
            $mail->addAttachment($pdf_path, 'Stock_Reporte_' . date('Ymd') . '.pdf');
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error al enviar alerta consolidada por correo. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}


// ----------------------------------------------------------------------
// --- 4. OBTENER Y PROCESAR DATOS DE INVENTARIO Y VENTAS (AJUSTADO A NUEVOS KPIS) ---
// ----------------------------------------------------------------------

$selected_supplier = $_GET['supplier_id'] ?? 'all';

// Rango fijo de 90 días para el cálculo de Promedio de Venta Diario de Proveedor.
$timeframe_days_for_avg = 90; 
$start_date_avg = date('Y-m-d', strtotime("-{$timeframe_days_for_avg} days")); 
$end_date_avg = date('Y-m-d');


try {
    // 1. OBTENER DATOS DE PRODUCTOS Y ÚLTIMA COMPRA POR PRODUCTO
    $last_purchase_subquery = "
        SELECT 
            pii.product_id,
            MAX(DATE(pii.created_at)) as last_stock_date
        FROM 
            purchase_invoice_items pii
        GROUP BY 
            pii.product_id
    ";

    // Subconsulta para obtener las ventas DESDE la última compra (detallado por fecha)
    $sold_since_last_purchase_subquery = "
        SELECT
            oi.product_id,
            SUM(oi.quantity) AS total_sold_since_purchase,
            DATE(s.created_at) AS sale_date
        FROM
            sale_items oi
        JOIN
            sales s ON oi.sale_id = s.id
        GROUP BY
            oi.product_id, sale_date
    ";
    
    // Query principal para obtener datos de inventario
    $sql = "
        SELECT
            p.id AS product_id,
            p.name AS product_name,
            p.stock,
            p.cost_price, 
            su.name AS supplier_name,
            su.id AS supplier_id,
            
            -- start_analysis_date: Última compra o '2020-01-01' si no hay registro.
            COALESCE(lps.last_stock_date, '2020-01-01') AS start_analysis_date,
            
            -- Suma de ventas que ocurrieron DESPUÉS o IGUAL a la fecha de análisis (última compra)
            (
                SELECT COALESCE(SUM(ssp.total_sold_since_purchase), 0)
                FROM ({$sold_since_last_purchase_subquery}) ssp
                WHERE 
                    ssp.product_id = p.id AND 
                    ssp.sale_date >= COALESCE(lps.last_stock_date, '2020-01-01')
            ) AS sold_since_last_purchase,
            
            -- Suma de ventas en el rango fijo de 90 días (originalmente para KPI 4 global, ahora usamos el rango de última compra)
            (
                SELECT COALESCE(SUM(ssp.total_sold_since_purchase), 0)
                FROM ({$sold_since_last_purchase_subquery}) ssp
                WHERE 
                    ssp.product_id = p.id AND 
                    ssp.sale_date BETWEEN '{$start_date_avg}' AND '{$end_date_avg}'
            ) AS sold_in_fixed_range
            
        FROM
            products p
        JOIN
            suppliers su ON p.supplier_id = su.id
        LEFT JOIN
            ({$last_purchase_subquery}) lps ON p.id = lps.product_id
        WHERE
            1=1
            " . ($selected_supplier !== 'all' ? "AND su.id = :supplier_id" : "") . "
        ORDER BY
            sold_since_last_purchase DESC, p.name ASC;
    ";

    $stmt = $pdo->prepare($sql);
    
    // Solo vinculamos el supplier_id
    if ($selected_supplier !== 'all') {
        $supplier_id_int = (int)$selected_supplier; 
        $stmt->bindParam(':supplier_id', $supplier_id_int, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $inventory_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. OBTENER PROVEEDORES
    $stmt_suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC");
    $suppliers = $stmt_suppliers->fetchAll(PDO::FETCH_ASSOC);

    // 3. OBTENER FECHA DE ÚLTIMA FACTURA DEL PROVEEDOR SELECCIONADO (KPI 3)
    $last_invoice_date_raw = null;
    $last_invoice_date = 'N/A';
    $is_latest_purchase_today = false; // Bandera para el control de KPIs
    
    if ($selected_supplier !== 'all') {
        $sql_last_invoice = "
            SELECT 
                MAX(DATE(pi.created_at)) 
            FROM 
                purchase_invoices pi
            WHERE 
                pi.supplier_id = :supplier_id;
        ";
        $stmt_last_invoice = $pdo->prepare($sql_last_invoice);
        $stmt_last_invoice->bindParam(':supplier_id', $supplier_id_int, PDO::PARAM_INT);
        $stmt_last_invoice->execute();
        $last_invoice_date_raw = $stmt_last_invoice->fetchColumn();
        
        if ($last_invoice_date_raw) {
            $last_invoice_date = date('d/m/Y', strtotime($last_invoice_date_raw));
            
            // --- 🎯 LÓGICA DE CONTROL DE FECHA: Si la fecha es HOY ---
            if ($last_invoice_date_raw === date('Y-m-d')) {
                $is_latest_purchase_today = true;
            }
        }
    }

} catch (PDOException $e) {
    die('Error de BD al obtener datos de inventario: ' . $e->getMessage() . '<br>SQL Ejecutada: ' . htmlspecialchars($sql)); 
}


// ----------------------------------------------------------------------
// --- 5. PROCESAMIENTO DE DATOS Y NUEVOS CÁLCULOS DE KPIS GLOBALES ---
// ----------------------------------------------------------------------

$products_for_table = [];
$low_stock_alerts = 0;
$critical_stock_alerts = 0;
$total_inventory_cost = 0; 

// Nuevos KPIs de Proveedor (Totales acumulados a nivel de producto para el proveedor seleccionado)
$total_sold_units_since_last_purchase_supplier = 0; // Para KPI 5
$total_sold_in_fixed_range_supplier = 0; // Para KPI 4 (si se usara el rango fijo)

$products_to_alert_all = []; 
$products_to_alert_critical_only = []; 

$email_status_message = '';

foreach ($inventory_data as $product) {
    
    // --- LÓGICA DE CONVERSIÓN DE GRANEL ---
    $is_granel = stripos($product['product_name'], 'Granel') !== false;
    
    $stock = (int)$product['stock'];
    $sold_since_purchase_raw = (int)$product['sold_since_last_purchase']; 
    $sold_in_fixed_range_raw = (int)$product['sold_in_fixed_range'];

    if ($is_granel) {
        $stock = $stock / 1000;
        $sold_since_purchase = $sold_since_purchase_raw / 1000; 
        $sold_in_fixed_range = $sold_in_fixed_range_raw / 1000;
    } else {
        $sold_since_purchase = $sold_since_purchase_raw;
        $sold_in_fixed_range = $sold_in_fixed_range_raw;
    }
    // ------------------------------------
    
    // --- CÁLCULO DE DÍAS DE VENTA PARA LA TABLA ---
    $start_analysis_date = $product['start_analysis_date'];
    $today = new DateTime(date('Y-m-d'));
    
    $purchase_date_obj = new DateTime($start_analysis_date); 
    
    $days_for_projection = $today->diff($purchase_date_obj)->days;
    $days_for_projection = max(1, $days_for_projection); // Mínimo 1 día para evitar división por cero

    $cost = (float)($product['cost_price'] ?? 0); 
    
    // Promedio Diario (basado en la última compra de este producto)
    $avg_daily_sold = $sold_since_purchase > 0 ? 
                          round($sold_since_purchase / $days_for_projection, 2) : 
                          0;
    // ------------------------------------


    // Acumular Totales para KPIs de Proveedor (usa valores convertidos)
    $total_inventory_cost += ($stock * $cost); 
    $total_sold_units_since_last_purchase_supplier += $sold_since_purchase; // Acumulador para KPI 5
    $total_sold_in_fixed_range_supplier += $sold_in_fixed_range; // Acumulador para KPI 4 (usando el rango fijo original)


    $alert_class = 'stock-green';
    $alert_icon = '✅';
    $alert_type = '';
    $days_of_stock_value = 'N/A';
    
    if ($avg_daily_sold > 0) {
        // Redondeo hacia abajo para días completos restantes
        $days_of_stock_value = floor($stock / $avg_daily_sold); 
    }
    
    // Lógica de Alarma: Stock < 40
    if ($stock < 10) {
        $alert_class = 'stock-danger'; 
        $alert_icon = '🚨';
        $low_stock_alerts++;
        $critical_stock_alerts++; 
        $alert_type = 'danger';
        
        // Acumular para envío diario (solo críticos)
        $products_to_alert_critical_only[] = [
            'product_name' => $product['product_name'] . ($is_granel ? ' (Kg)' : ''),
            'supplier_name' => $product['supplier_name'],
            'stock' => number_format($stock, $is_granel ? 2 : 0), 
            'alert_type' => $alert_type,
            'days_of_stock_value' => $days_of_stock_value,
        ];

    } elseif ($stock <= 40 && $stock >= 10) { 
        $alert_class = 'stock-warning'; 
        $alert_icon = '⚠️';
        $low_stock_alerts++;
        $alert_type = 'warning';
    }
    
    // Lógica de Alerta por Correo/PDF Manual: Acumular productos (< 40 y < 10)
    if (!empty($alert_type)) {
        $products_to_alert_all[] = [
            'product_name' => $product['product_name'] . ($is_granel ? ' (Kg)' : ''),
            'supplier_name' => $product['supplier_name'],
            'stock' => number_format($stock, $is_granel ? 2 : 0), 
            'alert_type' => $alert_type,
            'days_of_stock_value' => $days_of_stock_value,
        ];
    }
    
    // Días de Stock Restante (Proyección)
    $days_of_stock = 'N/A';
    
    if ($days_of_stock_value !== 'N/A') {
        if ($days_of_stock_value <= 7) {
            $days_of_stock = "<span style='color: var(--danger-color); font-weight: 600;'>{$days_of_stock_value} d&iacute;as</span>";
        } elseif ($days_of_stock_value <= 14) {
            $days_of_stock = "<span style='color: var(--warning-color);'>{$days_of_stock_value} d&iacute;as</span>";
        } else {
            $days_of_stock = "{$days_of_stock_value} d&iacute;as";
        }
    }

    $products_for_table[] = [
        'id' => $product['product_id'],
        'name' => $product['product_name'] . ($is_granel ? ' (Kg)' : ''), 
        'supplier_name' => $product['supplier_name'],
        'stock' => number_format($stock, $is_granel ? 2 : 0), 
        'alert_class' => $alert_class,
        'alert_icon' => $alert_icon,
        'sold_since_purchase' => number_format($sold_since_purchase, $is_granel ? 2 : 0), 
        'avg_daily_sold' => number_format($avg_daily_sold, 2),
        'days_of_stock_value' => $days_of_stock_value, // Se usa para ordenar en JS
        'days_of_stock' => $days_of_stock,
        'alert_type' => $alert_type, // Se añade para el PDF manual
    ];
}

// --- CÁLCULO DE NUEVOS KPIS GLOBALES (Si se seleccionó un proveedor) ---

$today_date_obj = new DateTime(date('Y-m-d'));

// KPI 4: Promedio de Venta Diario (Prov.)
$avg_daily_supplier_sale = 'N/A';

// KPI 5: Ventas desde Última Compra (Prov.)
$sales_since_last_purchase_supplier = 'N/A';


if ($selected_supplier !== 'all') {
    
    // --- 🎯 LÓGICA CONDICIONAL ---
    if ($is_latest_purchase_today) {
        // Caso 1: Última factura es HOY. No se calcula promedio ni ventas, ya que el rango es nulo.
        $avg_daily_supplier_sale = 'N/A (Recién Surtido)';
        $sales_since_last_purchase_supplier = '0 Un';
        
    } else if ($last_invoice_date_raw) {
        // Caso 2: Última factura hace más de 1 día. Se calculan las métricas.
        
        $last_purchase_date_obj = new DateTime($last_invoice_date_raw);
        $days_since_last_purchase = $today_date_obj->diff($last_purchase_date_obj)->days;
        
        // Aseguramos que el rango sea al menos 1 día si hubo ventas, para evitar división por cero.
        $days_for_kpi_calculation = max(1, $days_since_last_purchase); 
        
        // KPI 4: Promedio de Unidades Vendidas (Diario) del Proveedor (Desde la última factura)
        if ($total_sold_units_since_last_purchase_supplier > 0) {
            $avg_daily_supplier_sale_val = $total_sold_units_since_last_purchase_supplier / $days_for_kpi_calculation;
            $avg_daily_supplier_sale = number_format($avg_daily_supplier_sale_val, 2, ',', '.') . ' Un';
        } else {
            $avg_daily_supplier_sale = '0 Un';
        }
        
        // KPI 5: Ventas del Proveedor desde Última Compra (Total ya acumulado)
        $sales_since_last_purchase_supplier = number_format($total_sold_units_since_last_purchase_supplier, 0, ',', '.') . ' Un';
    }
}
// --------------------------------------------------------------------------------


// --- LÓGICA DE EXPORTACIÓN Y CORREO (Se mantiene) ---
if ($is_export_pdf_action) {
    if (!empty($products_to_alert_all)) {
        $pdf_path = generate_pdf_report($products_to_alert_all);
        
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="Reporte_Stock_'. date('Ymd') . '.pdf"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($pdf_path));
        
        readfile($pdf_path);
        unlink($pdf_path);
        exit;
    } else {
        $email_status_message = '<div style="padding: 10px; background-color: #fef3c7; color: #92400e; border-radius: 5px; margin-bottom: 15px;">⚠️ No se generó el PDF: No hay productos con Stock Bajo (&le; 40) en la selección actual.</div>';
    }
}


$today_date = date('Y-m-d');
$should_send_email = false;

// 1. Envío manual solicitado por el usuario
if ($is_send_email_action) {
    $should_send_email = true;
    $products_for_email = $products_to_alert_critical_only; 
    $is_manual_send = true;
    $pdf_content_to_send = $products_to_alert_critical_only; 
} 
// 2. Envío diario automático (solo si hay críticos y no se ha enviado hoy)
elseif (!empty($products_to_alert_critical_only) && 
        (!isset($_SESSION['last_alert_date']) || $_SESSION['last_alert_date'] !== $today_date)) {
    $should_send_email = true;
    $products_for_email = $products_to_alert_critical_only;
    $is_manual_send = false;
    $pdf_content_to_send = $products_to_alert_critical_only;
}


if ($should_send_email) {
    if (!empty($products_for_email)) {
        
        // 1. Generar PDF (solo con stock < 10)
        $pdf_path = '';
        if (!empty($pdf_content_to_send)) {
            $pdf_path = generate_pdf_report($pdf_content_to_send); 
        }
        
        // 2. Enviar correo con la lista CRÍTICA (< 10) y el PDF adjunto
        $success = send_consolidated_stock_notification($products_for_email, $pdf_path, $is_manual_send);
        
        // 3. Si fue exitoso, actualizamos la fecha (solo si NO es manual)
        if ($success) {
            if ($is_manual_send) {
                $email_status_message = '<div style="padding: 10px; background-color: #d1fae5; color: #065f46; border-radius: 5px; margin-bottom: 15px;">✅ Informe de Stock Cr&iacute;tico enviado exitosamente a contacto@tiendaslistto.cl.</div>';
            } else {
                $_SESSION['last_alert_date'] = $today_date; 
            }
        } else {
            if ($is_manual_send) {
                $email_status_message = '<div style="padding: 10px; background-color: #fee2e2; color: #991b1b; border-radius: 5px; margin-bottom: 15px;">❌ Error al enviar el informe de stock. Revise los logs del servidor.</div>';
            }
        }
        
        // 4. Eliminar el archivo temporal
        if (!empty($pdf_path) && file_exists($pdf_path)) {
            unlink($pdf_path);
        }
    } else if ($is_manual_send) {
          $email_status_message = '<div style="padding: 10px; background-color: #fef3c7; color: #92400e; border-radius: 5px; margin-bottom: 15px;">⚠️ No se envi&oacute; correo: No hay productos con Stock CR&Iacute;TICO (&lt; 10) en este momento.</div>';
    }
}


// Variables de UI
$stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
$stmt->execute();
$system_version = $stmt->fetchColumn();

// Mensaje para el toast de información
$analysis_toast_message = '
    Analizando: <strong>' . count($products_for_table) . '</strong> productos. 
    Las m&eacute;tricas de la tabla se calculan desde la **&uacute;ltima compra de cada producto**.
';

if ($selected_supplier !== 'all') {
    $supplier_name_for_toast = 'Todos';
    foreach ($suppliers as $s) {
        if ($s['id'] == $selected_supplier) {
            $supplier_name_for_toast = $s['name'];
            break;
        }
    }
    $analysis_toast_message .= ' M&eacute;tricas Globales enfocadas en el proveedor: <strong>' . htmlspecialchars($supplier_name_for_toast) . '</strong>.';
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análisis de Inventario - Listto! ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" type="image/png" href="/img/fav.png"> 
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/analisisinventario.css"> 

</head>

<body>

    <header class="main-header">
        <div class="header-left">
            <a href="../launcher.php" class="launcher-icon" title="Ir al Lanzador de Aplicaciones">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="5" cy="5" r="3"/><circle cx="12" cy="5" r="3"/><circle cx="19" cy="5" r="3"/>
                    <circle cx="5" cy="12" r="3"/><circle cx="12" cy="12" r="3"/><circle cx="19" cy="12" r="3"/>
                    <circle cx="5" cy="19" r="3"/><circle cx="12" cy="19" r="3"/><circle cx="19" cy="19" r="3"/>
                </svg>
            </a>
            <span>Hola, <strong><?= htmlspecialchars($current_username); ?></strong></span>
        </div>
        <nav class="header-nav">
            <a href="../inventario.php" >Inventario</a>
            <a href="analisisinventario.php" class="active">Análisis de Inventario</a>
            <a href="conteo_inventario.php" >Conteo Físico</a>
        </nav>
        <div class="header-right">
            <span class="app-version"><?= htmlspecialchars($system_version); ?></span>
            <a href="../logout.php" class="btn-logout">Cerrar Sesi&oacute;n</a>
        </div>
    </header>

    <main class="container">

        <?= $email_status_message; ?>

        <div class="kpi-grid">
            
            <div class="kpi-card <?= $low_stock_alerts > 0 ? 'kpi-card-alert' : ''; ?>">
                <h3>Productos con Stock Bajo (&le; 40)</h3>
                <p class="value"><?= number_format($low_stock_alerts, 0, ',', '.'); ?></p>
            </div>
            
            <div class="kpi-card <?= $critical_stock_alerts > 0 ? 'kpi-card-alert' : ''; ?>">
                <h3>Productos con Stock Crítico (&lt; 10)</h3>
                <p class="value"><?= number_format($critical_stock_alerts, 0, ',', '.'); ?></p>
            </div>
            
            <div class="kpi-card">
                <h3>Fecha &Uacute;ltima Factura (Prov.)</h3>
                <p class="value">
                    <?= $last_invoice_date; ?>
                </p>
            </div>
            
            <div class="kpi-card">
                <h3>Promedio Venta Diario (Prov.)</h3>
                <p class="value">
                    <?= $avg_daily_supplier_sale; ?> 
                </p>
            </div>

            <div class="kpi-card">
                <h3>Ventas desde &Uacute;ltima Compra (Prov.)</h3>
                <p class="value">
                    <?= $sales_since_last_purchase_supplier; ?> 
                </p>
            </div>
            
        </div>

        <div class="page-header-controls">
            <form id="inventory-filter-form" class="filter-controls-group" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="GET">
                <div class="selector-container">
                    <label for="supplier-selector">Proveedor:</label>
                    <select id="supplier-selector" name="supplier_id">
                        <option value="all">Todos los Proveedores</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= htmlspecialchars($supplier['id']); ?>" 
                                <?= $selected_supplier == $supplier['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($supplier['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-filter">Aplicar Filtros</button>
                
                <button type="submit" name="action" value="send_email" class="btn-filter-test">
                    Enviar Informe (Crítico)
                </button>
                <button type="submit" name="action" value="export_pdf" class="btn-filter-test">
                    Descargar Reporte (Bajo y Cr&iacute;tico)
                </button>
            </form>
        </div>
        
        <p class="filter-toast">
            <?= $analysis_toast_message; ?>
        </p>

        <div class="content-card">
            <div class="table-header-controls">
                <h2>Detalle de Inventario y Pedidos</h2>
            </div>
            <div class="table-container">
                <table class="sales-table">
                    <thead>
                        <tr>
                            <th data-sort-column="id">ID</th>
                            <th data-sort-column="name">Producto</th>
                            <th data-sort-column="supplier_name">Proveedor</th>
                            <th data-sort-column="stock" class="sort-desc">Stock Actual</th>
                            
                            <th data-sort-column="sold_since_purchase">Ventas desde &Uacute;ltima Compra</th>
                            
                            <th data-sort-column="avg_daily_sold">Promedio de Ventas Diario</th>
                            
                            <th data-sort-column="days_of_stock_value">D&iacute;as Restantes</th>
                            
                            <th>Alerta</th>
                        </tr>
                    </thead>
                    <tbody id="inventory-table-body">
                        <?php if (empty($products_for_table)): ?>
                            <tr><td colspan="8" style="text-align: center; padding: 2rem; color: #4b5563;">No hay datos de productos.</td></tr>
                        <?php else: ?>
                            <?php 
                                // El PHP renderiza la tabla, el JS la reemplaza al ordenar.
                                foreach ($products_for_table as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['id']); ?></td>
                                    <td><?= htmlspecialchars($product['name']); ?></td>
                                    <td><?= htmlspecialchars($product['supplier_name']); ?></td>
                                    <td class="numeric-cell <?= htmlspecialchars($product['alert_class']); ?>">
                                        <?= htmlspecialchars($product['stock']); ?>
                                    </td>
                                    <td class="numeric-cell"><?= htmlspecialchars($product['sold_since_purchase']); ?></td>
                                    <td class="numeric-cell"><?= htmlspecialchars($product['avg_daily_sold']); ?></td>
                                    <td class="numeric-cell" data-sort-value="<?= htmlspecialchars($product['days_of_stock_value']); ?>">
                                        <?= $product['days_of_stock']; ?>
                                    </td>
                                    <td style="text-align: center; font-size: 1.2rem;"><?= $product['alert_icon']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // La variable inventoryData se llena con los datos de PHP.
        const inventoryData = <?= json_encode($products_for_table); ?>; 

        let currentSortColumn = 'stock';
        let currentSortDirection = 'desc';

        const sortData = (data, column, direction) => {
            // Se incluye 'sold_since_purchase'
            const numericColumns = ['id', 'stock', 'total_sold', 'avg_daily_sold', 'days_of_stock_value', 'sold_since_purchase'];
            const isNumeric = numericColumns.includes(column);

            return data.sort((a, b) => {
                let aVal = a[column];
                let bVal = b[column];
                let comparison = 0;

                if (isNumeric) {
                    // Manejo de 'N/A' y conversión a número (limpieza de formato con comas/puntos)
                    const cleanValA = String(aVal).replace(/\./g, '').replace(/,/g, '.');
                    const cleanValB = String(bVal).replace(/\./g, '').replace(/,/g, '.');

                    let numA = parseFloat(cleanValA) || 0;
                    let numB = parseFloat(cleanValB) || 0;

                    // Manejo de 'N/A' y 0 para Días Restantes (days_of_stock_value)
                    if (column === 'days_of_stock_value') {
                        // N/A o 0 deben ir al final en ASC y al principio en DESC (es decir, como Infinito para ordenar)
                        const isNAA = String(aVal).toUpperCase() === 'N/A' || numA === 0;
                        const isNAB = String(bVal).toUpperCase() === 'N/A' || numB === 0;
                        
                        if (isNAA && isNAB) {
                            comparison = 0;
                        } else if (isNAA) {
                            numA = Infinity;
                        } else if (isNAB) {
                            numB = Infinity;
                        }
                    }

                    comparison = numA - numB;

                } else {
                    comparison = String(aVal).localeCompare(String(bVal));
                }

                return direction === 'asc' ? comparison : -comparison;
            });
        };

        const updateTable = () => {
            const tableBody = document.getElementById('inventory-table-body');
            tableBody.innerHTML = '';

            let sortedData = sortData([...inventoryData], currentSortColumn, currentSortDirection);

            // COLSPAN AJUSTADO A 8
            if (!sortedData || sortedData.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 2rem; color: #4b5563;">No hay datos de productos.</td></tr>`;
                return;
            }

            sortedData.forEach(product => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${product.id}</td>
                    <td>${product.name}</td>
                    <td>${product.supplier_name}</td>
                    <td class="numeric-cell ${product.alert_class}">
                        ${product.stock}
                    </td>
                    <td class="numeric-cell">${product.sold_since_purchase}</td>
                    <td class="numeric-cell">${product.avg_daily_sold}</td>
                    <td class="numeric-cell" data-sort-value="${product.days_of_stock_value}">
                        ${product.days_of_stock}
                    </td>
                    <td style="text-align: center; font-size: 1.2rem;">${product.alert_icon}</td>
                `;
                tableBody.appendChild(row);
            });

            const headers = document.querySelectorAll('.sales-table th');
            headers.forEach(header => header.classList.remove('sort-asc', 'sort-desc'));

            const currentHeader = document.querySelector(`.sales-table th[data-sort-column="${currentSortColumn}"]`);
            if (currentHeader) {
                currentHeader.classList.add(`sort-${currentSortDirection}`);
            }
        };


        const setupTableHeaders = () => {
            const headers = document.querySelectorAll('.sales-table th[data-sort-column]');
            
            headers.forEach(header => {
                header.addEventListener('click', function() {
                    const column = this.getAttribute('data-sort-column');
                    
                    if (currentSortColumn === column) {
                        currentSortDirection = (currentSortDirection === 'asc') ? 'desc' : 'asc';
                    } else {
                        currentSortColumn = column;
                        // Default DESC para numéricas importantes, ASC para el resto
                        const defaultDir = (['stock', 'total_sold', 'avg_daily_sold', 'id', 'days_of_stock_value', 'sold_since_purchase'].includes(column)) ? 'desc' : 'asc';
                        currentSortDirection = defaultDir;
                    }
                    
                    updateTable();
                });
            });
        };

        // --- INITIALIZATION ---
        document.addEventListener('DOMContentLoaded', function() {
            setupTableHeaders();
            updateTable(); 
        });
    </script>
</body>

</html>