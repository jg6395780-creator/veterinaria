<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

require_once __DIR__ . '/../../config/db_credenciales.php';
require_once __DIR__ . '/../includes/rut.php';

function responderRegistro(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderRegistro(false, 'Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$nombre = trim($input['nombre'] ?? '');
$rut = normalizarRut(trim($input['rut'] ?? ''));
$telefono = preg_replace('/\D/', '', trim($input['telefono'] ?? ''));
$email = strtolower(trim($input['email'] ?? ''));
$password = $input['password'] ?? '';

if (!$nombre || !$rut || !$telefono || !$email || !$password) {
    responderRegistro(false, 'Completa todos los campos');
}
if (!validarRut($rut)) {
    responderRegistro(false, 'El RUT ingresado no es válido');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responderRegistro(false, 'El correo electrónico no es válido');
}
if (strlen($password) < 6) {
    responderRegistro(false, 'La contraseña debe tener al menos 6 caracteres');
}

if (strlen($telefono) === 11 && str_starts_with($telefono, '56')) {
    $telefono = substr($telefono, 2);
}
if (strlen($telefono) === 8) {
    $telefono = '9' . $telefono;
}
if (!preg_match('/^9[0-9]{8}$/', $telefono)) {
    responderRegistro(false, 'Ingresa un teléfono móvil chileno válido');
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, nombre, email FROM duenos WHERE rut = :rut LIMIT 1 FOR UPDATE");
    $stmt->execute([':rut' => $rut]);
    $dueno = $stmt->fetch();

    if ($dueno) {
        $emailRegistrado = strtolower(trim($dueno['email'] ?? ''));
        if ($emailRegistrado !== '' && $emailRegistrado !== $email) {
            $pdo->rollBack();
            responderRegistro(false, 'El correo no coincide con el registrado en la clínica');
        }

        $pdo->prepare("
            UPDATE duenos
            SET nombre = :nombre, telefono = :telefono, email = :email, password = :password
            WHERE id = :id
        ")->execute([
            ':nombre' => $nombre,
            ':telefono' => $telefono,
            ':email' => $email,
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':id' => $dueno['id'],
        ]);
        $duenoId = (int)$dueno['id'];
        $mensaje = 'Cuenta activada y mascotas vinculadas correctamente';
    } else {
        $emailExiste = $pdo->prepare("SELECT id FROM duenos WHERE email = :email LIMIT 1");
        $emailExiste->execute([':email' => $email]);
        if ($emailExiste->fetchColumn()) {
            $pdo->rollBack();
            responderRegistro(false, 'El correo ya está asociado a otra cuenta');
        }

        $pdo->prepare("
            INSERT INTO duenos (rut, nombre, telefono, email, password)
            VALUES (:rut, :nombre, :telefono, :email, :password)
        ")->execute([
            ':rut' => $rut,
            ':nombre' => $nombre,
            ':telefono' => $telefono,
            ':email' => $email,
            ':password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $duenoId = (int)$pdo->lastInsertId();
        $mensaje = 'Cuenta creada correctamente';
    }

    $pdo->commit();
    responderRegistro(true, $mensaje, [
        'dueno_id' => $duenoId,
        'nombre' => $nombre,
        'rut' => $rut,
    ]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    responderRegistro(false, 'No fue posible crear la cuenta');
}
