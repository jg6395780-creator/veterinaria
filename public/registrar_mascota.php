<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';

$mensaje_error = $_SESSION['mensaje_error'] ?? '';
unset($_SESSION['mensaje_error']);

$pdo  = getDB();
$stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(identificador, 3) AS UNSIGNED)) FROM mascotas WHERE identificador REGEXP '^M-[0-9]+$'");
$max  = (int)$stmt->fetchColumn();
$siguiente_id = 'M-' . str_pad($max + 1, 3, '0', STR_PAD_LEFT);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h3>Registrar Nueva Mascota</h3>
    <p>Complete los datos del dueño y la mascota para crear el expediente.</p>
</div>

<?php if ($mensaje_error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($mensaje_error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-9 col-xl-8">
        <div class="card card-stat">
            <div class="card-body p-4 p-md-5">
                <form action="procesar_mascota.php" method="POST" novalidate>
                    <input type="hidden" name="action" value="crear">

                    <!-- Sección Dueño -->
                    <div class="form-section-title">
                        <i class="bi bi-person-circle text-primary"></i>Datos del Dueño
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Nombre Completo</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" name="dueno_nombre" class="form-control"
                                       placeholder="Ej: María González" required>
                                <div class="invalid-feedback">Este campo es requerido.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Teléfono (WhatsApp)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-whatsapp"></i></span>
                                <input type="tel" name="dueno_telefono" class="form-control"
                                       placeholder="Ej: 5512345678"
                                       pattern="[0-9]{10,15}" required>
                                <div class="invalid-feedback">Ingrese un número válido (10-15 dígitos).</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Correo Electrónico</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="dueno_email" class="form-control"
                                       placeholder="Ej: maria@gmail.com" required>
                                <div class="invalid-feedback">Ingrese un correo válido.</div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4" style="border-color:#f1f5f9;">

                    <!-- Sección Mascota -->
                    <div class="form-section-title">
                        <i class="bi bi-heart-pulse text-primary"></i>Datos de la Mascota
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Identificador Único</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                                <input type="text" name="identificador" class="form-control bg-light"
                                       value="<?= e($siguiente_id) ?>" readonly>
                            </div>
                            <div class="form-text text-muted">Generado automáticamente.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nombre de la Mascota</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-tag"></i></span>
                                <input type="text" name="nombre" class="form-control"
                                       placeholder="Ej: Luna" required>
                                <div class="invalid-feedback">Este campo es requerido.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Especie</label>
                            <select name="especie" class="form-select" required>
                                <option value="Perro">🐕 Perro</option>
                                <option value="Gato">🐈 Gato</option>
                                <option value="Ave">🐦 Ave</option>
                                <option value="Otro">🐾 Otro</option>
                            </select>
                            <div class="invalid-feedback">Seleccione una especie.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Raza</label>
                            <input type="text" name="raza" class="form-control" placeholder="Ej: Labrador" required>
                            <div class="invalid-feedback">Este campo es requerido.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Peso (kg)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" name="peso" class="form-control"
                                       placeholder="Ej: 12.5" required>
                                <span class="input-group-text text-muted">kg</span>
                                <div class="invalid-feedback">Ingrese el peso.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fecha de Nacimiento</label>
                            <input type="date" name="fecha_nacimiento" class="form-control" required>
                            <div class="invalid-feedback">Este campo es requerido.</div>
                        </div>
                    </div>

                    <hr class="mt-4 mb-3" style="border-color:#f1f5f9;">

                    <div class="d-flex justify-content-end gap-2">
                        <a href="mascotas.php" class="btn btn-light px-4">
                            <i class="bi bi-x-circle me-1"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary px-4 shadow-sm">
                            <i class="bi bi-save me-2"></i>Guardar Registro
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
