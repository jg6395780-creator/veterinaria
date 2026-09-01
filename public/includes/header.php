<?php
$nombreClinicaHeader = 'VetClinic';
try {
    $dbHeader = isset($pdo) && $pdo instanceof PDO ? $pdo : getDB();
    if ($dbHeader->query("SHOW TABLES LIKE 'configuracion_clinica'")->fetchColumn()) {
        $qHeader = $dbHeader->prepare("SELECT valor FROM configuracion_clinica WHERE clave='nombre_clinica' LIMIT 1");
        $qHeader->execute();
        $nombreConfigurado = trim((string)$qHeader->fetchColumn());
        if ($nombreConfigurado !== '') $nombreClinicaHeader = $nombreConfigurado;
    }
} catch (Throwable $e) { error_log('Configuración de cabecera: ' . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'VetClinic Pro' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="assets/css/estilos.css" rel="stylesheet">
</head>
<body>

<div class="d-flex" id="wrapper">

    <!-- Sidebar -->
    <div id="sidebar-wrapper">
        <div class="sidebar-heading d-flex align-items-center gap-2">
            <i class="bi bi-heart-pulse-fill text-danger"></i>
            <span><?= e($nombreClinicaHeader) ?></span>
            <span class="badge bg-primary ms-auto" style="font-size:0.6rem;border-radius:6px;">PRO</span>
        </div>
        <div class="pt-2 pb-3">
            <span class="sidebar-section-label">Principal</span>
            <a href="index.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="mascotas.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'mascotas.php' ? 'active' : '' ?>">
                <i class="bi bi-clipboard2-pulse"></i> Mascotas
            </a>
            <a href="agenda.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'agenda.php' ? 'active' : '' ?>">
                <i class="bi bi-calendar2-week"></i> Agenda
            </a>
            <?php if ($_SESSION['user_rol'] !== 'dueno'): ?>
            <a href="registrar_mascota.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'registrar_mascota.php' ? 'active' : '' ?>">
                <i class="bi bi-plus-circle"></i> Nuevo Registro
            </a>
            <?php endif; ?>

            <?php if ($_SESSION['user_rol'] === 'admin' || $_SESSION['user_rol'] === 'veterinario'): ?>
            <a href="historial.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'historial.php' ? 'active' : '' ?>">
                <i class="bi bi-journal-medical"></i> Historial Clínico
            </a>
            <a href="recetas.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'recetas.php' ? 'active' : '' ?>">
                <i class="bi bi-prescription2"></i> Recetas
            </a>
            <a href="hospitalizacion.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'hospitalizacion.php' ? 'active' : '' ?>">
                <i class="bi bi-hospital"></i> Hospitalización
            </a>
            <?php endif; ?>

            <?php if (in_array($_SESSION['user_rol'] ?? '', ['admin', 'recepcion', 'veterinario'], true)): ?>
            <a href="urgencias.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'urgencias.php' ? 'active' : '' ?>">
                <i class="bi bi-exclamation-triangle-fill"></i> Urgencias
                <span id="urgenciaSidebarBadge" class="badge text-bg-danger ms-auto d-none">0</span>
            </a>
            <?php endif; ?>

            <?php if ($_SESSION['user_rol'] === 'admin' || $_SESSION['user_rol'] === 'recepcion'): ?>
            <a href="caja.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'caja.php' ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i> Finanzas
            </a>
            <a href="presupuestos.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'presupuestos.php' ? 'active' : '' ?>">
                <i class="bi bi-receipt-cutoff"></i> Presupuestos
            </a>
            <?php endif; ?>

            <?php if ($_SESSION['user_rol'] === 'admin'): ?>
            <span class="sidebar-section-label" style="margin-top:0.5rem;">Administración</span>
            <a href="empleados.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'empleados.php' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> Gestión Empleados
            </a>
            <a href="inventario.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'inventario.php' ? 'active' : '' ?>">
                <i class="bi bi-box-seam"></i> Inventario
            </a>
            <a href="reportes.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'reportes.php' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-line"></i> Reportes
            </a>
            <a href="auditoria.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'auditoria.php' ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i> Auditoría
            </a>
            <a href="configuracion.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'configuracion.php' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i> Configuración
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Page Content -->
    <div id="page-content-wrapper">

        <!-- Top Navigation -->
        <nav class="navbar top-navbar d-flex justify-content-between align-items-center">
            <button class="btn btn-sm btn-light border me-2" id="menu-toggle" title="Menú">
                <i class="bi bi-list fs-5"></i>
            </button>

            <div class="d-flex align-items-center gap-3">
                <?php if (in_array($_SESSION['user_rol'] ?? '', ['admin', 'recepcion', 'veterinario'], true)): ?>
                <a href="urgencias.php" class="btn btn-sm btn-outline-danger position-relative" title="Urgencias activas">
                    <i class="bi bi-bell-fill"></i>
                    <span id="urgenciaTopBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger d-none">0</span>
                </a>
                <?php endif; ?>
                <a href="notificaciones.php" class="btn btn-sm btn-outline-primary" title="Notificaciones"><i class="bi bi-inbox-fill"></i></a>
                <button type="button" class="btn btn-sm btn-light border" id="theme-toggle" title="Cambiar tema"><i class="bi bi-moon-stars"></i></button>
                <div class="text-end d-none d-sm-block">
                    <span class="d-block fw-semibold text-dark" style="font-size:0.88rem;line-height:1.3;">
                        <?= e($_SESSION['user_name'] ?? 'Usuario') ?>
                    </span>
                    <span class="badge bg-primary bg-opacity-10 text-primary" style="font-size:0.7rem;">
                        <?= e(ucfirst($_SESSION['user_rol'] ?? 'Invitado')) ?>
                    </span>
                </div>
                <div class="avatar bg-primary bg-opacity-10 text-primary fw-bold"
                     style="width:36px;height:36px;border-radius:10px;font-size:0.9rem;">
                    <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
                </div>
                <a href="logout.php" class="btn btn-sm btn-outline-danger px-3" title="Cerrar Sesión">
                    <i class="bi bi-box-arrow-right me-1"></i>
                    <span class="d-none d-sm-inline">Salir</span>
                </a>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="container-fluid px-4 py-4">

<?php if (in_array($_SESSION['user_rol'] ?? '', ['admin', 'recepcion', 'veterinario'], true)): ?>
<div id="urgenciaLiveAlert" class="urgencia-live-alert d-none" role="alert" aria-live="assertive">
    <div class="urgencia-live-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
    <div class="flex-grow-1">
        <strong>Nueva atención de urgencia</strong>
        <div id="urgenciaLiveText" class="small">Revisa la solicitud recibida.</div>
    </div>
    <a href="urgencias.php" class="btn btn-sm btn-light">Ver urgencia</a>
    <button type="button" class="btn-close btn-close-white" aria-label="Cerrar" onclick="this.parentElement.classList.add('d-none')"></button>
</div>
<?php endif; ?>
