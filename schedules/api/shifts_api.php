<?php
// shifts_api.php

// Habilitar errores para diagnóstico (IMPORTANTE para ver errores SQL)
error_reporting(E_ALL);
ini_set('display_errors', 1); 

header('Content-Type: application/json');

// La ruta debe ser la correcta relativa a donde está este archivo (api/)
require '../../config.php'; 
session_start();

// Validar que el usuario esté logueado
if (!isset($_SESSION['user_username']) || !isset($pdo)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado o conexión PDO fallida.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'GET':
            // ------------------------------------
            // READ: Obtener lista de turnos
            // ------------------------------------
            $stmt = $pdo->query("SELECT id, name, start_time, end_time, color_code FROM shifts ORDER BY start_time ASC");
            $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $shifts]);
            break;

        case 'POST':
        case 'PUT':
            // ------------------------------------
            // CREATE & UPDATE: Crear o Actualizar turno
            // ------------------------------------
            
            $id = $data['id'] ?? null;
            $name = trim($data['name'] ?? '');
            $start_time = $data['start_time'] ?? null;
            $end_time = $data['end_time'] ?? null;
            $color_code = $data['color_code'] ?? '#3b82f6';
            
            if (empty($name) || empty($start_time) || empty($end_time)) {
                throw new Exception("Nombre, hora de inicio y fin del turno son obligatorios.");
            }

            if ($id) {
                // UPDATE
                $sql = "UPDATE shifts SET name = ?, start_time = ?, end_time = ?, color_code = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$name, $start_time, $end_time, $color_code, $id]);
                echo json_encode(['success' => true, 'message' => 'Turno actualizado.', 'id' => $id]);
            } else {
                // CREATE
                $sql = "INSERT INTO shifts (name, start_time, end_time, color_code) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$name, $start_time, $end_time, $color_code]);
                $newId = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Turno creado.', 'id' => $newId]);
            }
            break;

        case 'DELETE':
            // ------------------------------------
            // DELETE: Eliminar turno
            // ------------------------------------
            // **IMPORTANTE**: Los datos se leen del cuerpo de la petición.
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null; // Obtener el ID del cuerpo JSON
            
            if (!$id) {
                // Si el ID no se encuentra en el cuerpo de la petición
                http_response_code(400); // Bad Request
                echo json_encode(['success' => false, 'message' => 'ID del turno es obligatorio para eliminar.']);
                exit();
            }
            
            // Revisa si hay horarios asociados para evitar errores de integridad
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM schedules WHERE shift_id = ?");
            $stmt_check->execute([$id]);
            if ($stmt_check->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No se puede eliminar el turno porque está asignado a uno o más horarios.']);
                exit();
            }

            // Ejecución de la eliminación
            $stmt = $pdo->prepare("DELETE FROM shifts WHERE id = ?");
            $success = $stmt->execute([$id]);

            if ($success) {
                 echo json_encode(['success' => true, 'message' => 'Turno eliminado.']);
            } else {
                 // Capturar posibles errores SQL (aunque PDOException suele hacerlo)
                 http_response_code(500);
                 echo json_encode(['success' => false, 'message' => 'Error al ejecutar la eliminación SQL.', 'errorInfo' => $stmt->errorInfo()]);
            }
            
            break;
    }

} catch (PDOException $e) {
    // Captura errores de base de datos (SQL)
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Captura otros errores de lógica
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>