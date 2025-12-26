<?php
/**
 * /erp/includes/module_check.php
 * Función para verificar el estado de activación de un módulo
 * consultando la tabla 'modules' en la base de datos.
 *
 * NOTA CRÍTICA: NO debe contener session_start(), ya que será incluido
 * en archivos que ya lo tienen (como sales.php).
 */

function is_module_enabled($folder_path) {
    // Es CRÍTICO hacer que la variable $pdo esté disponible
    // dentro del ámbito de esta función.
    global $pdo; 

    // 1. Verificación de la conexión PDO
    if (!isset($pdo)) {
        error_log("CRITICAL ERROR: PDO connection is not available in module_check.php");
        return false;
    }
    
    // 2. Verificación de la ruta
    if (empty($folder_path)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT is_active
            FROM modules
            WHERE folder_path = :path
        ");
        $stmt->bindParam(':path', $folder_path, PDO::PARAM_STR);
        $stmt->execute();
        $is_active = $stmt->fetchColumn();

        // Devuelve true si el resultado es 1, y false en cualquier otro caso (0 o no encontrado)
        return (int)$is_active === 1;

    } catch (PDOException $e) {
        error_log("Database Error in is_module_enabled: " . $e->getMessage());
        return false;
    }
}