<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';

if ($_SESSION['user_rol'] !== 'admin') {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: empleados.php");
    exit;
}

$action = trim($_POST['action'] ?? '');
$pdo    = getDB();

if ($action === 'editar') {
    $id     = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre_completo'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $rol    = trim($_POST['rol'] ?? '');
    $pass   = trim($_POST['password'] ?? '');

    if (!$id || !$nombre || !$rol || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['mensaje_empleado'] = 'error_campos_vacios';
        header("Location: empleados.php"); exit;
    }

    try {
        if ($pass !== '') {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE usuarios SET nombre_completo=:n, email=:e, rol=:r, password=:p WHERE id=:id")
                ->execute([':n'=>$nombre, ':e'=>$email, ':r'=>$rol, ':p'=>$hash, ':id'=>$id]);
        } else {
            $pdo->prepare("UPDATE usuarios SET nombre_completo=:n, email=:e, rol=:r WHERE id=:id")
                ->execute([':n'=>$nombre, ':e'=>$email, ':r'=>$rol, ':id'=>$id]);
        }
        $_SESSION['mensaje_empleado'] = 'empleado_actualizado';
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $_SESSION['mensaje_empleado'] = 'error';
    }
    header("Location: empleados.php"); exit;

} elseif ($action === 'toggle_activo') {
    $id = (int)($_POST['id'] ?? 0);
    // Cannot deactivate self
    if (!$id || $id === (int)$_SESSION['user_id']) {
        header("Location: empleados.php"); exit;
    }
    try {
        $pdo->prepare("UPDATE usuarios SET activo = 1 - activo WHERE id=:id")
            ->execute([':id' => $id]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
    header("Location: empleados.php"); exit;

} elseif ($action === 'eliminar') {
    $id = (int)($_POST['id'] ?? 0);
    // Cannot delete self
    if (!$id || $id === (int)$_SESSION['user_id']) {
        header("Location: empleados.php"); exit;
    }
    try {
        $pdo->prepare("DELETE FROM usuarios WHERE id=:id")->execute([':id' => $id]);
        $_SESSION['mensaje_empleado'] = 'empleado_eliminado';
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $_SESSION['mensaje_empleado'] = 'error';
    }
    header("Location: empleados.php"); exit;

} else {
    header("Location: empleados.php"); exit;
}
