<?php
// schedules_api.php

header('Content-Type: application/json');

require '../../config.php';
session_start();

// Validar que el usuario esté logueado y que la conexión PDO exista
if (!isset($_SESSION['user_username']) || !isset($pdo)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado o conexión PDO fallida.']);
    exit();
}

// Obtener el método HTTP y los datos JSON de la solicitud
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    switch ($method) {
        
        // La lógica de creación/actualización se maneja por POST
        case 'POST':
        case 'PUT': 
            // ------------------------------------
            // Lógica de CREAR y ACTUALIZAR Horario
            // ------------------------------------
            
            // Asumiendo que tu frontend envía 'action' solo para crear/actualizar
            // Si tu frontend siempre usa POST para ambas y distingue por 'action', mantenemos la variable:
            $action = $data['action'] ?? ($method === 'POST' ? 'create' : 'update');
            
            // Si el frontend distingue por 'action', lo manejamos aquí
            if ($action !== 'create' && $action !== 'update') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Acción POST no válida (debe ser create o update).']);
                exit();
            }

            // Validación básica de campos requeridos
            $employee_id = $data['employee_id'] ?? null;
            $schedule_date = $data['schedule_date'] ?? null;

            if (!$employee_id || !$schedule_date) {
                throw new Exception("Faltan campos obligatorios (empleado o fecha).");
            }
            
            // Preparar valores para la inserción/actualización
            $schedule_id = $data['id'] ?? null;
            $shift_id = $data['shift_id'] ?? null;
            $custom_start = $data['custom_start'] ?? null;
            $custom_end = $data['custom_end'] ?? null;
            $is_day_off = (int)($data['is_day_off'] ?? 0);
            $notes = $data['notes'] ?? null;
            
            // Lógica para determinar qué campos anular (como ya tenías)
            if ($data['schedule_type'] === 'shift') {
                $custom_start = null; $custom_end = null;
            } elseif ($data['schedule_type'] === 'custom') {
                $shift_id = null;
            } elseif ($data['schedule_type'] === 'dayoff') {
                $shift_id = null; $custom_start = null; $custom_end = null;
            }

            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO schedules (employee_id, schedule_date, shift_id, custom_start, custom_end, is_day_off, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$employee_id, $schedule_date, $shift_id, $custom_start, $custom_end, $is_day_off, $notes]);
                echo json_encode(['success' => true, 'message' => 'Horario creado.']);
            } elseif ($action === 'update') {
                if (!$schedule_id) {
                     throw new Exception("ID de horario para actualizar no proporcionado.");
                }
                $stmt = $pdo->prepare("UPDATE schedules SET shift_id = ?, custom_start = ?, custom_end = ?, is_day_off = ?, notes = ? WHERE id = ?");
                $stmt->execute([$shift_id, $custom_start, $custom_end, $is_day_off, $notes, $schedule_id]);
                echo json_encode(['success' => true, 'message' => 'Horario actualizado.']);
            }
            break;

        // La lógica de eliminación se maneja por DELETE
        case 'DELETE':
            // ------------------------------------
            // Lógica de ELIMINAR Horario
            // ------------------------------------
            $schedule_id = $data['id'] ?? null; // El ID ya viene del body JSON
            
            if (!$schedule_id) {
                http_response_code(400);
                throw new Exception("ID de horario para eliminar no proporcionado.");
            }
            
            $stmt = $pdo->prepare("DELETE FROM schedules WHERE id = ?");
            $stmt->execute([$schedule_id]);
            
            echo json_encode(['success' => true, 'message' => 'Horario eliminado.']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
            break;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de Base de Datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>