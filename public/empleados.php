<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';

// Protección de ruta: Solo admin puede ver esto
if ($_SESSION['user_rol'] !== 'admin') {
    die("<div class='container mt-5'><h1>Acceso Denegado</h1><p>No tienes permisos para ver esta página. <a href='index.php'>Volver</a></p></div>");
}

 $pdo = getDB();
 $mensaje = '';

// Procesar formulario de nuevo empleado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre_completo']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $rol = $_POST['rol'];

    if (!empty($nombre) && !empty($username) && !empty($password)) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (username, password, nombre_completo, rol) VALUES (:username, :password, :nombre, :rol)");
            $stmt->execute([':username' => $username, ':password' => $hash, ':nombre' => $nombre, ':rol' => $rol]);
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

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-0">Gestión de Empleados</h3>
        <p class="text-muted mb-0">Crea y administra los accesos al sistema clínico.</p>
    </div>
    <button type="button" class="btn btn-primary px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#nuevoEmpleadoModal">
        <i class="bi bi-person-plus-fill me-2"></i>Nuevo Empleado
    </button>
</div>

<?php if ($mensaje === 'empleado_creado'): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i>Empleado creado exitosamente.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php elseif ($mensaje === 'error_usuario_existe'): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>El nombre de usuario ya existe. Elige otro.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Tabla de Empleados Profesional -->
<div class="card card-stat border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tablaEmpleados" class="table table-hover align-middle mb-0" style="width:100%">
                <thead class="bg-light text-muted small" style="border-bottom: 2px solid #e2e8f0;">
                    <tr>
                        <th class="ps-4 py-3">ID</th>
                        <th class="py-3">Nombre Completo</th>
                        <th class="py-3">Usuario (Login)</th>
                        <th class="py-3">Rol en el Sistema</th>
                        <th class="py-3">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($empleados as $emp): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td class="ps-4 fw-bold text-muted">#<?= str_pad($emp['id'], 3, "0", STR_PAD_LEFT) ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar bg-light text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; font-weight: 600;">
                                    <?= strtoupper(substr($emp['nombre_completo'], 0, 1)) ?>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-semibold text-dark"><?= e($emp['nombre_completo']) ?></h6>
                                </div>
                            </div>
                        </td>
                        <td><code class="bg-light px-2 py-1 rounded text-dark"><?= e($emp['username']) ?></code></td>
                        <td>
                            <?php 
                                $config = [
                                    'admin' => ['color' => 'danger', 'icon' => 'shield-lock'],
                                    'veterinario' => ['color' => 'success', 'icon' => 'heart-pulse'],
                                    'recepcion' => ['color' => 'info', 'icon' => 'headset']
                                ];
                                $c = $config[$emp['rol']] ?? ['color' => 'secondary', 'icon' => 'person'];
                            ?>
                            <span class="badge bg-<?= $c['color'] ?> bg-opacity-10 text-<?= $c['color'] ?> p-2 rounded-pill">
                                <i class="bi bi-<?= $c['icon'] ?> me-1"></i><?= ucfirst($emp['rol']) ?>
                            </span>
                        </td>
                        <td>
                            <?= $emp['activo'] ? '<span class="text-success fw-semibold"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem"></i>Activo</span>' : '<span class="text-secondary"><i class="bi bi-circle me-1" style="font-size:0.5rem"></i>Inactivo</span>' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para Nuevo Empleado (Diseño Limpio) -->
<div class="modal fade" id="nuevoEmpleadoModal" tabindex="-1" aria-labelledby="nuevoEmpleadoLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold" id="nuevoEmpleadoLabel"><i class="bi bi-person-plus text-primary me-2"></i>Registrar Nuevo Empleado</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="empleados.php">
      <div class="modal-body px-4">
            <div class="mb-3">
                <label class="form-label text-muted small fw-semibold">Nombre Completo</label>
                <input type="text" name="nombre_completo" class="form-control py-2" placeholder="Ej: Dr. Juan Pérez" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small fw-semibold">Usuario (Inicio de Sesión)</label>
                <input type="text" name="username" class="form-control py-2" placeholder="Ej: jperez" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small fw-semibold">Contraseña Temporal</label>
                <input type="password" name="password" class="form-control py-2" placeholder="Mínimo 6 caracteres" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small fw-semibold">Rol en el Sistema</label>
                <select name="rol" class="form-select py-2">
                    <option value="recepcion">Recepción (Registros y búsquedas)</option>
                    <option value="veterinario">Veterinario (Historial y diagnóstico)</option>
                    <option value="admin">Administrador (Acceso total)</option>
                </select>
            </div>
      </div>
      <div class="modal-footer border-0 pt-0 px-4 pb-4">
        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-check-lg me-1"></i>Guardar</button>
      </div>
      </form>
    </div>
  </div>
</div>

<script>
    // Inicializar DataTable en la tabla de empleados
    $(document).ready(function() {
        $('#tablaEmpleados').DataTable({
            language: {
                search: "Buscar empleado:",
                lengthMenu: "Mostrar _MENU_ registros",
                info: "Mostrando _START_ a _END_ de _TOTAL_",
                paginate: { first: "Primero", last: "Último", next: "Siguiente", previous: "Anterior" },
                zeroRecords: "No se encontraron empleados",
                infoEmpty: "No hay empleados disponibles",
                infoFiltered: "(filtrado de _MAX_ registros totales)"
            },
            responsive: true,
            pageLength: 10,
            order: [[0, 'desc']]
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>