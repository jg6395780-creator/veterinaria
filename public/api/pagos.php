<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/rut.php';
require_once __DIR__ . '/../includes/modulos_clinica.php';

$rut = normalizarRut(trim((string)($_GET['rut'] ?? '')));
if (!validarRut($rut)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Sesion invalida']);
    exit;
}
try {
    $pdo = getDB();
    asegurarModulosClinica($pdo);
    $stmt = $pdo->prepare("SELECT p.id, p.concepto, p.detalle, p.monto, p.abonado, p.estado,
            p.fecha_emision, p.fecha_vencimiento, m.id mascota_id, m.nombre mascota,
            GREATEST(0, p.monto - p.abonado) saldo
        FROM presupuestos p
        JOIN mascotas m ON m.id = p.mascota_id
        JOIN duenos d ON d.id = m.dueno_id
        WHERE d.rut = :rut
        ORDER BY FIELD(p.estado, 'pendiente', 'aceptado', 'pagado', 'rechazado', 'vencido'), p.id DESC");
    $stmt->execute([':rut' => $rut]);
    echo json_encode(['success' => true, 'pagos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    error_log('Listado de pagos: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No fue posible cargar los pagos']);
}
