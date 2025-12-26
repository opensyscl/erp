<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
require_once "config.php";

// Inicializar carrito en sesión
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Variables de sesión del usuario
$customer_name = null;
if (isset($_SESSION['customer_id'])) {
    $stmt = $pdo->prepare("SELECT name FROM customers WHERE id = ?");
    $stmt->execute([$_SESSION['customer_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    $customer_name = $customer['name'] ?? null;
}

// Obtener categorías
$stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// FILTRO CORREGIDO: Usando Prepared Statements para seguridad SQL
$params = [];
$where = "";

if (isset($_GET['cat'])) {
    $cat_id = intval($_GET['cat']);
    $where = "WHERE category_id = ?";
    $params[] = $cat_id;
}

// Obtener todos los productos
$sql = "SELECT id, name, price, stock, image_url, created_at 
        FROM products 
        $where 
        ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params); 
$allProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Conteo del carrito
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $qty) {
        if (is_numeric($qty)) {
            $cart_count += $qty;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tienda - Listto</title>
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="icon" type="image/png" href="/erp/img/fav.png">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    
    <style>
        /* NOTA: Estos estilos deben moverse a styles.css para la limpieza, pero se mantienen aquí por si no puedes editar el CSS externo temporalmente. */
        .spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .spinner.visible {
            display: block;
        }
        .spinner svg {
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<header class="header">
    <a href="index.php"><img src="https://tiendaslistto.cl/erp/img/Logo1.png" alt="Listto" class="logo-img"></a>
    <div class="header-center">
        <input type="text" id="search" placeholder="Buscar productos...">
    </div>
    <div class="header-right">
        <?php if (isset($_SESSION['customer_id'])): ?>
            <span class="welcome desktop-only">Hola, <?= htmlspecialchars($customer_name) ?></span>
            <a href="logout.php" class="btn-logout">Cerrar sesión</a>
        <?php else: ?>
            <a href="login.php" class="btn-login">Iniciar sesi&oacute;n</a>
        <?php endif; ?>
        <a href="cart.php" class="btn-cart">Carrito (<span id="cart-count"><?= $cart_count ?></span>)</a>
    </div>
</header>

<button class="toggle-categories" onclick="toggleSidebar()">Categorías</button>

<div class="layout">
    <aside class="sidebar" id="sidebar">
        <nav>
            <a href="index.php" class="<?= !isset($_GET['cat']) ? 'active' : '' ?>">Todos</a>
            <?php foreach ($categories as $cat): ?>
                <a href="index.php?cat=<?= $cat['id'] ?>"
                   class="<?= (isset($_GET['cat']) && $_GET['cat'] == $cat['id']) ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <main class="content">
        <div class="products" id="product-list">
            </div>
        <div class="spinner" id="spinner">
            <svg viewBox="0 0 50 50">
                <circle cx="25" cy="25" r="20" stroke="#e81c1c" stroke-width="4" fill="none" stroke-linecap="round"/>
            </svg>
        </div>
    </main>
</div>

<div class="floating-search-bar" id="floating-search-bar">
    <input type="text" id="search-float" placeholder="Buscar productos..." aria-label="Buscar productos">
</div>
<script>
    const allProducts = <?= json_encode($allProducts) ?>;
    const productList = document.getElementById('product-list');
    const spinner = document.getElementById('spinner');
    // Referencias a ambos campos de búsqueda
    const searchInputHeader = document.getElementById("search");
    const searchInputFloat = document.getElementById("search-float");

    let itemsPerLoad = 30;
    let currentIndex = 0;

    function renderProductsFromArray(arr) {
        arr.forEach(p => {
            const card = document.createElement('div');
            card.className = 'product';
            card.dataset.name = p.name.toLowerCase();
            card.dataset.stock = p.stock;
            card.innerHTML = `
                <img src="${p.image_url || 'assets/products/no-image.png'}" alt="${p.name}">
                <h3>${p.name}</h3>
                <p class="price">$${parseInt(p.price).toLocaleString('es-CL')}</p>
                <button class="btn add-to-cart" data-id="${p.id}" ${p.stock == 0 ? 'disabled' : ''}>Agregar</button>
            `;
            productList.appendChild(card);
        });
    }

    function renderProducts() {
        spinner.classList.add('visible');
        setTimeout(() => {
            const nextItems = allProducts.slice(currentIndex, currentIndex + itemsPerLoad);
            renderProductsFromArray(nextItems);
            currentIndex += itemsPerLoad;
            spinner.classList.remove('visible');
        }, 500);
    }

    function handleScroll() {
        const { scrollTop, scrollHeight, clientHeight } = document.documentElement;
        if (scrollTop + clientHeight >= scrollHeight - 100 && currentIndex < allProducts.length) {
            renderProducts();
        }
    }
    
    // Función centralizada de búsqueda para ambos campos
    function handleSearch(term) {
        const filtered = allProducts.filter(p => p.name.toLowerCase().includes(term));

        // Limpiar productos existentes
        productList.innerHTML = "";
        
        if (term) {
            // Mostrar los primeros itemsPerLoad del filtrado
            renderProductsFromArray(filtered.slice(0, itemsPerLoad));
            currentIndex = itemsPerLoad;
            window.removeEventListener('scroll', handleScroll);
        } else {
            // Mostrar todos los productos con scroll infinito
            productList.innerHTML = "";
            currentIndex = 0;
            renderProducts();
            window.addEventListener('scroll', handleScroll);
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        renderProducts();

        // 1. Evento para el buscador del Header
        searchInputHeader.addEventListener("keyup", function () {
            const term = this.value.toLowerCase();
            searchInputFloat.value = this.value; // Sincroniza el valor del flotante
            handleSearch(term);
        });
        
        // 2. Evento para el buscador Flotante
        searchInputFloat.addEventListener("keyup", function () {
            const term = this.value.toLowerCase();
            searchInputHeader.value = this.value; // Sincroniza el valor del header
            handleSearch(term);
        });

        window.addEventListener("scroll", handleScroll);

        document.body.addEventListener("click", function (e) {
            if (e.target.classList.contains("add-to-cart")) {
                const productId = e.target.dataset.id;
                fetch("add_to_cart.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `product_id=${productId}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById("cart-count").textContent = data.cartCount;
                        showNotification(data.message);
                    }
                });
            }
        });
    });

    function showNotification(msg) {
        const alert = document.createElement("div");
        alert.className = "notification";
        alert.innerText = msg;
        document.body.appendChild(alert);
        setTimeout(() => alert.classList.add("visible"), 100);
        setTimeout(() => {
            alert.classList.remove("visible");
            setTimeout(() => alert.remove(), 300);
        }, 2000);
    }

    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle("open");
    }
</script>

</body>
</html>