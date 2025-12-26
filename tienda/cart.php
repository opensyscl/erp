<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once "config.php";

// Procesar acciones del carrito (ANTES de cargar los productos)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    if ($_GET['action'] === 'add') {
        $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + 1;
    } elseif ($_GET['action'] === 'remove') {
        if (isset($_SESSION['cart'][$id]) && $_SESSION['cart'][$id] > 1) {
            $_SESSION['cart'][$id]--;
        } else {
            unset($_SESSION['cart'][$id]);
        }
    } elseif ($_GET['action'] === 'delete') {
        unset($_SESSION['cart'][$id]);
    }

    // Redirigir sin parámetros para evitar repetición de acciones
    header("Location: cart.php");
    exit;
}

// Inicializar carrito si no existe
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Nombre del cliente en sesión (Se obtiene más abajo para el header)
$customer_name = $_SESSION['customer_name'] ?? null;

// Obtener info de productos
$products = [];
$total = 0;

if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    // Usar parámetros seguros para la consulta IN
    $placeholders = rtrim(str_repeat('?,', count($ids)), ',');

    $stmt = $pdo->prepare("SELECT id, name, price, image_url FROM products WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $productData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $map = [];
    foreach ($productData as $p) {
        $pid = $p['id'];
        $qty = $_SESSION['cart'][$pid] ?? 1;
        $price = $p['price'];

        if (!is_numeric($price) || !is_numeric($qty)) continue;

        $p['qty'] = $qty;
        $p['subtotal'] = $qty * $price;
        $total += $p['subtotal'];
        $map[$pid] = $p;
    }
    // Asegurar el orden de los productos según el orden de aparición en el carrito (o el ID)
    $products = array_values($map);
}

// Contar items para el botón del header
$cart_items_count = 0;
foreach ($_SESSION['cart'] as $qty) {
    if (is_numeric($qty)) {
        $cart_items_count += $qty;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carrito - Listto</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
/* --- RAÍZ Y VARIABLES GLOBALES (ESTILO APLICADO) --- */
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
    /* Se quitó flex-wrap: wrap; */
}

.header-right span {
    font-weight: 500;
    color: var(--text-secondary);
    white-space: nowrap;
}

/* --- BOTONES MODERNIZADOS --- */
.btn, .btn-logout, .btn-login, .btn-cart {
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
}

.btn:hover, .btn-login:hover, .btn-cart:hover, .btn-logout:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(232, 28, 28, 0.3);
}

.btn-logout {
    background-image: linear-gradient(145deg, #555, #333);
}

.btn-logout:hover {
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
}

.btn-danger {
    display: inline-block;
    padding: 10px 16px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: all .3s;
    border: none;
    cursor: pointer;
    background: rgba(232, 28, 28, 0.1);
    color: var(--accent-color);
}

.btn-danger:hover {
    background: rgba(232, 28, 28, 0.2);
    transform: translateY(-2px);
}

/* --- CONTENEDOR Y TABLA --- */
.container {
    flex: 1;
    max-width: 1100px;
    margin: 30px auto;
    padding: 20px;
}

h2 {
    font-size: 28px;
    margin-bottom: 25px;
    font-weight: 700;
    text-align: center;
    color: var(--text-primary);
}

.cart-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
    background: var(--card-background);
    border: 1px solid var(--card-border);
    border-radius: 18px;
    box-shadow: 0 8px 32px 0 var(--card-shadow);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    overflow: hidden;
}

.cart-table th, .cart-table td {
    text-align: center;
    padding: 16px;
    border-bottom: 1px solid var(--card-border);
    vertical-align: middle;
}

/* Quitar el último borde inferior */
.cart-table tr:last-child td {
    border-bottom: none;
}

.cart-table th {
    background: rgba(255, 255, 255, 0.2);
    font-weight: 600;
    color: var(--text-primary);
    font-size: 14px;
    text-transform: uppercase;
}

.cart-table img {
    width: 70px;
    height: 70px;
    object-fit: contain;
    border-radius: 12px;
}

.qty-btn {
    padding: 6px 12px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 16px;
    font-weight: 500;
    transition: all .3s;
    border: 1px solid #ddd;
    cursor: pointer;
    background: #fff;
    color: #333;
    margin: 0 5px;
}

.qty-btn:hover { background: #f0f0f0; }

.total {
    text-align: right;
    font-size: 22px;
    font-weight: 700;
    margin-top: 20px;
    padding-right: 10px;
}

.actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    flex-wrap: wrap;
    margin-top: 15px;
}

/* --- ESTILOS MÓVILES MEJORADOS --- */
@media (max-width: 768px) {
    /* CORRECCIÓN DE ENCABEZADO: Apilar botones a la derecha */
    header {
        padding: 10px 15px;
    }
    .header-right {
        flex-direction: column;
        align-items: flex-end;
        gap: 5px;
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
        max-width: 150px;
    }

    /* Reglas para la tabla del carrito (ya estaban bien) */
    .cart-table {
        background: none;
        border: none;
        box-shadow: none;
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        border-collapse: separate;
        border-spacing: 0 12px;
    }

    .cart-table thead { display: none; }

    .cart-table tr {
        display: flex;
        align-items: center;
        background: var(--card-background);
        border: 1px solid var(--card-border);
        border-radius: 18px;
        padding: 12px;
        box-shadow: 0 8px 32px 0 var(--card-shadow);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        flex-wrap: wrap;
    }

    .cart-table td { border: none; text-align: left; padding: 6px; }
    .cart-table td::before { display: none; }

    .cart-table td[data-label="Imagen"] { flex: 0 0 70px; }
    .cart-table img { width: 60px; height: 60px; }

    .cart-table td[data-label="Producto"] { flex: 1 1 auto; font-weight: 600; }
    .cart-table td[data-label="Precio"] { font-size: 13px; color: var(--text-secondary); }

    .cart-table td[data-label="Cantidad"] { flex: 0 0 auto; display: flex; align-items: center; }
    .qty-btn { padding: 4px 10px; }

    .cart-table td[data-label="Subtotal"],
    .cart-table td[data-label="Acciones"] { display: none; }

    .cart-table tr::after {
        content: attr(data-subtotal);
        display: block;
        font-weight: 700;
        color: var(--accent-color);
        margin-left: 82px;
        margin-top: 4px;
        font-size: 15px;
    }

    .total { text-align: center; }
    .actions { justify-content: center; }
    .actions .btn { flex: 1; text-align: center; }
}
</style>
</head>
<body>

<header>
    <a href="index.php"><img src="https://tiendaslistto.cl/erp/img/Logo1.png" alt="Listto"></a>
    <div class="header-right">
        <?php 
          // Se mueve la lógica de obtener el nombre aquí para que sea más claro
          if (isset($_SESSION['customer_id'])): 
            $stmt = $pdo->prepare("SELECT name FROM customers WHERE id = ?");
            $stmt->execute([$_SESSION['customer_id']]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            $customer_name_header = $customer['name'] ?? '';
        ?>
            <span>Hola, <?= htmlspecialchars($customer_name_header) ?></span>
            <a href="logout.php" class="btn-logout">Cerrar sesi&oacute;n</a>
        <?php else: ?>
            <a href="login.php" class="btn-login"><i class="fas fa-sign-in-alt"></i> Iniciar sesi&oacute;n</a>
        <?php endif; ?>
        <a href="cart.php" class="btn-cart"><i class="fas fa-shopping-cart"></i> Carrito (<?= $cart_items_count ?>)</a>
    </div>
</header>

<main class="container">
    <h2>Tu carrito</h2>

<?php if (empty($products)): ?>
    <div style="text-align:center; margin-top:30px;">
        <p>Tu carrito est&aacute; vac&iacute;o</p>
        <a href="index.php" class="btn" style="margin-top: 15px; display: inline-block;">Ir a la tienda</a>
    </div>
<?php else: ?>
    <table class="cart-table">
        <thead>
            <tr>
                <th>Imagen</th>
                <th>Producto</th>
                <th>Precio</th>
                <th>Cantidad</th>
                <th>Subtotal</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
                <tr data-subtotal="Subtotal: $<?= number_format($p['subtotal'], 0, ',', '.') ?>">
                    <td data-label="Imagen">
                        <img src="<?= $p['image_url'] ?: 'assets/products/no-image.png' ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                    </td>
                    <td data-label="Producto"><?= htmlspecialchars($p['name']) ?></td>
                    <td data-label="Precio">$<?= number_format($p['price'], 0, ',', '.') ?></td>
                    <td data-label="Cantidad">
                        <a href="cart.php?action=remove&id=<?= $p['id'] ?>" class="qty-btn">Quitar</a>
                        <?= $p['qty'] ?>
                        <a href="cart.php?action=add&id=<?= $p['id'] ?>" class="qty-btn">Agregar</a>
                    </td>
                    <td data-label="Subtotal">$<?= number_format($p['subtotal'], 0, ',', '.') ?></td>
                    <td data-label="Acciones">
                        <a href="cart.php?action=delete&id=<?= $p['id'] ?>" class="btn-danger">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="total">
        Total: $<?= number_format($total, 0, ',', '.') ?>
    </div>

    <div class="actions">
        <a href="index.php" class="btn">Seguir comprando</a>
        <a href="checkout.php" class="btn">Finalizar compra</a>
    </div>
    <?php endif; ?>
</main>

</body>
</html>