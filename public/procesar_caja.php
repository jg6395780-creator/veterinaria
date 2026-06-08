<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: caja.php");
    exit;
}

$action = trim($_POST['action'] ?? '');
$pdo    = getDB();

if ($action === 'crear') {
    $tipo       = $_POST['tipo']     ?? '';
    $concepto   = trim($_POST['concepto'] ?? '');
    $monto      = (float)($_POST['monto'] ?? 0);
    $fecha      = $_POST['fecha']    ?? date('Y-m-d');
    $mascota_id = (int)($_POST['mascota_id'] ?? 0) ?: null;

    if (!in_array($tipo, ['ingreso', 'egreso']) || !$concepto || $monto <= 0) {
        $_SESSION['mensaje_error'] = "Complete todos los campos requeridos.";
        header("Location: caja.php");
        exit;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $fecha = date('Y-m-d');

    $pdo->prepare("INSERT INTO caja (tipo, concepto, monto, mascota_id, fecha) VALUES (:tipo, :concepto, :monto, :mascota_id, :fecha)")
        ->execute([
            ':tipo'       => $tipo,
            ':concepto'   => $concepto,
            ':monto'      => $monto,
            ':mascota_id' => $mascota_id,
            ':fecha'      => $fecha,
        ]);

    $_SESSION['mensaje'] = "Movimiento registrado correctamente.";
    header("Location: caja.php");
    exit;

} elseif ($action === 'eliminar') {
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
