
# Listto! POS - Minimal Web POS (PHP + MySQL)

Este es un prototipo funcional de **Punto de Venta (POS)** con **Inventario** para hosting compartido (PHP + MySQL).
Ruta sugerida: `tiendaslistto.cl/erp`

## Funcionalidades
- Inventario (CRUD): productos con código de barras, nombre, precio, stock y categoría.
- POS con búsqueda + lector de código de barras (entrada enfocada).
- Carrito, totales, pago y cambio.
- Cierre de venta: descuenta stock, guarda venta y sus items.
- Ticket imprimible con **CSS 80mm** (térmica).
- Importar productos desde **CSV** (cabeceras: `barcode,name,price,stock,category`).

## Requisitos
- PHP 7.4+ (recomendado PHP 8.x)
- MySQL 5.7+ o MariaDB 10+
- Extensión PDO MySQL habilitada
- Zona horaria del servidor: America/Santiago

## Instalación
1. Crea una base de datos (ejemplo `listto_pos`).
2. Importa `db.sql` en esa base.
3. Copia esta carpeta `erp/` completa a tu hosting: `public_html/erp` o `tiendaslistto.cl/erp`.
4. Edita `config.php` con tus credenciales MySQL.
5. Abre `https://tiendaslistto.cl/erp/` y prueba.

## CSV de ejemplo
```
barcode,name,price,stock,category
7801234567890,Coca Cola 350ml,990,100,Bebidas
7801111111111,Pan Hallulla 1u,450,50,Panadería
7802222222222,Snack Papas 95g,1290,40,Snacks
```
Guárdalo como `productos.csv` e impórtalo desde **Inventario → Importar CSV**.

## Impresión 80mm
- Usa el botón **Imprimir Ticket** (o impresión automática al abrir la página de ticket).
- Si tu navegador pide bordes: en opciones de impresión, desmarca encabezados/pies y márgenes ajustados.
- Opcional avanzado: integrar **QZ Tray** para impresión directa; este prototipo usa impresión del navegador con CSS térmica.

## Seguridad (nota)
Este prototipo **no incluye login**. Si lo expones a internet, agrega autenticación (por htpasswd o un login PHP).
