<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

require_once __DIR__ . '/../../config/db_credenciales.php';
require_once __DIR__ . '/../includes/rut.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$rut      = normalizarRut(trim($input['rut'] ?? ''));
$password = trim($input['password'] ?? '');

if (!$rut || !$password) {
    echo json_encode(['success' => false, 'message' => 'Completa todos los campos']);
    exit;
}

if (!validarRut($rut)) {
    echo json_encode(['success' => false, 'message' => 'El RUT ingresado no es válido']);
    exit;
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $stmt = $pdo->prepare("SELECT * FROM duenos WHERE rut = :rut LIMIT 1");
    $stmt->execute([':rut' => $rut]);
    $dueno = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dueno && password_verify($password, $dueno['password'])) {
        echo json_encode([
            'success'  => true,
            'dueno_id' => $dueno['id'],
            'nombre'   => $dueno['nombre'],
            'rut'      => $dueno['rut'],
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'RUT o contraseña incorrectos']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
