<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/modulos_clinica.php';
require_once __DIR__ . '/../includes/webpay.php';

$token = trim((string)($_POST['token_ws'] ?? ''));
$aprobado = false;
$titulo = 'Pago cancelado';
$mensaje = 'El pago no se completo. Puedes volver a intentarlo desde la aplicacion.';

try {
    if ($token === '' || !preg_match('/^[a-zA-Z0-9]{40,80}$/', $token)) {
        throw new RuntimeException('La operacion fue cancelada antes de confirmar el pago.');
    }
    $pdo = getDB();
    asegurarModulosClinica($pdo);
    // Los cambios de esquema hacen commit implicito en MySQL; se preparan antes
    // de iniciar la transaccion que contabiliza el pago.
    asegurarCajaParaPago($pdo);
    $stmt = $pdo->prepare('SELECT * FROM pagos_webpay WHERE token_ws=:token LIMIT 1');
    $stmt->execute([':token' => $token]);
    $pago = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pago) throw new RuntimeException('No encontramos esta operacion de pago.');
    if ($pago['estado'] === 'aprobado') {
        $aprobado = true;
        $titulo = 'Pago realizado';
        $mensaje = 'Tu pago ya estaba confirmado correctamente.';
    } else {
        $respuesta = webpayRequest('PUT', '/transactions/' . rawurlencode($token));
        $valido = ($respuesta['status'] ?? '') === 'AUTHORIZED'
            && (int)($respuesta['response_code'] ?? -1) === 0
            && ($respuesta['buy_order'] ?? '') === $pago['buy_order']
            && (int)round((float)($respuesta['amount'] ?? 0)) === (int)round((float)$pago['monto']);

        $pdo->beginTransaction();
        $lock = $pdo->prepare('SELECT * FROM pagos_webpay WHERE id=:id FOR UPDATE');
        $lock->execute([':id' => $pago['id']]);
        $actual = $lock->fetch(PDO::FETCH_ASSOC);
        if ($actual['estado'] === 'aprobado') {
            $valido = true;
        } elseif ($valido) {
            $presupuestoStmt = $pdo->prepare('SELECT p.*,m.nombre mascota FROM presupuestos p JOIN mascotas m ON m.id=p.mascota_id WHERE p.id=:id FOR UPDATE');
            $presupuestoStmt->execute([':id' => $pago['presupuesto_id']]);
            $presupuesto = $presupuestoStmt->fetch(PDO::FETCH_ASSOC);
            if (!$presupuesto) throw new RuntimeException('El presupuesto ya no existe.');
            if (!$presupuesto['caja_id']) {
                $caja = $pdo->prepare("INSERT INTO caja (tipo,concepto,monto,documento,medio_pago,mascota_id,fecha) VALUES ('ingreso',:concepto,:monto,'boleta','webpay',:mascota,CURDATE())");
                $caja->execute([
                    ':concepto' => 'Pago App · Presupuesto #' . $presupuesto['id'] . ' · ' . $presupuesto['concepto'],
                    ':monto' => $pago['monto'],
                    ':mascota' => $presupuesto['mascota_id'],
                ]);
                $cajaId = (int)$pdo->lastInsertId();
                $pdo->prepare("UPDATE presupuestos SET estado='pagado', abonado=monto, caja_id=:caja WHERE id=:id")
                    ->execute([':caja' => $cajaId, ':id' => $presupuesto['id']]);
                crearNotificacion($pdo, (int)$pago['dueno_id'], null, 'Pago confirmado', 'Recibimos el pago de ' . $presupuesto['mascota'] . ' por $' . number_format((float)$pago['monto'], 0, ',', '.') . '.', 'pago');
            }
            $ultimos = (string)($respuesta['card_detail']['card_number'] ?? '');
            $pdo->prepare("UPDATE pagos_webpay SET estado='aprobado',codigo_autorizacion=:auth,ultimos_cuatro=:tarjeta,respuesta_json=:respuesta WHERE id=:id")
                ->execute([':auth' => $respuesta['authorization_code'] ?? null, ':tarjeta' => substr($ultimos, -4), ':respuesta' => json_encode($respuesta), ':id' => $pago['id']]);
        } else {
            $pdo->prepare("UPDATE pagos_webpay SET estado='rechazado',respuesta_json=:respuesta WHERE id=:id")
                ->execute([':respuesta' => json_encode($respuesta), ':id' => $pago['id']]);
        }
        $pdo->commit();
        if ($valido) {
            $aprobado = true;
            $titulo = '¡Pago realizado!';
            $mensaje = 'Webpay confirmó el pago. Ya puedes volver a VetClinic y actualizar la lista.';
        } else {
            $titulo = 'Pago rechazado';
            $mensaje = 'Webpay no autorizó el pago. No se realizó ningún cobro.';
        }
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Retorno Webpay: ' . $e->getMessage());
    $mensaje = $e instanceof RuntimeException ? $e->getMessage() : 'No pudimos confirmar el resultado del pago.';
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= htmlspecialchars($titulo) ?></title>
<style>body{font-family:system-ui;background:<?= $aprobado ? '#ecfdf5' : '#fff7ed' ?>;display:grid;place-items:center;min-height:100vh;margin:0}.card{background:#fff;border-radius:22px;padding:34px;text-align:center;max-width:440px;box-shadow:0 12px 35px #0002}.icon{font-size:55px}.ok{color:#15803d}.no{color:#c2410c}h1{margin:8px 0}p{color:#475569;line-height:1.5}</style></head>
<body><main class="card"><div class="icon <?= $aprobado ? 'ok' : 'no' ?>"><?= $aprobado ? '&#10003;' : '!' ?></div><h1><?= htmlspecialchars($titulo) ?></h1><p><?= htmlspecialchars($mensaje) ?></p><p><strong>Ya puedes cerrar esta ventana.</strong></p></main></body></html>
