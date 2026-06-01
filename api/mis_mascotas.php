<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db_credenciales.php';

$dueno_id = (int)($_GET['dueno_id'] ?? 0);

if (!$dueno_id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $stmt = $pdo->prepare("
        SELECT m.id, m.identificador, m.nombre, m.especie, m.raza,
               m.peso, m.fecha_nacimiento, d.nombre AS dueno, d.telefono
        FROM mascotas m
        JOIN duenos d ON m.dueno_id = d.id
        WHERE m.dueno_id = :dueno_id
        ORDER BY m.nombre ASC
    ");
    $stmt->execute([':dueno_id' => $dueno_id]);
    $mascotas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'mascotas' => $mascotas]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
