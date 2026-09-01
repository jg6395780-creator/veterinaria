<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/rut.php';
require_once __DIR__ . '/../includes/modulos_clinica.php';

try {
    $pdo = getDB();
    asegurarModulosClinica($pdo);
    $rut = normalizarRut(trim((string)($_GET['rut'] ?? '')));
    $stmt = $pdo->prepare('SELECT id FROM duenos WHERE rut = :rut LIMIT 1');
    $stmt->execute([':rut' => $rut]);
    $duenoId = (int)$stmt->fetchColumn();
    if (!$duenoId) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Dueño no encontrado']);
        exit;
    }

    $recordatorios = [];
    $stmt = $pdo->prepare("SELECT v.id, m.nombre mascota, v.nombre_vacuna titulo,
            v.fecha_proxima_dosis fecha
        FROM vacunas v JOIN mascotas m ON m.id = v.mascota_id
        WHERE m.dueno_id = :dueno AND v.fecha_proxima_dosis IS NOT NULL
          AND v.fecha_proxima_dosis >= CURDATE()
        ORDER BY v.fecha_proxima_dosis LIMIT 30");
    $stmt->execute([':dueno' => $duenoId]);
    foreach ($stmt->fetchAll() as $item) {
        $recordatorios[] = array_merge($item, ['tipo' => 'vacuna', 'detalle' => 'Próxima dosis']);
    }

    $stmt = $pdo->prepare("SELECT r.id, m.nombre mascota, r.medicamento titulo,
            r.fecha_emision fecha, CONCAT(r.dosis, ' · ', r.frecuencia, ' · ', r.duracion) detalle
        FROM recetas r JOIN mascotas m ON m.id = r.mascota_id
        WHERE m.dueno_id = :dueno AND r.activa = 1
        ORDER BY r.fecha_emision DESC LIMIT 30");
    $stmt->execute([':dueno' => $duenoId]);
    foreach ($stmt->fetchAll() as $item) {
        $recordatorios[] = array_merge($item, ['tipo' => 'medicamento']);
    }

    $stmt = $pdo->prepare("SELECT c.id, m.nombre mascota, c.motivo titulo,
            c.fecha_hora fecha, 'Control agendado' detalle
        FROM citas c JOIN mascotas m ON m.id = c.mascota_id
        WHERE c.dueno_id = :dueno AND c.fecha_hora >= NOW()
          AND c.estado IN ('solicitada', 'confirmada')
        ORDER BY c.fecha_hora LIMIT 30");
    $stmt->execute([':dueno' => $duenoId]);
    foreach ($stmt->fetchAll() as $item) {
        $recordatorios[] = array_merge($item, ['tipo' => 'control']);
    }

    usort($recordatorios, fn($a, $b) => strcmp((string)$a['fecha'], (string)$b['fecha']));
    echo json_encode(['success' => true, 'recordatorios' => $recordatorios], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('API recordatorios: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
