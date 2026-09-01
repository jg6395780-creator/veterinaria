<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/rut.php';
require_once __DIR__ . '/../includes/urgencias.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Solicitud no válida']);
    exit;
}

$duenoId = filter_var($input['dueno_id'] ?? null, FILTER_VALIDATE_INT);
$mascotaId = filter_var($input['mascota_id'] ?? null, FILTER_VALIDATE_INT);
$rut = normalizarRut(trim((string)($input['rut'] ?? '')));
$motivo = trim((string)($input['motivo'] ?? ''));
$telefono = trim((string)($input['telefono'] ?? ''));
$minutosLlegada = filter_var($input['minutos_llegada'] ?? null, FILTER_VALIDATE_INT);
$formaPago = trim((string)($input['forma_pago'] ?? 'por_definir'));
$formasPago = formasPagoUrgencia();

if (!$duenoId || !$mascotaId || !validarRut($rut)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'No se pudo identificar al dueño o la mascota']);
    exit;
}

if (mb_strlen($motivo) < 10 || mb_strlen($motivo) > 500) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Describe la urgencia con al menos 10 caracteres']);
    exit;
}

if (!preg_match('/^[0-9+()\s-]{8,30}$/', $telefono)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Ingresa un teléfono válido']);
    exit;
}

if (!$minutosLlegada || $minutosLlegada < 1 || $minutosLlegada > 180) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Selecciona un tiempo estimado de llegada']);
    exit;
}

if (!array_key_exists($formaPago, $formasPago)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Selecciona una forma de pago válida']);
    exit;
}

try {
    $pdo = getDB();
    asegurarTablaUrgencias($pdo);

    $stmtMascota = $pdo->prepare("SELECT m.id
        FROM mascotas m
        INNER JOIN duenos d ON d.id = m.dueno_id
        WHERE m.id = :mascota_id AND d.id = :dueno_id AND d.rut = :rut
        LIMIT 1");
    $stmtMascota->execute([
        ':mascota_id' => $mascotaId,
        ':dueno_id' => $duenoId,
        ':rut' => $rut,
    ]);

    if (!$stmtMascota->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'La mascota no está asociada a este dueño']);
        exit;
    }

    $stmtActiva = $pdo->prepare("SELECT id FROM urgencias
        WHERE mascota_id = :mascota_id
          AND estado IN ('pendiente', 'recibida', 'confirmada', 'en_atencion')
          AND fecha_solicitud >= DATE_SUB(NOW(), INTERVAL 6 HOUR)
        ORDER BY id DESC LIMIT 1");
    $stmtActiva->execute([':mascota_id' => $mascotaId]);
    $urgenciaActiva = $stmtActiva->fetchColumn();
    if ($urgenciaActiva) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Esta mascota ya tiene una urgencia activa. Comunícate con recepción si necesitas actualizarla.',
        ]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO urgencias
        (dueno_id, mascota_id, motivo, telefono, minutos_llegada, forma_pago, estado)
        VALUES (:dueno_id, :mascota_id, :motivo, :telefono, :minutos_llegada, :forma_pago, 'recibida')");
    $stmt->execute([
        ':dueno_id' => $duenoId,
        ':mascota_id' => $mascotaId,
        ':motivo' => $motivo,
        ':telefono' => $telefono,
        ':minutos_llegada' => $minutosLlegada,
        ':forma_pago' => $formaPago,
    ]);

    echo json_encode([
        'success' => true,
        'urgencia_id' => (int)$pdo->lastInsertId(),
        'message' => 'Urgencia enviada a recepción. Te contactarán para confirmar la atención.',
    ]);
} catch (Throwable $e) {
    error_log('Solicitar urgencia: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No fue posible registrar la urgencia']);
}
