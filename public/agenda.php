<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/modulos_clinica.php';

$pdo = getDB();
asegurarModulosClinica($pdo);
$rol = $_SESSION['user_rol'] ?? '';
exigirRoles(['admin', 'recepcion', 'veterinario']);
$mensaje = '';
$tipoMensaje = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfModulo()) {
        $mensaje = 'La sesión del formulario venció. Recarga la página.';
        $tipoMensaje = 'danger';
    } else {
        $accion = (string)($_POST['accion'] ?? '');
        try {
            if ($accion === 'crear') {
                $mascotaId = (int)($_POST['mascota_id'] ?? 0);
                $veterinarioId = (int)($_POST['veterinario_id'] ?? 0) ?: null;
                $fecha = str_replace('T', ' ', trim((string)($_POST['fecha_hora'] ?? '')));
                $motivo = trim((string)($_POST['motivo'] ?? ''));
                $stmtDueno = $pdo->prepare('SELECT dueno_id, nombre FROM mascotas WHERE id = :id');
                $stmtDueno->execute([':id' => $mascotaId]);
                $mascota = $stmtDueno->fetch();
                if (!$mascota || !$fecha || !$motivo || strtotime($fecha) === false) {
                    throw new RuntimeException('Completa los campos requeridos.');
                }
                $stmt = $pdo->prepare("INSERT INTO citas (dueno_id, mascota_id, veterinario_id, fecha_hora, motivo, estado, observacion, creada_por) VALUES (:dueno, :mascota, :vet, :fecha, :motivo, 'confirmada', :observacion, 'personal')");
                $stmt->execute([
                    ':dueno' => (int)$mascota['dueno_id'], ':mascota' => $mascotaId, ':vet' => $veterinarioId,
                    ':fecha' => date('Y-m-d H:i:s', strtotime($fecha)), ':motivo' => $motivo,
                    ':observacion' => trim((string)($_POST['observacion'] ?? '')) ?: null,
                ]);
                $id = (int)$pdo->lastInsertId();
                crearNotificacion($pdo, (int)$mascota['dueno_id'], null, 'Cita confirmada', 'La cita de ' . $mascota['nombre'] . ' quedó agendada para el ' . date('d/m/Y H:i', strtotime($fecha)) . '.', 'cita');
                registrarAuditoria($pdo, 'agenda', 'crear', $id, $motivo);
                $mensaje = 'Cita creada y dueño notificado.';
            } elseif ($accion === 'estado') {
                $id = (int)($_POST['id'] ?? 0);
                $estado = (string)($_POST['estado'] ?? '');
                $veterinarioId = (int)($_POST['veterinario_id'] ?? 0) ?: null;
                $permitidos = ['solicitada', 'confirmada', 'en_espera', 'atendida', 'cancelada', 'no_asistio'];
                if (!$id || !in_array($estado, $permitidos, true)) throw new RuntimeException('Estado no válido.');
                $stmt = $pdo->prepare('SELECT c.dueno_id, c.fecha_hora, c.veterinario_id, m.nombre FROM citas c JOIN mascotas m ON m.id=c.mascota_id WHERE c.id=:id');
                $stmt->execute([':id' => $id]);
                $cita = $stmt->fetch();
                if (!$cita) throw new RuntimeException('Cita no encontrada.');
                if ($rol === 'veterinario') $veterinarioId = $cita['veterinario_id'] ? (int)$cita['veterinario_id'] : (int)$_SESSION['user_id'];
                $nombreVeterinario = '';
                if ($veterinarioId) {
                    $stmtVet = $pdo->prepare("SELECT nombre_completo FROM usuarios WHERE id=:id AND rol='veterinario' AND activo=1");
                    $stmtVet->execute([':id'=>$veterinarioId]);
                    $nombreVeterinario = (string)$stmtVet->fetchColumn();
                    if ($nombreVeterinario === '') throw new RuntimeException('El veterinario seleccionado no está disponible.');
                }
                $pdo->prepare('UPDATE citas SET estado=:estado, veterinario_id=:veterinario WHERE id=:id')->execute([':estado' => $estado, ':veterinario'=>$veterinarioId, ':id' => $id]);
                $detalleDueno = 'La cita de ' . $cita['nombre'] . ' ahora está ' . str_replace('_', ' ', $estado) . ($nombreVeterinario ? ' con ' . $nombreVeterinario : '') . '.';
                crearNotificacion($pdo, (int)$cita['dueno_id'], null, 'Cita actualizada', $detalleDueno, 'cita');
                if ($veterinarioId && (int)$cita['veterinario_id'] !== $veterinarioId) {
                    crearNotificacion($pdo, null, $veterinarioId, 'Nueva cita asignada', $cita['nombre'] . ' · ' . date('d/m/Y H:i', strtotime($cita['fecha_hora'])) . '.', 'cita', 'agenda.php?fecha='.date('Y-m-d', strtotime($cita['fecha_hora'])));
                }
                registrarAuditoria($pdo, 'agenda', 'actualizar_cita', $id, $estado . ($nombreVeterinario ? ' · '.$nombreVeterinario : ' · sin asignar'));
                $mensaje = 'Cita y veterinario actualizados.';
            }
        } catch (Throwable $e) {
            $mensaje = $e instanceof RuntimeException ? $e->getMessage() : 'No fue posible guardar la cita.';
            $tipoMensaje = 'danger';
            error_log('Agenda: ' . $e->getMessage());
        }
    }
}

$fechaFiltro = $_GET['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFiltro)) $fechaFiltro = date('Y-m-d');
$vista = $_GET['vista'] ?? 'dia';
if (!in_array($vista, ['dia','semana','mes'], true)) $vista = 'dia';
$base = new DateTimeImmutable($fechaFiltro);
if ($vista === 'semana') { $inicioPeriodo = $base->modify('monday this week'); $finPeriodo = $inicioPeriodo->modify('+6 days'); }
elseif ($vista === 'mes') { $inicioPeriodo = $base->modify('first day of this month'); $finPeriodo = $base->modify('last day of this month'); }
else { $inicioPeriodo = $base; $finPeriodo = $base; }
$sql = "SELECT c.*, m.nombre mascota, m.identificador, d.nombre dueno, d.telefono, u.nombre_completo veterinario
        FROM citas c JOIN mascotas m ON m.id=c.mascota_id JOIN duenos d ON d.id=c.dueno_id
        LEFT JOIN usuarios u ON u.id=c.veterinario_id WHERE DATE(c.fecha_hora) BETWEEN :inicio AND :fin";
$params = [':inicio' => $inicioPeriodo->format('Y-m-d'), ':fin' => $finPeriodo->format('Y-m-d')];
if ($rol === 'veterinario') {
    $sql .= ' AND (c.veterinario_id=:usuario OR c.veterinario_id IS NULL)';
    $params[':usuario'] = (int)$_SESSION['user_id'];
}
$sql .= ' ORDER BY c.fecha_hora';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $citas = $stmt->fetchAll();
$mascotas = $pdo->query('SELECT m.id, m.nombre, m.identificador, m.dueno_id, d.nombre dueno FROM mascotas m JOIN duenos d ON d.id=m.dueno_id ORDER BY m.nombre')->fetchAll();
$veterinarios = $pdo->query("SELECT id, nombre_completo FROM usuarios WHERE rol='veterinario' AND activo=1 ORDER BY nombre_completo")->fetchAll();
$estados = ['solicitada'=>'Solicitada','confirmada'=>'Confirmada','en_espera'=>'En espera','atendida'=>'Atendida','cancelada'=>'Cancelada','no_asistio'=>'No asistió'];

$pageTitle = 'Agenda - VetClinic Pro';
require_once __DIR__ . '/includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-3">
 <div><h3><i class="bi bi-calendar2-week text-primary me-2"></i>Agenda</h3><p>Citas, llegadas y atención del equipo clínico.</p></div>
 <?php if ($rol !== 'veterinario'): ?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCita"><i class="bi bi-plus-circle me-2"></i>Nueva cita</button><?php endif; ?>
</div>
<?php if ($mensaje): ?><div class="alert alert-<?= e($tipoMensaje) ?> alert-dismissible fade show"><?= e($mensaje) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="card card-stat mb-4"><div class="card-body py-3"><form class="module-toolbar"><label class="form-label mb-0">Fecha</label><input type="date" name="fecha" value="<?= e($fechaFiltro) ?>" class="form-control w-auto"><select name="vista" class="form-select w-auto"><option value="dia" <?= $vista==='dia'?'selected':'' ?>>Día</option><option value="semana" <?= $vista==='semana'?'selected':'' ?>>Semana</option><option value="mes" <?= $vista==='mes'?'selected':'' ?>>Mes</option></select><button class="btn btn-outline-primary">Ver agenda</button><a href="agenda.php" class="btn btn-light">Hoy</a><span class="ms-auto text-muted small"><?= e($inicioPeriodo->format('d/m/Y')) ?> – <?= e($finPeriodo->format('d/m/Y')) ?></span></form></div></div>
<div class="row g-3">
<?php if (!$citas): ?><div class="col-12"><div class="card card-stat"><div class="empty-state py-5"><i class="bi bi-calendar-check"></i><h5 class="text-dark">Día disponible</h5><p>No hay citas registradas para esta fecha.</p></div></div></div><?php endif; ?>
<?php foreach ($citas as $cita): ?>
<div class="col-12 col-xl-6"><div class="card card-stat appointment-card h-100"><div class="card-body p-4">
 <div class="d-flex justify-content-between"><div><span class="appointment-time"><?= e(date($vista==='dia'?'H:i':'d/m · H:i', strtotime($cita['fecha_hora']))) ?></span><h5 class="mt-2 mb-1"><?= e($cita['mascota']) ?> <small class="text-muted fw-normal">· <?= e($cita['identificador']) ?></small></h5><div class="text-muted small"><?= e($cita['dueno']) ?> · <a href="tel:<?= e($cita['telefono']) ?>"><?= e($cita['telefono']) ?></a> · <a target="_blank" rel="noopener" href="https://wa.me/<?= e(preg_replace('/\D+/', '', $cita['telefono'])) ?>">WhatsApp</a></div></div><span class="badge text-bg-primary align-self-start"><?= e($estados[$cita['estado']] ?? $cita['estado']) ?></span></div>
 <p class="mt-3 mb-2"><strong>Motivo:</strong> <?= e($cita['motivo']) ?></p><div class="small text-muted mb-3"><i class="bi bi-person-badge me-1"></i><?= e($cita['veterinario'] ?: 'Sin asignar') ?></div>
 <form method="post" class="d-flex gap-2 flex-wrap"><input type="hidden" name="csrf_token" value="<?= e(csrfModulo()) ?>"><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int)$cita['id'] ?>"><select name="estado" class="form-select form-select-sm flex-grow-1" style="min-width:150px"><?php foreach ($estados as $valor=>$etiqueta): ?><option value="<?= e($valor) ?>" <?= $valor===$cita['estado']?'selected':'' ?>><?= e($etiqueta) ?></option><?php endforeach; ?></select><?php if ($rol !== 'veterinario'): ?><select name="veterinario_id" class="form-select form-select-sm flex-grow-1" style="min-width:190px"><option value="">Sin asignar</option><?php foreach ($veterinarios as $v): ?><option value="<?= (int)$v['id'] ?>" <?= (int)$v['id']===(int)$cita['veterinario_id']?'selected':'' ?>><?= e($v['nombre_completo']) ?></option><?php endforeach; ?></select><?php else: ?><input type="hidden" name="veterinario_id" value="<?= (int)($cita['veterinario_id'] ?: $_SESSION['user_id']) ?>"><?php endif; ?><button class="btn btn-sm btn-outline-primary">Actualizar</button></form>
</div></div></div>
<?php endforeach; ?>
</div>
<div class="modal fade" id="modalCita" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Agendar cita</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><form method="post" novalidate><input type="hidden" name="csrf_token" value="<?= e(csrfModulo()) ?>"><input type="hidden" name="accion" value="crear"><div class="modal-body">
 <div class="mb-3"><label class="form-label">Paciente</label><select name="mascota_id" class="form-select" required><option value="">Seleccionar...</option><?php foreach ($mascotas as $m): ?><option value="<?= (int)$m['id'] ?>"><?= e($m['nombre'].' · '.$m['dueno'].' · '.$m['identificador']) ?></option><?php endforeach; ?></select></div>
 <div class="row g-3"><div class="col-6"><label class="form-label">Fecha y hora</label><input type="datetime-local" name="fecha_hora" class="form-control" min="<?= date('Y-m-d\TH:i') ?>" required></div><div class="col-6"><label class="form-label">Veterinario</label><select name="veterinario_id" class="form-select"><option value="">Por asignar</option><?php foreach ($veterinarios as $v): ?><option value="<?= (int)$v['id'] ?>"><?= e($v['nombre_completo']) ?></option><?php endforeach; ?></select></div></div>
 <div class="mt-3"><label class="form-label">Motivo</label><textarea name="motivo" class="form-control" rows="3" maxlength="500" required></textarea></div><div class="mt-3"><label class="form-label">Observación interna</label><textarea name="observacion" class="form-control" rows="2" maxlength="500"></textarea></div>
 </div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar cita</button></div></form></div></div></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
