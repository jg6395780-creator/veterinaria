<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/urgencias.php';

if (!in_array($_SESSION['user_rol'] ?? '', ['admin', 'recepcion', 'veterinario'], true)) {
    header('Location: index.php');
    exit;
}

$pdo = getDB();
asegurarTablaUrgencias($pdo);
$esVeterinario = ($_SESSION['user_rol'] ?? '') === 'veterinario';

if (empty($_SESSION['csrf_urgencias'])) {
    $_SESSION['csrf_urgencias'] = bin2hex(random_bytes(32));
}

$flash = $_SESSION['urgencia_flash'] ?? null;
unset($_SESSION['urgencia_flash']);
$mensaje = is_array($flash) ? (string)($flash['mensaje'] ?? '') : '';
$tipoMensaje = is_array($flash) ? (string)($flash['tipo'] ?? 'success') : 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    $urgenciaId = filter_var($_POST['urgencia_id'] ?? null, FILTER_VALIDATE_INT);
    $estado = trim((string)($_POST['estado'] ?? ''));
    $observacion = trim((string)($_POST['observacion'] ?? ''));
    $estadosPermitidos = $esVeterinario ? ['en_atencion', 'finalizada'] : ['confirmada', 'rechazada'];
    $transiciones = [
        'confirmada' => ['pendiente', 'recibida'],
        'rechazada' => ['pendiente', 'recibida'],
        'en_atencion' => ['confirmada'],
        'finalizada' => ['en_atencion'],
    ];

    if (!hash_equals($_SESSION['csrf_urgencias'], $token)) {
        $mensaje = 'La sesión del formulario venció. Recarga la página.';
        $tipoMensaje = 'danger';
    } elseif (!$urgenciaId || !in_array($estado, $estadosPermitidos, true)) {
        $mensaje = 'La acción seleccionada no es válida.';
        $tipoMensaje = 'danger';
    } elseif (mb_strlen($observacion) > 500) {
        $mensaje = 'La observación no puede superar los 500 caracteres.';
        $tipoMensaje = 'danger';
    } else {
        $origenes = $transiciones[$estado] ?? [];
        $nombresOrigen = array_map(fn($indice) => ':origen' . $indice, array_keys($origenes));
        $marcas = implode(',', $nombresOrigen);
        $stmt = $pdo->prepare("UPDATE urgencias
            SET estado = :estado, observacion_recepcion = :observacion, atendida_por = :usuario
            WHERE id = :id AND estado IN ($marcas)");
        $stmt->bindValue(':estado', $estado);
        $stmt->bindValue(':observacion', $observacion !== '' ? $observacion : null);
        $stmt->bindValue(':usuario', (int)$_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':id', $urgenciaId, PDO::PARAM_INT);
        foreach ($origenes as $indice => $origen) $stmt->bindValue(':origen' . $indice, $origen);
        $stmt->execute();
        $mensaje = $stmt->rowCount() ? 'La urgencia fue actualizada correctamente.' : 'La urgencia ya había sido actualizada.';
    }

    $_SESSION['urgencia_flash'] = ['mensaje' => $mensaje, 'tipo' => $tipoMensaje];
    header('Location: urgencias.php');
    exit;
}

$sqlUrgencias = "SELECT u.*, m.nombre AS mascota, m.especie, m.raza,
        d.nombre AS dueno, d.rut AS dueno_rut,
        responsable.nombre_completo AS responsable
    FROM urgencias u
    INNER JOIN mascotas m ON m.id = u.mascota_id
    INNER JOIN duenos d ON d.id = u.dueno_id
    LEFT JOIN usuarios responsable ON responsable.id = u.atendida_por";
$paramsUrgencias = [];
if ($esVeterinario) {
    $sqlUrgencias .= " WHERE u.estado IN ('confirmada','en_atencion') OR u.atendida_por = :usuario";
    $paramsUrgencias[':usuario'] = (int)$_SESSION['user_id'];
}
$sqlUrgencias .= "
    ORDER BY FIELD(u.estado, 'pendiente', 'recibida', 'confirmada', 'en_atencion', 'finalizada', 'atendida', 'rechazada', 'cancelada'),
             u.fecha_solicitud DESC
    LIMIT 100";
$stmt = $pdo->prepare($sqlUrgencias);
$stmt->execute($paramsUrgencias);
$urgencias = $stmt->fetchAll();
$activas = array_values(array_filter($urgencias, fn($u) => in_array($u['estado'], ['pendiente', 'recibida', 'confirmada', 'en_atencion'], true)));
$historial = array_values(array_filter($urgencias, fn($u) => !in_array($u['estado'], ['pendiente', 'recibida', 'confirmada', 'en_atencion'], true)));
$formasPago = formasPagoUrgencia();
$estados = estadosUrgencia();

$pageTitle = 'Urgencias - VetClinic Pro';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h3><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Urgencias</h3>
        <p>Solicitudes enviadas desde la aplicación de los dueños.</p>
    </div>
    <span class="badge rounded-pill text-bg-danger fs-6 px-3 py-2"><?= count($activas) ?> activas</span>
</div>

<?php if ($mensaje): ?>
<div class="alert alert-<?= e($tipoMensaje) ?> alert-dismissible fade show" role="alert">
    <?= e($mensaje) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!$activas): ?>
<div class="card card-stat mb-4">
    <div class="empty-state py-5">
        <i class="bi bi-shield-check text-success"></i>
        <h5 class="text-dark">No hay urgencias activas</h5>
        <p>Esta página se actualiza automáticamente cuando llega una solicitud.</p>
    </div>
</div>
<?php else: ?>
<div class="row g-4 mb-4">
    <?php foreach ($activas as $urgencia):
        $esPendiente = in_array($urgencia['estado'], ['pendiente', 'recibida'], true);
    ?>
    <div class="col-12 col-xl-6">
        <div class="card card-stat h-100 urgencia-card <?= $esPendiente ? 'urgencia-pendiente' : 'urgencia-confirmada' ?>">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <span class="badge <?= $esPendiente ? 'text-bg-danger' : 'text-bg-primary' ?> mb-2">
                            <?= e($estados[$urgencia['estado']] ?? $urgencia['estado']) ?>
                        </span>
                        <h4 class="mb-1"><?= e($urgencia['mascota']) ?></h4>
                        <div class="text-muted"><?= e($urgencia['especie']) ?> · <?= e($urgencia['raza'] ?: 'Sin raza') ?></div>
                    </div>
                    <div class="text-end small text-muted">
                        <i class="bi bi-clock me-1"></i><?= e(date('d/m/Y H:i', strtotime($urgencia['fecha_solicitud']))) ?>
                    </div>
                </div>

                <div class="urgencia-motivo mb-3"><?= nl2br(e($urgencia['motivo'])) ?></div>

                <div class="row g-3 small mb-3">
                    <div class="col-sm-6"><strong>Dueño:</strong><br><?= e($urgencia['dueno']) ?> · <?= e($urgencia['dueno_rut']) ?></div>
                    <div class="col-sm-6"><strong>Teléfono:</strong><br><a href="tel:<?= e($urgencia['telefono']) ?>"><?= e($urgencia['telefono']) ?></a></div>
                    <div class="col-sm-6"><strong>Llegada estimada:</strong><br><?= (int)$urgencia['minutos_llegada'] ?> minutos</div>
                    <div class="col-sm-6"><strong>Forma de pago:</strong><br><?= e($formasPago[$urgencia['forma_pago']] ?? 'Por definir') ?></div>
                </div>

                <form method="post" class="border-top pt-3">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_urgencias']) ?>">
                    <input type="hidden" name="urgencia_id" value="<?= (int)$urgencia['id'] ?>">
                    <label class="form-label small fw-semibold">Observación para el registro</label>
                    <textarea name="observacion" class="form-control mb-3" rows="2" maxlength="500" placeholder="Ej: Box 1 preparado, avisar al veterinario..."><?= e((string)($urgencia['observacion_recepcion'] ?? '')) ?></textarea>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($esPendiente && !$esVeterinario): ?>
                        <button class="btn btn-primary" name="estado" value="confirmada"><i class="bi bi-check-circle me-1"></i>Confirmar</button>
                        <button class="btn btn-outline-danger" name="estado" value="rechazada"><i class="bi bi-x-circle me-1"></i>Rechazar</button>
                        <?php elseif ($urgencia['estado'] === 'confirmada' && $esVeterinario): ?>
                        <button class="btn btn-warning" name="estado" value="en_atencion"><i class="bi bi-heart-pulse me-1"></i>Iniciar atención</button>
                        <?php elseif ($urgencia['estado'] === 'en_atencion' && $esVeterinario): ?>
                        <button class="btn btn-success" name="estado" value="finalizada"><i class="bi bi-check2-circle me-1"></i>Finalizar atención</button>
                        <?php else: ?>
                        <span class="text-muted small"><i class="bi bi-hourglass-split me-1"></i>Esperando acción del equipo responsable.</span>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card card-stat">
    <div class="card-header bg-white border-0 pt-4 px-4">
        <h6 class="fw-bold mb-0">Historial reciente</h6>
    </div>
    <div class="card-body px-4 pb-4">
        <?php if (!$historial): ?>
            <p class="text-muted mb-0">Todavía no hay urgencias finalizadas.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Mascota</th><th>Dueño</th><th>Pago</th><th>Estado</th><th>Responsable</th></tr></thead>
                <tbody>
                <?php foreach ($historial as $urgencia): ?>
                    <tr>
                        <td><?= e(date('d/m/Y H:i', strtotime($urgencia['fecha_solicitud']))) ?></td>
                        <td class="fw-semibold"><?= e($urgencia['mascota']) ?></td>
                        <td><?= e($urgencia['dueno']) ?></td>
                        <td><?= e($formasPago[$urgencia['forma_pago']] ?? 'Por definir') ?></td>
                        <td><span class="badge text-bg-secondary"><?= e($estados[$urgencia['estado']] ?? $urgencia['estado']) ?></span></td>
                        <td><?= e((string)($urgencia['responsable'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
setTimeout(() => window.location.reload(), 15000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
