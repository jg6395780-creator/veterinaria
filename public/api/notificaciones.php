<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/rut.php';
require_once __DIR__ . '/../includes/recordatorios.php';

try {
    $pdo = getDB();
    generarRecordatorios($pdo);
    $input = $_SERVER['REQUEST_METHOD'] === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?: []) : $_GET;
    $rut = normalizarRut(trim((string)($input['rut'] ?? '')));
    $q = $pdo->prepare('SELECT id FROM duenos WHERE rut=:rut');
    $q->execute([':rut'=>$rut]);
    $duenoId = (int)$q->fetchColumn();
    if (!$duenoId) {
        http_response_code(404);
        echo json_encode(['success'=>false,'message'=>'Dueño no encontrado']);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($input['id'] ?? 0);
        if ($id) $pdo->prepare('UPDATE notificaciones SET leida=1 WHERE id=:id AND dueno_id=:d')->execute([':id'=>$id,':d'=>$duenoId]);
        else $pdo->prepare('UPDATE notificaciones SET leida=1 WHERE dueno_id=:d')->execute([':d'=>$duenoId]);
    }
    $q = $pdo->prepare('SELECT id,titulo,mensaje,tipo,enlace,leida,fecha_creacion FROM notificaciones WHERE dueno_id=:d ORDER BY fecha_creacion DESC LIMIT 100');
    $q->execute([':d'=>$duenoId]);
    $avisos = $q->fetchAll();
    echo json_encode(['success'=>true,'no_leidas'=>count(array_filter($avisos,fn($a)=>!(bool)$a['leida'])),'notificaciones'=>$avisos]);
} catch (Throwable $e) {
    error_log('API notificaciones: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error del servidor']);
}
