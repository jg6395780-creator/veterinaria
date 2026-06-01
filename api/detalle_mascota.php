<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db_credenciales.php';

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $stmt = $pdo->prepare("
        SELECT m.*, d.nombre AS dueno, d.telefono
        FROM mascotas m
        JOIN duenos d ON m.dueno_id = d.id
        WHERE m.id = :id
    ");
    $stmt->execute([':id' => $id]);
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

    echo json_encode(['success' => true, 'mascota' => $mascota]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
