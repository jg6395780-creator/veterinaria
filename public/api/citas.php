<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/rut.php';
require_once __DIR__ . '/../includes/modulos_clinica.php';

function responderCita(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = getDB();
    asegurarModulosClinica($pdo);
    $input = $_SERVER['REQUEST_METHOD'] === 'POST'
        ? (json_decode(file_get_contents('php://input'), true) ?: [])
        : $_GET;
    $rut = normalizarRut(trim((string)($input['rut'] ?? '')));
    if (!validarRut($rut)) {
        http_response_code(422);
        responderCita(false, 'RUT inválido');
    }

    $stmt = $pdo->prepare('SELECT id FROM duenos WHERE rut = :rut LIMIT 1');
    $stmt->execute([':rut' => $rut]);
    $duenoId = (int)$stmt->fetchColumn();
    if (!$duenoId) {
        http_response_code(404);
        responderCita(false, 'Dueño no encontrado');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare("SELECT c.id, c.mascota_id, c.fecha_hora, c.motivo, c.estado,
                c.observacion, m.nombre mascota, m.identificador,
                u.nombre_completo veterinario
            FROM citas c
            JOIN mascotas m ON m.id = c.mascota_id
            LEFT JOIN usuarios u ON u.id = c.veterinario_id
            WHERE c.dueno_id = :dueno
            ORDER BY c.fecha_hora DESC LIMIT 100");
        $stmt->execute([':dueno' => $duenoId]);
        responderCita(true, 'Citas cargadas', ['citas' => $stmt->fetchAll()]);
    }

    $accion = trim((string)($input['accion'] ?? 'crear'));
    if ($accion === 'crear') {
        $mascotaId = (int)($input['mascota_id'] ?? 0);
        $fecha = str_replace('T', ' ', trim((string)($input['fecha_hora'] ?? '')));
        $motivo = trim((string)($input['motivo'] ?? ''));
        $stmt = $pdo->prepare('SELECT nombre FROM mascotas WHERE id = :mascota AND dueno_id = :dueno');
        $stmt->execute([':mascota' => $mascotaId, ':dueno' => $duenoId]);
        $mascota = $stmt->fetch();
        if (!$mascota || mb_strlen($motivo) < 5 || !strtotime($fecha) || strtotime($fecha) < time()) {
            http_response_code(422);
            responderCita(false, 'Revisa la mascota, fecha, hora y motivo');
        }
        $stmt = $pdo->prepare("INSERT INTO citas
            (dueno_id, mascota_id, fecha_hora, motivo, estado, creada_por)
            VALUES (:dueno, :mascota, :fecha, :motivo, 'solicitada', 'dueno')");
        $stmt->execute([
            ':dueno' => $duenoId,
            ':mascota' => $mascotaId,
            ':fecha' => date('Y-m-d H:i:s', strtotime($fecha)),
            ':motivo' => $motivo,
        ]);
        responderCita(true, 'Solicitud de cita enviada', ['cita_id' => (int)$pdo->lastInsertId()]);
    }

    $id = (int)($input['id'] ?? 0);
    if ($accion === 'cancelar') {
        $stmt = $pdo->prepare("UPDATE citas SET estado = 'cancelada'
            WHERE id = :id AND dueno_id = :dueno AND estado IN ('solicitada', 'confirmada')");
        $stmt->execute([':id' => $id, ':dueno' => $duenoId]);
        responderCita((bool)$stmt->rowCount(), $stmt->rowCount() ? 'Cita cancelada' : 'La cita no se puede cancelar');
    }

    if ($accion === 'reprogramar') {
        $fecha = str_replace('T', ' ', trim((string)($input['fecha_hora'] ?? '')));
        if (!strtotime($fecha) || strtotime($fecha) < time()) {
            http_response_code(422);
            responderCita(false, 'Selecciona una fecha y hora futura');
        }
        $stmt = $pdo->prepare("UPDATE citas
            SET fecha_hora = :fecha, estado = 'solicitada', veterinario_id = NULL,
                observacion = 'Reprogramación solicitada por el dueño'
            WHERE id = :id AND dueno_id = :dueno AND estado IN ('solicitada', 'confirmada')");
        $stmt->execute([
            ':fecha' => date('Y-m-d H:i:s', strtotime($fecha)),
            ':id' => $id,
            ':dueno' => $duenoId,
        ]);
        responderCita((bool)$stmt->rowCount(), $stmt->rowCount() ? 'Reprogramación solicitada' : 'La cita no se puede reprogramar');
    }

    http_response_code(422);
    responderCita(false, 'Acción no válida');
} catch (Throwable $e) {
    error_log('API citas: ' . $e->getMessage());
    http_response_code(500);
    responderCita(false, 'Error del servidor');
}
