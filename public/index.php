<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';

$pdo = getDB();
$totalMascotas = $pdo->query("SELECT COUNT(*) FROM mascotas")->fetchColumn();
$totalDuenos   = $pdo->query("SELECT COUNT(*) FROM duenos")->fetchColumn();

$stmtEspecies = $pdo->query("SELECT especie, COUNT(*) as total FROM mascotas GROUP BY especie ORDER BY total DESC");
$especies = $stmtEspecies->fetchAll();

$labelsEspecies = [];
$dataEspecies   = [];
foreach ($especies as $row) {
    $labelsEspecies[] = e($row['especie']);
    $dataEspecies[]   = $row['total'];
}

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
                    <div class="kpi-label">Promedio</div>
                    <div class="kpi-value"><?= $totalDuenos > 0 ? number_format($totalMascotas / $totalDuenos, 1) : '0' ?></div>
                    <div class="kpi-sub">Mascotas / dueño</div>
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
            <div class="card-body px-4 pb-4">
                <?php if (empty($especies)): ?>
                <div class="empty-state">
                    <i class="bi bi-bar-chart"></i>
                    <p>Sin datos para mostrar aún.</p>
                </div>
                <?php else: ?>
                <canvas id="chartEspecies" style="max-height:240px;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-stat h-100">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-lightning-charge-fill text-warning me-2"></i>Accesos Rápidos
                </h6>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="d-grid gap-2">
                    <a href="registrar_mascota.php" class="btn btn-outline-primary d-flex align-items-center gap-3 px-4 py-3 text-start">
                        <div class="icon-circle bg-primary bg-opacity-10 text-primary" style="width:40px;height:40px;font-size:1rem;border-radius:9px;flex-shrink:0;">
                            <i class="bi bi-plus-circle-fill"></i>
                        </div>
                        <div>
                            <div class="fw-semibold" style="font-size:0.875rem;">Nueva Mascota</div>
                            <div class="text-muted" style="font-size:0.78rem;">Registrar paciente y dueño</div>
                        </div>
                    </a>
                    <a href="mascotas.php" class="btn btn-outline-success d-flex align-items-center gap-3 px-4 py-3 text-start">
                        <div class="icon-circle bg-success bg-opacity-10 text-success" style="width:40px;height:40px;font-size:1rem;border-radius:9px;flex-shrink:0;">
                            <i class="bi bi-search"></i>
                        </div>
                        <div>
                            <div class="fw-semibold" style="font-size:0.875rem;">Ver Mascotas</div>
                            <div class="text-muted" style="font-size:0.78rem;">Lista completa de pacientes</div>
                        </div>
                    </a>
                    <?php if ($_SESSION['user_rol'] === 'admin'): ?>
                    <a href="empleados.php" class="btn btn-outline-danger d-flex align-items-center gap-3 px-4 py-3 text-start">
                        <div class="icon-circle bg-danger bg-opacity-10 text-danger" style="width:40px;height:40px;font-size:1rem;border-radius:9px;flex-shrink:0;">
                            <i class="bi bi-people-gear"></i>
                        </div>
                        <div>
                            <div class="fw-semibold" style="font-size:0.875rem;">Gestión Empleados</div>
                            <div class="text-muted" style="font-size:0.78rem;">Administrar accesos al sistema</div>
                        </div>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($especies)): ?>
<script>
const ctx = document.getElementById('chartEspecies').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($labelsEspecies) ?>,
        datasets: [{
            data: <?= json_encode($dataEspecies) ?>,
            backgroundColor: ['#3b82f6','#22c55e','#f59e0b','#ef4444','#06b6d4','#8b5cf6'],
            borderWidth: 0,
            hoverOffset: 8
        }]
    },
    options: {
        cutout: '62%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 16,
                    font: { family: 'Inter', size: 12 },
                    boxWidth: 12,
                    usePointStyle: true
                }
            }
        }
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
