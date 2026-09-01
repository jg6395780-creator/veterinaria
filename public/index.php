<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/modulos_clinica.php';

$pdo = getDB();
asegurarModulosClinica($pdo);
$rolActual = $_SESSION['user_rol'] ?? '';
$usuarioActual = (int)($_SESSION['user_id'] ?? 0);
$totalMascotas = $pdo->query("SELECT COUNT(*) FROM mascotas")->fetchColumn();
$totalDuenos   = $pdo->query("SELECT COUNT(*) FROM duenos")->fetchColumn();

$stmtEspecies = $pdo->query("SELECT especie, COUNT(*) as total FROM mascotas GROUP BY especie ORDER BY total DESC");
$especies = $stmtEspecies->fetchAll();

$coloresEspecies = ['#3b82f6','#22c55e','#f59e0b','#ef4444','#06b6d4','#8b5cf6'];
$segmentosGrafico = [];
$datosGraficoEspecies = [];
$acumuladoGrafico = 0;
foreach ($especies as $indice => $especie) {
    $porcentajeExacto = $totalMascotas > 0 ? ((int)$especie['total'] / (int)$totalMascotas) * 100 : 0;
    $finSegmento = $acumuladoGrafico + $porcentajeExacto;
    $colorSegmento = $coloresEspecies[$indice % count($coloresEspecies)];
    $segmentosGrafico[] = $colorSegmento . ' ' . round($acumuladoGrafico, 2) . '% ' . round($finSegmento, 2) . '%';
    $anguloMedio = (($acumuladoGrafico + $finSegmento) / 2) * 3.6;
    $datosGraficoEspecies[] = [
        'nombre' => $especie['especie'],
        'total' => (int)$especie['total'],
        'porcentaje' => round($porcentajeExacto),
        'color' => $colorSegmento,
        'etiqueta_x' => 50 + sin(deg2rad($anguloMedio)) * 37,
        'etiqueta_y' => 50 - cos(deg2rad($anguloMedio)) * 37,
    ];
    $acumuladoGrafico = $finSegmento;
}
$fondoGrafico = $segmentosGrafico ? 'conic-gradient(' . implode(', ', $segmentosGrafico) . ')' : '#e9ecef';

$ingresosMes = 0;
$egresosMes = 0;
if (($_SESSION['user_rol'] ?? '') === 'admin') {
    try {
        if ($pdo->query("SHOW TABLES LIKE 'caja'")->fetchColumn()) {
            $inicioMes = date('Y-m-01');
            $inicioMesSiguiente = date('Y-m-01', strtotime('+1 month'));
            $stmtFinanzas = $pdo->prepare("SELECT
                COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) AS ingresos,
                COALESCE(SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END), 0) AS egresos
                FROM caja WHERE fecha >= :inicio AND fecha < :fin");
            $stmtFinanzas->execute([':inicio' => $inicioMes, ':fin' => $inicioMesSiguiente]);
            $resumenFinanzas = $stmtFinanzas->fetch();
            $ingresosMes = (float)$resumenFinanzas['ingresos'];
            $egresosMes = (float)$resumenFinanzas['egresos'];
        }
    } catch (PDOException $e) {
        error_log('Dashboard finanzas: ' . $e->getMessage());
    }
}
$totalFlujoMes = $ingresosMes + $egresosMes;
$balanceMes = $ingresosMes - $egresosMes;
$porcentajeIngresos = $totalFlujoMes > 0 ? round(($ingresosMes / $totalFlujoMes) * 100) : 0;
$porcentajeEgresos = $totalFlujoMes > 0 ? 100 - $porcentajeIngresos : 0;
$fondoFinanzas = $totalFlujoMes > 0
    ? "conic-gradient(#22c55e 0% {$porcentajeIngresos}%, #ef4444 {$porcentajeIngresos}% 100%)"
    : '#e9ecef';

$sqlCitasHoy = "SELECT c.*, m.nombre AS mascota, d.nombre AS dueno, u.nombre_completo AS veterinario
    FROM citas c JOIN mascotas m ON m.id=c.mascota_id JOIN duenos d ON d.id=c.dueno_id
    LEFT JOIN usuarios u ON u.id=c.veterinario_id WHERE DATE(c.fecha_hora)=CURDATE()";
$paramsCitas = [];
if ($rolActual === 'veterinario') { $sqlCitasHoy .= ' AND (c.veterinario_id=:usuario OR c.veterinario_id IS NULL)'; $paramsCitas[':usuario']=$usuarioActual; }
$sqlCitasHoy .= " AND c.estado NOT IN ('cancelada','no_asistio') ORDER BY c.fecha_hora LIMIT 6";
$stmtCitasHoy=$pdo->prepare($sqlCitasHoy);$stmtCitasHoy->execute($paramsCitas);$citasHoy=$stmtCitasHoy->fetchAll();
$citasSolicitadas=(int)$pdo->query("SELECT COUNT(*) FROM citas WHERE estado='solicitada'")->fetchColumn();
$hospitalizadosActivos=(int)$pdo->query("SELECT COUNT(*) FROM hospitalizaciones WHERE estado='hospitalizado'")->fetchColumn();
$stockBajo=(int)$pdo->query("SELECT COUNT(*) FROM inventario WHERE activo=1 AND stock<=stock_minimo")->fetchColumn();
$kpiOperacionValor = $rolActual === 'recepcion' ? $citasSolicitadas : count($citasHoy);
$kpiOperacionLabel = $rolActual === 'recepcion' ? 'Solicitudes' : 'Citas hoy';
$kpiOperacionSub = $rolActual === 'recepcion' ? 'Esperan confirmación' : 'Agenda activa';

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h3>Dashboard</h3>
    <p>Bienvenido, <?= e($_SESSION['user_name'] ?? 'Usuario') ?>. Aquí tienes el resumen del sistema.</p>
</div>

<!-- KPI Cards -->
<div class="row g-4 mb-4">
    <div class="col-6 col-xl-3">
        <div class="kpi-card kpi-primary">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Total Mascotas</div>
                    <div class="kpi-value"><?= e($totalMascotas) ?></div>
                    <div class="kpi-sub">Pacientes registrados</div>
                </div>
                <div class="icon-circle bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-clipboard2-pulse"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi-card kpi-success">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Total Dueños</div>
                    <div class="kpi-value"><?= e($totalDuenos) ?></div>
                    <div class="kpi-sub">Clientes activos</div>
                </div>
                <div class="icon-circle bg-success bg-opacity-10 text-success">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi-card kpi-info">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Especies</div>
                    <div class="kpi-value"><?= count($especies) ?></div>
                    <div class="kpi-sub">Tipos registrados</div>
                </div>
                <div class="icon-circle bg-info bg-opacity-10 text-info">
                    <i class="bi bi-tags-fill"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi-card kpi-warning">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label"><?= e($kpiOperacionLabel) ?></div>
                    <div class="kpi-value"><?= (int)$kpiOperacionValor ?></div>
                    <div class="kpi-sub"><?= e($kpiOperacionSub) ?></div>
                </div>
                <div class="icon-circle bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts & Quick Actions -->
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card card-stat h-100">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-pie-chart-fill text-primary me-2"></i>Población por Especie
                </h6>
            </div>
            <div class="card-body px-4 pb-4 d-flex align-items-center">
                <?php if (empty($especies)): ?>
                <div class="empty-state">
                    <i class="bi bi-bar-chart"></i>
                    <p>Sin datos para mostrar aún.</p>
                </div>
                <?php else: ?>
                <div class="row g-4 align-items-center w-100" style="min-height:290px;">
                    <div class="col-sm-5">
                        <div class="d-grid gap-3 ps-sm-3">
                            <?php foreach ($datosGraficoEspecies as $dato): ?>
                            <div class="d-flex align-items-center justify-content-between gap-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="rounded-circle flex-shrink-0" style="width:11px;height:11px;background:<?= e($dato['color']) ?>;"></span>
                                    <span class="text-dark fw-medium"><?= e($dato['nombre']) ?></span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-muted small"><?= $dato['total'] ?></span>
                                    <strong style="min-width:38px;text-align:right;"><?= $dato['porcentaje'] ?>%</strong>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-sm-7 d-flex justify-content-center">
                        <div class="position-relative rounded-circle shadow-sm"
                             role="img" aria-label="Distribución de mascotas por especie"
                             style="width:min(270px,70vw);aspect-ratio:1;background:<?= e($fondoGrafico) ?>;">
                            <?php foreach ($datosGraficoEspecies as $dato): ?>
                            <span class="position-absolute fw-bold text-white"
                                  style="left:<?= round($dato['etiqueta_x'], 2) ?>%;top:<?= round($dato['etiqueta_y'], 2) ?>%;transform:translate(-50%,-50%);z-index:2;font-size:0.76rem;text-shadow:0 1px 2px rgba(0,0,0,.28);">
                                <?= $dato['porcentaje'] ?>%
                            </span>
                            <?php endforeach; ?>
                            <div class="position-absolute top-50 start-50 translate-middle rounded-circle bg-white d-flex flex-column align-items-center justify-content-center shadow-sm"
                                 style="width:54%;height:54%;z-index:3;">
                                <span class="fw-bold text-dark" style="font-size:2.25rem;line-height:1;"><?= (int)$totalMascotas ?></span>
                                <span class="text-muted small mt-2">Total</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <?php if (($_SESSION['user_rol'] ?? '') === 'admin'): ?>
        <div class="card card-stat h-100">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-cash-coin text-success me-2"></i>Finanzas del Mes
                </h6>
            </div>
            <div class="card-body px-4 pb-4 d-flex align-items-center">
                <div class="row g-4 align-items-center w-100" style="min-height:290px;">
                    <div class="col-sm-6 order-2 order-sm-1">
                        <div class="d-grid gap-3">
                            <div class="d-flex align-items-center justify-content-between border rounded-3 p-3 bg-success bg-opacity-10">
                                <div>
                                    <div class="small text-muted"><span class="d-inline-block rounded-circle bg-success me-2" style="width:9px;height:9px;"></span>Ingresos</div>
                                    <strong class="text-success">$<?= number_format($ingresosMes, 0, ',', '.') ?></strong>
                                </div>
                                <span class="fw-bold text-success"><?= $porcentajeIngresos ?>%</span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between border rounded-3 p-3 bg-danger bg-opacity-10">
                                <div>
                                    <div class="small text-muted"><span class="d-inline-block rounded-circle bg-danger me-2" style="width:9px;height:9px;"></span>Egresos</div>
                                    <strong class="text-danger">$<?= number_format($egresosMes, 0, ',', '.') ?></strong>
                                </div>
                                <span class="fw-bold text-danger"><?= $porcentajeEgresos ?>%</span>
                            </div>
                            <div class="border rounded-3 p-3 d-flex justify-content-between align-items-center">
                                <span class="text-muted small">Balance actual</span>
                                <strong class="<?= $balanceMes >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= $balanceMes < 0 ? '-' : '' ?>$<?= number_format(abs($balanceMes), 0, ',', '.') ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 order-1 order-sm-2 d-flex justify-content-center">
                        <div class="position-relative rounded-circle shadow-sm"
                             role="img" aria-label="Distribución mensual de ingresos y egresos"
                             style="width:min(230px,65vw);aspect-ratio:1;background:<?= e($fondoFinanzas) ?>;">
                            <div class="position-absolute top-50 start-50 translate-middle rounded-circle bg-white d-flex flex-column align-items-center justify-content-center shadow-sm text-center"
                                 style="width:58%;height:58%;padding:10px;">
                                <span class="fw-bold text-dark" style="font-size:1.25rem;line-height:1.1;">$<?= number_format($totalFlujoMes, 0, ',', '.') ?></span>
                                <span class="text-muted small mt-1">Total del mes</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white border-0 px-4 pb-4 pt-0 text-end">
                <a href="caja.php" class="btn btn-sm btn-outline-success px-3">Ver todas las finanzas <i class="bi bi-arrow-right ms-1"></i></a>
            </div>
        </div>
        <?php else: ?>
        <div class="card card-stat h-100">
            <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="bi bi-calendar2-check text-primary me-2"></i><?= $rolActual === 'veterinario' ? 'Mis pacientes de hoy' : 'Operación de hoy' ?></h6>
                <a href="agenda.php" class="btn btn-sm btn-outline-primary">Abrir agenda</a>
            </div>
            <div class="card-body px-4 pb-4">
                <?php if ($rolActual === 'recepcion' && $citasSolicitadas): ?><div class="alert-strip mb-3"><strong><?= $citasSolicitadas ?></strong> solicitudes de hora esperan confirmación.</div><?php endif; ?>
                <?php if (!$citasHoy): ?><div class="empty-state py-4"><i class="bi bi-calendar-check"></i><p>No hay citas activas para hoy.</p></div><?php endif; ?>
                <?php foreach ($citasHoy as $cita): ?><div class="dashboard-task"><div class="dashboard-task-icon"><strong><?= e(date('H:i', strtotime($cita['fecha_hora']))) ?></strong></div><div class="flex-grow-1"><strong><?= e($cita['mascota']) ?></strong><div class="small text-muted"><?= e($cita['motivo']) ?> · <?= e($cita['dueno']) ?></div></div><span class="badge text-bg-primary"><?= e(str_replace('_',' ',$cita['estado'])) ?></span></div><?php endforeach; ?>
                <?php if ($rolActual === 'veterinario' && $hospitalizadosActivos): ?><a href="hospitalizacion.php" class="alert-strip d-block text-decoration-none mt-3"><i class="bi bi-hospital me-2"></i><?= $hospitalizadosActivos ?> pacientes hospitalizados requieren seguimiento.</a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Accesos rápidos -->
<div class="card card-stat mt-4">
    <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Accesos Rápidos</h6>
    </div>
    <div class="card-body px-4 pb-4">
        <div class="row g-3">
            <div class="col-md-6 col-xl-3">
                <a href="agenda.php" class="btn btn-outline-primary w-100 h-100 d-flex align-items-center gap-3 px-3 py-3 text-start">
                    <div class="icon-circle bg-primary bg-opacity-10 text-primary flex-shrink-0" style="width:42px;height:42px;border-radius:10px;"><i class="bi bi-calendar2-week"></i></div>
                    <div><div class="fw-semibold">Agenda</div><div class="text-muted small"><?= count($citasHoy) ?> citas activas hoy</div></div>
                </a>
            </div>
            <div class="col-md-6 col-xl-3">
                <a href="registrar_mascota.php" class="btn btn-outline-primary w-100 h-100 d-flex align-items-center gap-3 px-3 py-3 text-start">
                    <div class="icon-circle bg-primary bg-opacity-10 text-primary flex-shrink-0" style="width:42px;height:42px;border-radius:10px;"><i class="bi bi-plus-circle-fill"></i></div>
                    <div><div class="fw-semibold">Nueva Mascota</div><div class="text-muted small">Registrar paciente</div></div>
                </a>
            </div>
            <div class="col-md-6 col-xl-3">
                <a href="mascotas.php" class="btn btn-outline-success w-100 h-100 d-flex align-items-center gap-3 px-3 py-3 text-start">
                    <div class="icon-circle bg-success bg-opacity-10 text-success flex-shrink-0" style="width:42px;height:42px;border-radius:10px;"><i class="bi bi-search"></i></div>
                    <div><div class="fw-semibold">Ver Mascotas</div><div class="text-muted small">Lista de pacientes</div></div>
                </a>
            </div>
            <?php if (in_array($_SESSION['user_rol'], ['admin', 'recepcion'], true)): ?>
            <div class="col-md-6 col-xl-3">
                <a href="caja.php" class="btn btn-outline-warning w-100 h-100 d-flex align-items-center gap-3 px-3 py-3 text-start">
                    <div class="icon-circle bg-warning bg-opacity-10 text-warning flex-shrink-0" style="width:42px;height:42px;border-radius:10px;"><i class="bi bi-cash-stack"></i></div>
                    <div><div class="fw-semibold">Finanzas</div><div class="text-muted small">Ingresos y egresos</div></div>
                </a>
            </div>
            <?php endif; ?>
            <?php if ($_SESSION['user_rol'] === 'admin'): ?>
            <div class="col-md-6 col-xl-3">
                <a href="inventario.php" class="btn btn-outline-danger w-100 h-100 d-flex align-items-center gap-3 px-3 py-3 text-start">
                    <div class="icon-circle bg-danger bg-opacity-10 text-danger flex-shrink-0" style="width:42px;height:42px;border-radius:10px;"><i class="bi bi-box-seam"></i></div>
                    <div><div class="fw-semibold">Inventario</div><div class="text-muted small"><?= $stockBajo ?> alertas de stock</div></div>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
