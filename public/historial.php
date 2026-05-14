<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: mascotas.php");
    exit;
}

$pdo        = getDB();
$id_mascota = (int)$_GET['id'];

$stmtMascota = $pdo->prepare("
    SELECT m.*, d.nombre as dueno, d.telefono
    FROM mascotas m
    JOIN duenos d ON m.dueno_id = d.id
    WHERE m.id = :id
");
$stmtMascota->execute([':id' => $id_mascota]);
$mascota = $stmtMascota->fetch();

if (!$mascota) {
    header("Location: mascotas.php");
    exit;
}

$stmtHist = $pdo->prepare("SELECT * FROM historial_clinico WHERE mascota_id = :id ORDER BY fecha_visita DESC");
$stmtHist->execute([':id' => $id_mascota]);
$historial = $stmtHist->fetchAll();

$stmtVac = $pdo->prepare("SELECT * FROM vacunas WHERE mascota_id = :id ORDER BY fecha_aplicacion DESC");
$stmtVac->execute([':id' => $id_mascota]);
$vacunas = $stmtVac->fetchAll();

$iconoEspecie = [
    'Perro' => 'bi-emoji-smile-fill',
    'Gato'  => 'bi-emoji-heart-eyes-fill',
    'Ave'   => 'bi-feather',
];
$icono = $iconoEspecie[$mascota['especie']] ?? 'bi-heart-pulse-fill';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<div class="mb-3">
    <a href="mascotas.php" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i>Volver a Mascotas
    </a>
</div>

<div class="page-header">
    <h3><?= e($mascota['nombre']) ?> <small class="text-muted fs-6 fw-normal">(<?= e($mascota['identificador']) ?>)</small></h3>
    <p>Expediente clínico completo del paciente.</p>
</div>

<div class="row g-4">

    <!-- Columna Izquierda -->
    <div class="col-md-4 col-lg-3">

        <!-- Card Mascota -->
        <div class="card card-stat mb-4">
            <div class="card-body p-4 text-center">
                <div class="avatar bg-primary bg-opacity-10 text-primary mb-3"
                     style="width:78px;height:78px;border-radius:20px;font-size:2.4rem;margin:0 auto;">
                    <i class="bi <?= $icono ?>"></i>
                </div>
                <h5 class="fw-bold mb-1"><?= e($mascota['nombre']) ?></h5>
                <p class="text-muted small mb-3">
                    <span class="badge bg-light text-dark border"><?= e($mascota['especie']) ?></span>
                    <?= e($mascota['raza']) ?>
                </p>
                <div class="row text-start g-0 border-top pt-3">
                    <div class="col-6">
                        <small class="text-muted d-block" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.5px;">Peso</small>
                        <strong><?= e($mascota['peso']) ?> kg</strong>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.5px;">Dueño</small>
                        <strong class="text-truncate d-block"><?= e($mascota['dueno']) ?></strong>
                    </div>
                </div>
                <?php if (!empty($mascota['fecha_nacimiento'])): ?>
                <div class="text-start mt-2 pt-2 border-top">
                    <small class="text-muted d-block" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.5px;">Nacimiento</small>
                    <strong><?= e(date("d M Y", strtotime($mascota['fecha_nacimiento']))) ?></strong>
                </div>
                <?php endif; ?>
                <a href="https://wa.me/52<?= e($mascota['telefono']) ?>"
                   class="btn btn-success btn-sm w-100 mt-3 shadow-sm" target="_blank">
                    <i class="bi bi-whatsapp me-1"></i>WhatsApp Dueño
                </a>
            </div>
        </div>

        <!-- Carnet de Vacunas -->
        <div class="card card-stat">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-shield-check text-success me-2"></i>Carnet de Vacunas
                </h6>
            </div>
            <div class="card-body px-4 pb-4 pt-2">
                <?php if (empty($vacunas)): ?>
                <div class="empty-state" style="padding:1.5rem 0;">
                    <i class="bi bi-shield-x" style="font-size:2rem;"></i>
                    <p>Sin vacunas registradas.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($vacunas as $v): ?>
                    <div class="vacuna-item">
                        <h6 class="mb-1 fw-semibold text-dark" style="font-size:0.875rem;"><?= e($v['nombre_vacuna']) ?></h6>
                        <div class="text-muted" style="font-size:0.78rem;">
                            <i class="bi bi-calendar-check me-1"></i><?= e(date("d M Y", strtotime($v['fecha_aplicacion']))) ?>
                        </div>
                        <?php if (!empty($v['fecha_proxima_dosis'])): ?>
                        <div class="mt-1">
                            <span class="badge bg-warning bg-opacity-10 text-warning" style="font-size:0.72rem;">
                                <i class="bi bi-clock me-1"></i>Próxima: <?= e(date("d M Y", strtotime($v['fecha_proxima_dosis']))) ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Columna Derecha: Historial -->
    <div class="col-md-8 col-lg-9">
        <div class="card card-stat">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-journal-medical text-primary me-2"></i>Historial Clínico
                </h6>
                <span class="badge bg-primary bg-opacity-10 text-primary px-3">
                    <?= count($historial) ?> <?= count($historial) === 1 ? 'consulta' : 'consultas' ?>
                </span>
            </div>
            <div class="card-body px-4 pb-4">
                <?php if (empty($historial)): ?>
                <div class="empty-state">
                    <i class="bi bi-folder2-open"></i>
                    <p>Sin consultas previas registradas.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($historial as $h): ?>
                    <div class="historial-item">
                        <div class="item-header">
                            <span class="fw-semibold text-dark" style="font-size:0.88rem;">
                                <i class="bi bi-calendar-event text-primary me-1"></i>
                                <?= e(date("d M, Y", strtotime($h['fecha_visita']))) ?>
                            </span>
                            <span class="badge bg-primary bg-opacity-10 text-primary px-3" style="font-size:0.75rem;">
                                <?= e($h['veterinario']) ?>
                            </span>
                        </div>
                        <div class="item-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="label-section text-danger">Diagnóstico</div>
                                    <p class="mb-0 text-dark" style="font-size:0.875rem;"><?= nl2br(e($h['diagnostico'])) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <div class="label-section text-success">Tratamiento</div>
                                    <p class="mb-0 text-dark" style="font-size:0.875rem;"><?= nl2br(e($h['tratamiento'])) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
