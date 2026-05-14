<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';

$pdo  = getDB();
$stmt = $pdo->prepare("
    SELECT m.id, m.identificador, m.nombre, m.especie, m.raza, m.peso,
           d.nombre as dueno, d.telefono
    FROM mascotas m
    JOIN duenos d ON m.dueno_id = d.id
    ORDER BY m.nombre ASC
");
$stmt->execute();
$mascotas = $stmt->fetchAll();

$mensaje = $_SESSION['mensaje'] ?? '';
unset($_SESSION['mensaje']);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-3">
    <div>
        <h3>Registro de Mascotas</h3>
        <p>Lista completa de pacientes y sus dueños registrados.</p>
    </div>
    <a href="registrar_mascota.php" class="btn btn-primary px-4 shadow-sm">
        <i class="bi bi-plus-circle me-2"></i>Nueva Mascota
    </a>
</div>

<?php if ($mensaje): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i><?= e($mensaje) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card card-stat">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tablaMascotas" class="table table-hover align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Paciente</th>
                        <th>Especie / Raza</th>
                        <th>Peso</th>
                        <th>Dueño</th>
                        <th>Contacto</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mascotas as $m): ?>
                    <?php
                        $iconos = ['Perro' => 'bi-emoji-smile', 'Gato' => 'bi-emoji-heart-eyes', 'Ave' => 'bi-feather'];
                        $icono  = $iconos[$m['especie']] ?? 'bi-heart-pulse';
                    ?>
                    <tr>
                        <td class="ps-4">
                            <code class="bg-light px-2 py-1 rounded" style="font-size:0.78rem;"><?= e($m['identificador']) ?></code>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar bg-primary bg-opacity-10 text-primary" style="width:36px;height:36px;border-radius:10px;">
                                    <i class="bi <?= $icono ?>"></i>
                                </div>
                                <span class="fw-semibold text-dark"><?= e($m['nombre']) ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border" style="font-size:0.78rem;font-weight:500;">
                                <?= e($m['especie']) ?>
                            </span>
                            <span class="text-muted ms-1" style="font-size:0.85rem;"><?= e($m['raza']) ?></span>
                        </td>
                        <td>
                            <span class="fw-semibold"><?= e($m['peso']) ?></span>
                            <span class="text-muted small"> kg</span>
                        </td>
                        <td>
                            <span class="fw-medium"><?= e($m['dueno']) ?></span>
                        </td>
                        <td>
                            <a href="https://wa.me/52<?= e($m['telefono']) ?>"
                               class="btn btn-sm btn-success d-inline-flex align-items-center gap-1"
                               target="_blank" title="Enviar WhatsApp">
                                <i class="bi bi-whatsapp"></i>
                                <span class="d-none d-md-inline"><?= e($m['telefono']) ?></span>
                            </a>
                        </td>
                        <td class="text-center">
                            <a href="historial.php?id=<?= (int)$m['id'] ?>"
                               class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
                                <i class="bi bi-journal-medical"></i> Historial
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
