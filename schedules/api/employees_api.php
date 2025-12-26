<?php
// employees_api.php
header('Content-Type: application/json');
require '../../config.php'; // Ajusta la ruta a tu config.php
session_start();

// Validar que el usuario esté logueado
if (!isset($_SESSION['user_username']) || !isset($pdo)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado o conexión PDO fallida.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // ------------------------------------
            // READ: Obtener lista de empleados (o uno específico)
            // ------------------------------------
            $id = $_GET['id'] ?? null;
            
            if ($id) {
                // Obtener un solo empleado
                $stmt = $pdo->prepare("SELECT id, name, is_active FROM employees WHERE id = ?");
                $stmt->execute([$id]);
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($employee) {
                    echo json_encode(['success' => true, 'data' => $employee]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Empleado no encontrado.']);
                }
            } else {
                // Obtener todos los empleados
                $stmt = $pdo->query("SELECT id, name, is_active FROM employees ORDER BY name ASC");
                $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $employees]);
            }
            break;

        case 'POST':
        case 'PUT':
            // ------------------------------------
            // CREATE & UPDATE: Crear o Actualizar empleado
            // ------------------------------------
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            $name = trim($data['name'] ?? '');
            $is_active = (int)($data['is_active'] ?? 1); // Por defecto activo
            
            if (empty($name)) {
                throw new Exception("El nombre del empleado es obligatorio.");
            }

            if ($id) {
                // UPDATE
                $stmt = $pdo->prepare("UPDATE employees SET name = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $is_active, $id]);
                echo json_encode(['success' => true, 'message' => 'Empleado actualizado.', 'id' => $id]);
            } else {
                // CREATE
                $stmt = $pdo->prepare("INSERT INTO employees (name, is_active) VALUES (?, ?)");
                $stmt->execute([$name, $is_active]);
                $newId = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Empleado creado.', 'id' => $newId]);
            }
            break;

        case 'DELETE':
            // ------------------------------------
            // DELETE: Eliminar empleado
            // ------------------------------------
            // Nota: Para solicitudes DELETE, el cuerpo JSON no siempre está disponible,
            // por lo que a menudo se usa un query param o una simulación POST.
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            
            if (!$id) {
                throw new Exception("ID del empleado es obligatorio para eliminar.");
            }
            
            // Revisa si hay horarios asociados (buena práctica de integridad)
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM schedules WHERE employee_id = ?");
            $stmt_check->execute([$id]);
            if ($stmt_check->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No se puede eliminar el empleado porque tiene horarios asignados. Desactívelo en su lugar.']);
                exit();
            }

            $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Empleado eliminado.']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>