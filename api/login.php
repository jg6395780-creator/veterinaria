<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

require_once __DIR__ . '/../config/db_credenciales.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email    = trim($input['email']    ?? '');
$password = trim($input['password'] ?? '');

if (!$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Completa todos los campos']);
    exit;
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $stmt = $pdo->prepare("SELECT * FROM duenos WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $dueno = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dueno && password_verify($password, $dueno['password'])) {
        echo json_encode([
            'success'  => true,
            'dueno_id' => $dueno['id'],
            'nombre'   => $dueno['nombre'],
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Email o contraseña incorrectos']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
