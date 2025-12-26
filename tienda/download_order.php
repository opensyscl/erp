<?php
session_start();

if (!isset($_SESSION['customer_id'])) {
    header('HTTP/1.0 403 Forbidden');
    exit('Acceso denegado.');
}

// Carpeta donde realmente se guardan los PDFs
$dir = __DIR__ . '/orders/';

$filename = basename($_GET['file']); // Nombre seguro
$filepath = $dir . $filename;

if (!is_file($filepath)) {
    header('HTTP/1.0 404 Not Found');
    exit('Archivo no encontrado.');
}

// Descarga forzada
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
