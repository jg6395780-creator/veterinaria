<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/documentos_clinicos.php';

$token = strtolower(trim((string)($_GET['token'] ?? '')));
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(404);
    exit('Documento no encontrado.');
}
try {
    $pdo = getDB();
    asegurarTablaDocumentosClinicos($pdo);
    $stmt = $pdo->prepare("SELECT nombre_original, nombre_guardado, mime_type, tamano
        FROM documentos_clinicos WHERE token_descarga = :token LIMIT 1");
    $stmt->execute([':token' => $token]);
    $documento = $stmt->fetch();

    if (!$documento) {
        http_response_code(404);
        exit('Documento no encontrado.');
    }

    $ruta = directorioDocumentosClinicos() . DIRECTORY_SEPARATOR . basename($documento['nombre_guardado']);
    if (!is_file($ruta)) {
        http_response_code(404);
        exit('Archivo no disponible.');
    }

    $nombre = preg_replace('/[^A-Za-z0-9._ -]/u', '_', (string)$documento['nombre_original']);
    header('Content-Type: ' . $documento['mime_type']);
    header('Content-Length: ' . filesize($ruta));
    header('Content-Disposition: inline; filename="' . addslashes($nombre) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=300');
    readfile($ruta);
} catch (Throwable $e) {
    error_log('Ver documento clínico: ' . $e->getMessage());
    http_response_code(500);
    exit('No fue posible abrir el documento.');
}
