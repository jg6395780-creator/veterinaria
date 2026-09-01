<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/modulos_clinica.php';

$token = trim((string)($_GET['token'] ?? ''));
$url = null;
if (preg_match('/^[a-zA-Z0-9]{40,80}$/', $token)) {
    $pdo = getDB();
    asegurarModulosClinica($pdo);
    $stmt = $pdo->prepare("SELECT respuesta_json FROM pagos_webpay WHERE token_ws=:token AND estado='iniciado' LIMIT 1");
    $stmt->execute([':token' => $token]);
    $respuesta = json_decode((string)$stmt->fetchColumn(), true);
    $url = is_array($respuesta) ? ($respuesta['url'] ?? null) : null;
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pago VetClinic</title><style>body{font-family:system-ui;background:#e8f5e9;display:grid;place-items:center;min-height:100vh;margin:0}.card{background:white;border-radius:20px;padding:32px;text-align:center;box-shadow:0 10px 30px #0002;max-width:420px}.btn{border:0;border-radius:12px;background:#2563eb;color:white;padding:14px 24px;font-weight:700;font-size:16px}</style></head>
<body><div class="card">
<?php if ($url): ?>
<h1>Conectando con Webpay</h1><p>Serás dirigido al sitio seguro de Transbank.</p>
<form id="webpay" action="<?= htmlspecialchars($url, ENT_QUOTES) ?>" method="post"><input type="hidden" name="token_ws" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>"><button class="btn">Continuar a Webpay</button></form>
<script>document.getElementById('webpay').submit();</script>
<?php else: ?><h1>Enlace no válido</h1><p>Vuelve a la aplicación e inicia el pago nuevamente.</p><?php endif; ?>
</div></body></html>
