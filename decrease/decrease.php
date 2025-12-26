<?php
// Reporte de Errores
/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/

// Incluye configuración de DB y Start Session
require '../config.php';
session_start();


// ----------------------------------------------------------------------
// --- 1. VERIFICACIÓN DE LOGIN Y CONEXIÓN CRÍTICA ---
// ----------------------------------------------------------------------

// 1.1 Redireccionar si el usuario no está logueado (Usamos user_id, necesario para la BD)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// 1.2 Asegurar que la conexión PDO esté lista para el chequeo
if (!isset($pdo)) {
    die('Error fatal: Conexión PDO ($pdo) no disponible para el chequeo de módulos.');
}

$current_user_id = $_SESSION['user_id'];


// ----------------------------------------------------------------------
// --- 2. VALIDACIÓN DE ROL ESPECÍFICO (MÓDULO DE CAJA) ---
// ----------------------------------------------------------------------

$user_can_access = false;
// RUTA CONFIGURADA: Módulo de Registro de Mermas
$module_path = '/erp/decrease/'; 

try {
    // Obtenemos el rol del usuario logueado
    $stmt_role = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_role->execute([$current_user_id]);
    $user_role = $stmt_role->fetchColumn();

    // LÓGICA DE ACCESO: Roles permitidos para el módulo de Caja
    // Uso de in_array para manejar múltiples POS fácilmente
    if (in_array($user_role, ['POS1', 'POS2', 'Admin', 'Manager'])) { 
        $user_can_access = true;
    }

} catch (PDOException $e) {
    // Si falla la BD, por seguridad, denegamos el acceso.
    error_log("Error de BD al verificar rol del usuario: " . $e->getMessage());
    header('Location: ../not_authorized.php');
    exit();
}


// ----------------------------------------------------------------------
// --- 3. VALIDACIÓN GLOBAL DE MÓDULO (GUARDIÁN) ---
// ----------------------------------------------------------------------

// Solo se chequea si ya tiene permiso de rol.
if ($user_can_access) {
    // Se requiere el chequeador (asume que está en includes/module_check.php)
    require '../includes/module_check.php';

    if (!is_module_enabled($module_path)) {
        // Redirigir si el módulo está DESACTIVADO GLOBALMENTE por el admin
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

// --- ACCESO CONCEDIDO: COMIENZA EL CÓDIGO ESPECÍFICO DEL MÓDULO DE CAJA ---
// ...

// Verificar conexión PDO
if (!isset($pdo)) {
    die('Error: PDO connection not established.');
}

// --- CONFIGURACIÓN Y UTILIDADES ---

// Función para generar opciones de meses (Mantiene la lógica original)
$monthOptions = [];
if (class_exists('IntlDateFormatter')) {
    // Configuración para español de Chile (es-CL) o español de España (es-ES) si no está CL
    $locale = 'es_CL';
    if (!extension_loaded('intl') || !class_exists('IntlDateFormatter')) {
        $locale = 'es_ES'; // Fallback
    }

    $formatter = new IntlDateFormatter($locale, IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'America/Santiago', IntlDateFormatter::GREGORIAN, 'MMMM yyyy');
    for ($i = 0; $i < 12; $i++) {
        $date = new DateTime("-$i month");
        $value = $date->format('Y-m');
        $label = $formatter->format($date->getTimestamp());
        $monthOptions[$value] = ucfirst($label);
    }
} else {
    // Fallback sin extensión intl
    $meses = ['January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'];
    for ($i = 0; $i < 12; $i++) {
        $date = new DateTime("-$i month");
        $value = $date->format('Y-m');
        $monthNameEn = $date->format('F');
        $year = $date->format('Y');
        $label = ($meses[$monthNameEn] ?? $monthNameEn) . ' ' . $year;
        $monthOptions[$value] = $label;
    }
}

// --- 2. OBTENER Y VALIDAR EL MES SELECCIONADO PARA FILTRADO ---
$selectedMonthYear = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonthYear)) {
    $selectedMonthYear = date('Y-m');
}
$startOfMonth = date('Y-m-01', strtotime($selectedMonthYear . '-01'));
$endOfMonth = date('Y-m-t', strtotime($selectedMonthYear . '-01'));


// ====================================================================
// --- 1. PROCESAR FORMULARIO DE NUEVO REGISTRO (MERMA) ---
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_decrease') {
    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $supplierId = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    $recordedBy = $_SESSION['user_username'] ?? 'System';
    $errorMessage = '';

    if ($productId && $supplierId && $quantity > 0 && in_array($type, ['vencimiento', 'daño', 'devolucion'])) {
        try {
            $pdo->beginTransaction();

            $stmt_prod = $pdo->prepare("SELECT cost_price, stock, sale_price FROM products WHERE id = ? FOR UPDATE");
            $stmt_prod->execute([$productId]);
            $product = $stmt_prod->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                $errorMessage = "Producto no encontrado.";
            } elseif ($product['stock'] < $quantity) {
                $errorMessage = "La cantidad a registrar ($quantity) excede el stock actual ({$product['stock']}).";
            } else {
                $costPerUnit = $product['cost_price'];
                $totalCostLoss = $quantity * $costPerUnit;

                // 1. Insertar el registro de merma
                $stmt_insert = $pdo->prepare("
                    INSERT INTO decrease_records
                    (product_id, supplier_id, quantity, type, cost_per_unit, total_cost_loss, reason_notes, recorded_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt_insert->execute([$productId, $supplierId, $quantity, $type, $costPerUnit, $totalCostLoss, $notes, $recordedBy]);

                // 2. Actualizar stock y updated_at en products
                $stmt_update = $pdo->prepare("UPDATE products SET stock = stock - ?, updated_at = NOW() WHERE id = ?");
                $stmt_update->execute([$quantity, $productId]);

                $pdo->commit();
                $_SESSION['success_message'] = "Disminución de $quantity unidades registrada y stock actualizado.";
                header('Location: decrease.php?month=' . $selectedMonthYear);
                exit();
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMessage = "Error al registrar la disminución: " . $e->getMessage();
        }
    } else {
        $errorMessage = "Datos incompletos o inválidos para registrar la disminución.";
    }
    
    if ($errorMessage) $_SESSION['error_message'] = $errorMessage;
    header('Location: decrease.php?month=' . $selectedMonthYear);
    exit();
}


// ====================================================================
// --- PROCESAR REINGRESO DE STOCK (REPOSICIÓN CON GESTIÓN DE SALDO) ---
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reingreso_stock') {
    $recordId = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT);
    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $reingresoQuantity = filter_input(INPUT_POST, 'reingreso_quantity', FILTER_VALIDATE_INT);
    $errorMessage = '';

    if ($recordId && $productId && $reingresoQuantity > 0) {
        try {
            $pdo->beginTransaction();

            // 1. Obtener la cantidad mermada PENDIENTE y el costo total asociado
            $stmt_get_record = $pdo->prepare("SELECT quantity, total_cost_loss FROM decrease_records WHERE id = ? FOR UPDATE");
            $stmt_get_record->execute([$recordId]);
            $record = $stmt_get_record->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                $errorMessage = "Registro de merma no encontrado (ID: $recordId).";
            } elseif ($reingresoQuantity > $record['quantity']) {
                $errorMessage = "La cantidad a reingresar ($reingresoQuantity) no puede exceder el saldo mermado ({$record['quantity']}).";
            } else {
                // CORRECCIÓN: Usamos un nombre de variable más claro
                $currentPendingQuantity = $record['quantity']; 
                
                // 2. Aumentar el stock y actualizar updated_at en products
                $stmt_update_stock = $pdo->prepare("UPDATE products SET stock = stock + ?, updated_at = NOW() WHERE id = ?");
                $stmt_update_stock->execute([$reingresoQuantity, $productId]);
                
                // 3. Gestionar el registro de merma
                if ($reingresoQuantity == $currentPendingQuantity) {
                    // Reposición Total: Eliminar el registro
                    $stmt_delete_record = $pdo->prepare("DELETE FROM decrease_records WHERE id = ?");
                    $stmt_delete_record->execute([$recordId]);
                    $message = "Reingreso TOTAL de $reingresoQuantity unidades. Registro de merma ID $recordId eliminado.";
                } else {
                    // Reposición Parcial: Reducir la cantidad en el registro
                    $newQuantity = $currentPendingQuantity - $reingresoQuantity;
                    
                    // Ajustar el costo total de la merma (proporcionalmente)
                    // new_cost_loss = current_cost_loss * (new_quantity / current_pending_quantity)
                    $costPerUnit = $record['total_cost_loss'] / $currentPendingQuantity;
                    $newCostLoss = $costPerUnit * $newQuantity;

                    $stmt_update_record = $pdo->prepare("
                        UPDATE decrease_records 
                        SET quantity = ?, total_cost_loss = ? 
                        WHERE id = ?
                    ");
                    $stmt_update_record->execute([$newQuantity, $newCostLoss, $recordId]);
                    $message = "Reingreso PARCIAL de $reingresoQuantity unidades. Saldo pendiente del registro ID $recordId reducido a $newQuantity unidades.";
                }

                $pdo->commit();
                $_SESSION['success_message'] = $message;
                header('Location: decrease.php?month=' . $selectedMonthYear);
                exit();
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMessage = "Error al reingresar stock: " . $e->getMessage();
        }
    } else {
        $errorMessage = "Datos de reingreso incompletos o inválidos.";
    }
    
    if ($errorMessage) $_SESSION['error_message'] = $errorMessage;
    header('Location: decrease.php?month=' . $selectedMonthYear);
    exit();
}

// --- CÁLCULO DE MÉTRICAS (KPIs) DEL MES SELECCIONADO ---
// KPI 1: Pérdida por Costo (Bruta) y KPI 4: Unidades Disminuidas
$stmt_loss_total = $pdo->prepare("
    SELECT SUM(total_cost_loss) AS total_cost_loss, SUM(quantity) AS total_quantity
    FROM decrease_records
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt_loss_total->execute([$startOfMonth, $endOfMonth]);
$loss_data = $stmt_loss_total->fetch(PDO::FETCH_ASSOC);

$total_cost_loss = $loss_data['total_cost_loss'] ?: 0;
$total_quantity_decreased = $loss_data['total_quantity'] ?: 0;


// KPI 2: Pérdida Potencial (Margen Bruto Perdido)
$stmt_gross_margin_loss = $pdo->prepare("
    SELECT SUM(dr.quantity * (p.sale_price - dr.cost_per_unit)) AS total_margin_loss
    FROM decrease_records dr
    JOIN products p ON dr.product_id = p.id
    WHERE DATE(dr.created_at) BETWEEN ? AND ?
");
$stmt_gross_margin_loss->execute([$startOfMonth, $endOfMonth]);
$total_margin_loss = $stmt_gross_margin_loss->fetchColumn() ?: 0;

// KPI 3: Pérdida Total (Potencial de Venta)
$total_potential_loss = $total_cost_loss + $total_margin_loss;


// --- KPIs ADICIONALES ---

// KPI 5: Número Total de Registros de Merma
$stmt_record_count = $pdo->prepare("
    SELECT COUNT(id) AS record_count
    FROM decrease_records
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt_record_count->execute([$startOfMonth, $endOfMonth]);
$total_records = $stmt_record_count->fetchColumn() ?: 0;

// KPI 6: Costo Promedio por Unidad Perdida
$avg_cost_per_unit_lost = ($total_quantity_decreased > 0) ? $total_cost_loss / $total_quantity_decreased : 0;

// KPI 7: Producto con Mayor Pérdida de Costo
$stmt_top_loss = $pdo->prepare("
    SELECT p.name AS product_name, SUM(dr.total_cost_loss) AS total_loss
    FROM decrease_records dr
    JOIN products p ON dr.product_id = p.id
    WHERE DATE(dr.created_at) BETWEEN ? AND ?
    GROUP BY p.name
    ORDER BY total_loss DESC
    LIMIT 1
");
$stmt_top_loss->execute([$startOfMonth, $endOfMonth]);
$top_losing_product = $stmt_top_loss->fetch(PDO::FETCH_ASSOC);
$top_product_name = $top_losing_product['product_name'] ?? 'N/A';

// KPI 8: % Pérdida de Margen vs. Margen Bruto Total
$stmt_total_margin = $pdo->prepare("
    SELECT SUM(total - cost_of_goods_sold)
    FROM sales
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt_total_margin->execute([$startOfMonth, $endOfMonth]);

$total_margin_real = $stmt_total_margin->fetchColumn() ?: 0;

$margin_base_for_calculation = ($total_margin_real > 0) ? $total_margin_real : 1; 
$margin_base_for_display = $total_margin_real; 

$loss_vs_margin_percentage = ($margin_base_for_calculation > 0) 
    ? ($total_margin_loss / $margin_base_for_calculation) * 100 
    : 0;

$CRITICAL_THRESHOLD = 10; 
$HIGH_THRESHOLD = 5; 

$kpi_loss_status = 'success';
if ($loss_vs_margin_percentage >= $CRITICAL_THRESHOLD) {
    $kpi_loss_status = 'danger';
} elseif ($loss_vs_margin_percentage >= $HIGH_THRESHOLD) {
    $kpi_loss_status = 'warning';
}

$percentage_display = ($margin_base_for_display <= 0 && $total_margin_loss > 0) 
    ? 'N/A' 
    : number_format($loss_vs_margin_percentage, 2, ',', '.') . '%';

if ($margin_base_for_display <= 0 && $total_margin_loss > 0) {
    $kpi_loss_status = 'danger';
}

// KPI 9: Proveedor con Mayor Pérdida de Costo
$stmt_top_supplier_loss = $pdo->prepare("
    SELECT s.name AS supplier_name, SUM(dr.total_cost_loss) AS total_loss
    FROM decrease_records dr
    JOIN suppliers s ON dr.supplier_id = s.id
    WHERE DATE(dr.created_at) BETWEEN ? AND ?
    GROUP BY s.name
    ORDER BY total_loss DESC
    LIMIT 1
");
$stmt_top_supplier_loss->execute([$startOfMonth, $endOfMonth]);
$top_losing_supplier = $stmt_top_supplier_loss->fetch(PDO::FETCH_ASSOC);
$top_supplier_name = $top_losing_supplier['supplier_name'] ?? 'N/A';
$top_supplier_loss_amount = $top_losing_supplier['total_loss'] ?? 0;

// --- OBTENER DATA PARA TABLA/GRÁFICO ---
$stmt_records = $pdo->prepare("
    SELECT
        dr.id, dr.quantity, dr.type, dr.total_cost_loss, dr.created_at, dr.reason_notes,
        dr.recorded_by,
        p.id AS product_id,
        p.name AS product_name, p.barcode, p.sale_price, p.cost_price,
        s.name AS supplier_name
    FROM decrease_records dr
    JOIN products p ON dr.product_id = p.id
    LEFT JOIN suppliers s ON dr.supplier_id = s.id
    WHERE DATE(dr.created_at) BETWEEN ? AND ?
    AND dr.quantity > 0 /* <-- CORRECCIÓN CLAVE: Solo registros pendientes de reingreso */
    ORDER BY dr.created_at DESC;
");
$stmt_records->execute([$startOfMonth, $endOfMonth]);
$decrease_records = $stmt_records->fetchAll(PDO::FETCH_ASSOC);

$stmt_loss_by_type = $pdo->prepare("
    SELECT type, SUM(total_cost_loss) AS loss_sum
    FROM decrease_records
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY type
");
$stmt_loss_by_type->execute([$startOfMonth, $endOfMonth]);
$loss_by_type = $stmt_loss_by_type->fetchAll(PDO::FETCH_KEY_PAIR);

// --- OBTENER VERSIÓN DEL SISTEMA ---
$stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
$stmt->execute();
$system_version = $stmt->fetchColumn() ?? 'v1.0.0';


// --- BÚSQUEDA GLOBAL DE PRODUCTOS (AJAX) ---
if (isset($_GET['action']) && $_GET['action'] === 'search_products') {
    $query = $_GET['query'] ?? '';

    $filteredProducts = [];

    if (strlen(trim($query)) > 0) {
        try {
            $search_term = "%" . $query . "%"; 
            $search_lower = strtolower($search_term); 

            $sql = "
                SELECT
                    p.id,
                    p.name,
                    p.barcode,
                    p.stock,
                    p.cost_price,
                    COALESCE(p.supplier_id, 0) AS supplier_id, /* Asegurar un ID de proveedor válido */
                    COALESCE(p.image_url, 'https://placehold.co/50x50/cccccc/333333?text=N/A') as image_url,
                    COALESCE(s.name, 'Sin Proveedor') AS supplier_name 
                FROM
                    products p
                LEFT JOIN 
                    suppliers s ON p.supplier_id = s.id
                WHERE
                    (LOWER(p.name) LIKE ? OR p.barcode LIKE ?)
                ORDER BY
                    p.name ASC
                LIMIT 10
            ";

            $stmt_search = $pdo->prepare($sql);
            
            $stmt_search->execute([$search_lower, $search_term]); 

            $filteredProducts = $stmt_search->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error al buscar productos (Global Search): " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Database Query Error', 'message' => $e->getMessage(), 'query' => $query]); 
            exit;
        }
    }

    header('Content-Type: application/json');
    echo json_encode($filteredProducts);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Mermas y Devoluciones - Listto! ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" type="image/png" href="/erp/img/fav.png">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" xintegrity="sha512-SnH5WK+bZxgPHs44uWIX+LLMDJz9a9LL33P0vS6U8Qd80Gf3bVz9w8C4/pBfWp2c/W7Gz/PzR6uR9g38l/kQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/decrease.css">
    <style>
        /* Estilos básicos para el botón deshabilitado del reingreso */
        .disabled-btn {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none; /* Asegura que no se dispare el evento click del botón */
        }
    </style>
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
            <span>Hola, <strong><?php echo htmlspecialchars($_SESSION['user_username'] ?? 'Usuario'); ?></strong></span>
        </div>
        <nav class="header-nav">
            <a href="decrease.php" class="active">Mermas y Devoluciones</a>
        </nav>
        <div class="header-right">
            <span class="app-version"><?php echo htmlspecialchars($system_version); ?></span>
            <a href="../logout.php" class="btn-logout">Cerrar Sesi&oacute;n</a>
        </div>
    </header>

    <main class="container">
        <div class="page-header-controls">
            <h1 class="page-title">Gestión de Mermas y Devoluciones</h1>
            <div class="month-selector-container">
                <label for="month-selector">Ver Mes de Reporte:</label>
                <select id="month-selector" onchange="window.location.href = 'decrease.php?month=' + this.value">
                    <?php foreach ($monthOptions as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value) ?>" <?= ($value === $selectedMonthYear) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <div class="kpi-grid kpi-grid-expanded">
            <div class="kpi-card">
                <h3>1. Pérdida por Costo (Bruta)</h3>
                <p class="value danger-color">$<?= number_format($total_cost_loss, 0, ',', '.') ?></p>
                <span class="subtitle">Costo directo de los productos mermados.</span>
            </div>
            <div class="kpi-card">
                <h3>2. Pérdida Potencial (Margen Bruto)</h3>
                <p class="value warning-color">$<?= number_format($total_margin_loss, 0, ',', '.') ?></p>
                <span class="subtitle">Margen que se dejó de ganar por la merma.</span>
            </div>
            <div class="kpi-card accent-card">
                <h3>3. Pérdida Total (Potencial de Venta)</h3>
                <p class="value accent-color-text">$<?= number_format($total_potential_loss, 0, ',', '.') ?></p>
                <span class="subtitle">Costo + Margen (Total que impacta al P&L).</span>
            </div>
            <div class="kpi-card">
                <h3>4. Proveedor con Más Pérdida</h3>
                <p class="value danger-color">$<?= number_format($top_supplier_loss_amount, 0, ',', '.') ?></p>
                <span class="subtitle">Proveedor: <strong><?= htmlspecialchars($top_supplier_name) ?></strong></span>
            </div>

            <div class="kpi-card">
                <h3>5. Unidades Mermadas Totales</h3>
                <p class="value"><?= number_format($total_quantity_decreased, 0, ',', '.') ?> u.</p>
                <span class="subtitle">Total de unidades retiradas del stock.</span>
            </div>
            <div class="kpi-card">
                <h3>6. Producto con Mayor Pérdida</h3>
                <p class="value danger-color">$<?= number_format($top_losing_product['total_loss'] ?? 0, 0, ',', '.') ?></p>
                <span class="subtitle">Producto: <strong><?= htmlspecialchars($top_product_name) ?></strong></span>
            </div>
            <div class="kpi-card">
                <h3>7. Costo Promedio por Unidad</h3>
                <p class="value warning-color">$<?= number_format($avg_cost_per_unit_lost, 0, ',', '.') ?></p>
                <span class="subtitle">Costo unitario promedio de los productos perdidos.</span>
            </div>
        <div class="kpi-card">
            <h3>8. % Merma vs. Margen Bruto</h3> 
            <p class="value <?= $kpi_loss_status ?>-color">
                <?= $percentage_display ?>
                <span class="status-badge <?= $kpi_loss_status ?>">
                    <?php if ($margin_base_for_display <= 0 && $total_margin_loss > 0): ?>
                        CRÍTICO
                    <?php elseif ($loss_vs_margin_percentage >= $CRITICAL_THRESHOLD): // 10% o más ?>
                        ALTO
                    <?php elseif ($loss_vs_margin_percentage > 0): ?>
                        Monitoreo
                    <?php else: ?>
                        Bajo
                    <?php endif; ?>
                </span>
            </p>
            <span class="subtitle">% / Venta Mensual (Base: $<?= number_format($margin_base_for_display, 0, ',', '.') ?>).</span> 
        </div>

            <div class="kpi-card type-kpi">
                <h3>9. Merma por Vencimiento (Costo)</h3>
                <p class="value danger-color">$<?= number_format($loss_by_type['vencimiento'] ?? 0, 0, ',', '.') ?></p>
                <span class="subtitle">Pérdida por productos caducados.</span>
            </div>
            <div class="kpi-card type-kpi">
                <h3>10. Merma por Daño (Costo)</h3>
                <p class="value warning-color">$<?= number_format($loss_by_type['daño'] ?? 0, 0, ',', '.') ?></p>
                <span class="subtitle">Pérdida por rotura o deterioro.</span>
            </div>
            <div class="kpi-card type-kpi">
                <h3>11. Merma por Devolución (Costo)</h3>
                <p class="value success-color">$<?= number_format($loss_by_type['devolucion'] ?? 0, 0, ',', '.') ?></p>
                <span class="subtitle">Pérdida por ítems devueltos al proveedor.</span>
            </div>
            <div class="kpi-card type-kpi">
                <h3>12. Total de Registros</h3>
                <p class="value"><?= number_format($total_records, 0, ',', '.') ?></p>
                <span class="subtitle">Número total de transacciones registradas.</span>
            </div>
        </div>

        <div class="form-and-chart-container">
            <div class="content-card form-card">
                <h2>Registrar Disminución de Stock</h2>
                <form action="decrease.php?month=<?= htmlspecialchars($selectedMonthYear) ?>" method="POST" id="decrease-form">
                    <input type="hidden" name="action" value="record_decrease">
                    <input type="hidden" name="product_id" id="product-id-input" required>
                    <input type="hidden" name="supplier_id" id="supplier-id-input" required>

                    <label for="product-search">Buscar Producto (Nombre o Código de Barras)</label>
                    <div style="position: relative;">
                        <input type="text" id="product-search" placeholder="Escriba para buscar un producto..." autocomplete="off">
                        <div id="search-results" class="search-results" style="display: none;"></div>
                    </div>

                    <div id="selected-product-container" class="selected-product-info" style="display: none;">
                        <strong>Producto:</strong> <span id="product-name-display"></span><br>
                        <strong>Proveedor:</strong> <span id="product-supplier-display"></span><br>
                        <strong>Stock Disponible:</strong> <span id="product-stock-display"></span> |
                        <strong>Costo Unitario:</strong> <span id="product-cost-display"></span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="quantity">Cantidad a Disminuir</label>
                            <input type="number" name="quantity" id="quantity" min="1" required disabled>
                        </div>
                        <div class="form-group">
                            <label for="type">Razón</label>
                            <select name="type" id="type" required disabled>
                                <option value="" disabled selected>Seleccione una razón</option>
                                <option value="vencimiento">Vencimiento</option>
                                <option value="daño">Daño</option>
                                <option value="devolucion">Devolución a Proveedor</option>
                            </select>
                        </div>
                    </div>

                    <label for="notes">Notas de la Merma (Opcional)</label>
                    <textarea name="notes" id="notes" rows="2" disabled></textarea>

                    <button type="submit" class="btn-submit" disabled id="submit-button">Registrar Merma y Descontar Stock</button>
                </form>
            </div>

            <div class="content-card chart-card">
                <h2>Distribución de Pérdida por Razón (Costo)</h2>
                <div style="height: 100%;">
                    <canvas id="lossChart"></canvas>
                </div>
            </div>
        </div>

        <div class="content-card">
            <h2>Registros de Disminución Recientes (<?= htmlspecialchars($monthOptions[$selectedMonthYear] ?? 'Mes') ?>)</h2>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Producto</th>
                            <th>Proveedor</th>
                            <th class="numeric-cell">Cantidad Pendiente</th>
                            <th>Tipo</th>
                            <th class="numeric-cell">Costo Perdido</th>
                            <th>Notas</th>
                            <th>Registrado</th>
                            <th>Fecha/Hora</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($decrease_records)): ?>
                            <tr><td colspan="10" style="text-align: center; padding: 1rem;">No hay registros de disminución de stock pendientes de reingreso para este mes.</td></tr>
                        <?php else: ?>
                            <?php foreach ($decrease_records as $record): ?>
                                <tr>
                                    <td><?= $record['id'] ?></td>
                                    <td><?= htmlspecialchars($record['product_name']) ?></td>
                                    <td><?= htmlspecialchars($record['supplier_name']) ?></td>
                                    <td class="numeric-cell danger-text"><?= number_format($record['quantity'], 0, ',', '.') ?></td>
                                    <td><?= htmlspecialchars(ucfirst($record['type'])) ?></td>
                                    <td class="numeric-cell danger-text">$<?= number_format($record['total_cost_loss'], 0, ',', '.') ?></td>
                                    <td><?= htmlspecialchars(substr($record['reason_notes'] ?: 'N/A', 0, 50)) ?></td>
                                    <td><?= htmlspecialchars($record['recorded_by']) ?></td>
                                    <td><?= date('d-m-Y H:i', strtotime($record['created_at'])) ?></td>
                                    <td>
                                        <button 
                                            class="btn-action reingreso-btn <?= ($record['quantity'] <= 0) ? 'disabled-btn' : '' ?>" 
                                            data-id="<?= $record['id'] ?>"
                                            data-product-id="<?= $record['product_id'] ?>" data-product="<?= htmlspecialchars($record['product_name']) ?>"
                                            data-maxqty="<?= $record['quantity'] ?>"
                                            title="Reingresar stock repuesto por proveedor"
                                            <?= ($record['quantity'] <= 0) ? 'disabled' : '' ?>>
                                            <i class="fas fa-undo-alt"></i> Reingreso
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="reingreso-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h2>Reingresar Stock por Reposición</h2>
            <p>Complete el formulario para devolver unidades al stock disponible.</p>
            <div id="reingreso-info" class="selected-product-info" style="margin-bottom: 1rem;">
                <strong>Merma ID:</strong> <span id="modal-record-id"></span><br>
                <strong>Producto:</strong> <span id="modal-product-name"></span><br>
                <strong>Cantidad Mermada Pendiente:</strong> <span id="modal-original-qty" class="danger-color"></span>
            </div>
            <form action="decrease.php?month=<?= htmlspecialchars($selectedMonthYear) ?>" method="POST" id="reingreso-form">
                <input type="hidden" name="action" value="reingreso_stock">
                <input type="hidden" name="record_id" id="reingreso-record-id" required>
                <input type="hidden" name="product_id" id="reingreso-product-id" required>

                <label for="reingreso-quantity">Cantidad a Reingresar (Unidades)</label>
                <input type="number" name="reingreso_quantity" id="reingreso-quantity" min="1" required>

                <label for="reingreso-notes">Notas (Opcional, Ej: N° Guía de Reposición)</label>
                <textarea name="reingreso_notes" id="reingreso-notes" rows="2"></textarea>

                <button type="submit" class="btn-submit" id="modal-submit-button">Confirmar Reingreso de Stock</button>
            </form>
        </div>
    </div>

    <script>
        const formatCurrency = (amount) => {
            return parseFloat(amount).toLocaleString('es-CL', {
                style: 'currency',
                currency: 'CLP',
                minimumFractionDigits: 0
            });
        };

        // --- REFERENCIAS DOM ---
        const searchInput = document.getElementById('product-search');
        const resultsDiv = document.getElementById('search-results');
        const productIdInput = document.getElementById('product-id-input');
        const supplierIdInput = document.getElementById('supplier-id-input');
        const selectedContainer = document.getElementById('selected-product-container');
        const supplierDisplay = document.getElementById('product-supplier-display');
        const quantityInput = document.getElementById('quantity');
        const typeSelect = document.getElementById('type');
        const notesTextarea = document.getElementById('notes');
        const submitButton = document.getElementById('submit-button');

        // Estado del formulario
        let selectedProduct = null;
        let maxStock = 0;

        const resetFormState = () => {
            selectedProduct = null;
            productIdInput.value = '';
            supplierIdInput.value = '';

            document.getElementById('product-name-display').textContent = '';
            supplierDisplay.textContent = '';
            document.getElementById('product-stock-display').textContent = '';
            document.getElementById('product-cost-display').textContent = '';
            
            selectedContainer.style.display = 'none';
            resultsDiv.style.display = 'none';
            searchInput.value = ''; 

            quantityInput.disabled = true;
            typeSelect.disabled = true;
            notesTextarea.disabled = true;
            submitButton.disabled = true;

            quantityInput.value = '';
            typeSelect.value = '';
            notesTextarea.value = '';
            quantityInput.classList.remove('input-error');
        };

        // Función para seleccionar un producto de la búsqueda
        const selectProduct = (product) => {
            selectedProduct = product;
            maxStock = product.stock;

            productIdInput.value = product.id;
            supplierIdInput.value = product.supplier_id;

            document.getElementById('product-name-display').textContent = product.name;
            supplierDisplay.textContent = product.supplier_name;
            document.getElementById('product-stock-display').textContent = maxStock;
            document.getElementById('product-cost-display').textContent = formatCurrency(product.cost_price);

            selectedContainer.style.display = 'block';
            resultsDiv.style.display = 'none';
            searchInput.value = product.name; 

            // Habilitar campos restantes
            quantityInput.disabled = false;
            typeSelect.disabled = false;
            notesTextarea.disabled = false;
            
            quantityInput.max = maxStock;
            quantityInput.value = (maxStock >= 1) ? 1 : 0; 

            // Si el stock es 0, no permitir registrar merma.
            if (maxStock <= 0) {
                quantityInput.disabled = true;
                typeSelect.disabled = true;
                selectedContainer.style.borderColor = 'var(--danger-color)';
                selectedContainer.style.backgroundColor = 'rgba(239, 68, 68, 0.1)';
            } else {
                selectedContainer.style.borderColor = '';
                selectedContainer.style.backgroundColor = 'var(--accent-color-transparent)';
            }

            validateForm();
        };

        // Función para buscar productos globalmente
        const searchProducts = async (query) => {
            if (query.length < 1) {
                return [];
            }
            // La URL de búsqueda apunta al mismo script con action=search_products
            const apiUrl = `decrease.php?action=search_products&query=${encodeURIComponent(query)}`; 
            try {
                const response = await fetch(apiUrl);
                if (!response.ok) {
                    throw new Error(`Error de red: ${response.status} ${response.statusText}`);
                }
                const products = await response.json(); 
                if (products.error) {
                    console.error('Error del Servidor:', products.message || products.error);
                    return [];
                }
                return products;
            } catch (error) {
                console.error('Error fetching search results:', error);
                return [];
            }
        };

        // Renderiza los resultados de la búsqueda
        const renderResults = (products) => {
            resultsDiv.innerHTML = '';
            resultsDiv.style.display = 'block';

            if (products.length > 0) {
                products.forEach(product => {
                    const item = document.createElement('div');
                    item.classList.add('search-item-detail');
                    item.innerHTML = `
                        <img src="${product.image_url}" alt="${product.name}" class="product-thumb" onerror="this.onerror=null;this.src='https://placehold.co/50x50/cccccc/333333?text=N/A'">
                        <div class="info-group">
                            <span class="product-name">${product.name}</span>
                            <span class="product-supplier">Proveedor: ${product.supplier_name}</span>
                        </div>
                        <span class="product-stock">Stock: <strong>${product.stock}</strong></span>
                    `;
                    item.addEventListener('click', () => selectProduct(product));
                    resultsDiv.appendChild(item);
                });
            } else {
                resultsDiv.innerHTML = `<div class="no-results">No se encontraron productos coincidentes.</div>`;
            }
        };

        // Manejo de la búsqueda de productos
        searchInput.addEventListener('input', async () => {
            const query = searchInput.value.trim();
            
            if (query.length < 1) {
                resetFormState();
                resultsDiv.style.display = 'none';
                return;
            }

            if (searchInput.timer) {
                clearTimeout(searchInput.timer);
            }
            searchInput.timer = setTimeout(async () => {
                // Si ya seleccionamos algo y el texto coincide, no volver a buscar.
                if (!selectedProduct || selectedProduct.name.toLowerCase() !== query.toLowerCase()) { 
                    if(selectedProduct) resetFormState(); // Si el usuario sigue escribiendo, reinicia el estado
                    
                    const products = await searchProducts(query);
                    renderResults(products);
                } else {
                    resultsDiv.style.display = 'none';
                }
            }, 300);
        });
        
        // Validación de stock y formulario
        quantityInput.addEventListener('input', validateForm);
        typeSelect.addEventListener('change', validateForm);

        function validateForm() {
            const quantity = parseInt(quantityInput.value);
            
            if (!selectedProduct) {
                submitButton.disabled = true;
                return;
            }

            const isValidQuantity = quantity > 0 && quantity <= maxStock;
            
            if (isValidQuantity && typeSelect.value) {
                submitButton.disabled = false;
                quantityInput.classList.remove('input-error');
            } else {
                submitButton.disabled = true;
                if (quantity > maxStock) {
                    quantityInput.classList.add('input-error');
                } else {
                    quantityInput.classList.remove('input-error');
                }
            }
        }
        
        // --- LÓGICA DEL MODAL DE REINGRESO ---

        const modal = document.getElementById('reingreso-modal');
        const closeBtn = modal.querySelector('.close-btn');
        const reingresoQtyInput = document.getElementById('reingreso-quantity');

        // Función para abrir el modal
        const openReingresoModal = (recordId, productId, productName, maxQty) => {
            // Rellenar la información en el modal
            document.getElementById('modal-record-id').textContent = recordId;
            document.getElementById('modal-product-name').textContent = productName;
            document.getElementById('modal-original-qty').textContent = maxQty; // Es la cantidad pendiente

            // Rellenar los campos del formulario
            document.getElementById('reingreso-record-id').value = recordId;
            document.getElementById('reingreso-product-id').value = productId;
            reingresoQtyInput.max = maxQty;
            reingresoQtyInput.value = maxQty; 

            // Resetear la validación visual y el estado del botón
            reingresoQtyInput.classList.remove('input-error');
            document.getElementById('modal-submit-button').disabled = false;
            document.getElementById('reingreso-notes').value = ''; 

            modal.style.display = 'flex';

            // Scroll automático hacia la posición del modal
            modal.scrollIntoView({
                behavior: 'smooth', 
                block: 'center'    
            });
        };

        // Función para cerrar el modal
        const closeReingresoModal = () => {
            modal.style.display = 'none';
        };

        // Asignar eventos a los botones de la tabla
        document.addEventListener('click', (event) => {
            const button = event.target.closest('.reingreso-btn');
            if (button) {
                const recordId = button.getAttribute('data-id');
                const productId = button.getAttribute('data-product-id');
                const productName = button.getAttribute('data-product');
                const maxQty = parseInt(button.getAttribute('data-maxqty')); // Cantidad pendiente

                // La lógica PHP ya filtra registros con cantidad 0, pero esta es una doble comprobación de seguridad
                if (maxQty > 0 && productId) { 
                    openReingresoModal(recordId, productId, productName, maxQty);
                } else {
                    // Si llegara a ocurrir este mensaje, indica un fallo en la limpieza de la DB o en el filtro PHP.
                    alert('Este registro ya tiene 0 unidades pendientes de reingreso.');
                }
            }
        });

        // Cerrar modal al hacer click en 'X' o fuera
        closeBtn.addEventListener('click', closeReingresoModal);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeReingresoModal();
            }
        });

        // Validación de cantidad en el modal
        reingresoQtyInput.addEventListener('input', () => {
            const quantity = parseInt(reingresoQtyInput.value);
            const max = parseInt(reingresoQtyInput.max);
            const submitBtn = document.getElementById('modal-submit-button');
            
            if (isNaN(quantity) || quantity <= 0 || quantity > max) {
                reingresoQtyInput.classList.add('input-error');
                submitBtn.disabled = true;
            } else {
                reingresoQtyInput.classList.remove('input-error');
                submitBtn.disabled = false;
            }
        });


        // Ejecutar validación inicial
        document.addEventListener('DOMContentLoaded', function() {
            resetFormState(); 

            // 3. Lógica del Gráfico (Chart.js)
            const lossData = <?= json_encode($loss_by_type, JSON_UNESCAPED_UNICODE); ?>;
            
            const chartLabelsMap = {
                'vencimiento': 'Vencimiento',
                'daño': 'Daño',
                'devolucion': 'Devolución'
            };
            
            const orderedLabels = ['vencimiento', 'daño', 'devolucion'];
            const labels = orderedLabels.map(key => chartLabelsMap[key]);
            const data = orderedLabels.map(key => lossData[key] || 0);

            const backgroundColors = [
                'rgba(239, 68, 68, 0.8)', // Rojo (Vencimiento)
                'rgba(251, 191, 36, 0.8)', // Amarillo/Oro (Daño)
                'rgba(59, 130, 246, 0.8)' // Azul (Devolución)
            ];

            const ctx = document.getElementById('lossChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: backgroundColors,
                        hoverOffset: 10,
                        borderWidth: 2,
                        borderColor: 'white'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? (value / total * 100).toFixed(1) : 0;
                                    return context.label + ': ' + formatCurrency(value) + ` (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>