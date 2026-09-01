<?php
require_once __DIR__ . '/modulos_clinica.php';

function generarRecordatorios(PDO $pdo): void
{
    asegurarModulosClinica($pdo);
    $recordatorios = $pdo->query("SELECT m.dueno_id, m.nombre mascota, v.nombre_vacuna detalle,
            v.fecha_proxima_dosis fecha, 'vacuna' tipo
        FROM vacunas v JOIN mascotas m ON m.id=v.mascota_id
        WHERE v.fecha_proxima_dosis BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        UNION ALL
        SELECT c.dueno_id, m.nombre mascota, c.motivo detalle,
            DATE(c.fecha_hora) fecha, 'cita' tipo
        FROM citas c JOIN mascotas m ON m.id=c.mascota_id
        WHERE DATE(c.fecha_hora) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)
          AND c.estado IN ('solicitada','confirmada')")->fetchAll();
    foreach ($recordatorios as $r) {
        $titulo = $r['tipo'] === 'vacuna' ? 'Próxima vacuna de ' . $r['mascota'] : 'Próxima cita de ' . $r['mascota'];
        $mensaje = $r['detalle'] . ' · ' . date('d/m/Y', strtotime($r['fecha'])) . '.';
        $q = $pdo->prepare('SELECT id FROM notificaciones WHERE dueno_id=:d AND titulo=:t AND mensaje=:m AND fecha_creacion>=DATE_SUB(NOW(), INTERVAL 7 DAY) LIMIT 1');
        $q->execute([':d'=>(int)$r['dueno_id'], ':t'=>$titulo, ':m'=>$mensaje]);
        if (!$q->fetchColumn()) crearNotificacion($pdo, (int)$r['dueno_id'], null, $titulo, $mensaje, 'recordatorio');
    }
}
