<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';

$pdo = getDB();

$pdo->exec("CREATE TABLE IF NOT EXISTS caja (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    tipo          ENUM('ingreso','egreso') NOT NULL,
    concepto      VARCHAR(200) NOT NULL,
    monto         DECIMAL(10,2) UNSIGNED NOT NULL,
    mascota_id    INT NULL,
    fecha         DATE NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mascota_id) REFERENCES mascotas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$mes_filtro = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes_filtro)) $mes_filtro = date('Y-m');
[$year, $month] = explode('-', $mes_filtro);

$stmt = $pdo->prepare("
    SELECT c.*, m.nombre AS mascota_nombre, m.identificador
    FROM caja c
    LEFT JOIN mascotas m ON c.mascota_id = m.id
    WHERE YEAR(c.fecha) = :y AND MONTH(c.fecha) = :m
    ORDER BY c.fecha DESC, c.id DESC
");
$stmt->execute([':y' => (int)$year, ':m' => (int)$month]);
$movimientos = $stmt->fetchAll();

$ingresos = 0; $egresos = 0;
foreach ($movimientos as $mov) {
    $mov['tipo'] === 'ingreso' ? $ingresos += $mov['monto'] : $egresos += $mov['monto'];
}
$balance = $ingresos - $egresos;

$mensaje       = $_SESSION['mensaje']       ?? '';
$mensaje_error = $_SESSION['mensaje_error'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['mensaje_error']);

$mascotas_lista = $pdo->query("SELECT id, nombre, identificador FROM mascotas ORDER BY nombre")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-3">
    <div>
        <h3>Finanzas</h3>
        <p>Control de ingresos y egresos del consultorio.</p>
    </div>
    <?php if ($_SESSION['user_rol'] !== 'dueno'): ?>
    <button class="btn btn-primary px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#nuevoModal">
        <i class="bi bi-plus-circle me-2"></i>Nuevo Movimiento
    </button>
    <?php endif; ?>
</div>

<?php if ($mensaje): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle-fill me-2"></i><?= e($mensaje) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($mensaje_error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($mensaje_error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- KPI Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="kpi-card kpi-success">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Ingresos</div>
                    <div class="kpi-value">$<?= number_format($ingresos, 0, ',', '.') ?></div>
                    <div class="kpi-sub"><?= strftime('%B %Y', mktime(0,0,0,(int)$month,1,(int)$year)) ?></div>
                </div>
                <div class="icon-circle bg-success bg-opacity-10 text-success">
                    <i class="bi bi-arrow-down-circle-fill"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kpi-card kpi-danger">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Egresos</div>
                    <div class="kpi-value text-danger">$<?= number_format($egresos, 0, ',', '.') ?></div>
                    <div class="kpi-sub"><?= strftime('%B %Y', mktime(0,0,0,(int)$month,1,(int)$year)) ?></div>
                </div>
                <div class="icon-circle bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-arrow-up-circle-fill"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kpi-card <?= $balance >= 0 ? 'kpi-primary' : 'kpi-warning' ?>">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Balance</div>
                    <div class="kpi-value <?= $balance < 0 ? 'text-danger' : '' ?>">
                        <?= $balance < 0 ? '-' : '' ?>$<?= number_format(abs($balance), 0, ',', '.') ?>
                    </div>
                    <div class="kpi-sub"><?= $balance >= 0 ? 'Saldo positivo' : 'Saldo negativo' ?></div>
                </div>
                <div class="icon-circle bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-wallet2"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtro mes -->
<div class="card card-stat mb-3">
    <div class="card-body py-3 px-4">
        <form method="GET" class="d-flex align-items-center gap-3 flex-wrap">
            <label class="form-label mb-0 fw-semibold text-muted small">MES:</label>
            <input type="month" name="mes" class="form-control form-control-sm w-auto"
                   value="<?= e($mes_filtro) ?>">
            <button type="submit" class="btn btn-sm btn-primary px-3">Filtrar</button>
            <a href="caja.php" class="btn btn-sm btn-light px-3">Este mes</a>
        </form>
    </div>
</div>

<!-- Tabla -->
<div class="card card-stat">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tablaCaja" class="table table-hover align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th class="ps-4">Fecha</th>
                        <th>Tipo</th>
                        <th>Concepto</th>
                        <th>Mascota</th>
                        <th class="text-end pe-4">Monto</th>
                        <?php if ($_SESSION['user_rol'] !== 'dueno'): ?>
                        <th class="text-center">Acción</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movimientos as $mov): ?>
                    <tr>
                        <td class="ps-4 text-muted small">
                            <?= e(date('d/m/Y', strtotime($mov['fecha']))) ?>
                        </td>
                        <td>
                            <?php if ($mov['tipo'] === 'ingreso'): ?>
                            <span class="badge bg-success bg-opacity-15 text-success fw-semibold px-3 py-1" style="border-radius:20px;">
                                <i class="bi bi-arrow-down me-1"></i>Ingreso
                            </span>
                            <?php else: ?>
                            <span class="badge bg-danger bg-opacity-15 text-danger fw-semibold px-3 py-1" style="border-radius:20px;">
                                <i class="bi bi-arrow-up me-1"></i>Egreso
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-medium"><?= e($mov['concepto']) ?></td>
                        <td class="text-muted small">
                            <?php if ($mov['mascota_nombre']): ?>
                            <code class="bg-light px-2 py-1 rounded" style="font-size:0.75rem;"><?= e($mov['identificador']) ?></code>
                            <?= e($mov['mascota_nombre']) ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="text-end pe-4 fw-bold <?= $mov['tipo'] === 'ingreso' ? 'text-success' : 'text-danger' ?>">
                            <?= $mov['tipo'] === 'ingreso' ? '+' : '-' ?>$<?= number_format($mov['monto'], 0, ',', '.') ?>
                        </td>
                        <?php if ($_SESSION['user_rol'] !== 'dueno'): ?>
                        <td class="text-center">
                            <form method="POST" action="procesar_caja.php" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar este movimiento?')">
                                <input type="hidden" name="action" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int)$mov['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nuevo Movimiento -->
<?php if ($_SESSION['user_rol'] !== 'dueno'): ?>
<div class="modal fade" id="nuevoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-plus-circle text-primary me-2"></i>Nuevo Movimiento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="procesar_caja.php" novalidate>
                <input type="hidden" name="action" value="crear">
                <div class="modal-body px-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select" required>
                                <option value="ingreso">💰 Ingreso</option>
                                <option value="egreso">💸 Egreso</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Fecha</label>
                            <input type="date" name="fecha" class="form-control"
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Concepto</label>
                            <select name="concepto" id="selectConcepto" class="form-select" required>
                            </select>
                            <div class="invalid-feedback">Seleccione un concepto.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Monto</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="monto" class="form-control"
                                       placeholder="0" min="1" step="any" required>
                                <div class="invalid-feedback">Ingrese un monto válido.</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">
                                Mascota <span class="text-muted small">(opcional)</span>
                            </label>
                            <select name="mascota_id" class="form-select">
                                <option value="">— Sin asociar —</option>
                                <?php foreach ($mascotas_lista as $m): ?>
                                <option value="<?= (int)$m['id'] ?>">
                                    <?= e($m['identificador']) ?> — <?= e($m['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-2">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm">
                        <i class="bi bi-save me-1"></i>Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
var conceptos = {
    ingreso: [
        'Consulta general',
        'Consulta de urgencia',
        'Vacunación',
        'Cirugía',
        'Desparasitación',
        'Baño y peluquería',
        'Hospitalización',
        'Examen de laboratorio',
        'Radiografía / Ecografía',
        'Venta de medicamentos',
        'Venta de alimentos',
        'Otro'
    ],
    egreso: [
        'Insumos médicos',
        'Compra de medicamentos',
        'Compra de alimentos',
        'Servicios (agua, luz, internet)',
        'Arriendo / Renta',
        'Sueldos / Salarios',
        'Equipos y herramientas',
        'Mantenimiento',
        'Publicidad',
        'Otro'
    ]
};

function actualizarConceptos(tipo) {
    var select = document.getElementById('selectConcepto');
    var lista  = conceptos[tipo] || [];
    select.innerHTML = '';
    lista.forEach(function(c) {
        var opt = document.createElement('option');
        opt.value = c;
        opt.textContent = c;
        select.appendChild(opt);
    });
}

var selectTipo = document.querySelector('[name="tipo"]');
if (selectTipo) {
    selectTipo.addEventListener('change', function() { actualizarConceptos(this.value); });
    actualizarConceptos(selectTipo.value);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
