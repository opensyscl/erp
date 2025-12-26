<?php
// ==============================================================================
// === VERSIÓN FINAL: PRODUCCIÓN. TOMA DATOS DIRECTAMENTE DE LA URL (GET) ===
// Esta versión es independiente de la base de datos y utiliza los parámetros 
// de la URL (name, barcode, price).
// ==============================================================================

// Configuraciones para depuración (pueden ser desactivadas en producción si no son necesarias)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inicializamos el array de producto vacío
$product = [];

// -----------------------------------------------------------
// 1. CONSTRUCCIÓN DE OBJETO $product A PARTIR DE $_GET
// -----------------------------------------------------------

if (isset($_GET['name']) && isset($_GET['price'])) {
    $product['name'] = $_GET['name'];
    $product['barcode'] = $_GET['barcode'] ?? 'N/A'; // Usamos un fallback si no hay código de barras
    
    // Proceso de sanitización y conversión del precio (formato chileno: 2.690 -> 2690)
    $price_str = str_replace('.', '', $_GET['price']);    
    $product['sale_price'] = (float)$price_str;
}

// Convertimos el array PHP a JSON para inyectarlo en JavaScript
if (!empty($product)) {
    $product_json = json_encode($product);
} else {
    // Manejo de error si faltan datos críticos
    $product_json = json_encode(['error' => 'Error de Datos', 'message' => 'Faltan parámetros críticos (name o price) en la URL.']);
}
// -----------------------------------------------------------
// FIN DE LÓGICA PHP
// -----------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impresión de Etiqueta: <?php echo htmlspecialchars($product['name'] ?? 'Producto'); ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;800;900&display=swap" rel="stylesheet">
    
  <style>
    /* Variables y Estilos Generales */
    :root {
      --color-primary: #000000;
      --color-background: #ffffff;
      --font-main: 'Gotham', 'Century Gothic', CenturyGothic, Futura, 'Inter', sans-serif;
     
      /* CAMBIOS CLAVE: Reducción del 10% adicional */
      --label-width: 277px;
      --label-height: 109px;
    }

    body {
      /* IMPORTANTE: Sin márgenes para impresión */
      font-family: var(--font-main);
      margin: 0;
      padding: 0;
      background-color: var(--color-background);
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
    }

    .product-label {
width: var(--label-width);
    height: var(--label-height);
    background-color: var(--color-background);
    border: 1px solid #ccc;
    padding: 27px 20px 8px;
    display: flex
;
    box-sizing: border-box;
    overflow: visible;
    box-shadow: none;
    position: relative;
    }
   
    /* Estilos específicos para impresión */
    @media print {
      body {
        background: none;
        display: block;
        padding: 0;
      }
      .product-label {
        border: none; /* Eliminar borde para impresión */
        box-shadow: none;
        margin: 0;
        page-break-after: always;
      }
    }


    /* BARRA LATERAL (Branding) */
    .sidebar {
      /* Ajuste de 65px a 58px */
      width: 58px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      align-items: center;
      border-right: 1px solid var(--color-primary);
      /* Ajuste de 6px a 5px */
      padding-right: 5px;
      margin-right: 5px;
      text-align: center;
    }

    .sidebar-logo-text {
      font-size: 8px;
      font-weight: 700; 
      line-height: 1;
      margin-bottom: 2px;
    }

    .sidebar-image-logo {
      /* Ajuste de 32px a 28px */
      width: 28px;
      height: 28px;
      object-fit: contain;
      margin: 2px 0;
    }

    .sidebar-social {
      /* Ajuste de 10px a 9px */
      font-size: 9px;
      font-weight: 400;
      margin-bottom: 1px;
    }

    /* CONTENIDO PRINCIPAL */
    .main-content {
      flex-grow: 1;
      /* MODIFICADO: Eliminamos flex-direction para un flujo normal */
      display: block; 
      /* MODIFICADO: Agregamos padding para evitar que el nombre se superponga al precio si es muy largo */
      padding-bottom: 30px; 
    }

    .product-name {
      /* Ajuste de 20px a 18px */
      font-size: 14px;
      font-weight: 800; 
      line-height: 1.1;
      /* MODIFICADO: Aseguramos que el contenido fluya */
      overflow: visible;
      margin-bottom: 3px; /* Ajuste de 4px a 3px */
      word-wrap: break-word; /* Para nombres muy largos */
    }

    .price-section {
      /* MODIFICADO: Posicionamiento absoluto para desvincular del flujo del nombre */
      position: absolute; 
      bottom: 7px; /* Alinear con el padding inferior de .product-label */
      right: 7px; /* Alinear con el padding derecho de .product-label */
     
      /* Mantenemos estos estilos para el formato del precio dentro de la sección */
      display: flex;
      justify-content: flex-end; 
      align-items: flex-end;
      text-align: right;
      /* Eliminado margin-top: auto; */
    }

    .price-details {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      line-height: 1;
    }

    .price-label {
      /* Ajuste de 8px a 7px */
      font-size: 7px;
      font-weight: 400;
      margin-bottom: 0px;
    }

    .price-value {
      /* Ajuste de 32px a 28px */
      font-size: 28px;
      font-weight: 900; 
      letter-spacing: -0.8px; /* Ajuste sutil */
      color: var(--color-primary);
    }
   
    .barcode-number {
      /* Ajuste de 8px a 7px */
      font-size: 7px;
      font-weight: 400;
      margin-top: 1px;
    }

    .simulated-data {
      display: none;
    }
  </style>
</head>
<body>

    <div class="product-label" id="label-container">
        
        <div class="sidebar">
            <div class="sidebar-logo-text">Tiendas Listto!</div>
            <img src="https://tiendaslistto.cl/erp/img/fav.png" class="sidebar-image-logo" alt="Logo de la Tienda" 
                onerror="this.onerror=null; this.src='https://placehold.co/28x28/000/fff?text=LST';" />
            <div class="sidebar-social" id="category-display">@listtod</div>
        </div>

        <div class="main-content">
            
            <div class="product-name" id="product-name-display">Cargando Nombre...</div>

            <div class="price-section">
                <div class="price-details">
                    <div class="price-label" id="price-label-display">Precio:</div>
                    <div class="price-value" id="price-value-display">---</div>
                    <div class="barcode-number" id="barcode-display">00000000000</div>
                </div>
            </div>

        </div>
    </div>


    <script>
        // Objeto de datos inyectado desde PHP
        const productData = <?php echo $product_json; ?>;    

        /**
         * Convierte una cadena a 'Title Case'.
         */
        function toTitleCase(str) {
            if (!str) return '';
            return str.toLowerCase().split(' ').map(function(word) {
                return (word.charAt(0).toUpperCase() + word.slice(1));
            }).join(' ');
        }

        /**
         * Capitaliza solo la primera letra de la primera palabra.
         */
        function capitalizeFirstLetter(str) {
            if (!str) return '';
            return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
        }


        // Función principal para actualizar la etiqueta
        function updateLabel(product) {
            
            const nameElement = document.getElementById('product-name-display');
            const rawName = product.name || 'PRODUCTO SIN NOMBRE';
            const formattedName = toTitleCase(rawName);
            
            // Lógica de salto de línea para nombres largos
            const nameWords = formattedName.split(' ');
            
            if (nameWords.length >= 2 && nameWords[0].length + nameWords[1].length < 15) {    
                const line1Text = nameWords.slice(0, 2).join(' ');
                const line2Text = nameWords.slice(2).join(' ');
                nameElement.innerHTML = line1Text + '<br>' + line2Text;
            } else {
                nameElement.textContent = formattedName;
            }

            
            // 2. Precio (sale_price) - Formato CLP sin decimales
            const priceElement = document.getElementById('price-value-display');
            const priceValue = product.sale_price || product.price || 0;
            
            const formattedPrice = new Intl.NumberFormat('es-CL', {
                style: 'currency',
                currency: 'CLP',    
                minimumFractionDigits: 0
            }).format(priceValue).replace('CLP', '$');    
            
            priceElement.textContent = formattedPrice;

            // 3. Etiqueta "Precio"
            const priceLabelElement = document.getElementById('price-label-display');
            priceLabelElement.textContent = capitalizeFirstLetter(priceLabelElement.textContent);


            // 4. Código de Barras / SKU (barcode)
            const barcodeElement = document.getElementById('barcode-display');
            barcodeElement.textContent = product.barcode || 'N/A';
        }

        // Ejecutar al cargar la ventana
        window.onload = function() {
            if (productData && !productData.error) {
                updateLabel(productData);
                
                // 1. Lanza la ventana de impresión
                window.print();    

                // 2. CIERRE AUTOMÁTICO DE LA VENTANA
                setTimeout(function() {
                    window.close();
                }, 500); // Retraso de 500ms

            } else {
                // Muestra el error si faltan datos
                console.error("No se encontraron datos de producto para imprimir. Mensaje:", productData.message);
                const container = document.getElementById('label-container');
                container.style.justifyContent = 'center';
                container.style.alignItems = 'center';
                container.style.flexDirection = 'column';

                const errorMessage = productData.message || 'Faltan parámetros críticos (name o price) en la URL.';

                container.innerHTML =    
                    '<h2 style="color: red; font-size: 16px;">ERROR DE DATOS</h2>' +
                    '<p style="font-size: 12px; text-align: center;">' + errorMessage + '</p>';
            }
        };

    </script>
</body>
</html>