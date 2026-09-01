<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/rut.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: caja.php");
    exit;
}

$action = trim($_POST['action'] ?? '');
$pdo    = getDB();
$rol    = $_SESSION['user_rol'] ?? '';

if (!in_array($rol, ['admin', 'recepcion'], true)) {
    header('Location: index.php');
    exit;
}

if ($action === 'crear') {
    $tipo       = $_POST['tipo']     ?? '';
    $concepto   = trim($_POST['concepto'] ?? '');
    $montoTexto = str_replace(['.', ' '], '', trim((string)($_POST['monto'] ?? '0')));
    $montoTexto = str_replace(',', '.', $montoTexto);
    $monto      = (float)$montoTexto;
    $fecha      = $_POST['fecha']    ?? date('Y-m-d');
    $mascota_id = (int)($_POST['mascota_id'] ?? 0) ?: null;
    $documento  = $_POST['documento'] ?? '';
    $medioPago  = $_POST['medio_pago'] ?? '';
    $facturaRut = normalizarRut(trim($_POST['factura_rut'] ?? ''));
    $facturaRazonSocial = trim($_POST['factura_razon_social'] ?? '');
    $facturaGiro = trim($_POST['factura_giro'] ?? '');
    $facturaDireccion = trim($_POST['factura_direccion'] ?? '');
    $facturaComuna = trim($_POST['factura_comuna'] ?? '');
    $facturaEmail = trim($_POST['factura_email'] ?? '');

    if ($rol === 'recepcion') $tipo = 'ingreso';

    if (!in_array($tipo, ['ingreso', 'egreso'], true) || !in_array($documento, ['boleta', 'factura'], true)
        || !in_array($medioPago, ['efectivo', 'debito', 'credito', 'cheque'], true) || !$concepto || $monto <= 0) {
        $_SESSION['mensaje_error'] = "Complete todos los campos requeridos.";
        header("Location: caja.php");
        exit;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $fecha = date('Y-m-d');

    if ($documento === 'factura') {
        if (!validarRut($facturaRut) || !$facturaRazonSocial || !$facturaGiro || !$facturaDireccion || !$facturaComuna
            || ($facturaEmail !== '' && !filter_var($facturaEmail, FILTER_VALIDATE_EMAIL))) {
            $_SESSION['mensaje_error'] = 'Complete correctamente los datos requeridos de la factura.';
            header('Location: caja.php');
            exit;
        }
    } else {
        $facturaRut = $facturaRazonSocial = $facturaGiro = $facturaDireccion = $facturaComuna = $facturaEmail = null;
    }

    $pdo->prepare("INSERT INTO caja (tipo, concepto, monto, documento, medio_pago, mascota_id, fecha, factura_rut, factura_razon_social, factura_giro, factura_direccion, factura_comuna, factura_email) VALUES (:tipo, :concepto, :monto, :documento, :medio_pago, :mascota_id, :fecha, :factura_rut, :factura_razon_social, :factura_giro, :factura_direccion, :factura_comuna, :factura_email)")
        ->execute([
            ':tipo'       => $tipo,
            ':concepto'   => $concepto,
            ':monto'      => $monto,
            ':documento'  => $documento,
            ':medio_pago' => $medioPago,
            ':mascota_id' => $mascota_id,
            ':fecha'      => $fecha,
            ':factura_rut' => $facturaRut,
            ':factura_razon_social' => $facturaRazonSocial,
            ':factura_giro' => $facturaGiro,
            ':factura_direccion' => $facturaDireccion,
            ':factura_comuna' => $facturaComuna,
            ':factura_email' => $facturaEmail,
        ]);

    $_SESSION['mensaje'] = "Movimiento registrado correctamente.";
    header("Location: caja.php");
    exit;

} elseif ($action === 'eliminar') {
    if ($rol !== 'admin') {
        $_SESSION['mensaje_error'] = 'No tiene permiso para eliminar movimientos.';
        header('Location: caja.php');
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $pdo->prepare("DELETE FROM caja WHERE id = :id")->execute([':id' => $id]);
        $_SESSION['mensaje'] = "Movimiento eliminado.";
    }
    header("Location: caja.php");
    exit;
}

header("Location: caja.php");
exit;
