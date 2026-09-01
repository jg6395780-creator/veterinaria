<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/rut.php';
require_once __DIR__ . '/../includes/documentos_clinicos.php';
require_once __DIR__ . '/../includes/modulos_clinica.php';

$id = (int)($_GET['id'] ?? 0);
$rut = normalizarRut(trim((string)($_GET['rut'] ?? '')));

if (!$id || !validarRut($rut)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Datos de acceso inválidos']);
    exit;
}

try {
    $pdo = getDB();
    asegurarTablaDocumentosClinicos($pdo);
    asegurarModulosClinica($pdo);

    $stmt = $pdo->prepare("
        SELECT m.*, d.nombre AS dueno, d.telefono
        FROM mascotas m
        JOIN duenos d ON m.dueno_id = d.id
        WHERE m.id = :id AND d.rut = :rut
    ");
    $stmt->execute([':id' => $id, ':rut' => $rut]);
    $mascota = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mascota) {
        echo json_encode(['success' => false, 'message' => 'Mascota no encontrada']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM historial_clinico WHERE mascota_id = :id ORDER BY fecha_visita DESC");
    $stmt->execute([':id' => $id]);
    $mascota['historial'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM vacunas WHERE mascota_id = :id ORDER BY fecha_aplicacion DESC");
    $stmt->execute([':id' => $id]);
    $mascota['vacunas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT dc.id, dc.tipo, dc.titulo, dc.descripcion,
            dc.nombre_original, dc.mime_type, dc.tamano, dc.token_descarga,
            dc.fecha_subida, u.nombre_completo AS subido_por
        FROM documentos_clinicos dc
        LEFT JOIN usuarios u ON u.id = dc.subido_por
        WHERE dc.mascota_id = :id
        ORDER BY dc.fecha_subida DESC");
    $stmt->execute([':id' => $id]);
    $mascota['documentos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT r.id, r.diagnostico, r.medicamento, r.dosis, r.frecuencia,
            r.duracion, r.indicaciones, r.fecha_emision, u.nombre_completo AS veterinario
        FROM recetas r JOIN usuarios u ON u.id = r.veterinario_id
        WHERE r.mascota_id = :id AND r.activa = 1 ORDER BY r.fecha_emision DESC, r.id DESC");
    $stmt->execute([':id' => $id]);
    $mascota['recetas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT c.id, c.fecha_hora, c.motivo, c.estado, c.observacion,
            u.nombre_completo AS veterinario
        FROM citas c LEFT JOIN usuarios u ON u.id = c.veterinario_id
        WHERE c.mascota_id = :id ORDER BY c.fecha_hora DESC LIMIT 30");
    $stmt->execute([':id' => $id]);
    $mascota['citas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT p.id, p.concepto, p.detalle, p.monto, p.abonado, p.estado,
            p.fecha_emision, p.fecha_vencimiento
        FROM presupuestos p WHERE p.mascota_id = :id ORDER BY p.fecha_emision DESC LIMIT 30");
    $stmt->execute([':id' => $id]);
    $mascota['presupuestos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT h.id, h.box, h.motivo, h.indicaciones, h.estado,
            h.fecha_ingreso, h.fecha_alta, u.nombre_completo AS veterinario
        FROM hospitalizaciones h LEFT JOIN usuarios u ON u.id = h.veterinario_id
        WHERE h.mascota_id = :id ORDER BY h.fecha_ingreso DESC LIMIT 20");
    $stmt->execute([':id' => $id]);
    $mascota['hospitalizaciones'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'mascota' => $mascota]);
} catch (Throwable $e) {
    error_log('Detalle de mascota: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
