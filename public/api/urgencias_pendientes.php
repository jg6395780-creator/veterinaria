<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_rol'] ?? '', ['admin', 'recepcion', 'veterinario'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión no autorizada']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/urgencias.php';

try {
    $pdo = getDB();
    asegurarTablaUrgencias($pdo);
    $esVeterinario = ($_SESSION['user_rol'] ?? '') === 'veterinario';
    $estadosVisibles = $esVeterinario ? "'confirmada', 'en_atencion'" : "'pendiente', 'recibida', 'confirmada', 'en_atencion'";
    $stmt = $pdo->query("SELECT u.id, u.motivo, u.telefono, u.minutos_llegada,
            u.forma_pago, u.estado, u.fecha_solicitud,
            m.nombre AS mascota, m.especie,
            d.nombre AS dueno, d.rut AS dueno_rut
        FROM urgencias u
        INNER JOIN mascotas m ON m.id = u.mascota_id
        INNER JOIN duenos d ON d.id = u.dueno_id
        WHERE u.estado IN ($estadosVisibles)
        ORDER BY FIELD(u.estado, 'pendiente', 'recibida', 'confirmada', 'en_atencion'), u.fecha_solicitud ASC");
    $urgencias = $stmt->fetchAll();
    $formasPago = formasPagoUrgencia();

    foreach ($urgencias as &$urgencia) {
        $urgencia['id'] = (int)$urgencia['id'];
        $urgencia['minutos_llegada'] = (int)$urgencia['minutos_llegada'];
        $urgencia['forma_pago_texto'] = $formasPago[$urgencia['forma_pago']] ?? 'Por definir';
    }
    unset($urgencia);

    echo json_encode([
        'success' => true,
        'cantidad' => count($urgencias),
        'urgencias' => $urgencias,
    ]);
} catch (Throwable $e) {
    error_log('Urgencias pendientes: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No fue posible consultar las urgencias']);
}
