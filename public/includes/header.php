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
            <span>VetClinic</span>
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
            <?php if ($_SESSION['user_rol'] !== 'dueno'): ?>
            <a href="registrar_mascota.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'registrar_mascota.php' ? 'active' : '' ?>">
                <i class="bi bi-plus-circle"></i> Nuevo Registro
            </a>
            <?php endif; ?>

            <?php if ($_SESSION['user_rol'] === 'admin' || $_SESSION['user_rol'] === 'veterinario'): ?>
            <a href="historial.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'historial.php' ? 'active' : '' ?>">
                <i class="bi bi-journal-medical"></i> Historial Clínico
            </a>
            <?php endif; ?>

            <?php if ($_SESSION['user_rol'] === 'admin'): ?>
            <span class="sidebar-section-label" style="margin-top:0.5rem;">Administración</span>
            <a href="empleados.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'empleados.php' ? 'active' : '' ?>">
                <i class="bi bi-people-gear"></i> Gestión Empleados
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
