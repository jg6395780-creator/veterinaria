<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pdo = getDB();
        $columnasUsuarios = $pdo->query('SHOW COLUMNS FROM usuarios')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('email', $columnasUsuarios, true)) {
            $pdo->exec('ALTER TABLE usuarios ADD email VARCHAR(150) NULL UNIQUE AFTER nombre_completo');
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS restablecimientos_contrasena (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expira_en DATETIME NOT NULL,
            usado_en DATETIME NULL,
            creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $pdo->prepare('SELECT id, email FROM usuarios WHERE email = :email AND activo = 1');
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $pdo->prepare('DELETE FROM restablecimientos_contrasena WHERE usuario_id = :usuario_id OR expira_en < NOW()')
                ->execute([':usuario_id' => $usuario['id']]);
            $pdo->prepare('INSERT INTO restablecimientos_contrasena (usuario_id, token_hash, expira_en) VALUES (:usuario_id, :token_hash, DATE_ADD(NOW(), INTERVAL 30 MINUTE))')
                ->execute([':usuario_id' => $usuario['id'], ':token_hash' => $tokenHash]);

            $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $ruta = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            $enlace = $protocolo . '://' . $_SERVER['HTTP_HOST'] . $ruta . '/restablecer_contrasena.php?token=' . rawurlencode($token);
            enviarCorreoRestablecimiento($usuario['email'], $enlace);
        }
    }
    // El mismo texto evita revelar qué direcciones pertenecen al sistema.
    $mensaje = 'Si el correo está registrado, ya enviamos un enlace para restablecer tu contraseña. Revisa también la carpeta de spam.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña — VetClinic Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 p-3">
    <main class="card border-0 shadow-sm" style="max-width: 460px; width: 100%;">
        <div class="card-body p-4 p-md-5 text-center">
            <i class="bi bi-shield-lock-fill text-primary" style="font-size: 2.5rem;"></i>
            <h1 class="h4 mt-3">¿Olvidaste tu contraseña?</h1>
            <p class="text-muted mb-4">Ingresa tu correo de empleado y te enviaremos un enlace válido por 30 minutos.</p>
            <?php if ($mensaje): ?><div class="alert alert-success text-start small"><?= e($mensaje) ?></div><?php endif; ?>
            <form method="POST" class="text-start">
                <label for="email" class="form-label">Correo electrónico</label>
                <input type="email" name="email" id="email" class="form-control mb-3" required autofocus>
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send me-2"></i>Enviar enlace</button>
            </form>
            <a href="login.php" class="btn btn-link btn-sm w-100 mt-2"><i class="bi bi-arrow-left me-1"></i>Volver al inicio de sesión</a>
        </div>
    </main>
</body>
</html>
