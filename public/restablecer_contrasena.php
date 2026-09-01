<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$exito = false;
$solicitud = null;

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    $error = 'El enlace de restablecimiento no es válido.';
} else {
    $pdo = getDB();
    $pdo->exec("CREATE TABLE IF NOT EXISTS restablecimientos_contrasena (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expira_en DATETIME NOT NULL,
        usado_en DATETIME NULL,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare("SELECT r.id, r.usuario_id FROM restablecimientos_contrasena r JOIN usuarios u ON u.id = r.usuario_id WHERE r.token_hash = :token_hash AND r.usado_en IS NULL AND r.expira_en > NOW() AND u.activo = 1");
    $stmt->execute([':token_hash' => $tokenHash]);
    $solicitud = $stmt->fetch();

    if (!$solicitud) {
        $error = 'Este enlace ya fue usado o venció. Solicita uno nuevo.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        $confirmacion = $_POST['confirmacion'] ?? '';
        if (strlen($password) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif (!hash_equals($password, $confirmacion)) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE usuarios SET password = :password WHERE id = :id')
                    ->execute([':password' => password_hash($password, PASSWORD_DEFAULT), ':id' => $solicitud['usuario_id']]);
                $pdo->prepare('UPDATE restablecimientos_contrasena SET usado_en = NOW() WHERE id = :id')
                    ->execute([':id' => $solicitud['id']]);
                $pdo->commit();
                $exito = true;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = 'No fue posible cambiar la contraseña. Intenta nuevamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva contraseña — VetClinic Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 p-3">
    <main class="card border-0 shadow-sm" style="max-width: 460px; width: 100%;">
        <div class="card-body p-4 p-md-5">
            <h1 class="h4 text-center mb-4">Crear nueva contraseña</h1>
            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <?php if ($exito): ?>
                <div class="alert alert-success">Tu contraseña fue actualizada correctamente.</div>
                <a href="login.php" class="btn btn-primary w-100">Iniciar sesión</a>
            <?php elseif (!$error || $solicitud): ?>
                <form method="POST">
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <label class="form-label">Nueva contraseña</label>
                    <input type="password" name="password" class="form-control mb-3" minlength="8" required>
                    <label class="form-label">Confirmar contraseña</label>
                    <input type="password" name="confirmacion" class="form-control mb-4" minlength="8" required>
                    <button type="submit" class="btn btn-primary w-100">Guardar nueva contraseña</button>
                </form>
            <?php else: ?>
                <a href="recuperar_contrasena.php" class="btn btn-primary w-100">Solicitar un nuevo enlace</a>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
