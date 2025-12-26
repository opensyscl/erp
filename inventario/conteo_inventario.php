<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// NOTA: Asegúrate de que la ruta a tu archivo config.php sea correcta
require '../config.php';
session_start();

// ----------------------------------------------------------------------
// --- 1. VERIFICACIÓN DE LOGIN Y ROLES ---
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
$status_message = ''; // Inicializamos el mensaje de estado

try {
    $stmt_role = $pdo->prepare("SELECT role, username FROM users WHERE id = ?");
    $stmt_role->execute([$current_user_id]);
    $user_data = $stmt_role->fetch(PDO::FETCH_ASSOC);

    $user_role = $user_data['role'] ?? null;
    $current_username = $user_data['username'] ?? 'Usuario';

    // Roles autorizados para hacer conteo físico
    if ($user_role === 'POS1' || $user_role === 'ADMIN') {
        $user_can_access = true;
    }

} catch (PDOException $e) {
    error_log("Error de BD al verificar rol del usuario: " . $e->getMessage());
    $status_message = '<div class="alert danger">❌ Error de autenticación: Contacte a soporte.</div>';
    $user_can_access = false;
}

if (!$user_can_access) {
    if (empty($status_message)) {
        header('Location: ../not_authorized.php');
        exit();
    }
}

// ----------------------------------------------------------------------
// --- 2. CONFIGURACIÓN INICIAL Y OBTENER FILTROS ---
// ----------------------------------------------------------------------

$selected_supplier = $_GET['supplier_id'] ?? 'all';
$selected_category = $_GET['category_id'] ?? 'all';
$filter_message = '';


// Obtener proveedores y categorías para los filtros
try {
    $stmt_suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC");
    $suppliers = $stmt_suppliers->fetchAll(PDO::FETCH_ASSOC);

    $stmt_categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $status_message = '<div class="alert danger">❌ Error al cargar filtros de proveedores/categorías.</div>';
    $suppliers = [];
    $categories = [];
}

// ----------------------------------------------------------------------
// --- 3. LÓGICA DE ACTUALIZACIÓN DE STOCK (POST y PRG) ---
// ----------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $updated_products = 0;
    $current_datetime = date('Y-m-d H:i:s'); // Capturar la hora de la transacción
    
    try {
        $pdo->beginTransaction();

        // Actualiza stock y la fecha del último conteo
        $stmt_update = $pdo->prepare("
            UPDATE products 
            SET stock = :new_stock, ultconteo = :current_datetime 
            WHERE id = :product_id
        ");

        foreach ($_POST['new_stock'] as $product_id => $new_stock_value) {
            
            $product_id = (int)$product_id;
            // Sanitización y conversión de la entrada (reemplazar coma por punto para PHP)
            $new_stock_cleaned = str_replace(',', '.', $new_stock_value);
            $new_stock = is_numeric($new_stock_cleaned) ? (float)$new_stock_cleaned : null;
            
            if ($product_id > 0 && $new_stock !== null && trim($new_stock_value) !== '') {
                
                $stmt_update->execute([
                    ':new_stock' => $new_stock, 
                    ':current_datetime' => $current_datetime,
                    ':product_id' => $product_id
                ]);
                $updated_products += $stmt_update->rowCount();
            }
        }

        $pdo->commit();
        
        // --- IMPLEMENTACIÓN CLAVE DEL PATRÓN PRG (POST/REDIRECT/GET) ---
        // Esto previene que se reenvíe el formulario al presionar F5.
        $redirect_url = 'conteo_inventario.php?apply_filter=1';
        if ($selected_supplier !== 'all') $redirect_url .= '&supplier_id=' . urlencode($selected_supplier);
        if ($selected_category !== 'all') $redirect_url .= '&category_id=' . urlencode($selected_category);
        $redirect_url .= '&msg=success&count=' . $updated_products;
        
        header('Location: ' . $redirect_url);
        exit(); 
        // ---------------------------------------------------------------

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error de BD al actualizar stock: " . $e->getMessage());
        $status_message = '<div class="alert danger">❌ Error al guardar el inventario: ' . $e->getMessage() . '</div>';
    }
}

// Mostrar mensaje de éxito después de la redirección
if (isset($_GET['msg']) && $_GET['msg'] === 'success' && isset($_GET['count'])) {
    $updated_products = (int)$_GET['count'];
    $status_message = '<div class="alert success">✅ Se han actualizado **' . $updated_products . '** productos exitosamente.</div>';
}


// ----------------------------------------------------------------------
// --- 4. OBTENER PRODUCTOS PARA EL CONTEO (GET/Filtro) ---
// ----------------------------------------------------------------------

$products_for_count = [];
$where_clauses = [];
$params = [];

$sql = "
    SELECT
        p.id,
        p.barcode,
        p.ultconteo, 
        p.name AS product_name,
        SUM(p.stock) AS stock, 
        s.name AS supplier_name,
        c.name AS category_name
    FROM
        products p
    LEFT JOIN
        suppliers s ON p.supplier_id = s.id
    LEFT JOIN
        categories c ON p.category_id = c.id
";

// Aplicar filtros y construir mensaje
if ($selected_supplier !== 'all') {
    $where_clauses[] = "s.id = :supplier_id";
    $params[':supplier_id'] = (int)$selected_supplier;
    
    $filter_name = array_filter($suppliers, fn($s) => $s['id'] == $selected_supplier);
    $filter_name = reset($filter_name)['name'] ?? 'Proveedor Desconocido';
    
    $filter_message .= 'Productos filtrados por **Proveedor: ' . htmlspecialchars($filter_name) . '**';
}

if ($selected_category !== 'all') {
    $where_clauses[] = "c.id = :category_id";
    $params[':category_id'] = (int)$selected_category;
    
    $filter_name = array_filter($categories, fn($c) => $c['id'] == $selected_category);
    $filter_name = reset($filter_name)['name'] ?? 'Categoría Desconocida';
    
    if (!empty($filter_message)) $filter_message .= ' y ';
    $filter_message .= 'Productos filtrados por **Categoría: ' . htmlspecialchars($filter_name) . '**';
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

// Nota: p.id sigue siendo necesario en el GROUP BY aunque no se muestre en la tabla
$sql .= " GROUP BY p.id, p.barcode, p.ultconteo, p.name, s.name, c.name "; 
$sql .= " ORDER BY s.name ASC, p.name ASC;";


// Solo cargar productos si se aplicó algún filtro
if (isset($_GET['apply_filter'])) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products_for_count = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $status_message = '<div class="alert danger">❌ Error de BD al cargar productos: ' . $e->getMessage() . '</div>';
    }
} else {
    $filter_message = 'Seleccione un filtro (Proveedor o Categoría) y presione "Aplicar Filtros" para comenzar el conteo.';
}

// ----------------------------------------------------------------------
// --- 5. LÓGICA DE CÁLCULO DE KPIS (Simplificada) ---
// ----------------------------------------------------------------------

$kpis = [
    'total_products_count' => count($products_for_count),
    'total_stock_value' => 0,
    'products_with_diff' => '?', 
    'total_absolute_diff' => '?',
];

foreach ($products_for_count as $product) {
    $kpis['total_stock_value'] += (float)($product['stock'] ?? 0);
}

$stmt_version = $pdo->prepare("SELECT value FROM config WHERE name='version'");
$stmt_version->execute();
$system_version = $stmt_version->fetchColumn();

// Mensaje de estado de conteo
$count_message = '';
if (!empty($products_for_count)) {
    $count_message = 'Mostrando **' . count($products_for_count) . '** productos listos para el conteo.';
} elseif (isset($_GET['apply_filter'])) {
    $count_message = 'No se encontraron productos con los filtros seleccionados.';
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conteo de Inventario - Listto! ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" type="image/png" href="/img/fav.png"> 
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/conteo_inventario.css">

    <!-- Estilos CSS necesarios para las tarjetas KPI y el contenido -->
    <style>
        :root {
            /* Colores Base (ajustados para un tema claro/neutro) */
            --background-color: #f7f7f7;
            --text-primary: #1f2937; /* Gris oscuro */
            --text-secondary: #6b7280; /* Gris medio */

            /* Colores de Tarjetas (Glassmorphism Light) */
            --card-background: rgba(255, 255, 255, 0.7);
            --card-border: rgba(229, 231, 235, 0.7);
            --card-shadow: rgba(0, 0, 0, 0.05);

            /* Colores de KPI */
            --kpi-orange: #f97316; /* Naranja fuerte */
            --kpi-green: #10b981; /* Verde esmeralda */
            --kpi-red: #ef4444; /* Rojo vibrante */
            --kpi-purple: #8b5cf6; /* Púrpura */
            --kpi-blue: #3b82f6; /* Azul */
        }

        /* Aplica los estilos proporcionados */
        
        /* ==========================================================================
        4. TARJETAS DE KPIS Y GRÁFICO
        ========================================================================== */

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .kpi-card {
            background: var(--card-background);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 8px 32px 0 var(--card-shadow);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            /* Nuevo estilo para el borde de color */
            border-left: 4px solid;
            overflow: hidden; /* Asegura que el borde izquierdo se vea bien */
        }

        /* Clases específicas para el color del borde izquierdo del KPI */
        .kpi-card.orange { border-left-color: var(--kpi-orange); }
        .kpi-card.green { border-left-color: var(--kpi-green); }
        .kpi-card.red { border-left-color: var(--kpi-red); }
        .kpi-card.purple { border-left-color: var(--kpi-purple); }
        .kpi-card.blue { border-left-color: var(--kpi-blue); }


        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px 0 rgba(0,0,0,0.12);
        }

        .kpi-card h3 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
            text-transform: uppercase;
        }

        .kpi-card .value {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .kpi-card .subtitle {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }

        .kpi-card .projection {
            color: var(--kpi-green);
            font-weight: 600;
        }

        .content-card {
            background: var(--card-background);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px 0 var(--card-shadow);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            margin-bottom: 2.5rem;
        }

        .content-card h2 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
    </style>
    <!-- Fin de Estilos CSS -->

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
            <a href="analisisinventario.php">Análisis de Inventario</a>
            <a href="conteo_inventario.php" class="active">Conteo Físico</a>
        </nav>
        <div class="header-right">
            <span class="app-version"><?= htmlspecialchars($system_version); ?></span>
            <a href="../logout.php" class="btn-logout">Cerrar Sesi&oacute;n</a>
        </div>
    </header>

    <main class="container">
        
        <?= $status_message; ?>

        <?php if (isset($_GET['apply_filter'])): ?>
        <!-- APLICACIÓN DE CLASES DE ESTILO KPI -->
        <div class="kpi-grid">
            
            <div class="kpi-card green">
                <h3>Productos en Lista</h3>
                <p class="value"><?= number_format($kpis['total_products_count'], 0, ',', '.'); ?></p>
            </div>
            
            <div class="kpi-card orange">
                <h3>Productos con Diferencia</h3>
                <p class="value"><?= htmlspecialchars($kpis['products_with_diff']); ?></p>
                <p class="subtitle">(Se calcula al ingresar conteo)</p>
            </div>
            
            <div class="kpi-card blue">
                <h3>Total en Stock (Sistema)</h3>
                <p class="value">
                    <?= number_format($kpis['total_stock_value'], 0, ',', '.'); ?> Unid.
                </p>
            </div>

            <div class="kpi-card purple">
                <h3>Diferencia Absoluta Total</h3>
                <p class="value"><?= htmlspecialchars($kpis['total_absolute_diff']); ?></p>
                <p class="subtitle">(Se calcula al ingresar conteo)</p>
            </div>
            
        </div>
        <?php endif; ?>
        <form id="inventory-filter-form" class="filter-controls-group" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="GET">
            <div class="selector-container">
                <label for="supplier-selector">Proveedor:</label>
                <select id="supplier-selector" name="supplier_id">
                    <option value="all">-- Todos los Proveedores --</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= htmlspecialchars($supplier['id']); ?>" 
                            <?= $selected_supplier == $supplier['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($supplier['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="selector-container">
                <label for="category-selector">Categoría:</label>
                <select id="category-selector" name="category_id">
                    <option value="all">-- Todas las Categorías --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= htmlspecialchars($category['id']); ?>" 
                            <?= $selected_category == $category['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" name="apply_filter" value="1" class="btn-filter">Aplicar Filtros</button>
        </form>
        
        <p class="filter-toast"><?= $filter_message; ?> **<?= $count_message; ?>**</p>
        
        <?php if (!empty($products_for_count)): ?>
        <div class="content-card">
            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>?supplier_id=<?= urlencode($selected_supplier); ?>&category_id=<?= urlencode($selected_category); ?>&apply_filter=1">
                <input type="hidden" name="update_stock" value="1">

                <div class="table-container">
                    <table class="sales-table">
                        <thead>
                            <tr>
                                <th>Cód. de Barras</th>
                                <th>Producto</th>
                                <th>Proveedor</th>
                                <th>Categoría</th>
                                <th>Stock Actual</th> 
                                <th>Fecha Ult. Conteo</th> 
                                <th>Diferencia (Nuevo - Actual)</th> 
                                <th>Nuevo Stock (Conteo Físico)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ($products_for_count as $product): 
                            $supplier_display = $product['supplier_name'] ?? 'N/A';
                            $category_display = $product['category_name'] ?? 'N/A';
                            
                            $stock_for_js = number_format((float)($product['stock'] ?? 0), 0, '.', '');
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($product['barcode'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($product['product_name'] ?? ''); ?></td>
                                
                                <td><?= htmlspecialchars($supplier_display); ?></td>
                                <td><?= htmlspecialchars($category_display); ?></td>
                                
                                <td class="actual-stock-cell" 
                                    data-stock="<?= $stock_for_js; ?>" 
                                    style="text-align: right; font-weight: 600;">
                                    <?= number_format((float)($product['stock'] ?? 0), 0, ',', '.'); ?>
                                </td>
                                
                                <td style="text-align: center; font-size: 0.8em; color: #4b5563;">
                                    <?php 
                                        echo $product['ultconteo'] 
                                            ? htmlspecialchars(date('Y-m-d', strtotime($product['ultconteo']))) 
                                            : 'N/A'; 
                                    ?>
                                </td>
                                
                                <td class="difference-cell" style="text-align: right; font-weight: 700;">
                                    0
                                </td>
                                
                                <td>
                                    <input type="text" 
                                        name="new_stock[<?= $product['id']; ?>]" 
                                        class="stock-input" 
                                        data-actual-stock="<?= $stock_for_js; ?>" 
                                        value="" 
                                        placeholder="<?= number_format((float)($product['stock'] ?? 0), 0, ',', '.'); ?>"
                                        pattern="[0-9]*[.,]?[0-9]*" 
                                        title="Solo números. Use punto o coma para decimales si aplica."
                                    >
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="text-align: right; padding: 15px 0;">
                    <button type="submit" class="btn-save-stock" onclick="return confirm('ATENCIÓN: ¿Está seguro de que desea actualizar el stock con los valores introducidos? Solo los campos llenados serán actualizados. Esta acción sobrescribe el stock actual y es irreversible.');">
                        💾 Guardar Ajustes de Inventario
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const stockInputs = document.querySelectorAll('.stock-input');
            
            // Función para formatear números sin decimales para el display
            function formatNumber(num) {
                // Si el número es entero, no muestra decimales.
                if (Number.isInteger(num)) {
                    return num.toLocaleString('es-ES', { maximumFractionDigits: 0 });
                }
                // Si tiene decimales (e.g., para un cálculo de diferencia no exacto)
                return num.toLocaleString('es-ES', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
            }

            // Función principal de cálculo de diferencia
            function updateDifference(event) {
                const input = event.target;
                
                // 1. Obtener valores
                // Sanitizar: convertir coma a punto y eliminar separadores de miles no deseados.
                let newStockValue = input.value.trim().replace(/\./g, '').replace(',', '.'); 
                let actualStock = parseFloat(input.getAttribute('data-actual-stock'));
                
                // 2. Encontrar la celda de diferencia
                const row = input.closest('tr');
                const differenceCell = row.querySelector('.difference-cell');
                
                if (!differenceCell) return;

                // 3. Validar y calcular
                if (newStockValue === '' || isNaN(parseFloat(newStockValue))) {
                    // Si el input está vacío o no es un número válido, mostrar 0 y color neutro
                    differenceCell.textContent = '0';
                    differenceCell.style.color = '#4b5563'; // Color gris neutro
                    return;
                }

                newStockValue = parseFloat(newStockValue);
                // Usamos toFixed(2) para asegurar precisión en la resta flotante y luego parseamos
                const difference = parseFloat((newStockValue - actualStock).toFixed(2));
                
                // 4. Mostrar y colorear la diferencia
                
                differenceCell.textContent = formatNumber(difference);
                
                // Aplicar color:
                if (difference > 0) {
                    differenceCell.style.color = 'var(--kpi-blue)'; // Azul (Sobra)
                } else if (difference < 0) {
                    differenceCell.style.color = 'var(--kpi-red)'; // Rojo (Falta)
                } else {
                    differenceCell.style.color = 'var(--kpi-green)'; // Verde (Cero/Correcto)
                }
            }

            stockInputs.forEach(input => {
                // Evento para actualizar la diferencia al escribir
                input.addEventListener('input', updateDifference);
                
                // Seleccionar todo el texto al enfocarse para facilitar el tipeo
                input.addEventListener('focus', function() {
                    this.select();
                });
            });
        });
    </script>
</body>
</html>