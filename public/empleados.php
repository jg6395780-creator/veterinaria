<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';

if ($_SESSION['user_rol'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$pdo     = getDB();
$columnasUsuarios = $pdo->query('SHOW COLUMNS FROM usuarios')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('email', $columnasUsuarios, true)) {
    $pdo->exec('ALTER TABLE usuarios ADD email VARCHAR(150) NULL UNIQUE AFTER nombre_completo');
}
$mensaje = $_SESSION['mensaje_empleado'] ?? '';
unset($_SESSION['mensaje_empleado']);

// Crear nuevo empleado (formulario en modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    $nombre   = trim($_POST['nombre_completo'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $username = trim($_POST['username']        ?? '');
    $password = $_POST['password']             ?? '';
    $rol      = $_POST['rol']                  ?? 'recepcion';

    if ($nombre && $email && filter_var($email, FILTER_VALIDATE_EMAIL) && $username && $password) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO usuarios (username, password, nombre_completo, email, rol) VALUES (:u, :p, :n, :e, :r)")
                ->execute([':u'=>$username, ':p'=>$hash, ':n'=>$nombre, ':e'=>$email, ':r'=>$rol]);
            $mensaje = 'empleado_creado';
        } catch (PDOException $e) {
            $mensaje = 'error_usuario_existe';
        }
    } else {
        $mensaje = 'error_campos_vacios';
    }
}

$empleados = $pdo->query("SELECT * FROM usuarios ORDER BY id DESC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-3">
    <div>
        <h3>Gestión de Empleados</h3>
        <p>Crea y administra los accesos al sistema clínico.</p>
    </div>
    <button type="button" class="btn btn-primary px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoEmpleado">
        <i class="bi bi-person-plus-fill me-2"></i>Nuevo Empleado
    </button>
</div>

<?php
$alertas = [
    'empleado_creado'    => ['success', 'Empleado creado exitosamente.'],
    'empleado_actualizado'=> ['success', 'Empleado actualizado correctamente.'],
    'empleado_eliminado' => ['success', 'Empleado eliminado correctamente.'],
    'error_usuario_existe'=> ['danger',  'El nombre de usuario ya existe. Elige otro.'],
    'error_campos_vacios' => ['danger',  'Por favor complete todos los campos requeridos.'],
    'error'              => ['danger',  'Ocurrió un error. Intente de nuevo.'],
];
if (isset($alertas[$mensaje])): [$tipo, $txt] = $alertas[$mensaje]; ?>
<div class="alert alert-<?= $tipo ?> alert-dismissible fade show" role="alert">
    <i class="bi bi-<?= $tipo === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i><?= e($txt) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card card-stat">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tablaEmpleados" class="table table-hover align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Nombre Completo</th>
                        <th>Usuario</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($empleados as $emp): ?>
                    <?php
                        $roleConfig = [
                            'admin'       => ['danger',    'shield-lock'],
                            'veterinario' => ['success',   'heart-pulse'],
                            'recepcion'   => ['info',      'headset'],
                        ];
                        $rc = $roleConfig[$emp['rol']] ?? ['secondary', 'person'];
                        $esSelf = (int)$emp['id'] === (int)$_SESSION['user_id'];
                    ?>
                    <tr>
                        <td class="ps-4 fw-bold text-muted">#<?= str_pad($emp['id'], 3, '0', STR_PAD_LEFT) ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar bg-<?= $rc[0] ?> bg-opacity-10 text-<?= $rc[0] ?> fw-bold"
                                     style="width:38px;height:38px;border-radius:10px;font-size:0.95rem;">
                                    <?= strtoupper(substr($emp['nombre_completo'], 0, 1)) ?>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-semibold text-dark"><?= e($emp['nombre_completo']) ?></h6>
                                    <?php if ($esSelf): ?>
                                    <small class="text-muted">(tú)</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><code class="bg-light px-2 py-1 rounded text-dark"><?= e($emp['username']) ?></code></td>
                        <td class="text-muted small"><?= e($emp['email'] ?? 'Sin correo') ?></td>
                        <td>
                            <span class="badge bg-<?= $rc[0] ?> bg-opacity-10 text-<?= $rc[0] ?> p-2 rounded-pill">
                                <i class="bi bi-<?= $rc[1] ?> me-1"></i><?= ucfirst($emp['rol']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($emp['activo']): ?>
                                <span class="text-success fw-semibold"><span class="status-dot active"></span>Activo</span>
                            <?php else: ?>
                                <span class="text-muted"><span class="status-dot inactive"></span>Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <!-- Editar -->
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-emp" title="Editar"
                                    data-id="<?= (int)$emp['id'] ?>"
                                    data-nombre="<?= e($emp['nombre_completo']) ?>"
                                    data-email="<?= e($emp['email'] ?? '') ?>"
                                    data-rol="<?= e($emp['rol']) ?>"
                                    data-bs-toggle="modal" data-bs-target="#modalEditEmpleado">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <!-- Toggle activo/inactivo -->
                                <?php if (!$esSelf): ?>
                                <form method="POST" action="procesar_empleado.php" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_activo">
                                    <input type="hidden" name="id" value="<?= (int)$emp['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-<?= $emp['activo'] ? 'warning' : 'success' ?>"
                                        title="<?= $emp['activo'] ? 'Desactivar' : 'Activar' ?>">
                                        <i class="bi bi-<?= $emp['activo'] ? 'pause-circle' : 'play-circle' ?>"></i>
                                    </button>
                                </form>
                                <!-- Eliminar -->
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-emp" title="Eliminar"
                                    data-id="<?= (int)$emp['id'] ?>"
                                    data-nombre="<?= e($emp['nombre_completo']) ?>"
                                    data-bs-toggle="modal" data-bs-target="#modalDeleteEmpleado">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===== MODAL NUEVO EMPLEADO ===== -->
<div class="modal fade" id="modalNuevoEmpleado" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus text-primary me-2"></i>Registrar Nuevo Empleado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="empleados.php" novalidate id="formNuevoEmp">
                <input type="hidden" name="action" value="crear">
                <div class="modal-body px-4">
                    <div class="mb-3">
                        <label class="form-label">Nombre Completo</label>
                        <input type="text" name="nombre_completo" class="form-control" placeholder="Ej: Dr. Juan Pérez" required>
                        <div class="invalid-feedback">Este campo es requerido.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Usuario (inicio de sesión)</label>
                        <input type="text" name="username" class="form-control" placeholder="Ej: jperez" required>
                        <div class="invalid-feedback">Este campo es requerido.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" name="email" class="form-control" placeholder="Ej: juan@correo.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña</label>
                        <input type="password" name="password" class="form-control" placeholder="Mínimo 6 caracteres" minlength="6" required>
                        <div class="invalid-feedback">Ingrese una contraseña de al menos 6 caracteres.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol en el Sistema</label>
                        <select name="rol" class="form-select" required>
                            <option value="recepcion">Recepción (Registros y búsquedas)</option>
                            <option value="veterinario">Veterinario (Historial y diagnóstico)</option>
                            <option value="admin">Administrador (Acceso total)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL EDITAR EMPLEADO ===== -->
<div class="modal fade" id="modalEditEmpleado" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Editar Empleado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="procesar_empleado.php" novalidate id="formEditEmp">
                <input type="hidden" name="action" value="editar">
                <input type="hidden" name="id" id="ee_id">
                <div class="modal-body px-4">
                    <div class="mb-3">
                        <label class="form-label">Nombre Completo</label>
                        <input type="text" name="nombre_completo" id="ee_nombre" class="form-control" required>
                        <div class="invalid-feedback">Este campo es requerido.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" name="email" id="ee_email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol en el Sistema</label>
                        <select name="rol" id="ee_rol" class="form-select" required>
                            <option value="recepcion">Recepción</option>
                            <option value="veterinario">Veterinario</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nueva Contraseña <span class="text-muted fw-normal">(dejar vacío para no cambiar)</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Nueva contraseña">
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-check-lg me-1"></i>Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL ELIMINAR EMPLEADO ===== -->
<div class="modal fade" id="modalDeleteEmpleado" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Eliminar Empleado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <p class="mb-0">¿Estás seguro de que deseas eliminar a <strong id="de_nombre"></strong>? Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-2">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" action="procesar_empleado.php" class="d-inline">
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="id" id="de_id">
                    <button type="submit" class="btn btn-danger px-4"><i class="bi bi-trash me-1"></i>Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Poblar modal editar empleado
document.querySelectorAll('.btn-edit-emp').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('ee_id').value     = this.dataset.id;
        document.getElementById('ee_nombre').value = this.dataset.nombre;
        document.getElementById('ee_email').value  = this.dataset.email;
        document.getElementById('ee_rol').value    = this.dataset.rol;
        document.getElementById('formEditEmp').classList.remove('was-validated');
    });
});
// Poblar modal eliminar empleado
document.querySelectorAll('.btn-delete-emp').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('de_id').value              = this.dataset.id;
        document.getElementById('de_nombre').textContent    = this.dataset.nombre;
    });
});

$(document).ready(function() {
    $('#tablaEmpleados').DataTable({
        language: {
            search: "Buscar empleado:", lengthMenu: "Mostrar _MENU_ registros",
            info: "Mostrando _START_ a _END_ de _TOTAL_",
            paginate: { first:"Primero", last:"Último", next:"Siguiente", previous:"Anterior" },
            zeroRecords: "No se encontraron empleados", infoEmpty: "No hay empleados",
            infoFiltered: "(filtrado de _MAX_ totales)"
        },
        responsive: true, pageLength: 10, order: [[0, 'desc']]
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
