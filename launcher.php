<?php
/**
* ===========================================================================
* LAUNCHER PRINCIPAL DEL SISTEMA LISTTO
* ---------------------------------------------------------------------------
* Este script gestiona la autenticación, carga datos del usuario y versión
* del sistema, y presenta el menú principal de acceso (Launcher).
* Requiere el archivo de configuración de la base de datos (config.php).
* ===========================================================================
*/

// Iniciar sesión (es la primera instrucción)
session_start();

// Cargar la configuración de la base de datos ($pdo)
require_once "config.php";

// ------------------------------------------------------------------
// 1. SEGURIDAD Y VERIFICACIÓN DE SESIÓN
// ------------------------------------------------------------------

// Si el usuario no ha iniciado sesión, redirigir a la página de login
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

// ------------------------------------------------------------------
// 2. LÓGICA: Obtener el nombre de usuario de la DB
// ------------------------------------------------------------------

$current_user_id = $_SESSION['user_id'];
// Establecemos un valor predeterminado si la consulta falla
$_SESSION['user_username'] = 'Usuario';

try {
  // Consulta SQL para obtener el 'username' usando el 'id' del usuario logueado
  $stmt = $pdo->prepare("SELECT username FROM users WHERE id = :user_id");
  $stmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
  $stmt->execute();
  $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

  // Si se encuentra el usuario, actualizamos el nombre de usuario en la sesión
  if ($user_data && isset($user_data['username'])) {
    $_SESSION['user_username'] = $user_data['username'];
  }
} catch (PDOException $e) {
  // Registrar el error en el log en caso de problemas con la DB
  error_log("Error al obtener username: " . $e->getMessage());
}

// ------------------------------------------------------------------
// 3. LÓGICA: Obtener la versión del sistema
// ------------------------------------------------------------------

$app_version = "1.0"; // Valor predeterminado en caso de fallo
try {
  // Consulta la versión de la aplicación desde la tabla 'config'
  $stmt = $pdo->prepare("SELECT value FROM config WHERE name='version'");
  $stmt->execute();
  $version_result = $stmt->fetchColumn();
 
  if ($version_result) {
    $app_version = $version_result;
  }
} catch (PDOException $e) {
  // Si la consulta de la versión falla, se mantiene el valor predeterminado.
  error_log("Error al obtener la versión del sistema: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Launcher - Tiendas Listto</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="img/fav.png">
 
    <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
 
    <link href="css/launcher.css" rel="stylesheet">
</head>
<body>
 
        <header class="main-header">
    <div class="header-left">
            <span>Hola, <strong><?php echo htmlspecialchars($_SESSION['user_username']); ?></strong></span>
    </div>
    <div class="logo-container">
      <img src="img/asigna.png" alt="Logo Tiendas Listto" class="logo">
    </div>
    <div class="header-right">
            <span class="app-version"><?= htmlspecialchars($app_version) ?></span>

            <form action="logout.php" method="post">
        <button type="submit" class="btn-logout">Salir</button>
      </form>
    </div>
  </header>
 
    <div class="container">
    <main>
            <input type="text" id="appSearch" placeholder="Escribe para buscar un módulo..." class="hidden-search">

                        <div class="grid" id="appGrid">

 <a class="app" href="pos.php">
  <div class="app-content">
   <div class="app-icon">💳</div>
   Punto de Venta
   <small class="app-description">Vende en tu tienda fácilmente</small>
  </div>
  <div class="app-footer">
   Ir a Punto de Venta →
  </div>
 </a>

 <a class="app" href="sales/sales.php">
  <div class="app-content">
   <div class="app-icon">📊</div>
   Ventas
   <small class="app-description">Analiza ventas de productos, proveedores y categorías</small>
  </div>
  <div class="app-footer">
   Ir a Ventas →
  </div>
 </a>

 <a class="app" href="inventario.php">
  <div class="app-content">
   <div class="app-icon">📦</div>
   Inventario
   <small class="app-description">Gestiona tus productos y analiza su rendimiento</small>
  </div>
  <div class="app-footer">
   Ir a Inventario →
  </div>
 </a>

 <a class="app" href="offers/offers.php">
  <div class="app-content">
   <div class="app-icon">🔖️</div>
   Packs y Promos
   <small class="app-description">Crea y gestiona ofertas para impulsar ventas</small>
  </div>
  <div class="app-footer">
   Ir a Promociones →
  </div>
 </a>

 <a class="app" href="ranking/ranking.php">
  <div class="app-content">
   <div class="app-icon">🏆</div>
   Ranking de Productos
   <small class="app-description">Identifica productos estrella y de menor rotación</small>
  </div>
  <div class="app-footer">
   Ir a Ranking →
  </div>
 </a>

 <a class="app" href="labels/labels.php">
  <div class="app-content">
   <div class="app-icon">🖨️️</div>
   Centro de Etiquetas
   <small class="app-description">Imprime etiquetas de precios con un solo clic</small>
  </div>
  <div class="app-footer">
   Ir a Etiquetas →
  </div>
 </a>

  <a class="app" href="terceros/terceros.php">
  <div class="app-content">
   <div class="app-icon">💼️</div>
   Gestión de Terceros
   <small class="app-description">Gestiona tus Clientes y Proveedores</small>
  </div>
  <div class="app-footer">
   Ir a Terceros →
  </div>
 </a>

  <a class="app" href="quotation/quotation.php">
  <div class="app-content">
   <div class="app-icon">📝️</div>
   Cotizaciones
   <small class="app-description">Genera cotizaciones a tus proveedores</small>
  </div>
  <div class="app-footer">
   Ir a Cotizaciones →
  </div>
 </a>

 <a class="app" href="orders/orders.php">
  <div class="app-content">
   <div class="app-icon">📋</div>
   Pedidos
   <small class="app-description">Crea pedidos a tus proveedores</small>
  </div>
  <div class="app-footer">
   Ir a Pedidos →
  </div>
 </a>

 <a class="app" href="suppliers/suppliers.php">
  <div class="app-content">
   <div class="app-icon">🏭</div>
   Ingreso de Facturas
   <small class="app-description">Ingresa facturas de tus proveedores</small>
  </div>
  <div class="app-footer">
   Ir a Compras →
  </div>
 </a>

 <a class="app" href="pagos/pagos.php">
  <div class="app-content">
   <div class="app-icon">💸</div>
   Pagos Proveedores
   <small class="app-description">Reivsa pagos efectuados, pendientes y por realziar</small>
  </div>
  <div class="app-footer">
   Ir a Pagos →
  </div>
 </a>

 <a class="app" href="capital/capital.php">
  <div class="app-content">
   <div class="app-icon">📈</div>
   Análisis de Capital
   <small class="app-description">Visualiza la estructura de tu capital y toma decisiones</small>
  </div>
  <div class="app-footer">
   Ir a Análisis →
  </div>
  </a>

 
 <a class="app" href="caja/caja.php">
  <div class="app-content">
   <div class="app-icon">🧾</div>
   Cuadres de Caja
   <small class="app-description">Controla correctamente tu caja y cierres diarios</small>
  </div>
  <div class="app-footer">
   Ir a Caja →
  </div>
 </a>

 <a class="app" href="operaciones/operaciones.php">
  <div class="app-content">
   <div class="app-icon">💰️️</div>
   Gastos Operativos
   <small class="app-description">Registra y categoriza todos los gastos de operación</small>
  </div>
  <div class="app-footer">
   Ir a Gastos →
  </div>
 </a>

 <a class="app" href="decrease/decrease.php">
  <div class="app-content">
   <div class="app-icon">📉</div>
   Registro de Mermas
   <small class="app-description">Lleva un control detallado de pérdidas y mermas</small>
  </div>
  <div class="app-footer">
   Ir a Mermas →
  </div>
 </a>

 <a class="app" href="outputs/outputs.php">
  <div class="app-content">
   <div class="app-icon">🗑️</div>
   Consumo Interno
   <small class="app-description">Gestiona productos para uso interno</small>
  </div>
  <div class="app-footer">
   Ir a Consumos →
  </div>
 </a>

<a class="app" href="task/task.php">
  <div class="app-content">
    <div class="app-icon">🗃️</div>
    Centro de Tareas
    <small class="app-description">Gestiona y organiza tus tareas pendientes</small>
  </div>
  <div class="app-footer">
    Ir a Tareas →
  </div>
</a>

 <a class="app" href="schedules/schedules.php">
  <div class="app-content">
   <div class="app-icon">📅️️</div>
   Horarios y Turnos
   <small class="app-description">Gestión de horarios de empleados y turnos de caja</small>
  </div>
  <div class="app-footer">
   Ir a Turnos →
  </div>
 </a>
 
  <a class="app" href="attendance/attendance.php">
  <div class="app-content">
   <div class="app-icon">⏰️️</div>
   Registro de Asistencias
   <small class="app-description">Registra y gestiona la asistencia de tu equipo</small>
  </div>
  <div class="app-footer">
   Ir a Turnos →
  </div>
 </a>
 
        <a class="app" href="tienda/index.php">
     <div class="app-content">
      <div class="app-icon">🛍️</div>
      eCommerce
      <small class="app-description">Accede a la eCommerce de tu tienda</small>
     </div>
     <div class="app-footer">
      Ir a eCommerce →
     </div>
    </a>
   
        <a class="app" href="tienda/dash/login.php">
     <div class="app-content">
      <div class="app-icon">💻</div>
      Ventas eCommerce
      <small class="app-description">Administra las ventas de tu eCommerce</small>
     </div>
     <div class="app-footer">
      Ir al Panel →
     </div>
    </a>
   </div> 
    </main>
  </div>
 
      <footer class="footer">
 &copy; 2025 <strong>Asigna!</strong> - Todos los derechos reservados.
</footer>

<script src="js/launcher.js"></script>

</body>
</html>