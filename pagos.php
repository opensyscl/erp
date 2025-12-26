<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config.php';
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_username'])) {
    header('Location: ../login.php');
    exit();
}

// ----------------------------------------------------------------------
// 1. BLOQUE DE LÓGICA AJAX PARA PROCESAR PAGOS Y EDICIÓN
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $invoice_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $action = $_POST['action'];

    if ($invoice_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de factura no válido.']);
        exit;
    }

    try {
        if ($action === 'mark_as_paid') {
            // Marcar como PAGADA
            $stmt = $pdo->prepare("UPDATE purchase_invoices SET is_paid = 1, payment_date = NOW() WHERE id = ? AND is_paid = 0");
            $stmt->execute([$invoice_id]);
            $message = 'Factura N°' . $invoice_id . ' marcada como PAGADA.';
            $new_status = 1;
            $new_amount = null;
        } elseif ($action === 'undo_payment') {
            // Deshacer el pago (marcar como PENDIENTE)
            $stmt = $pdo->prepare("UPDATE purchase_invoices SET is_paid = 0, payment_date = NULL WHERE id = ? AND is_paid = 1");
            $stmt->execute([$invoice_id]);
            $message = 'Pago de Factura N°' . $invoice_id . ' ha sido DESHECHO.';
            $new_status = 0;
            $new_amount = null;
        } elseif ($action === 'edit_amount') {
            // Editar Monto Total
            $new_amount = isset($_POST['new_amount']) ? floatval($_POST['new_amount']) : -1;

            if ($new_amount < 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Monto no válido. Debe ser un número positivo.']);
                exit;
            }

            $new_amount_db = round($new_amount);

            $stmt = $pdo->prepare("UPDATE purchase_invoices SET total_amount = ? WHERE id = ?");
            $stmt->execute([$new_amount_db, $invoice_id]);

            $message = 'Monto de Factura N°' . $invoice_id . ' actualizado a $' . number_format($new_amount_db, 0, ',', '.') . '.';
            $new_status = null;
            $new_amount = $new_amount_db;

        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
            exit;
        }

        if (isset($stmt) && $stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => $message,
                'new_status' => $new_status,
                'payment_date' => $new_status == 1 ? date('Y-m-d H:i:s') : null,
                'new_amount' => $new_amount
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Factura no encontrada o estado/monto sin cambios.']);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    }
    exit;
}

// ----------------------------------------------------------------------
// 2. BLOQUE DE LÓGICA AJAX PARA OBTENER KPIS Y DATOS DE TABLA CON FILTROS (REVISADO)
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_data') {
    header('Content-Type: application/json');

    // Recuperar parámetros de fecha y asegurar que sean NULL si están vacíos
    $start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : null;
    $end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : null;

    // Construir cláusula WHERE para el filtro de fechas
    $date_filter_sql = "";
    $date_params = [];

    if ($start_date) {
        $date_filter_sql .= " AND pi.date >= ?";
        $date_params[] = $start_date;
    }
    if ($end_date) {
        // Aseguramos que se incluye todo el día final agregando un día
        $date_filter_sql .= " AND pi.date <= ?";
        $date_params[] = $end_date;
    }

    try {
        // --- OBTENER DATOS DE FACTURAS PARA LA TABLA ---
        $stmt_invoices_data = $pdo->prepare("
            SELECT
                pi.id,
                pi.invoice_number,
                pi.total_amount,
                pi.is_paid,
                pi.payment_date,
                pi.date AS invoice_date,
                pi.created_at,
                s.name AS supplier_name
            FROM purchase_invoices pi
            JOIN suppliers s ON pi.supplier_id = s.id
            WHERE 1=1 " . $date_filter_sql . "
            ORDER BY pi.created_at DESC;
        ");
        $stmt_invoices_data->execute($date_params);
        $invoices_data_full = $stmt_invoices_data->fetchAll(PDO::FETCH_ASSOC);


        // --- OBTENER KPIS (SUMAS) APLICANDO EL FILTRO DE FECHA ---
        $stmt_total = $pdo->prepare("
            SELECT SUM(total_amount) AS total_invoices_amount,
                   SUM(CASE WHEN is_paid = 1 THEN total_amount ELSE 0 END) AS paid_amount,
                   SUM(CASE WHEN is_paid = 0 THEN total_amount ELSE 0 END) AS pending_amount,
                   COUNT(id) AS total_count,
                   COUNT(CASE WHEN is_paid = 1 THEN id ELSE NULL END) AS paid_count,
                   COUNT(CASE WHEN is_paid = 0 THEN id ELSE NULL END) AS pending_count
            FROM purchase_invoices pi
            WHERE 1=1 " . $date_filter_sql . ";
        ");
        // Reutilizamos $date_params
        $stmt_total->execute($date_params);
        $kpis = $stmt_total->fetch(PDO::FETCH_ASSOC);

        // Monto Pendiente del Mes Actual (se mantiene sin filtro de rango de fecha para su propósito original)
        $startOfMonth = date('Y-m-01');
        $stmt_month_pending = $pdo->prepare("
            SELECT SUM(total_amount) AS month_pending_amount
            FROM purchase_invoices
            WHERE date >= ? AND is_paid = 0
        ");
        $stmt_month_pending->execute([$startOfMonth]);
        $month_pending_amount = $stmt_month_pending->fetchColumn() ?: 0;

        // Combinar todos los resultados en una respuesta JSON
        $response = [
            'success' => true,
            'invoices' => $invoices_data_full,
            'kpis' => [
                'total_invoices_amount' => $kpis['total_invoices_amount'] ?: 0,
                'paid_amount' => $kpis['paid_amount'] ?: 0,
                'pending_amount' => $kpis['pending_amount'] ?: 0,
                'total_count' => $kpis['total_count'] ?: 0,
                'paid_count' => $kpis['paid_count'] ?: 0,
                'pending_count' => $kpis['pending_count'] ?: 0,
                'month_pending_amount' => $month_pending_amount
            ]
        ];

        echo json_encode($response);
        exit;

    } catch (PDOException $e) {
        // MUY IMPORTANTE: SI LLEGA AQUÍ, DEVUELVE UN ERROR VISIBLE
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error fatal de BD. ' . $e->getMessage() . '. SQL: ' . $stmt_invoices_data->queryString]);
        exit;
    }
}


// ----------------------------------------------------------------------
// 3. Lógica para inicialización de variables de PHP para el HTML.
// ----------------------------------------------------------------------
$total_invoices_amount = 0;
$paid_amount = 0;
$pending_amount = 0;
$total_count = 0;
$paid_count = 0;
$pending_count = 0;
$month_pending_amount = 0;
$invoices_data_full = [];


$stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
$stmt->execute();
$system_version = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagos a Proveedores - Mi Sistema</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/pagos.css">
    <link rel="icon" type="image/png" href="../img/fav.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

</head>

<body>

    <header class="main-header">
        <div class="header-left">
            <a href="../launcher.php" class="launcher-icon" title="Ir al Lanzador de Aplicaciones">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="5" cy="5" r="3"/>
                    <circle cx="12" cy="5" r="3"/>
                    <circle cx="19" cy="5" r="3"/>
                    <circle cx="5" cy="12" r="3"/>
                    <circle cx="12" cy="12" r="3"/>
                    <circle cx="19" cy="12" r="3"/>
                    <circle cx="5" cy="19" r="3"/>
                    <circle cx="12" cy="19" r="3"/>
                    <circle cx="19" cy="19" r="3"/>
                </svg>
            </a>
            <span>Hola, <strong><?php echo htmlspecialchars($_SESSION['user_username'] ?? 'Usuario'); ?></strong></span>
        </div>

        <nav class="header-nav">
            <a href="pagos.php" class="active">Gestión de Pagos</a>
        </nav>
        <div class="header-right">
            <span class="app-version"><?php echo htmlspecialchars($system_version); ?></span>
            <a href="../logout.php" class="btn-logout">Cerrar Sesi&oacute;n</a>
        </div>
    </header>

    <main class="container">
        <div id="ajax-message-container" class="message-container"></div>

        <div class="kpi-grid" id="kpi-grid">
            <div class="kpi-card">
                <h3>Total Facturas (Monto)</h3>
                <p class="value" id="total_invoices_amount">$<?= number_format($total_invoices_amount, 0, ',', '.') ?></p>
            </div>

            <div class="kpi-card" style="border-left: 5px solid #10b981;"> <h3>Monto Pagado</h3>
                <p class="value" style="color: #10b981;" id="paid_amount">$<?= number_format($paid_amount, 0, ',', '.') ?></p>
                <span style="font-size: 0.8rem; color: var(--text-secondary);">Facturas: <span id="paid_count"><?= number_format($paid_count, 0, ',', '.') ?></span></span>
            </div>

            <div class="kpi-card" style="border-left: 5px solid var(--danger-color);"> <h3>Monto Pendiente</h3>
                <p class="value" style="color: var(--danger-color);" id="pending_amount">$<?= number_format($pending_amount, 0, ',', '.') ?></p>
                <span style="font-size: 0.8rem; color: var(--text-secondary);">Facturas: <span id="pending_count"><?= number_format($pending_count, 0, ',', '.') ?></span></span>
            </div>

            <div class="kpi-card">
                <h3>Pendiente Este Mes</h3>
                <p class="value projection" id="month_pending_amount">$<?= number_format($month_pending_amount, 0, ',', '.') ?></p>
            </div>
        </div>

        <div class="content-card">
            <div class="table-header-controls">
                <h2>Facturas de Proveedores</h2>
                <div class="table-controls">

                    <div class="date-filter-group" style="display: flex; gap: 10px; align-items: center; margin-right: 20px;">
                        
                        <label for="month-filter">Mes:</label>
                        <select id="month-filter" class="date-input">
                            </select>
                        
                        <label for="start-date">Desde:</label>
                        <input type="date" id="start-date" class="date-input">

                        <label for="end-date">Hasta:</label>
                        <input type="date" id="end-date" class="date-input">

                        <button id="apply-filter-btn" class="action-button btn-primary">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                    </div>
                    <button class="btn-add"><i class="fas fa-plus"></i> Nueva Factura</button>
                    <label for="sort-by">Estado:</label>
                    <select id="status-filter">
                        <option value="all">Todas</option>
                        <option value="pending">Pendientes</option>
                        <option value="paid">Pagadas</option>
                    </select>
                    <label for="limit">Mostrar:</label>
                    <select id="limit">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="all">Todas</option>
                    </select>
                </div>
            </div>
            <div class="table-container">
                <table class="sales-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>N° Factura</th>
                            <th>Proveedor</th>
                            <th>Fecha Emisión</th>
                            <th>Monto Total</th>
                            <th>Estado</th>
                            <th>Fecha Pago</th>
                            <th>Pagar</th>
                            <th>Editar</th>
                        </tr>
                    </thead>
                    <tbody id="invoices-table-body">
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Cargando facturas...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

<div id="editAmountModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="closeModal('editAmountModal')">&times;</span>
        <h2><i class="fas fa-edit"></i> Editar Monto de Factura</h2>
        <div class="form-group">
            <p>Factura ID: <strong id="modal-invoice-id"></strong></p>
            <p>Número: <strong id="modal-invoice-number"></strong></p>
            <p>Proveedor: <strong id="modal-supplier-name"></strong></p>
        </div>
        <div class="form-group">
            <label for="new-amount">Nuevo Monto Total (CLP):</label>
            <input type="text" id="new-amount" placeholder="Ingrese el nuevo monto sin puntos de miles" autocomplete="off">
            <small class="help-text">Monto actual: <span id="modal-current-amount"></span></small>
        </div>
        <div class="modal-actions">
            <button id="cancel-edit" class="action-button btn-secondary" onclick="closeModal('editAmountModal')">
                Cancelar
            </button>
            <button id="confirm-edit-btn" class="action-button btn-primary">
                <i class="fas fa-check"></i>
                Confirmar Edición
            </button>
        </div>
        <input type="hidden" id="current-invoice-id">
    </div>
</div>
<div id="confirmPaidModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="closeModal('confirmPaidModal')">&times;</span>
        <h2><i class="fas fa-check-circle" style="color: var(--success-color);"></i> Confirmar Pago</h2>
        <div class="form-group">
            <p>¿Está seguro de marcar la siguiente factura como **PAGADA**?</p>
            <p>Factura ID: <strong id="paid-modal-invoice-id"></strong></p>
            <p>Monto: <strong id="paid-modal-invoice-amount"></strong></p>
        </div>
        <div class="modal-actions">
            <button class="action-button btn-secondary" onclick="closeModal('confirmPaidModal')">
                Cancelar
            </button>
            <button id="confirm-mark-paid-btn" class="action-button btn-primary" data-id="">
                <i class="fas fa-check"></i>
                Marcar como Pagada
            </button>
        </div>
    </div>
</div>
<div id="confirmUndoModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="closeModal('confirmUndoModal')">&times;</span>
        <h2><i class="fas fa-undo" style="color: var(--danger-color);"></i> Deshacer Pago</h2>
        <div class="form-group">
            <p>¿Está seguro de **DESHACER** el pago de la factura ID:</p>
            <p>Factura ID: <strong id="undo-modal-invoice-id"></strong></p>
        </div>
        <div class="modal-actions">
            <button class="action-button btn-secondary" onclick="closeModal('confirmUndoModal')">
                No, Mantener Pagada
            </button>
            <button id="confirm-undo-paid-btn" class="action-button" style="background-color: var(--danger-color);" data-id="">
                <i class="fas fa-undo"></i>
                Sí, Deshacer Pago
            </button>
        </div>
    </div>
</div>

<?php

// ----------------------------------------------------------------------
// 4. BLOQUE DE SCRIPTS JAVASCRIPT CORREGIDO
// ----------------------------------------------------------------------
?>
<script>
    let invoicesData = [];
    let currentEditingInvoiceId = null;

    const formatCurrency = (amount) => {
        const numberAmount = parseFloat(amount) || 0;
        return numberAmount.toLocaleString('es-CL', {
            style: 'currency',
            currency: 'CLP',
            minimumFractionDigits: 0
        });
    };

    const showAjaxMessage = (message, isError = false) => {
        const container = document.getElementById('ajax-message-container');
        const alertClass = isError ? 'error' : 'success';
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert ${alertClass}`;
        alertDiv.textContent = message;
        container.appendChild(alertDiv);
        setTimeout(() => {
            alertDiv.classList.add('show');
        }, 10);
        setTimeout(() => {
            alertDiv.classList.add('hide');
            alertDiv.addEventListener('transitionend', function() {
                alertDiv.remove();
            }, { once: true });
        }, 4000);
    };

    const updateKPIs = (data) => {
        const totalInvoicesAmount = (data.paid_amount || 0) + (data.pending_amount || 0);
        document.getElementById('total_invoices_amount').textContent = formatCurrency(totalInvoicesAmount);
        document.getElementById('paid_amount').textContent = formatCurrency(data.paid_amount);
        document.getElementById('paid_count').textContent = data.paid_count.toLocaleString('es-CL');
        document.getElementById('pending_amount').textContent = formatCurrency(data.pending_amount);
        document.getElementById('pending_count').textContent = data.pending_count.toLocaleString('es-CL');
        document.getElementById('month_pending_amount').textContent = formatCurrency(data.month_pending_amount);
    };

    const cleanValue = (id) => parseFloat(document.getElementById(id).textContent.replace(/[$.]/g, '').replace(',', '.')) || 0;
    const cleanCount = (id) => parseInt(document.getElementById(id).textContent.replace(/[.]/g, '')) || 0;

    const updateTable = () => {
        const statusFilter = document.getElementById('status-filter').value;
        const limit = document.getElementById('limit').value;
        const tableBody = document.getElementById('invoices-table-body');
        tableBody.innerHTML = '';

        let filteredData = invoicesData.filter(invoice => {
            const isPaid = parseInt(invoice.is_paid) === 1;
            if (statusFilter === 'all') return true;
            if (statusFilter === 'paid') return isPaid;
            if (statusFilter === 'pending') return !isPaid;
        });

        filteredData.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

        let finalData;
        if (limit === 'all') {
            finalData = filteredData;
        } else {
            finalData = filteredData.slice(0, parseInt(limit));
        }

        if (!finalData || finalData.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="9" style="text-align: center; padding: 2rem;">No hay facturas registradas con este filtro.</td></tr>`;
            return;
        }

        finalData.forEach(invoice => {
            const row = document.createElement('tr');
            row.id = `invoice-row-${invoice.id}`;
            const invoiceDate = new Date(invoice.invoice_date);
            const paymentDate = invoice.payment_date ? new Date(invoice.payment_date) : null;

            const formattedInvoiceDate = `${String(invoiceDate.getDate()).padStart(2, '0')}-${String(invoiceDate.getMonth() + 1).padStart(2, '0')}-${invoiceDate.getFullYear()}`;

            const formattedPaymentDate = paymentDate
                ? `${String(paymentDate.getDate()).padStart(2, '0')}-${String(paymentDate.getMonth() + 1).padStart(2, '0')}-${paymentDate.getFullYear()}`
                : 'N/A';

            const isPaid = parseInt(invoice.is_paid) === 1;
            const statusClass = isPaid ? 'status-paid' : 'status-pending';
            const statusText = isPaid ? 'PAGADA' : 'PENDIENTE';

            const paymentButton = isPaid
                ? `<button class="action-button undo-paid-btn" style="background-color: var(--danger-color);" data-id="${invoice.id}" onclick="showUndoModal(${invoice.id})"><i class="fas fa-undo"></i> Deshacer Pago</button>`
                : `<button class="action-button mark-paid-btn" data-id="${invoice.id}" onclick="showMarkPaidModal(${invoice.id})"><i class="fas fa-check"></i> Pagar</button>`;

            const editButton = `<button class="action-button edit-amount-btn" style="background-color: #f59e0b;" data-id="${invoice.id}" data-amount="${invoice.total_amount}" onclick="editAmount(${invoice.id})"><i class="fas fa-edit"></i> Editar Total</button>`;

            row.innerHTML = `
                <td>${invoice.id}</td>
                <td>${invoice.invoice_number}</td>
                <td>${invoice.supplier_name}</td>
                <td>${formattedInvoiceDate}</td>
                <td class="invoice-amount-cell">${formatCurrency(invoice.total_amount)}</td>
                <td class="${statusClass} invoice-status-cell">${statusText}</td>
                <td class="invoice-payment-date-cell">${formattedPaymentDate}</td>
                <td class="actions-pay-cell">${paymentButton}</td>
                <td class="actions-edit-cell">${editButton}</td>
                `;
            tableBody.appendChild(row);
        });
    };

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'block';

    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
    // 🚨 El scrollIntoView ha sido eliminado porque el CSS con fixed ya lo centra.
}
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
            if (modalId === 'editAmountModal') {
                currentEditingInvoiceId = null;
                document.getElementById('new-amount').value = '';
            }
        }, 300);
    }

    function showMarkPaidModal(invoiceId) {
        const invoice = invoicesData.find(inv => parseInt(inv.id) === invoiceId);
        if (!invoice) return showAjaxMessage('Factura no encontrada.', true);
        document.getElementById('paid-modal-invoice-id').textContent = invoiceId;
        document.getElementById('paid-modal-invoice-amount').textContent = formatCurrency(invoice.total_amount);
        document.getElementById('confirm-mark-paid-btn').dataset.id = invoiceId;
        openModal('confirmPaidModal');
    }

    function showUndoModal(invoiceId) {
        const invoice = invoicesData.find(inv => parseInt(inv.id) === invoiceId);
        if (!invoice) return showAjaxMessage('Factura no encontrada.', true);
        document.getElementById('undo-modal-invoice-id').textContent = invoiceId;
        document.getElementById('confirm-undo-paid-btn').dataset.id = invoiceId;
        openModal('confirmUndoModal');
    }

    function confirmMarkAsPaid(invoiceId) {
        closeModal('confirmPaidModal');
        const btn = $(`#invoice-row-${invoiceId} .mark-paid-btn`);
        const originalText = `<i class="fas fa-check"></i> Pagar`;
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Procesando...');

        $.post('pagos.php', { action: 'mark_as_paid', id: invoiceId })
            .done(function(response) {
                if (response.success) {
                    showAjaxMessage(response.message, false);
                    const index = invoicesData.findIndex(inv => parseInt(inv.id) === invoiceId);
                    if (index !== -1) {
                        invoicesData[index].is_paid = response.new_status;
                        invoicesData[index].payment_date = response.payment_date;
                    }
                    updateLocalKPIs(invoiceId, 'PAGAR');
                    updateTable();
                } else {
                    showAjaxMessage(response.message, true);
                }
            })
            .fail(function(xhr) {
                const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : `Error desconocido (${xhr.status}).`;
                showAjaxMessage(`Error: ${errorMsg}`, true);
            })
            .always(function() {
                const newInvoice = invoicesData.find(inv => parseInt(inv.id) === invoiceId);
                if (newInvoice && parseInt(newInvoice.is_paid) === 0) {
                    btn.prop('disabled', false).html(originalText);
                }
            });
    }

    function confirmUndoPayment(invoiceId) {
        closeModal('confirmUndoModal');
        const btn = $(`#invoice-row-${invoiceId} .undo-paid-btn`);
        const originalText = `<i class="fas fa-undo"></i> Deshacer Pago`;
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Procesando...');

        $.post('pagos.php', { action: 'undo_payment', id: invoiceId })
            .done(function(response) {
                if (response.success) {
                    showAjaxMessage(response.message, false);
                    const index = invoicesData.findIndex(inv => parseInt(inv.id) === invoiceId);
                    if (index !== -1) {
                        invoicesData[index].is_paid = response.new_status;
                        invoicesData[index].payment_date = null;
                    }
                    updateLocalKPIs(invoiceId, 'DESHACER');
                    updateTable();
                } else {
                    showAjaxMessage(response.message, true);
                }
            })
            .fail(function(xhr) {
                const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : `Error desconocido (${xhr.status}).`;
                showAjaxMessage(`Error: ${errorMsg}`, true);
            })
            .always(function() {
                const newInvoice = invoicesData.find(inv => parseInt(inv.id) === invoiceId);
                if (newInvoice && parseInt(newInvoice.is_paid) === 1) {
                    btn.prop('disabled', false).html(originalText);
                }
            });
    }

    function editAmount(invoiceId) {
        const invoice = invoicesData.find(inv => parseInt(inv.id) === invoiceId);
        if (!invoice) return showAjaxMessage('Factura no encontrada.', true);
        currentEditingInvoiceId = invoiceId;
        const currentAmount = parseFloat(invoice.total_amount);
        document.getElementById('current-invoice-id').value = invoiceId;
        document.getElementById('modal-invoice-id').textContent = invoiceId;
        document.getElementById('modal-invoice-number').textContent = invoice.invoice_number;
        document.getElementById('modal-supplier-name').textContent = invoice.supplier_name;
        document.getElementById('modal-current-amount').textContent = formatCurrency(currentAmount);
        const newAmountInput = document.getElementById('new-amount');
        newAmountInput.value = Math.round(currentAmount).toLocaleString('es-CL', { useGrouping: true });
        openModal('editAmountModal');
        setTimeout(() => {
            newAmountInput.focus();
            newAmountInput.select();
        }, 350);
    }

    function confirmEditAmount() {
        const invoiceId = parseInt(document.getElementById('current-invoice-id').value);
        const newAmountInput = document.getElementById('new-amount');
        if (isNaN(invoiceId) || invoiceId <= 0) {
            closeModal('editAmountModal');
            return showAjaxMessage('ID de factura para editar no válido.', true);
        }
        const cleanStr = newAmountInput.value.replace(/[$.]/g, '').replace(',', '.');
        const newAmount = parseFloat(cleanStr);
        if (isNaN(newAmount) || newAmount < 0) {
            return showAjaxMessage('Monto no válido. Por favor, ingrese un número positivo.', true);
        }
        const newAmountRounded = Math.round(newAmount);
        const btn = $(`#invoice-row-${invoiceId} .edit-amount-btn`);
        const originalText = `<i class="fas fa-edit"></i> Editar Total`;
        const originalBg = btn.css('background-color');
        closeModal('editAmountModal');
        const invoice = invoicesData.find(inv => parseInt(inv.id) === invoiceId);
        const currentAmount = parseFloat(invoice.total_amount);
        if (newAmountRounded === Math.round(currentAmount)) {
            return showAjaxMessage('El monto ingresado es el mismo que el actual.', false);
        }
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Procesando...');

        $.post('pagos.php', {
            action: 'edit_amount',
            id: invoiceId,
            new_amount: newAmountRounded
        })
        .done(function(response) {
            if (response.success) {
                showAjaxMessage(response.message, false);
                btn.css('background-color', '#10b981').html('<i class="fas fa-check-circle"></i> Completado');
                const index = invoicesData.findIndex(inv => parseInt(inv.id) === invoiceId);
                if (index !== -1) {
                    invoicesData[index].total_amount = response.new_amount;
                }
                loadData();
                setTimeout(() => {
                    btn.prop('disabled', false).html(originalText).css('background-color', originalBg);
                }, 2000);
            } else {
                showAjaxMessage(response.message, true);
                btn.prop('disabled', false).html(originalText).css('background-color', originalBg);
            }
        })
        .fail(function(xhr) {
            const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : `Error desconocido (${xhr.status}).`;
            showAjaxMessage(`Error: ${errorMsg}`, true);
            btn.prop('disabled', false).html(originalText).css('background-color', originalBg);
        });
    }

    function updateLocalKPIs(invoiceId, action) {
        const invoice = invoicesData.find(inv => parseInt(inv.id) === invoiceId);
        if (!invoice) return;
        const amount = parseFloat(invoice.total_amount);
        const isInvoiceCurrentMonth = isCurrentMonth(invoice.invoice_date);

        let paidAmount = cleanValue('paid_amount');
        let pendingAmount = cleanValue('pending_amount');
        let paidCount = cleanCount('paid_count');
        let pendingCount = cleanCount('pending_count');
        let monthPendingAmount = cleanValue('month_pending_amount');

        if (action === 'PAGAR') {
            paidAmount += amount;
            pendingAmount -= amount;
            paidCount += 1;
            pendingCount -= 1;
            if (isInvoiceCurrentMonth) monthPendingAmount -= amount;
        } else if (action === 'DESHACER') {
            paidAmount -= amount;
            pendingAmount += amount;
            paidCount -= 1;
            pendingCount += 1;
            if (isInvoiceCurrentMonth) monthPendingAmount += amount;
        }
        updateKPIs({ paid_amount: paidAmount, pending_amount: pendingAmount, paid_count: paidCount, pending_count: pendingCount, month_pending_amount: monthPendingAmount });
    }

    function isCurrentMonth(invoiceDateString) {
        const now = new Date();
        const invoiceDate = new Date(invoiceDateString);
        return invoiceDate.getFullYear() === now.getFullYear() && invoiceDate.getMonth() === now.getMonth() && invoiceDate <= now;
    }


// 📌 FUNCIÓN: Carga de Datos y KPIs por AJAX con filtros (CORRECCIÓN FINAL)
    const loadData = () => {
        const rangeStartDate = document.getElementById('start-date').value;
        const rangeEndDate = document.getElementById('end-date').value;
        const monthFilterSelect = document.getElementById('month-filter');
        const monthFilterValue = monthFilterSelect.value;
        
        let finalStartDate = null;
        let finalEndDate = null;

        // 1. PRIORIDAD: RANGO MANUAL ("Desde/Hasta")
        if (rangeStartDate && rangeEndDate) {
            finalStartDate = rangeStartDate;
            finalEndDate = rangeEndDate;
            
            // Si hay un rango manual, el selector de mes debe estar en "Todas las Fechas" (opción vacía)
            if (monthFilterValue !== '') {
                monthFilterSelect.value = '';
            }
        } 
        // 2. SEGUNDA PRIORIDAD: FILTRO DE MES
        else if (monthFilterValue) {
             const [year, month] = monthFilterValue.split('-');
            
            // Calculamos el rango del mes
            finalStartDate = `${year}-${month}-01`;
            const lastDay = new Date(year, month, 0).getDate();
            finalEndDate = `${year}-${month}-${lastDay}`;
            
            // Limpiamos los inputs de rango para que no causen confusión visual
            document.getElementById('start-date').value = '';
            document.getElementById('end-date').value = '';
        } 
        // 3. TERCERA PRIORIDAD: NO FILTRO (Todas las Fechas)
        else {
            // Aseguramos que los inputs de fecha estén limpios
            document.getElementById('start-date').value = '';
            document.getElementById('end-date').value = '';
        }
        
        // Mostrar indicador de carga en la tabla
        const tableBody = document.getElementById('invoices-table-body');
        tableBody.innerHTML = `<tr><td colspan="9" style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Cargando datos...</td></tr>`;
        document.getElementById('kpi-grid').style.opacity = '0.5';

        // Llama a AJAX con el rango determinado
        $.get('pagos.php', {
            action: 'get_data',
            start_date: finalStartDate,
            end_date: finalEndDate
        })
        .done(function(response) {
            // ... (El resto de la lógica .done, .fail y .always se mantiene igual)
            if (response.success) {
                invoicesData = response.invoices;
                updateKPIs(response.kpis);
                updateTable();
                document.getElementById('kpi-grid').style.opacity = '1';

                if (response.invoices.length === 0) {
                    // Solo muestra el mensaje si no hay datos.
                    // showAjaxMessage('Filtro aplicado. No se encontraron facturas en el rango.', false);
                }

            } else {
                showAjaxMessage(response.message, true);
                tableBody.innerHTML = `<tr><td colspan="9" style="text-align: center; padding: 2rem;">Error al cargar datos: ${response.message}</td></tr>`;
                document.getElementById('kpi-grid').style.opacity = '1';
            }
        })
        .fail(function(xhr) {
            const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : `Error de conexión o de servidor. (Status: ${xhr.status})`;
            showAjaxMessage(`Error al cargar datos: ${errorMsg}`, true);
            tableBody.innerHTML = `<tr><td colspan="9" style="text-align: center; padding: 2rem;">Fallo la conexión al servidor. Si el error persiste, revise los logs de PHP.</td></tr>`;
            document.getElementById('kpi-grid').style.opacity = '1';
        });
    };

    // FUNCIÓN: Rellenar el selector de meses
    const populateMonthFilter = () => {
        const select = document.getElementById('month-filter');
        const months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        const today = new Date();
        const currentMonth = today.getMonth();
        const currentYear = today.getFullYear();

        const optionAll = document.createElement('option');
        optionAll.value = '';
        optionAll.textContent = 'Todas las Fechas'; 
        select.appendChild(optionAll);

        // Generar 12 meses (últimos 12, incluyendo el actual)
        for (let i = 0; i < 12; i++) {
            let date = new Date(currentYear, currentMonth - i, 1);
            let monthIndex = date.getMonth(); 
            let year = date.getFullYear();

            const monthKey = `${year}-${String(monthIndex + 1).padStart(2, '0')}`;
            const monthText = `${months[monthIndex]} ${year}`;

            const option = document.createElement('option');
            option.value = monthKey;
            option.textContent = monthText;
            
            if (i === 0) { 
                option.selected = true; // Selecciona el mes actual por defecto
            }
            select.appendChild(option);
        }
    };

    // FUNCIÓN: Aplicar el filtro de mes (solo cambia el select, loadData hace el trabajo)
    const applyMonthFilter = () => {
        // Al seleccionar un mes, solo necesitamos llamar a loadData.
        loadData();
    };

    // FUNCIÓN: Aplicar el filtro de rango de fechas (solo cambia los inputs, loadData hace el trabajo)
    const applyRangeFilter = () => {
        // Validación rápida si solo se llenó un campo
        const startDate = document.getElementById('start-date').value;
        const endDate = document.getElementById('end-date').value;
        if ((startDate && !endDate) || (!startDate && endDate)) {
            return showAjaxMessage('Debe especificar ambas fechas de rango (Desde y Hasta).', true);
        }

        // Si el usuario usa el rango, se limpiará el filtro de mes dentro de loadData
        loadData();
    }


    // 5. INICIALIZACIÓN DE LA PÁGINA
    document.addEventListener('DOMContentLoaded', function() {
        
        // 1. Rellenar y cargar datos:
        populateMonthFilter();
        loadData(); // Carga datos del mes actual por defecto (detectado en loadData)

        // 2. Event Listeners de Filtros
        document.getElementById('status-filter').addEventListener('change', updateTable);
        document.getElementById('limit').addEventListener('change', updateTable);

        // Nuevo: Escuchar el selector de meses
        document.getElementById('month-filter').addEventListener('change', applyMonthFilter);

        // El botón de filtro aplica el filtro de rango
        document.getElementById('apply-filter-btn').addEventListener('click', applyRangeFilter);

        // Si se cambia una fecha de rango, el usuario debe hacer clic en "Filtrar" para aplicar
        // document.getElementById('start-date').addEventListener('change', loadData); // Deshabilitado para forzar el botón
        // document.getElementById('end-date').addEventListener('change', loadData); // Deshabilitado para forzar el botón

        document.querySelector('.btn-add').addEventListener('click', function() {
            window.location.href = 'https://tiendaslistto.cl/erp/suppliers/suppliers.php';
        });

        // Event Listeners de MODALES (Se mantienen)
        const modalEdit = document.getElementById('editAmountModal');
        const modalPaid = document.getElementById('confirmPaidModal');
        const modalUndo = document.getElementById('confirmUndoModal');

        const confirmEditBtn = document.getElementById('confirm-edit-btn');
        const confirmMarkPaidBtn = document.getElementById('confirm-mark-paid-btn');
        const confirmUndoPaidBtn = document.getElementById('confirm-undo-paid-btn');

        confirmEditBtn.addEventListener('click', confirmEditAmount);

        confirmMarkPaidBtn.addEventListener('click', function() {
            const invoiceId = parseInt(this.dataset.id);
            if (invoiceId) confirmMarkAsPaid(invoiceId);
        });

        confirmUndoPaidBtn.addEventListener('click', function() {
            const invoiceId = parseInt(this.dataset.id);
            if (invoiceId) confirmUndoPayment(invoiceId);
        });

        window.addEventListener('click', function(event) {
            if (event.target === modalEdit) {
                closeModal('editAmountModal');
            } else if (event.target === modalPaid) {
                closeModal('confirmPaidModal');
            } else if (event.target === modalUndo) {
                closeModal('confirmUndoModal');
            }
        });

        document.getElementById('new-amount').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmEditBtn.click();
            }
        });
    });
</script>
</body>
</html>