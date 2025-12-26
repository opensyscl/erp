<?php
/**
 * dashboard.php (VERSIÓN FINAL UNIFICADA)
 * - Corrección de Warnings en fechas.
 * - Navegación simplificada (sin barra de tabs duplicada).
 * - Gráfico de Top Productos mejorado (Doble eje).
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// AJUSTA esta ruta si tu config.php está en otro lado
require_once __DIR__ . "/../config.php"; 

// ----------------------
// Autenticación
// ----------------------
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header("Location: login.php");
    exit;
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];
$system_version = 'v2.1-final';

// ----------------------
// Crear tabla de audit/log y columna admin_note
// ----------------------
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS order_status_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            old_status VARCHAR(100) NULL,
            new_status VARCHAR(100) NOT NULL,
            changed_by INT NULL,
            changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            note TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e) {
    error_log("DB Warning (Log table): " . $e->getMessage());
}

try {
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'admin_note'");
    $stmtCheck->execute();
    if ($stmtCheck->fetchColumn() == 0) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN admin_note TEXT NULL");
    }
} catch (Exception $e) {
    error_log("DB Warning (admin_note column): " . $e->getMessage());
}

// ----------------------
// Helpers
// ----------------------
function jsonResponse($arr) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

function sendOrderEmailSimple($to, $subject, $htmlBody) {
    $from = 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'example.com');
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Tienda <{$from}>\r\n";
    if (function_exists('mail')) {
        return mail($to, $subject, $htmlBody, $headers);
    }
    return false;
}

// ----------------------
// AJAX endpoints (POST actions)
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];

    // (Actualizar estado AJAX)
    if ($action === 'update_status') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($CSRF, $token)) jsonResponse(['success'=>false,'error'=>'Token CSRF inválido']);

        $order_id = intval($_POST['order_id'] ?? 0);
        $new_status = trim($_POST['new_status'] ?? '');

        $allowed = ['Pendiente','En proceso','Enviado','Cancelado','Completado'];
        if (!in_array($new_status, $allowed)) jsonResponse(['success'=>false,'error'=>'Estado no permitido']);

        $stmt = $pdo->prepare("SELECT status, customer_id, total FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $ord = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ord) jsonResponse(['success'=>false,'error'=>'Pedido no encontrado']);

        $old_status = $ord['status'];
        if ($old_status === $new_status) jsonResponse(['success'=>true,'message'=>'Sin cambios']);

        try {
            $pdo->beginTransaction();
            $stmtU = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmtU->execute([$new_status, $order_id]);

            $stmtLog = $pdo->prepare("INSERT INTO order_status_log (order_id, old_status, new_status, changed_by) VALUES (?, ?, ?, ?)");
            $stmtLog->execute([$order_id, $old_status, $new_status, $user['id']]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(['success'=>false,'error'=>'Error al actualizar: '.$e->getMessage()]);
        }

        if ($new_status === 'Enviado') {
            $stmtC = $pdo->prepare("SELECT c.email, c.name FROM customers c INNER JOIN orders o ON o.customer_id = c.id WHERE o.id = ?");
            $stmtC->execute([$order_id]);
            $c = $stmtC->fetch(PDO::FETCH_ASSOC);
            if ($c && !empty($c['email'])) {
                $to = $c['email'];
                $subject = "Tu pedido #{$order_id} ha sido enviado";
                $html = "<p>Hola " . htmlspecialchars($c['name']) . ",</p>";
                $html .= "<p>Tu pedido <strong>#" . $order_id . "</strong> ha sido marcado como <strong>Enviado</strong>.</p>";
                $html .= "<p>Pronto recibirás más información de entrega.</p>";
                $html .= "<p>Gracias por comprar con nosotros.</p>";
                $sent = sendOrderEmailSimple($to, $subject, $html);
                try {
                    $pdo->prepare("UPDATE order_status_log SET note = ? WHERE order_id = ? AND new_status = ? ORDER BY changed_at DESC LIMIT 1")
                        ->execute([ $sent ? 'Notificación por email enviada' : 'Intento de notificación por email fallido', $order_id, $new_status ]);
                } catch (Exception $e) { /* ignore */ }
            }
        }

        // recalcular counts y devolverlos a JS
        $rows = $pdo->query("SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
        $resCounts = [
            'total' => array_sum($rows),
            'Pendiente' => (int)($rows['Pendiente'] ?? 0),
            'En proceso' => (int)($rows['En proceso'] ?? 0),
            'Enviado' => (int)($rows['Enviado'] ?? 0),
            'Completado' => (int)($rows['Completado'] ?? 0),
            'Cancelado' => (int)($rows['Cancelado'] ?? 0),
        ];
        jsonResponse(['success'=>true,'counts'=>$resCounts]);
    }

    // (Guardar nota interna AJAX)
    if ($action === 'save_note') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($CSRF, $token)) jsonResponse(['success'=>false,'error'=>'Token CSRF inválido']);
        $order_id = intval($_POST['order_id'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        try {
            $stmt = $pdo->prepare("UPDATE orders SET admin_note = ? WHERE id = ?");
            $stmt->execute([$note ?: null, $order_id]);
            $pdo->prepare("INSERT INTO order_status_log (order_id, old_status, new_status, changed_by, note) VALUES (?, ?, ?, ?, ?)")
                ->execute([$order_id, null, null, $user['id'], "Nota admin actualizada"]);
            jsonResponse(['success'=>true]);
        } catch (Exception $e) {
            jsonResponse(['success'=>false,'error'=>$e->getMessage()]);
        }
    }

    jsonResponse(['success'=>false,'error'=>'Acción desconocida']);
}

// ----------------------
// AJAX GET: Detalles
// ----------------------
if (isset($_GET['ajax'])) {
    if ($_GET['ajax'] === 'order_details' && isset($_GET['id'])) {
        $order_id = intval($_GET['id']);
        $stmt = $pdo->prepare("SELECT o.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, c.address AS customer_address FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) jsonResponse(['success'=>false,'error'=>'Orden no encontrada']);

        $stmt = $pdo->prepare("SELECT oi.*, p.name AS product_name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT osl.*, u.username FROM order_status_log osl LEFT JOIN users u ON osl.changed_by = u.id WHERE osl.order_id = ? ORDER BY osl.changed_at DESC");
        $stmt->execute([$order_id]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse(['success'=>true,'order'=>$order,'items'=>$items,'logs'=>$logs]);
    }

    if ($_GET['ajax'] === 'client_details' && isset($_GET['id'])) {
        $client_id = intval($_GET['id']);
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$client_id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$client) jsonResponse(['success'=>false,'error'=>'Cliente no encontrado']);

        $stmt = $pdo->prepare("SELECT o.id,o.total,o.status,o.created_at FROM orders o WHERE o.customer_id = ? ORDER BY o.created_at DESC");
        $stmt->execute([$client_id]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse(['success'=>true,'client'=>$client,'orders'=>$orders]);
    }
}

// ----------------------
// Lógica para DataTables de Pedidos (Orders)
// ----------------------
$filter_status = $_GET['filter_status'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$where = [];
$params = [];

if ($filter_status !== 'all' && in_array($filter_status, ['Pendiente','En proceso','Enviado','Cancelado','Completado'])) {
    $where[] = "o.status = ?";
    $params[] = $filter_status;
}

if (!empty($start_date)) {
    $where[] = "o.created_at >= ?";
    $params[] = $start_date . " 00:00:00";
}
if (!empty($end_date)) {
    $where[] = "o.created_at <= ?";
    $params[] = $end_date . " 23:59:59";
}

$where_sql = "";
if (!empty($where)) $where_sql = "WHERE " . implode(" AND ", $where);

$stmtOrders = $pdo->prepare("
    SELECT o.id, o.customer_id, o.total, o.status, o.created_at, c.name AS customer_name
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    $where_sql
    ORDER BY o.created_at DESC
");
$stmtOrders->execute($params);
$orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

// ----------------------
// Datos para la página (KPIs y gráficos)
// ----------------------

// counts globales
$rows = $pdo->query("SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$counts = [
    'total' => array_sum($rows),
    'Pendiente' => (int)($rows['Pendiente'] ?? 0),
    'En proceso' => (int)($rows['En proceso'] ?? 0),
    'Enviado' => (int)($rows['Enviado'] ?? 0),
    'Completado' => (int)($rows['Completado'] ?? 0),
    'Cancelado' => (int)($rows['Cancelado'] ?? 0),
];

// -------------------------------------------------------
// FIX 1: CORRECCIÓN WARNING FECHAS (Últimos 7 días)
// -------------------------------------------------------
$days = 7;
$stmt_days = $pdo->prepare("
    SELECT DATE(created_at) AS d, COUNT(*) AS orders_count, COALESCE(SUM(total),0) AS total_sum
    FROM orders
    WHERE created_at >= (CURDATE() - INTERVAL :days_minus1 DAY)
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at)
");
$stmt_days->bindValue(':days_minus1', $days-1, PDO::PARAM_INT);
$stmt_days->execute();
$last_days = $stmt_days->fetchAll(PDO::FETCH_ASSOC);

$labels_days = [];
$date_keys = []; // Array para guardar YYYY-MM-DD y usar como índice
$orders_per_day = [];
$sales_per_day = [];

for ($i = $days-1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $labels_days[] = date('D d/M', strtotime($d)); 
    $date_keys[] = $d; // Guardamos fecha pura
    $orders_per_day[$d] = 0;
    $sales_per_day[$d] = 0;
}
foreach ($last_days as $row) {
    $orders_per_day[$row['d']] = (int)$row['orders_count'];
    $sales_per_day[$row['d']] = (float)$row['total_sum'];
}

// Usamos date_keys para el map, evitando el Warning de array key undefined
$orders_js = array_map(fn($d) => $orders_per_day[$d], $date_keys);
$sales_js = array_map(fn($d) => $sales_per_day[$d], $date_keys);

// status donut
$status_labels = ['Pendiente','En proceso','Enviado','Completado','Cancelado'];
$status_data = array_map(fn($s) => $counts[$s] ?? 0, $status_labels);
$status_colors = ['#f0ad4e','#17a2b8','#6f42c1','#28a745','#dc3545'];

// Clientes para la tabla
$stmt_clients = $pdo->query("
    SELECT c.id, c.name, c.email, c.phone,
           COUNT(o.id) AS total_orders,
           COALESCE(SUM(oi.quantity),0) AS total_products,
           COALESCE(SUM(o.total),0) AS total_spent
    FROM customers c
    LEFT JOIN orders o ON o.customer_id = c.id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    GROUP BY c.id
    ORDER BY total_spent DESC
");
$clients = $stmt_clients->fetchAll(PDO::FETCH_ASSOC);

// Productos para la tabla y Top 10
$stmt_products = $pdo->query("
    SELECT p.id, p.name, p.barcode, COALESCE(SUM(oi.quantity),0) AS total_sold, COALESCE(SUM(oi.quantity * oi.price),0) AS total_revenue
    FROM products p
    LEFT JOIN order_items oi ON oi.product_id = p.id
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 100
");
$top_products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

$top_labels = array_map(fn($p) => $p['name'], array_slice($top_products, 0, 10));
$top_sold = array_map(fn($p) => (int)$p['total_sold'], array_slice($top_products, 0, 10));

// -------------------------------------------------------
// FIX 3: AGREGAR INGRESOS PARA GRÁFICO MEJORADO
// -------------------------------------------------------
$top_revenue = array_map(fn($p) => (float)$p['total_revenue'], array_slice($top_products, 0, 10));

// Versión del sistema
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
        $stmt->execute();
        $version_result = $stmt->fetchColumn();
        if ($version_result) $system_version = $version_result;
    } catch (PDOException $e) { /* ignore */ }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Mi Sistema</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" type="image/png" href="img/fav.png">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <meta name="csrf-token" content="<?= htmlspecialchars($CSRF) ?>">
    <link rel="stylesheet" href="../assets/css/dash.css">
   
</head>

<body>
<div class="dashboard-wrapper">
    <header class="main-header">
        <div class="header-left">
            <span>Bienvenido, <strong><?= htmlspecialchars($user['username']) ?></strong></span>
        </div>
        <nav class="header-nav">
            <a href="#" id="nav-overview" class="active">Overview</a>
            <a href="#" id="nav-orders">Pedidos</a>
            <a href="#" id="nav-clients">Clientes</a>
            <a href="#" id="nav-products">Productos</a>
        </nav>
        <div class="header-right">
            <span class="app-version"><?= htmlspecialchars($system_version) ?></span>
            <a href="logout.php" class="btn-logout">Cerrar Sesi&oacute;n</a>
        </div>
    </header>

    <main class="container-fluid">
        <div class="page-header-controls">
            <h1 class="page-title">Dashboard Administrativo</h1>
        </div>

<div class="kpi-grid">
    <div class="kpi-card clickable" data-status="all">
        <h3>Total de pedidos</h3>
        <p class="value" id="kpi-total"><?= number_format($counts['total'], 0, ',', '.') ?></p>
    </div>
    
    <div class="kpi-card clickable" data-status="Pendiente">
        <h3>Pedidos Pendientes</h3>
        <p class="value" id="kpi-pending" style="color:#f0ad4e;"><?= number_format($counts['Pendiente'], 0, ',', '.') ?></p>
    </div>
    
    <div class="kpi-card clickable" data-status="En proceso">
        <h3>En Proceso / Enviado</h3>
        <p class="value" id="kpi-processing" style="color:#17a2b8;"><?= number_format($counts['En proceso'] + $counts['Enviado'], 0, ',', '.') ?></p>
    </div>
    
    <div class="kpi-card clickable" data-status="Completado">
        <h3>Pedidos Completados</h3>
        <p class="value" id="kpi-completed" style="color:#28a745;"><?= number_format($counts['Completado'], 0, ',', '.') ?></p>
    </div>
</div>

        <div class="tab-content" id="mainTabsContent">

            <div class="tab-pane fade show active" id="pane-overview" role="tabpanel">
                
                <div class="kpi-grid" style="grid-template-columns: 2fr 1fr;">
                    <div class="content-card">
                        <h2>Ventas (Últimos 7 días)</h2>
                        <div style="height: 350px;"><canvas id="salesChart"></canvas></div>
                    </div>
                    <div class="content-card">
                        <h2>Distribución de Estados (Total)</h2>
                        <div style="height: 350px;"><canvas id="statusChart"></canvas></div>
                    </div>
                </div>

                <div class="content-card">
                    <h2>Top 10 Productos Más Vendidos (Histórico)</h2>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="table-responsive">
                                <table id="topProductsTable" class="table table-striped table-hover">
                                    <thead><tr><th>Producto</th><th>Vendidos</th><th>Ingresos</th></tr></thead>
                                    <tbody>
                                    <?php foreach (array_slice($top_products, 0, 10) as $p): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($p['name']) ?></td>
                                            <td><?= number_format($p['total_sold'], 0, ',', '.') ?></td>
                                            <td>$<?= number_format($p['total_revenue'], 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-6 d-flex align-items-center justify-content-center">
                            <div style="position: relative; width: 100%; height: 500px;"><canvas id="topProductsChart"></canvas></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="pane-orders" role="tabpanel">
                <div class="content-card">
                    <h2>Gestión de Pedidos</h2>
                    <form id="orders-filter-form" class="row g-3 filter-row mb-4" method="GET" action="">
                        <input type="hidden" name="page" value="dashboard">
                        <div class="col-auto"><label class="form-label">Desde</label><input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>"></div>
                        <div class="col-auto"><label class="form-label">Hasta</label><input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>"></div>
                        <div class="col-auto">
                            <label class="form-label">Estado</label>
                            <select name="filter_status" class="form-select">
                                <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>Todos</option>
                                <?php foreach ($status_labels as $state): ?>
                                    <option value="<?= $state ?>" <?= $filter_status === $state ? 'selected' : '' ?>><?= $state ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto align-self-end">
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">Limpiar</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table id="ordersTable" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr><th>ID Pedido</th><th>Cliente</th><th>Total</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr data-order-id="<?= $order['id'] ?>">
                                    <td><?= $order['id'] ?></td>
                                    <td><a href="#" class="client-link" data-client-id="<?= $order['customer_id'] ?>"><?= htmlspecialchars($order['customer_name']) ?></a></td>
                                    <td>$<?= number_format($order['total'],0,',','.') ?></td>
                                    <td>
                                        <select class="form-select form-select-sm status-select" data-order-id="<?= $order['id'] ?>">
                                            <?php foreach ($status_labels as $st): ?>
                                                <option value="<?= $st ?>" <?= $st === $order['status'] ? 'selected' : '' ?>><?= $st ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info details-btn" data-order-id="<?= $order['id'] ?>">Ver</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="pane-clients" role="tabpanel">
                <div class="content-card">
                    <h2>Listado de Clientes</h2>
                    <div class="table-responsive">
                        <table id="clientsTable" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr><th>Cliente</th><th>Email</th><th>Teléfono</th><th>Total Pedidos</th><th>Total Productos</th><th>Total Gastado</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($clients as $c): ?>
                                <tr>
                                    <td><a href="#" class="client-link" data-client-id="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></a></td>
                                    <td><?= htmlspecialchars($c['email']) ?></td>
                                    <td><?= htmlspecialchars($c['phone']) ?></td>
                                    <td><?= (int)$c['total_orders'] ?></td>
                                    <td><?= (int)$c['total_products'] ?></td>
                                    <td>$<?= number_format($c['total_spent'],0,',','.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="pane-products" role="tabpanel">
                <div class="content-card">
                    <h2>Inventario y Productos (Ordenados por ventas)</h2>
                    <div class="table-responsive">
                        <table id="productsTable" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr><th>Producto</th><th>Cod Barras</th><th>Vendidos</th><th>Ingresos</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($top_products as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['name']) ?></td>
                                    <td><?= htmlspecialchars($p['barcode'] ?? '') ?></td>
                                    <td><?= (int)$p['total_sold'] ?></td>
                                    <td>$<?= number_format($p['total_revenue'],0,',','.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div> 
    </main>

    <!--<footer class="footer">
        <!--<?= date("Y"); ?> - Listto! ERP. Todos los derechos reservados.-->
    <!--</footer>-->

    <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
     <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
       <div class="modal-header"><h5 class="modal-title">Detalle Pedido <span id="modal-order-id"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
       <div class="modal-body">
        <div id="order-general" class="mb-3"></div>
        <h6>Productos</h6>
        <div id="order-items-container" class="mb-3"></div>
        <h6>Historial de cambios</h6>
        <div id="order-logs-container" class="mb-3"></div>
        <hr>
        <div class="mb-3">
          <label for="admin-note" class="form-label">Nota interna (solo admin)</label>
          <textarea id="admin-note" class="form-control" rows="3"></textarea>
          <div class="mt-2 text-end">
            <button id="save-note-btn" class="btn btn-sm btn-primary">Guardar nota</button>
          </div>
        </div>
       </div>
       <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
      </div>
     </div>
    </div>
    
    <div class="modal fade" id="clientModal" tabindex="-1" aria-hidden="true">
     <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
       <div class="modal-header"><h5 class="modal-title">Detalle Cliente <span id="modal-client-name"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
       <div class="modal-body">
        <div id="client-general"></div>
        <h6 class="mt-3">Historial de pedidos</h6>
        <div id="client-orders"></div>
       </div>
       <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
      </div>
     </div>
    </div>


    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
    (() => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const statusColors = <?= json_encode($status_colors) ?>;

        // =========================================================================
        // 1. DATA TABLES
        // =========================================================================
        let ordersTable, clientsTable, productsTable, topProductsTable;

        const initDataTables = () => {
             if ($.fn.DataTable.isDataTable('#ordersTable')) $('#ordersTable').DataTable().destroy();
             if ($.fn.DataTable.isDataTable('#clientsTable')) $('#clientsTable').DataTable().destroy();
             if ($.fn.DataTable.isDataTable('#productsTable')) $('#productsTable').DataTable().destroy();
             if ($.fn.DataTable.isDataTable('#topProductsTable')) $('#topProductsTable').DataTable().destroy();

             ordersTable = $('#ordersTable').DataTable({ 
                 language: { url: 'https://cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json' }, 
                 pageLength: 10, 
                 lengthMenu:[5,10,25,50],
                 order: [[4, 'desc']] 
             });
             clientsTable = $('#clientsTable').DataTable({ 
                 language: { url: 'https://cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json' }, 
                 pageLength: 10,
                 order: [[5, 'desc']] 
             });
             productsTable = $('#productsTable').DataTable({ 
                 language: { url: 'https://cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json' }, 
                 pageLength: 10,
                 order: [[2, 'desc']] 
             });
             topProductsTable = $('#topProductsTable').DataTable({ 
                paging:false, searching:false, info:false, ordering: true, order: [[1, 'desc']] 
             });
        }
        
        $(document).ready(initDataTables);

        // =========================================================================
        // 2. GRÁFICOS (Chart.js)
        // =========================================================================
        
        const statusLabels = <?= json_encode($status_labels) ?>;
        const statusData = <?= json_encode($status_data) ?>;
        const dayLabels = <?= json_encode($labels_days) ?>;
        const salesData = <?= json_encode($sales_js) ?>;
        const topLabels = <?= json_encode($top_labels) ?>;
        const topSold = <?= json_encode($top_sold) ?>;
        // FIX 3: VARIABLE NUEVA PARA INGRESOS
        const topRevenue = <?= json_encode($top_revenue) ?>;

        // Chart: status donut
        const ctxStatus = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(ctxStatus, {
            type: 'doughnut',
            data: { 
                labels: statusLabels, 
                datasets: [{ 
                    data: statusData, 
                    backgroundColor: statusColors 
                }] 
            },
            options: { 
                plugins: { legend: { position: 'bottom' } }, 
                responsive: true, 
                maintainAspectRatio: false 
            }
        });

        // Chart: sales line
        const ctxSales = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctxSales, {
            type: 'line',
            data: { 
                labels: dayLabels, 
                datasets: [{ 
                    label: 'Ventas (CLP)', 
                    data: salesData, 
                    tension: 0.3, 
                    fill: true, 
                    backgroundColor:'var(--accent-color-transparent)', 
                    borderColor:'var(--accent-color)', 
                    pointRadius:4 
                }] 
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                scales: { 
                    y: { 
                        ticks: { 
                            callback: v => '$' + Number(v).toLocaleString('es-CL', {minimumFractionDigits: 0})
                        } 
                    } 
                }, 
                plugins: { 
                    legend: { display: false } 
                } 
            }
        });
        
        // FIX 3: GRÁFICO TOP PRODUCTOS MEJORADO (Doble Eje + Degradado)
        const ctxTopProducts = document.getElementById('topProductsChart').getContext('2d');
        
        let gradientBar = ctxTopProducts.createLinearGradient(0, 0, 0, 400);
        gradientBar.addColorStop(0, 'rgba(59, 130, 246, 0.8)');
        gradientBar.addColorStop(1, 'rgba(59, 130, 246, 0.2)');

        const topProductsChart = new Chart(ctxTopProducts, {
            type: 'bar',
            data: {
                labels: topLabels,
                datasets: [
                    {
                        label: 'Ingresos Totales ($)',
                        data: topRevenue,
                        backgroundColor: gradientBar,
                        borderColor: 'var(--accent-color)',
                        borderWidth: 1,
                        borderRadius: 4,
                        order: 2,
                        yAxisID: 'y',
                    },
                    {
                        type: 'line',
                        label: 'Unidades Vendidas',
                        data: topSold,
                        borderColor: '#f0ad4e',
                        backgroundColor: '#f0ad4e',
                        borderWidth: 2,
                        pointStyle: 'circle',
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        order: 1,
                        yAxisID: 'y1',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: { display: true, position: 'top' },
                    tooltip: {
                        usePointStyle: true,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.dataset.yAxisID === 'y') {
                                     label += '$' + context.raw.toLocaleString('es-CL');
                                } else {
                                     label += context.raw;
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { maxRotation: 45, minRotation: 0, font: { size: 11 } }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        title: { display: true, text: 'Ingresos ($)' },
                        ticks: { callback: v => '$' + Number(v).toLocaleString('es-CL', {notation: 'compact'}) }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        title: { display: true, text: 'Unidades' },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });

        // =========================================================================
        // 3. FUNCIONALIDAD AJAX Y MODALES
        // =========================================================================

        const bsDetails = new bootstrap.Modal(document.getElementById('detailsModal'));
        const bsClient = new bootstrap.Modal(document.getElementById('clientModal'));
        let currentOrderId = null;

        const updateKPIs = (counts) => {
            $('#kpi-total').text(counts.total.toLocaleString('es-CL'));
            $('#kpi-pending').text(counts.Pendiente.toLocaleString('es-CL'));
            $('#kpi-processing').text((counts['En proceso'] + counts.Enviado).toLocaleString('es-CL')); 
            $('#kpi-completed').text(counts.Completado.toLocaleString('es-CL'));
            $('#kpi-cancelled').text(counts.Cancelado.toLocaleString('es-CL'));
            statusChart.data.datasets[0].data = statusLabels.map(label => counts[label] || 0);
            statusChart.update();
        }

        $(document).on('change', '.status-select', function(){
            const orderId = $(this).data('order-id');
            const newStatus = this.value;
            if (!confirm(`¿Estás seguro de cambiar el estado del Pedido #${orderId} a "${newStatus}"?`)) {
                const originalStatus = $(this).find('option:selected').data('original-status');
                if (originalStatus) $(this).val(originalStatus);
                return;
            }
            $.post('dashboard.php', {
                action: 'update_status',
                order_id: orderId,
                new_status: newStatus,
                csrf_token: csrfToken
            }, function(response) {
                if (response.success) {
                    alert(`Estado del Pedido #${orderId} actualizado a ${newStatus}.`);
                    $(this).find('option:selected').data('original-status', newStatus);
                    updateKPIs(response.counts);
                } else {
                    alert(`Error al actualizar estado: ${response.error}`);
                }
            }.bind(this), 'json').fail(function() {
                alert('Error de conexión al intentar actualizar el estado.');
            });
        });

        $(document).on('click', '.details-btn', function(){
            currentOrderId = $(this).data('order-id');
            $('#modal-order-id').text(`(#${currentOrderId})`);
            
            $.get('dashboard.php', { ajax: 'order_details', id: currentOrderId }, function(response) {
                if (response.success) {
                    const order = response.order;
                    const items = response.items;
                    const logs = response.logs;
                    
                    let generalHtml = `<p><strong>Cliente:</strong> <a href="#" class="client-link" data-client-id="${order.customer_id}">${order.customer_name}</a> (${order.customer_email})</p>`;
                    generalHtml += `<p><strong>Total:</strong> $${Number(order.total).toLocaleString('es-CL')}</p>`;
                    generalHtml += `<p><strong>Estado:</strong> ${order.status}</p>`;
                    generalHtml += `<p><strong>Dirección:</strong> ${order.customer_address || 'N/A'}</p>`;
                    $('#order-general').html(generalHtml);
                    
                    let itemsHtml = '<table class="table table-sm"><thead><tr><th>Producto</th><th>Cantidad</th><th>Precio Unitario</th></tr></thead><tbody>';
                    items.forEach(item => {
                        itemsHtml += `<tr><td>${item.product_name}</td><td>${item.quantity}</td><td>$${Number(item.price).toLocaleString('es-CL')}</td></tr>`;
                    });
                    itemsHtml += '</tbody></table>';
                    $('#order-items-container').html(itemsHtml);
                    
                    let logsHtml = '<ul class="list-group list-group-flush">';
                    logs.forEach(log => {
                        let note = log.note ? ` (${log.note})` : '';
                        let statusChange = log.old_status && log.new_status ? `Estado cambiado de <strong>${log.old_status}</strong> a <strong>${log.new_status}</strong>` : 'Nota administrativa';
                        logsHtml += `<li class="list-group-item d-flex justify-content-between align-items-start">${statusChange}${note} <span class="badge bg-secondary">${log.username || 'Sistema'} @ ${log.changed_at}</span></li>`;
                    });
                    logsHtml += '</ul>';
                    $('#order-logs-container').html(logsHtml);
                    
                    $('#admin-note').val(order.admin_note || '');

                    bsDetails.show();
                } else {
                    alert(`Error: ${response.error}`);
                }
            }, 'json').fail(() => {
                alert('Error de conexión al obtener detalles del pedido.');
            });
        });
        
        $('#save-note-btn').on('click', function(){
            const note = $('#admin-note').val();
            $.post('dashboard.php', {
                action: 'save_note',
                order_id: currentOrderId,
                note: note,
                csrf_token: csrfToken
            }, function(response) {
                if (response.success) {
                    alert('Nota de administrador guardada.');
                } else {
                    alert(`Error al guardar nota: ${response.error}`);
                }
            }, 'json').fail(() => {
                alert('Error de conexión al guardar la nota.');
            });
        });
        
        $(document).on('click', '.client-link', function(e){
            e.preventDefault();
            const clientId = $(this).data('client-id');
            const clientName = $(this).text();
            $('#modal-client-name').text(`(${clientName})`);
            
            $.get('dashboard.php', { ajax: 'client_details', id: clientId }, function(response) {
                if (response.success) {
                    const client = response.client;
                    const orders = response.orders;
                    
                    let generalHtml = `<p><strong>Email:</strong> ${client.email}</p>`;
                    generalHtml += `<p><strong>Teléfono:</strong> ${client.phone || 'N/A'}</p>`;
                    generalHtml += `<p><strong>Dirección:</strong> ${client.address || 'N/A'}</p>`;
                    $('#client-general').html(generalHtml);

                    let ordersHtml = '<table class="table table-sm"><thead><tr><th>ID Pedido</th><th>Total</th><th>Estado</th><th>Fecha</th></tr></thead><tbody>';
                    orders.forEach(order => {
                        ordersHtml += `<tr><td>${order.id}</td><td>$${Number(order.total).toLocaleString('es-CL')}</td><td>${order.status}</td><td>${order.created_at}</td></tr>`;
                    });
                    ordersHtml += '</tbody></table>';
                    $('#client-orders').html(ordersHtml);

                    bsClient.show();
                } else {
                    alert(`Error: ${response.error}`);
                }
            }, 'json').fail(() => {
                alert('Error de conexión al obtener detalles del cliente.');
            });
        });

        // =========================================================================
        // 4. NAVEGACIÓN (Sin barra de tabs)
        // =========================================================================

        const switchTab = (paneId, navId) => {
           $('.tab-pane').removeClass('show active');
           $('.header-nav a').removeClass('active');
           $(paneId).addClass('show active');
           $(navId).addClass('active');
           $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
        };

        $('#nav-overview').on('click', (e) => { e.preventDefault(); switchTab('#pane-overview', '#nav-overview'); });
        $('#nav-orders').on('click', (e) => { e.preventDefault(); switchTab('#pane-orders', '#nav-orders'); });
        $('#nav-clients').on('click', (e) => { e.preventDefault(); switchTab('#pane-clients', '#nav-clients'); });
        $('#nav-products').on('click', (e) => { e.preventDefault(); switchTab('#pane-products', '#nav-products'); });


// =========================================================================
        // 5. INTERACTIVIDAD DE KPIs (NUEVO)
        // =========================================================================

        // Al hacer clic en una tarjeta KPI
        $('.kpi-card.clickable').on('click', function() {
            const statusToFilter = $(this).data('status');
            
            // 1. Cambiamos visualmente a la pestaña pedidos
            switchTab('#pane-orders', '#nav-orders');
            
            // 2. Establecemos el valor en el select del filtro
            $('select[name="filter_status"]').val(statusToFilter);
            
            // 3. Enviamos el formulario (esto recargará la página con el filtro aplicado)
            $('#orders-filter-form').submit();
        });

        // DETECTAR SI VENIMOS DE UN FILTRO PARA MANTENER LA PESTAÑA "PEDIDOS" ABIERTA
        // Si la URL tiene ?filter_status=..., abrimos la pestaña pedidos automáticamente
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('filter_status')) {
             switchTab('#pane-orders', '#nav-orders');
        }
    })();
    </script>
    </div>
</body>
</html>