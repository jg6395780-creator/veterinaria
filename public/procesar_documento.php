<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/documentos_clinicos.php';
require_once __DIR__ . '/includes/modulos_clinica.php';

if (!in_array($_SESSION['user_rol'] ?? '', ['admin', 'veterinario'], true)) {
    header('Location: index.php');
    exit;
}

$mascotaId = filter_var($_POST['mascota_id'] ?? null, FILTER_VALIDATE_INT);
$redirect = $mascotaId ? "historial.php?id=$mascotaId" : 'historial.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $redirect");
    exit;
}

$csrf = (string)($_POST['csrf_documento'] ?? '');
if (empty($_SESSION['csrf_documento']) || !hash_equals($_SESSION['csrf_documento'], $csrf)) {
    $_SESSION['mensaje_error'] = 'La sesión del formulario venció. Inténtalo nuevamente.';
    header("Location: $redirect");
    exit;
}

$tipo = trim((string)($_POST['tipo'] ?? ''));
$titulo = trim((string)($_POST['titulo'] ?? ''));
$descripcion = trim((string)($_POST['descripcion'] ?? ''));
$tipos = tiposDocumentoClinico();
$archivo = $_FILES['archivo'] ?? null;

if (!$mascotaId || !array_key_exists($tipo, $tipos) || $titulo === '' || mb_strlen($titulo) > 180) {
    $_SESSION['mensaje_error'] = 'Completa correctamente el tipo y título del documento.';
    header("Location: $redirect");
    exit;
}

if (mb_strlen($descripcion) > 500) {
    $_SESSION['mensaje_error'] = 'La descripción no puede superar los 500 caracteres.';
    header("Location: $redirect");
    exit;
}

if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $_SESSION['mensaje_error'] = 'Selecciona un archivo válido para subir.';
    header("Location: $redirect");
    exit;
}

$tamano = (int)($archivo['size'] ?? 0);
if ($tamano < 1 || $tamano > 15 * 1024 * 1024) {
    $_SESSION['mensaje_error'] = 'El documento debe pesar como máximo 15 MB.';
    header("Location: $redirect");
    exit;
}

$mimePermitidos = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($archivo['tmp_name']) ?: '';
if (!isset($mimePermitidos[$mime])) {
    $_SESSION['mensaje_error'] = 'Formato no permitido. Usa PDF, JPG, PNG o WEBP.';
    header("Location: $redirect");
    exit;
}

$pdo = getDB();
asegurarTablaDocumentosClinicos($pdo);
asegurarModulosClinica($pdo);
$stmtMascota = $pdo->prepare('SELECT id FROM mascotas WHERE id = :id LIMIT 1');
$stmtMascota->execute([':id' => $mascotaId]);
if (!$stmtMascota->fetchColumn()) {
    $_SESSION['mensaje_error'] = 'No se encontró la mascota seleccionada.';
    header('Location: historial.php');
    exit;
}

$directorio = directorioDocumentosClinicos();
if (!is_dir($directorio) && !mkdir($directorio, 0750, true) && !is_dir($directorio)) {
    $_SESSION['mensaje_error'] = 'No fue posible preparar el almacenamiento de documentos.';
    header("Location: $redirect");
    exit;
}

$nombreGuardado = bin2hex(random_bytes(20)) . '.' . $mimePermitidos[$mime];
$rutaDestino = $directorio . DIRECTORY_SEPARATOR . $nombreGuardado;
$nombreOriginal = basename((string)$archivo['name']);
$tokenDescarga = bin2hex(random_bytes(32));

if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
    $_SESSION['mensaje_error'] = 'No fue posible guardar el archivo.';
    header("Location: $redirect");
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO documentos_clinicos
        (mascota_id, tipo, titulo, descripcion, nombre_original, nombre_guardado,
         mime_type, tamano, token_descarga, subido_por)
        VALUES (:mascota_id, :tipo, :titulo, :descripcion, :nombre_original,
                :nombre_guardado, :mime_type, :tamano, :token_descarga, :subido_por)");
    $stmt->execute([
        ':mascota_id' => $mascotaId,
        ':tipo' => $tipo,
        ':titulo' => $titulo,
        ':descripcion' => $descripcion !== '' ? $descripcion : null,
        ':nombre_original' => $nombreOriginal,
        ':nombre_guardado' => $nombreGuardado,
        ':mime_type' => $mime,
        ':tamano' => $tamano,
        ':token_descarga' => $tokenDescarga,
        ':subido_por' => (int)$_SESSION['user_id'],
    ]);
    notificarDuenoMascota($pdo, (int)$mascotaId, 'Nuevo documento clínico', $titulo . ' ya está disponible en la aplicación.', 'documento');
    $_SESSION['mensaje'] = 'Documento clínico agregado correctamente.';
} catch (Throwable $e) {
    if (is_file($rutaDestino)) {
        unlink($rutaDestino);
    }
    error_log('Guardar documento clínico: ' . $e->getMessage());
    $_SESSION['mensaje_error'] = 'No fue posible registrar el documento.';
}

header("Location: $redirect");
exit;
