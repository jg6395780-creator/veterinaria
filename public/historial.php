<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/documentos_clinicos.php';

$pdo = getDB();

if (!isset($_GET['id'])) {
    $stmtPacientes = $pdo->query("SELECT m.id, m.identificador, m.nombre, m.especie, m.raza,
            d.nombre AS dueno,
            COUNT(DISTINCT h.id) AS total_consultas,
            MAX(h.fecha_visita) AS ultima_consulta,
            COUNT(DISTINCT v.id) AS total_vacunas
        FROM mascotas m
        INNER JOIN duenos d ON d.id = m.dueno_id
        LEFT JOIN historial_clinico h ON h.mascota_id = m.id
        LEFT JOIN vacunas v ON v.mascota_id = m.id
        GROUP BY m.id, m.identificador, m.nombre, m.especie, m.raza, d.nombre
        ORDER BY m.nombre ASC");
    $pacientes = $stmtPacientes->fetchAll();
    $pageTitle = 'Historial Clínico - VetClinic Pro';
    require_once __DIR__ . '/includes/header.php';
    ?>

    <div class="page-header">
        <h3><i class="bi bi-journal-medical text-primary me-2"></i>Historial Clínico</h3>
        <p>Selecciona una mascota para revisar o actualizar su expediente clínico.</p>
    </div>

    <?php if (!empty($_SESSION['mensaje_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($_SESSION['mensaje_error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['mensaje_error']); endif; ?>

    <div class="card card-stat">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h6 class="fw-bold text-dark mb-1">Pacientes registrados</h6>
                <small class="text-muted"><?= count($pacientes) ?> mascotas disponibles</small>
            </div>
            <div class="input-group" style="max-width:320px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" id="buscarPacienteHistorial" class="form-control" placeholder="Buscar mascota, dueño o ficha">
            </div>
        </div>
        <div class="card-body px-4 pb-4">
            <?php if (!$pacientes): ?>
                <div class="empty-state">
                    <i class="bi bi-clipboard2-x"></i>
                    <p>No hay mascotas registradas.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaPacientesHistorial">
                    <thead>
                        <tr><th>Paciente</th><th>Ficha</th><th>Dueño</th><th>Consultas</th><th>Vacunas</th><th>Última consulta</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pacientes as $paciente): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar bg-primary bg-opacity-10 text-primary" style="width:36px;height:36px;border-radius:10px;">
                                        <i class="bi bi-heart-pulse-fill"></i>
                                    </span>
                                    <div><strong><?= e($paciente['nombre']) ?></strong><div class="small text-muted"><?= e($paciente['especie']) ?> · <?= e($paciente['raza'] ?: 'Sin raza') ?></div></div>
                                </div>
                            </td>
                            <td><?= e($paciente['identificador']) ?></td>
                            <td><?= e($paciente['dueno']) ?></td>
                            <td><span class="badge bg-primary bg-opacity-10 text-primary"><?= (int)$paciente['total_consultas'] ?></span></td>
                            <td><span class="badge bg-success bg-opacity-10 text-success"><?= (int)$paciente['total_vacunas'] ?></span></td>
                            <td><?= $paciente['ultima_consulta'] ? e(date('d/m/Y', strtotime($paciente['ultima_consulta']))) : '<span class="text-muted">Sin consultas</span>' ?></td>
                            <td class="text-end">
                                <a href="historial.php?id=<?= (int)$paciente['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-folder2-open me-1"></i>Ver historial
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.getElementById('buscarPacienteHistorial')?.addEventListener('input', function () {
        const texto = this.value.toLowerCase().trim();
        document.querySelectorAll('#tablaPacientesHistorial tbody tr').forEach((fila) => {
            fila.classList.toggle('d-none', !fila.textContent.toLowerCase().includes(texto));
        });
    });
    </script>

    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if (!is_numeric($_GET['id']) || (int)$_GET['id'] <= 0) {
    $_SESSION['mensaje_error'] = 'La mascota seleccionada no es válida.';
    header('Location: historial.php');
    exit;
}

$id_mascota = (int)$_GET['id'];

$stmtMascota = $pdo->prepare("
    SELECT m.*, d.nombre AS dueno, d.telefono
    FROM mascotas m
    JOIN duenos d ON m.dueno_id = d.id
    WHERE m.id = :id
");
$stmtMascota->execute([':id' => $id_mascota]);
$mascota = $stmtMascota->fetch();

if (!$mascota) {
    $_SESSION['mensaje_error'] = 'No se encontró la mascota seleccionada.';
    header("Location: historial.php");
    exit;
}

$stmtHist = $pdo->prepare("SELECT * FROM historial_clinico WHERE mascota_id=:id ORDER BY fecha_visita DESC");
$stmtHist->execute([':id' => $id_mascota]);
$historial = $stmtHist->fetchAll();

$stmtVac = $pdo->prepare("SELECT * FROM vacunas WHERE mascota_id=:id ORDER BY fecha_aplicacion DESC");
$stmtVac->execute([':id' => $id_mascota]);
$vacunas = $stmtVac->fetchAll();

asegurarTablaDocumentosClinicos($pdo);
$stmtDocumentos = $pdo->prepare("SELECT dc.*, u.nombre_completo AS autor
    FROM documentos_clinicos dc
    LEFT JOIN usuarios u ON u.id = dc.subido_por
    WHERE dc.mascota_id = :id
    ORDER BY dc.fecha_subida DESC");
$stmtDocumentos->execute([':id' => $id_mascota]);
$documentos = $stmtDocumentos->fetchAll();
$tiposDocumento = tiposDocumentoClinico();

if (empty($_SESSION['csrf_documento'])) {
    $_SESSION['csrf_documento'] = bin2hex(random_bytes(32));
}

$iconoEspecie = ['Perro'=>'bi-emoji-smile-fill', 'Gato'=>'bi-emoji-heart-eyes-fill', 'Ave'=>'bi-feather'];
$icono = $iconoEspecie[$mascota['especie']] ?? 'bi-heart-pulse-fill';

$mensaje       = $_SESSION['mensaje']       ?? '';
$mensaje_error = $_SESSION['mensaje_error'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['mensaje_error']);

$puedeEditar = in_array($_SESSION['user_rol'], ['admin', 'veterinario']);
$veterinarios_lista = $pdo->query("SELECT nombre_completo FROM usuarios WHERE rol = 'veterinario' AND activo = 1 ORDER BY nombre_completo")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-3">
    <a href="mascotas.php" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i>Volver a Mascotas
    </a>
</div>

<div class="page-header">
    <h3><?= e($mascota['nombre']) ?> <small class="text-muted fs-6 fw-normal">(<?= e($mascota['identificador']) ?>)</small></h3>
    <p>Expediente clínico completo del paciente.</p>
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

<div class="row g-4">

    <!-- ===== COLUMNA IZQUIERDA ===== -->
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
                <a href="https://wa.me/52<?= e($mascota['telefono']) ?>" class="btn btn-success btn-sm w-100 mt-3 shadow-sm" target="_blank">
                    <i class="bi bi-whatsapp me-1"></i>WhatsApp Dueño
                </a>
            </div>
        </div>

        <!-- Carnet de Vacunas -->
        <div class="card card-stat">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-shield-check text-success me-2"></i>Carnet de Vacunas
                </h6>
                <?php if ($puedeEditar): ?>
                <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalNuevaVacuna">
                    <i class="bi bi-plus-lg"></i>
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body px-4 pb-4 pt-2">
                <?php if (empty($vacunas)): ?>
                <div class="empty-state" style="padding:1.5rem 0;">
                    <i class="bi bi-shield-x" style="font-size:2rem;"></i>
                    <p>Sin vacunas registradas.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($vacunas as $v): ?>
                    <div class="vacuna-item position-relative">
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
                        <?php if ($puedeEditar): ?>
                        <div class="d-flex gap-1 mt-2">
                            <button type="button" class="btn btn-xs btn-outline-secondary btn-edit-vacuna"
                                style="font-size:0.72rem;padding:2px 8px;"
                                data-id="<?= (int)$v['id'] ?>"
                                data-nombre="<?= e($v['nombre_vacuna']) ?>"
                                data-fecha="<?= e($v['fecha_aplicacion']) ?>"
                                data-proxima="<?= e($v['fecha_proxima_dosis'] ?? '') ?>"
                                data-bs-toggle="modal" data-bs-target="#modalEditVacuna">
                                <i class="bi bi-pencil"></i> Editar
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-danger btn-delete-vacuna"
                                style="font-size:0.72rem;padding:2px 8px;"
                                data-id="<?= (int)$v['id'] ?>"
                                data-nombre="<?= e($v['nombre_vacuna']) ?>"
                                data-bs-toggle="modal" data-bs-target="#modalDeleteVacuna">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ===== COLUMNA DERECHA ===== -->
    <div class="col-md-8 col-lg-9">
        <div class="card card-stat mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-journal-medical text-primary me-2"></i>Historial Clínico
                    <span class="badge bg-primary bg-opacity-10 text-primary ms-2 px-3"><?= count($historial) ?> consulta<?= count($historial) !== 1 ? 's' : '' ?></span>
                </h6>
                <?php if ($puedeEditar): ?>
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalNuevaConsulta">
                    <i class="bi bi-plus-lg me-1"></i>Nueva Consulta
                </button>
                <?php endif; ?>
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
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary bg-opacity-10 text-primary px-3" style="font-size:0.75rem;"><?= e($h['veterinario']) ?></span>
                                <?php if ($puedeEditar): ?>
                                <button type="button" class="btn btn-xs btn-outline-secondary btn-edit-consulta"
                                    style="font-size:0.72rem;padding:2px 8px;"
                                    data-id="<?= (int)$h['id'] ?>"
                                    data-fecha="<?= e($h['fecha_visita']) ?>"
                                    data-diagnostico="<?= e($h['diagnostico']) ?>"
                                    data-tratamiento="<?= e($h['tratamiento']) ?>"
                                    data-veterinario="<?= e($h['veterinario']) ?>"
                                    data-bs-toggle="modal" data-bs-target="#modalEditConsulta">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-danger btn-delete-consulta"
                                    style="font-size:0.72rem;padding:2px 8px;"
                                    data-id="<?= (int)$h['id'] ?>"
                                    data-fecha="<?= e(date('d M Y', strtotime($h['fecha_visita']))) ?>"
                                    data-bs-toggle="modal" data-bs-target="#modalDeleteConsulta">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
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

        <div class="card card-stat">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-file-earmark-medical text-info me-2"></i>Documentos clínicos
                    <span class="badge bg-info bg-opacity-10 text-info ms-2 px-3"><?= count($documentos) ?></span>
                </h6>
                <?php if ($puedeEditar): ?>
                <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalNuevoDocumento">
                    <i class="bi bi-cloud-arrow-up me-1"></i>Agregar documento
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body px-4 pb-4">
                <?php if (!$documentos): ?>
                <div class="empty-state py-4">
                    <i class="bi bi-file-earmark-x"></i>
                    <p>Sin recetas, exámenes o imágenes registradas.</p>
                </div>
                <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($documentos as $documento):
                        $esPdf = $documento['mime_type'] === 'application/pdf';
                    ?>
                    <div class="col-12 col-lg-6">
                        <a href="api/ver_documento.php?token=<?= e($documento['token_descarga']) ?>" target="_blank"
                           class="documento-clinico-card text-decoration-none">
                            <span class="documento-clinico-icon <?= $esPdf ? 'documento-pdf' : 'documento-imagen' ?>">
                                <i class="bi <?= $esPdf ? 'bi-file-earmark-pdf-fill' : 'bi-file-earmark-image-fill' ?>"></i>
                            </span>
                            <span class="flex-grow-1 overflow-hidden">
                                <strong class="d-block text-dark text-truncate"><?= e($documento['titulo']) ?></strong>
                                <small class="d-block text-muted"><?= e($tiposDocumento[$documento['tipo']] ?? 'Documento clínico') ?> · <?= e(date('d/m/Y', strtotime($documento['fecha_subida']))) ?></small>
                                <?php if (!empty($documento['descripcion'])): ?>
                                <small class="d-block text-muted text-truncate mt-1"><?= e($documento['descripcion']) ?></small>
                                <?php endif; ?>
                            </span>
                            <i class="bi bi-box-arrow-up-right text-muted"></i>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php if ($puedeEditar): ?>

<!-- ===== MODAL NUEVO DOCUMENTO ===== -->
<div class="modal fade" id="modalNuevoDocumento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-cloud-arrow-up text-info me-2"></i>Agregar documento clínico</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="procesar_documento.php" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="mascota_id" value="<?= $id_mascota ?>">
                <input type="hidden" name="csrf_documento" value="<?= e($_SESSION['csrf_documento']) ?>">
                <div class="modal-body px-4">
                    <div class="mb-3">
                        <label class="form-label">Tipo de documento</label>
                        <select name="tipo" class="form-select" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($tiposDocumento as $valor => $etiqueta): ?>
                            <option value="<?= e($valor) ?>"><?= e($etiqueta) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" class="form-control" maxlength="180" placeholder="Ej: Radiografía de tórax" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción <span class="text-muted fw-normal">(opcional)</span></label>
                        <textarea name="descripcion" class="form-control" rows="2" maxlength="500" placeholder="Observaciones para el dueño..."></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Archivo</label>
                        <input type="file" name="archivo" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" required>
                        <div class="form-text">PDF, JPG, PNG o WEBP. Tamaño máximo: 15 MB.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-2">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-info text-white px-4"><i class="bi bi-upload me-1"></i>Subir documento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL NUEVA CONSULTA ===== -->
<div class="modal fade" id="modalNuevaConsulta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>Nueva Consulta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="procesar_historial.php" novalidate id="formNuevaConsulta">
                <input type="hidden" name="action" value="crear_consulta">
                <input type="hidden" name="mascota_id" value="<?= $id_mascota ?>">
                <div class="modal-body px-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Visita</label>
                            <input type="date" name="fecha_visita" class="form-control" required>
                            <div class="invalid-feedback">Seleccione la fecha.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Veterinario</label>
                            <select name="veterinario" class="form-select" required>
                                <option value="">— Seleccione veterinario —</option>
                                <?php foreach ($veterinarios_lista as $v): ?>
                                <option value="<?= e($v['nombre_completo']) ?>"
                                    <?= $_SESSION['user_rol'] === 'veterinario' && $_SESSION['user_name'] === $v['nombre_completo'] ? 'selected' : '' ?>>
                                    <?= e($v['nombre_completo']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Seleccione un veterinario.</div>
                            <?php if (!$veterinarios_lista): ?>
                            <div class="form-text text-danger">Primero debe crear un empleado con cargo Veterinario.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Diagnóstico</label>
                            <textarea name="diagnostico" class="form-control" rows="4" placeholder="Descripción del diagnóstico..." required></textarea>
                            <div class="invalid-feedback">Este campo es requerido.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tratamiento</label>
                            <textarea name="tratamiento" class="form-control" rows="4" placeholder="Medicamentos y tratamiento indicado..." required></textarea>
                            <div class="invalid-feedback">Este campo es requerido.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-2">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm">
                        <i class="bi bi-save me-1"></i>Guardar Consulta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL EDITAR CONSULTA ===== -->
<div class="modal fade" id="modalEditConsulta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Editar Consulta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="procesar_historial.php" novalidate id="formEditConsulta">
                <input type="hidden" name="action" value="editar_consulta">
                <input type="hidden" name="mascota_id" value="<?= $id_mascota ?>">
                <input type="hidden" name="id" id="ec_id">
                <div class="modal-body px-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Visita</label>
                            <input type="date" name="fecha_visita" id="ec_fecha" class="form-control" required>
                            <div class="invalid-feedback">Seleccione la fecha.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Veterinario</label>
                            <select name="veterinario" id="ec_veterinario" class="form-select" required>
                                <option value="">— Seleccione veterinario —</option>
                                <?php foreach ($veterinarios_lista as $v): ?>
                                <option value="<?= e($v['nombre_completo']) ?>"><?= e($v['nombre_completo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Seleccione un veterinario.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Diagnóstico</label>
                            <textarea name="diagnostico" id="ec_diagnostico" class="form-control" rows="4" required></textarea>
                            <div class="invalid-feedback">Este campo es requerido.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tratamiento</label>
                            <textarea name="tratamiento" id="ec_tratamiento" class="form-control" rows="4" required></textarea>
                            <div class="invalid-feedback">Este campo es requerido.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-2">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm">
                        <i class="bi bi-check-lg me-1"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL ELIMINAR CONSULTA ===== -->
<div class="modal fade" id="modalDeleteConsulta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Eliminar Consulta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <p class="mb-0">¿Eliminar la consulta del <strong id="dc_fecha"></strong>? Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-2">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" action="procesar_historial.php" class="d-inline">
                    <input type="hidden" name="action" value="eliminar_consulta">
                    <input type="hidden" name="mascota_id" value="<?= $id_mascota ?>">
                    <input type="hidden" name="id" id="dc_id">
                    <button type="submit" class="btn btn-danger px-4"><i class="bi bi-trash me-1"></i>Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ===== MODAL NUEVA VACUNA ===== -->
<div class="modal fade" id="modalNuevaVacuna" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-plus text-success me-2"></i>Nueva Vacuna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="procesar_historial.php" novalidate id="formNuevaVacuna">
                <input type="hidden" name="action" value="crear_vacuna">
                <input type="hidden" name="mascota_id" value="<?= $id_mascota ?>">
                <div class="modal-body px-4">
                    <div class="mb-3">
                        <label class="form-label">Nombre de la Vacuna</label>
                        <input type="text" name="nombre_vacuna" class="form-control" placeholder="Ej: Rabia, Parvovirus..." required>
                        <div class="invalid-feedback">Este campo es requerido.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha de Aplicación</label>
                        <input type="date" name="fecha_aplicacion" class="form-control" required>
                        <div class="invalid-feedback">Seleccione la fecha.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha Próxima Dosis <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="date" name="fecha_proxima_dosis" class="form-control">
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-2">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success px-4 shadow-sm">
                        <i class="bi bi-save me-1"></i>Guardar Vacuna
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL EDITAR VACUNA ===== -->
<div class="modal fade" id="modalEditVacuna" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-success me-2"></i>Editar Vacuna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="procesar_historial.php" novalidate id="formEditVacuna">
                <input type="hidden" name="action" value="editar_vacuna">
                <input type="hidden" name="mascota_id" value="<?= $id_mascota ?>">
                <input type="hidden" name="id" id="ev_id">
                <div class="modal-body px-4">
                    <div class="mb-3">
                        <label class="form-label">Nombre de la Vacuna</label>
                        <input type="text" name="nombre_vacuna" id="ev_nombre" class="form-control" required>
                        <div class="invalid-feedback">Este campo es requerido.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha de Aplicación</label>
                        <input type="date" name="fecha_aplicacion" id="ev_fecha" class="form-control" required>
                        <div class="invalid-feedback">Seleccione la fecha.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha Próxima Dosis <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="date" name="fecha_proxima_dosis" id="ev_proxima" class="form-control">
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-2">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success px-4 shadow-sm">
                        <i class="bi bi-check-lg me-1"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL ELIMINAR VACUNA ===== -->
<div class="modal fade" id="modalDeleteVacuna" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Eliminar Vacuna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <p class="mb-0">¿Eliminar la vacuna <strong id="dv_nombre"></strong>? Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-2">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" action="procesar_historial.php" class="d-inline">
                    <input type="hidden" name="action" value="eliminar_vacuna">
                    <input type="hidden" name="mascota_id" value="<?= $id_mascota ?>">
                    <input type="hidden" name="id" id="dv_id">
                    <button type="submit" class="btn btn-danger px-4"><i class="bi bi-trash me-1"></i>Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Editar consulta
document.querySelectorAll('.btn-edit-consulta').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var d = this.dataset;
        document.getElementById('ec_id').value          = d.id;
        document.getElementById('ec_fecha').value       = d.fecha;
        document.getElementById('ec_diagnostico').value = d.diagnostico;
        document.getElementById('ec_tratamiento').value = d.tratamiento;
        document.getElementById('ec_veterinario').value = d.veterinario;
        document.getElementById('formEditConsulta').classList.remove('was-validated');
    });
});
// Eliminar consulta
document.querySelectorAll('.btn-delete-consulta').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('dc_id').value   = this.dataset.id;
        document.getElementById('dc_fecha').textContent = this.dataset.fecha;
    });
});
// Editar vacuna
document.querySelectorAll('.btn-edit-vacuna').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var d = this.dataset;
        document.getElementById('ev_id').value      = d.id;
        document.getElementById('ev_nombre').value  = d.nombre;
        document.getElementById('ev_fecha').value   = d.fecha;
        document.getElementById('ev_proxima').value = d.proxima;
        document.getElementById('formEditVacuna').classList.remove('was-validated');
    });
});
// Eliminar vacuna
document.querySelectorAll('.btn-delete-vacuna').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('dv_id').value             = this.dataset.id;
        document.getElementById('dv_nombre').textContent   = this.dataset.nombre;
    });
});
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
