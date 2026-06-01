<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: mascotas.php");
    exit;
}

$action = trim($_POST['action'] ?? '');
$pdo    = getDB();

if ($action === 'crear') {
    $dueno_nombre   = trim($_POST['dueno_nombre']   ?? '');
    $dueno_telefono = trim($_POST['dueno_telefono'] ?? '');
    $dueno_email    = trim($_POST['dueno_email']    ?? '');
    $nombre         = trim($_POST['nombre']         ?? '');
    $especie        = trim($_POST['especie']        ?? '');
    $peso           = trim($_POST['peso']           ?? '');

    $raza            = trim($_POST['raza']            ?? '');
    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');

    if (!$dueno_nombre || !$dueno_telefono || !$dueno_email || !$nombre || !$especie || !$peso || !$raza || !$fecha_nacimiento) {
        $_SESSION['mensaje_error'] = "Por favor complete todos los campos requeridos.";
        header("Location: registrar_mascota.php");
        exit;
    }

    if (!filter_var($dueno_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['mensaje_error'] = "El correo electrónico no es válido.";
        header("Location: registrar_mascota.php");
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(identificador, 3) AS UNSIGNED)) FROM mascotas WHERE identificador REGEXP '^M-[0-9]+$'");
        $max  = (int)$stmt->fetchColumn();
        $identificador = 'M-' . str_pad($max + 1, 3, '0', STR_PAD_LEFT);

        $temp_password_hash = password_hash($identificador, PASSWORD_DEFAULT);

        $pdo->prepare("INSERT INTO duenos (nombre, telefono, email, password) VALUES (:nombre, :telefono, :email, :password)")
            ->execute([
                ':nombre'   => $dueno_nombre,
                ':telefono' => $dueno_telefono,
                ':email'    => $dueno_email ?: null,
                ':password' => $temp_password_hash,
            ]);
        $dueno_id = $pdo->lastInsertId();

        $pdo->prepare("
            INSERT INTO mascotas (dueno_id, identificador, nombre, especie, raza, peso, fecha_nacimiento)
            VALUES (:dueno_id, :identificador, :nombre, :especie, :raza, :peso, :fecha_nacimiento)
        ")->execute([
            ':dueno_id'         => $dueno_id,
            ':identificador'    => $identificador,
            ':nombre'           => $nombre,
            ':especie'          => $especie,
            ':raza'             => $raza,
            ':peso'             => (float)$peso,
            ':fecha_nacimiento' => $fecha_nacimiento ?: null,
        ]);

        $pdo->commit();

        $_SESSION['mensaje'] = "Mascota registrada. Acceso portal dueño — usuario: {$dueno_email} / contraseña temporal: {$identificador}";
        header("Location: mascotas.php");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        $_SESSION['mensaje_error'] = "Error al guardar el registro. Intente de nuevo.";
        header("Location: registrar_mascota.php");
        exit;
    }

} elseif ($action === 'editar') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { header("Location: mascotas.php"); exit; }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT dueno_id FROM mascotas WHERE id=:id");
        $stmt->execute([':id' => $id]);
        $dueno_id = $stmt->fetchColumn();

        if (!$dueno_id) { $pdo->rollBack(); header("Location: mascotas.php"); exit; }

        $pdo->prepare("UPDATE duenos SET nombre=:nombre, telefono=:telefono, email=:email WHERE id=:id")
            ->execute([
                ':nombre'   => trim($_POST['dueno_nombre']),
                ':telefono' => trim($_POST['dueno_telefono']),
                ':email'    => trim($_POST['dueno_email']) ?: null,
                ':id'       => $dueno_id,
            ]);

        $pdo->prepare("
            UPDATE mascotas
            SET identificador=:identificador, nombre=:nombre, especie=:especie,
                raza=:raza, peso=:peso, fecha_nacimiento=:fecha_nacimiento
            WHERE id=:id
        ")->execute([
            ':identificador'    => trim($_POST['identificador']),
            ':nombre'           => trim($_POST['nombre']),
            ':especie'          => $_POST['especie'],
            ':raza'             => trim($_POST['raza']),
            ':peso'             => (float)$_POST['peso'],
            ':fecha_nacimiento' => $_POST['fecha_nacimiento'] ?: null,
            ':id'               => $id,
        ]);

        $pdo->commit();
        $_SESSION['mensaje'] = "Mascota actualizada correctamente.";
        header("Location: mascotas.php");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        $_SESSION['mensaje_error'] = "Error al actualizar. Intente de nuevo.";
        header("Location: mascotas.php");
        exit;
    }

} elseif ($action === 'eliminar') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { header("Location: mascotas.php"); exit; }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT dueno_id FROM mascotas WHERE id=:id");
        $stmt->execute([':id' => $id]);
        $dueno_id = $stmt->fetchColumn();

        $pdo->prepare("DELETE FROM historial_clinico WHERE mascota_id=:id")->execute([':id' => $id]);
        $pdo->prepare("DELETE FROM vacunas          WHERE mascota_id=:id")->execute([':id' => $id]);
        $pdo->prepare("DELETE FROM mascotas         WHERE id=:id"        )->execute([':id' => $id]);

        if ($dueno_id) {
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM mascotas WHERE dueno_id=:id");
            $cnt->execute([':id' => $dueno_id]);
            if ((int)$cnt->fetchColumn() === 0) {
                $pdo->prepare("DELETE FROM duenos WHERE id=:id")->execute([':id' => $dueno_id]);
            }
        }

        $pdo->commit();
        $_SESSION['mensaje'] = "Mascota eliminada correctamente.";
        header("Location: mascotas.php");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        $_SESSION['mensaje_error'] = "Error al eliminar. Intente de nuevo.";
        header("Location: mascotas.php");
        exit;
    }

} else {
    header("Location: mascotas.php");
    exit;
}
