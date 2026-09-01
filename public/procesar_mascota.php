<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/rut.php';
require_once __DIR__ . '/includes/identificadores_mascotas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: mascotas.php");
    exit;
}

$action = trim($_POST['action'] ?? '');
$pdo    = getDB();

if ($action === 'crear') {
    $dueno_nombre   = trim($_POST['dueno_nombre']   ?? '');
    $dueno_rut      = normalizarRut(trim($_POST['dueno_rut'] ?? ''));
    $dueno_telefono = '9' . preg_replace('/\D/', '', trim($_POST['dueno_telefono'] ?? ''));
    $dueno_email    = trim($_POST['dueno_email']    ?? '');
    $nombre         = trim($_POST['nombre']         ?? '');
    $especie        = trim($_POST['especie']        ?? '');
    $peso           = trim($_POST['peso']           ?? '');

    $raza            = trim($_POST['raza']            ?? '');
    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');

    if (!$dueno_nombre || !$dueno_rut || !$dueno_telefono || !$dueno_email || !$nombre || !$especie || !$peso || !$raza || !$fecha_nacimiento) {
        $_SESSION['mensaje_error'] = "Por favor complete todos los campos requeridos.";
        header("Location: registrar_mascota.php");
        exit;
    }

    if (!filter_var($dueno_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['mensaje_error'] = "El correo electrónico no es válido.";
        header("Location: registrar_mascota.php");
        exit;
    }

    if (!validarRut($dueno_rut)) {
        $_SESSION['mensaje_error'] = "El RUT ingresado no es válido.";
        header("Location: registrar_mascota.php");
        exit;
    }

    try {
        $pdo->beginTransaction();

        $identificador = siguienteIdentificadorMascota($pdo, $especie);

        $buscarDueno = $pdo->prepare("SELECT id, rut FROM duenos WHERE rut = :rut LIMIT 1 FOR UPDATE");
        $buscarDueno->execute([':rut' => $dueno_rut]);
        $dueno = $buscarDueno->fetch();

        // Compatibilidad con dueños creados antes de incorporar el RUT.
        if (!$dueno) {
            $buscarPorEmail = $pdo->prepare("SELECT id, rut FROM duenos WHERE email = :email LIMIT 1 FOR UPDATE");
            $buscarPorEmail->execute([':email' => $dueno_email]);
            $duenoPorEmail = $buscarPorEmail->fetch();
            if ($duenoPorEmail && !empty($duenoPorEmail['rut']) && $duenoPorEmail['rut'] !== $dueno_rut) {
                $pdo->rollBack();
                $_SESSION['mensaje_error'] = "El correo indicado ya está asociado a otro RUT.";
                header("Location: registrar_mascota.php");
                exit;
            }
            $dueno = $duenoPorEmail ?: null;
        }

        $dueno_id = $dueno['id'] ?? null;
        $dueno_nuevo = !$dueno_id;

        if ($dueno_nuevo) {
            $temp_password_hash = password_hash($identificador, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO duenos (rut, nombre, telefono, email, password) VALUES (:rut, :nombre, :telefono, :email, :password)")
                ->execute([
                    ':rut'      => $dueno_rut,
                    ':nombre'   => $dueno_nombre,
                    ':telefono' => $dueno_telefono,
                    ':email'    => $dueno_email,
                    ':password' => $temp_password_hash,
                ]);
            $dueno_id = $pdo->lastInsertId();
        } else {
            $pdo->prepare("UPDATE duenos SET rut = :rut, nombre = :nombre, telefono = :telefono, email = :email WHERE id = :id")
                ->execute([
                    ':rut'      => $dueno_rut,
                    ':nombre'   => $dueno_nombre,
                    ':telefono' => $dueno_telefono,
                    ':email'    => $dueno_email,
                    ':id'       => $dueno_id,
                ]);
        }

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

        $_SESSION['mensaje'] = $dueno_nuevo
            ? "Mascota registrada. Acceso portal dueño — RUT: " . formatearRut($dueno_rut) . " / contraseña temporal: {$identificador}"
            : "Mascota registrada y asociada al dueño con RUT " . formatearRut($dueno_rut) . ".";
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

    $dueno_rut = normalizarRut(trim($_POST['dueno_rut'] ?? ''));
    if (!validarRut($dueno_rut)) {
        $_SESSION['mensaje_error'] = "El RUT ingresado no es válido.";
        header("Location: mascotas.php");
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT dueno_id, identificador, especie FROM mascotas WHERE id=:id FOR UPDATE");
        $stmt->execute([':id' => $id]);
        $mascotaOriginal = $stmt->fetch();
        $dueno_id_original = $mascotaOriginal['dueno_id'] ?? null;

        if (!$dueno_id_original) { $pdo->rollBack(); header("Location: mascotas.php"); exit; }

        $buscarDueno = $pdo->prepare("SELECT id FROM duenos WHERE rut=:rut LIMIT 1 FOR UPDATE");
        $buscarDueno->execute([':rut' => $dueno_rut]);
        $dueno_id = $buscarDueno->fetchColumn() ?: $dueno_id_original;

        if ((int)$dueno_id === (int)$dueno_id_original) {
            $pdo->prepare("UPDATE duenos SET rut=:rut, nombre=:nombre, telefono=:telefono, email=:email WHERE id=:id")
                ->execute([
                    ':rut'      => $dueno_rut,
                    ':nombre'   => trim($_POST['dueno_nombre']),
                    ':telefono' => trim($_POST['dueno_telefono']),
                    ':email'    => trim($_POST['dueno_email']) ?: null,
                    ':id'       => $dueno_id,
                ]);
        }

        $especieNueva = trim((string)($_POST['especie'] ?? ''));
        $prefijoEsperado = prefijoPorEspecie($especieNueva) . '-';
        $identificadorNuevo = str_starts_with((string)$mascotaOriginal['identificador'], $prefijoEsperado)
            ? (string)$mascotaOriginal['identificador']
            : siguienteIdentificadorMascota($pdo, $especieNueva);

        $pdo->prepare("
            UPDATE mascotas
            SET dueno_id=:dueno_id, identificador=:identificador, nombre=:nombre, especie=:especie,
                raza=:raza, peso=:peso, fecha_nacimiento=:fecha_nacimiento
            WHERE id=:id
        ")->execute([
            ':dueno_id'         => $dueno_id,
            ':identificador'    => $identificadorNuevo,
            ':nombre'           => trim($_POST['nombre']),
            ':especie'          => $especieNueva,
            ':raza'             => trim($_POST['raza']),
            ':peso'             => (float)$_POST['peso'],
            ':fecha_nacimiento' => $_POST['fecha_nacimiento'] ?: null,
            ':id'               => $id,
        ]);

        if ((int)$dueno_id !== (int)$dueno_id_original) {
            $contarMascotas = $pdo->prepare("SELECT COUNT(*) FROM mascotas WHERE dueno_id=:id");
            $contarMascotas->execute([':id' => $dueno_id_original]);
            if ((int)$contarMascotas->fetchColumn() === 0) {
                $pdo->prepare("DELETE FROM duenos WHERE id=:id")->execute([':id' => $dueno_id_original]);
            }
        }

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
