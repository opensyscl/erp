<?php

session_start();

require_once "../app/config.php";

// Verificar si el usuario ha iniciado sesión
if (empty($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Obtener datos del usuario
$stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Usuario no encontrado.");
}

// Validar roles permitidos
$allowed_roles = ['POS1', 'POS2'];
if (!in_array($user['role'], $allowed_roles)) {
    die("Acceso denegado. Rol no autorizado.");
}

$error = "";
$success = "";

// Procesar cambio de estado de pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['new_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['new_status'];

    $allowed_status = ['Pendiente', 'En proceso', 'Enviado', 'Cancelado', 'Completado'];
    if (!in_array($new_status, $allowed_status)) {
        $error = "Estado no válido.";
    } else {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $order_id])) {
            $success = "Estado actualizado correctamente.";
        } else {
            $error = "Error al actualizar estado.";
        }
    }
}

// Obtener pedidos y datos de cliente
$stmt = $pdo->query("
    SELECT
        o.id AS order_id,
        o.customer_id,
        o.total,
        o.payment_method,
        o.delivery_method,
        o.status,
        o.created_at,
        c.name AS customer_name,
        c.email AS customer_email,
        c.phone AS customer_phone,
        c.address AS customer_address
    FROM orders o
    INNER JOIN customers c ON o.customer_id = c.id
    ORDER BY o.created_at DESC
");

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Función para obtener los productos del pedido
function getOrderItems($pdo, $order_id) {
    $stmt = $pdo->prepare("
        SELECT oi.product_id, oi.quantity, oi.price, p.name 
        FROM order_items oi
        INNER JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard de Pedidos - Tiendas Listto</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f6f8; }
        h1 { color: #3F51B5; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; background: #fff; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #3F51B5; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .status-select {
            padding: 6px;
            font-size: 14px;
        }
        .btn-update {
            padding: 6px 12px;
            background-color: #3F51B5;
            border: none;
            color: white;
            cursor: pointer;
            border-radius: 4px;
        }
        .btn-update:hover {
            background-color: #2c3e9e;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        .success {
            background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;
        }
        details {
            margin-bottom: 15px;
            background: #eef1f7;
            padding: 10px;
            border-radius: 6px;
        }
        summary {
            font-weight: bold;
            cursor: pointer;
        }
        .order-header {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>

    <h1>Dashboard de Pedidos</h1>
    <p>Usuario: <strong><?= htmlspecialchars($user['username']) ?></strong> (Rol: <?= htmlspecialchars($user['role']) ?>) | 
    <a href="logout.php">Cerrar sesión</a></p>

    <?php if ($success): ?>
        <div class="message success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <p>No hay pedidos registrados aún.</p>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <details>
                <summary>
                    Pedido #<?= $order['order_id'] ?> - Cliente: <?= htmlspecialchars($order['customer_name']) ?> - Total: $<?= number_format($order['total'], 0, ',', '.') ?> - Estado: <?= htmlspecialchars($order['status']) ?>
                </summary>

                <div class="order-header">
                    <strong>Fecha:</strong> <?= $order['created_at'] ?><br>
                    <strong>Email:</strong> <?= htmlspecialchars($order['customer_email']) ?><br>
                    <strong>Teléfono:</strong> <?= htmlspecialchars($order['customer_phone']) ?><br>
                    <strong>Dirección:</strong> <?= htmlspecialchars($order['customer_address']) ?><br>
                    <strong>Método de pago:</strong> <?= htmlspecialchars($order['payment_method']) ?><br>
                    <strong>Método de entrega:</strong> <?= $order['delivery_method'] === 'retiro' ? 'Retiro en tienda' : 'Envío a domicilio' ?>
                </div>

                <h4>Productos:</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio unitario</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $items = getOrderItems($pdo, $order['order_id']);
                        foreach ($items as $item):
                            $subtotal = $item['price'] * $item['quantity'];
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= $item['quantity'] ?></td>
                                <td>$<?= number_format($item['price'], 0, ',', '.') ?></td>
                                <td>$<?= number_format($subtotal, 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <form method="POST" style="margin-top: 10px; max-width: 300px;">
                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                    <label for="new_status_<?= $order['order_id'] ?>">Cambiar estado:</label>
                    <select name="new_status" id="new_status_<?= $order['order_id'] ?>" class="status-select" required>
                        <?php
                        $states = ['Pendiente', 'En proceso', 'Enviado', 'Cancelado', 'Completado'];
                        foreach ($states as $state):
                            $selected = ($state === $order['status']) ? 'selected' : '';
                        ?>
                            <option value="<?= $state ?>" <?= $selected ?>><?= $state ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-update">Actualizar</button>
                </form>

            </details>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>
