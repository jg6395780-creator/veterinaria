<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/db_credenciales.php';
require_once __DIR__ . '/../includes/rut.php';
require_once __DIR__ . '/../includes/mailer.php';

function responderRecuperacion(bool $success, string $message): void
{
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    responderRecuperacion(false, 'Método no permitido');
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$identificador = trim($input['identificador'] ?? $input['email'] ?? $input['rut'] ?? '');
$mensajeGenerico = 'Si los datos están registrados, enviaremos un enlace al correo asociado. Revisa también la carpeta de spam.';

if ($identificador === '') {
    responderRecuperacion(true, $mensajeGenerico);
}

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec("CREATE TABLE IF NOT EXISTS restablecimientos_duenos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dueno_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expira_en DATETIME NOT NULL,
        usado_en DATETIME NULL,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_restablecimiento_dueno (dueno_id),
        FOREIGN KEY (dueno_id) REFERENCES duenos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (filter_var($identificador, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare('SELECT id, email FROM duenos WHERE LOWER(email) = LOWER(:email) AND password IS NOT NULL LIMIT 1');
        $stmt->execute([':email' => $identificador]);
    } else {
        $rut = normalizarRut($identificador);
        if (!validarRut($rut)) {
            responderRecuperacion(true, $mensajeGenerico);
        }
        $stmt = $pdo->prepare('SELECT id, email FROM duenos WHERE rut = :rut AND password IS NOT NULL LIMIT 1');
        $stmt->execute([':rut' => $rut]);
    }

    $dueno = $stmt->fetch();
    if ($dueno && filter_var($dueno['email'], FILTER_VALIDATE_EMAIL)) {
        $limite = $pdo->prepare('SELECT creado_en FROM restablecimientos_duenos WHERE dueno_id = :dueno_id ORDER BY id DESC LIMIT 1');
        $limite->execute([':dueno_id' => $dueno['id']]);
        $ultimoEnvio = $limite->fetchColumn();

        if (!$ultimoEnvio || strtotime($ultimoEnvio) <= time() - 60) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $pdo->prepare('DELETE FROM restablecimientos_duenos WHERE dueno_id = :dueno_id OR expira_en < NOW()')
                ->execute([':dueno_id' => $dueno['id']]);
            $pdo->prepare('INSERT INTO restablecimientos_duenos (dueno_id, token_hash, expira_en) VALUES (:dueno_id, :token_hash, DATE_ADD(NOW(), INTERVAL 30 MINUTE))')
                ->execute([':dueno_id' => $dueno['id'], ':token_hash' => $tokenHash]);

            $hostActual = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
            if (in_array($hostActual, ['localhost', '127.0.0.1'], true)) {
                $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $rutaPublica = rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/\\');
                $baseUrl = $protocolo . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $rutaPublica;
            } else {
                $baseUrl = 'https://vetclinicapp.online/veterinaria/public';
            }

            $enlace = $baseUrl . '/restablecer_contrasena_dueno.php?token=' . rawurlencode($token);
            enviarCorreoRestablecimiento($dueno['email'], $enlace);
        }
    }

    responderRecuperacion(true, $mensajeGenerico);
} catch (Throwable $e) {
    error_log('Recuperación de dueño: ' . $e->getMessage());
    http_response_code(500);
    responderRecuperacion(false, 'No fue posible procesar la solicitud. Intenta nuevamente.');
}
