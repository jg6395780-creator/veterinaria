<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/rut.php';
require_once __DIR__ . '/../includes/modulos_clinica.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('Método no permitido');
    }
    $pdo = getDB();
    asegurarModulosClinica($pdo);
    $rut = normalizarRut(trim((string)($_POST['rut'] ?? '')));
    $mascotaId = (int)($_POST['mascota_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT m.id FROM mascotas m JOIN duenos d ON d.id = m.dueno_id WHERE m.id = :id AND d.rut = :rut');
    $stmt->execute([':id' => $mascotaId, ':rut' => $rut]);
    if (!$stmt->fetchColumn()) {
        http_response_code(404);
        throw new RuntimeException('Mascota no encontrada');
    }
    $foto = $_FILES['foto'] ?? null;
    if (!$foto || ($foto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int)$foto['size'] > 5 * 1024 * 1024) {
        http_response_code(422);
        throw new RuntimeException('Selecciona una imagen de hasta 5 MB');
    }
    $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($foto['tmp_name']) ?: '';
    if (!isset($permitidos[$mime])) {
        http_response_code(422);
        throw new RuntimeException('Usa una imagen JPG, PNG o WEBP');
    }
    $directorio = __DIR__ . '/../uploads/mascotas';
    if (!is_dir($directorio) && !mkdir($directorio, 0750, true) && !is_dir($directorio)) {
        throw new RuntimeException('No se pudo preparar el almacenamiento');
    }
    $nombre = 'mascota_' . $mascotaId . '_' . bin2hex(random_bytes(10)) . '.' . $permitidos[$mime];
    if (!move_uploaded_file($foto['tmp_name'], $directorio . '/' . $nombre)) {
        throw new RuntimeException('No se pudo guardar la fotografía');
    }
    $ruta = 'uploads/mascotas/' . $nombre;
    $pdo->prepare('UPDATE mascotas SET foto_url = :foto WHERE id = :id')->execute([':foto' => $ruta, ':id' => $mascotaId]);
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
    echo json_encode(['success' => true, 'message' => 'Foto actualizada', 'foto_url' => $scheme . '://' . $_SERVER['HTTP_HOST'] . $base . '/' . $ruta]);
} catch (Throwable $e) {
    error_log('Foto mascota: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Error del servidor']);
}
