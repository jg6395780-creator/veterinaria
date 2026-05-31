<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';

$pdo  = getDB();
$stmt = $pdo->prepare("
    SELECT m.id, m.dueno_id, m.identificador, m.nombre, m.especie, m.raza,
           m.peso, m.fecha_nacimiento, d.nombre AS dueno, d.telefono
    FROM mascotas m
    JOIN duenos d ON m.dueno_id = d.id
    ORDER BY m.nombre ASC
");
$stmt->execute();
$mascotas = $stmt->fetchAll();

$mensaje       = $_SESSION['mensaje']       ?? '';
$mensaje_error = $_SESSION['mensaje_error'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['mensaje_error']);

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
<?php if ($mensaje_error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($mensaje_error) ?>
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
                            <span class="badge bg-light text-dark border" style="font-size:0.78rem;font-weight:500;"><?= e($m['especie']) ?></span>
                            <span class="text-muted ms-1" style="font-size:0.85rem;"><?= e($m['raza']) ?></span>
                        </td>
                        <td><span class="fw-semibold"><?= e($m['peso']) ?></span> <span class="text-muted small">kg</span></td>
                        <td class="fw-medium"><?= e($m['dueno']) ?></td>
                        <td>
                            <a href="https://wa.me/52<?= e($m['telefono']) ?>" class="btn btn-sm btn-success d-inline-flex align-items-center gap-1" target="_blank">
                                <i class="bi bi-whatsapp"></i>
                                <span class="d-none d-md-inline"><?= e($m['telefono']) ?></span>
                            </a>
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <a href="historial.php?id=<?= (int)$m['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver historial">
                                    <i class="bi bi-journal-medical"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-mascota" title="Editar"
                                    data-id="<?= (int)$m['id'] ?>"
                                    data-identificador="<?= e($m['identificador']) ?>"
                                    data-nombre="<?= e($m['nombre']) ?>"
                                    data-especie="<?= e($m['especie']) ?>"
                                    data-raza="<?= e($m['raza']) ?>"
                                    data-peso="<?= e($m['peso']) ?>"
                                    data-dueno="<?= e($m['dueno']) ?>"
                                    data-telefono="<?= e($m['telefono']) ?>"
                                    data-fecha="<?= e($m['fecha_nacimiento'] ?? '') ?>"
                                    data-bs-toggle="modal" data-bs-target="#editModal">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-mascota" title="Eliminar"
                                    data-id="<?= (int)$m['id'] ?>"
                                    data-nombre="<?= e($m['nombre']) ?>"
                                    data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===== MODAL EDITAR ===== -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Editar Mascota</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="procesar_mascota.php" novalidate id="formEditMascota">
                <input type="hidden" name="action" value="editar">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body px-4 pb-0">
                    <div class="form-section-title mb-3"><i class="bi bi-person-circle"></i>Datos del Dueño</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Nombre del Dueño</label>
                            <input type="text" name="dueno_nombre" id="edit_dueno_nombre" class="form-control" required>
                            <div class="invalid-feedback">Este campo es requerido.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" name="dueno_telefono" id="edit_dueno_telefono" class="form-control" required>
                            <div class="invalid-feedback">Este campo es requerido.</div>
                        </div>
                    </div>
                    <div class="form-section-title mb-3"><i class="bi bi-heart-pulse"></i>Datos de la Mascota</div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Identificador</label>
                            <input type="text" name="identificador" id="edit_identificador" class="form-control" required>
                            <div class="invalid-feedback">Este campo es requerido.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                            <div class="invalid-feedback">Este campo es requerido.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Especie</label>
                            <select name="especie" id="edit_especie" class="form-select" required>
                                <option value="Perro">🐕 Perro</option>
                                <option value="Gato">🐈 Gato</option>
                                <option value="Ave">🐦 Ave</option>
                                <option value="Otro">🐾 Otro</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Raza</label>
                            <input type="text" name="raza" id="edit_raza" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Peso (kg)</label>
                            <input type="number" step="0.01" min="0" name="peso" id="edit_peso" class="form-control" required>
                            <div class="invalid-feedback">Este campo es requerido.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fecha Nacimiento</label>
                            <input type="date" name="fecha_nacimiento" id="edit_fecha_nacimiento" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-3">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm">
                        <i class="bi bi-check-lg me-1"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL ELIMINAR ===== -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Confirmar Eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <p class="mb-1">¿Estás seguro de que deseas eliminar a <strong id="delete_nombre_display"></strong>?</p>
                <p class="text-muted small mb-0">Esta acción también eliminará su historial clínico y vacunas registradas.</p>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" action="procesar_mascota.php" class="d-inline">
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="id" id="delete_id">
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="bi bi-trash me-1"></i>Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Poblar modal de edición
document.querySelectorAll('.btn-edit-mascota').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var d = this.dataset;
        document.getElementById('edit_id').value                = d.id;
        document.getElementById('edit_identificador').value     = d.identificador;
        document.getElementById('edit_nombre').value            = d.nombre;
        document.getElementById('edit_especie').value           = d.especie;
        document.getElementById('edit_raza').value              = d.raza;
        document.getElementById('edit_peso').value              = d.peso;
        document.getElementById('edit_dueno_nombre').value      = d.dueno;
        document.getElementById('edit_dueno_telefono').value    = d.telefono;
        document.getElementById('edit_fecha_nacimiento').value  = d.fecha;
        document.getElementById('formEditMascota').classList.remove('was-validated');
    });
});

// Poblar modal de eliminación
document.querySelectorAll('.btn-delete-mascota').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('delete_id').value            = this.dataset.id;
        document.getElementById('delete_nombre_display').textContent = this.dataset.nombre;
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
