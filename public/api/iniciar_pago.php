<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/rut.php';
require_once __DIR__ . '/../includes/modulos_clinica.php';
require_once __DIR__ . '/../includes/webpay.php';

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$rut = normalizarRut(trim((string)($input['rut'] ?? '')));
$presupuestoId = (int)($input['presupuesto_id'] ?? 0);
if (!validarRut($rut) || $presupuestoId < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Datos de pago invalidos']);
    exit;
}

try {
    $pdo = getDB();
    asegurarModulosClinica($pdo);
    $stmt = $pdo->prepare("SELECT p.*, m.nombre mascota, m.dueno_id
        FROM presupuestos p JOIN mascotas m ON m.id=p.mascota_id
        JOIN duenos d ON d.id=m.dueno_id
        WHERE p.id=:id AND d.rut=:rut LIMIT 1");
    $stmt->execute([':id' => $presupuestoId, ':rut' => $rut]);
    $presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$presupuesto || !in_array($presupuesto['estado'], ['pendiente', 'aceptado'], true)) {
        throw new RuntimeException('Este cobro no esta disponible para pago.');
    }
    $monto = (int)round((float)$presupuesto['monto'] - (float)$presupuesto['abonado']);
    if ($monto < 1) throw new RuntimeException('Este cobro no tiene saldo pendiente.');

    $buyOrder = 'VET-' . $presupuestoId . '-' . substr((string)time(), -9);
    $sessionId = 'D' . (int)$presupuesto['dueno_id'] . '-P' . $presupuestoId . '-' . bin2hex(random_bytes(6));

    if (WEBPAY_ENV === 'integration') {
        asegurarCajaParaPago($pdo);
        $pdo->beginTransaction();
        $lock = $pdo->prepare("SELECT p.*, m.nombre mascota, m.dueno_id
            FROM presupuestos p JOIN mascotas m ON m.id=p.mascota_id
            JOIN duenos d ON d.id=m.dueno_id
            WHERE p.id=:id AND d.rut=:rut FOR UPDATE");
        $lock->execute([':id' => $presupuestoId, ':rut' => $rut]);
        $actual = $lock->fetch(PDO::FETCH_ASSOC);
        if (!$actual || !in_array($actual['estado'], ['pendiente', 'aceptado'], true)) {
            throw new RuntimeException('Este cobro ya no esta pendiente.');
        }
        $montoActual = (int)round((float)$actual['monto'] - (float)$actual['abonado']);
        if ($montoActual < 1) throw new RuntimeException('Este cobro no tiene saldo pendiente.');

        $caja = $pdo->prepare("INSERT INTO caja
            (tipo,concepto,monto,documento,medio_pago,mascota_id,fecha)
            VALUES ('ingreso',:concepto,:monto,'boleta','webpay',:mascota,CURDATE())");
        $caja->execute([
            ':concepto' => 'Pago de prueba App · Presupuesto #' . $actual['id'] . ' · ' . $actual['concepto'],
            ':monto' => $montoActual,
            ':mascota' => $actual['mascota_id'],
        ]);
        $cajaId = (int)$pdo->lastInsertId();

        $pdo->prepare("UPDATE presupuestos SET estado='pagado', abonado=monto, caja_id=:caja WHERE id=:id")
            ->execute([':caja' => $cajaId, ':id' => $presupuestoId]);
        $pdo->prepare("INSERT INTO pagos_webpay
            (presupuesto_id,dueno_id,buy_order,session_id,monto,estado,codigo_autorizacion,respuesta_json)
            VALUES (:presupuesto,:dueno,:orden,:sesion,:monto,'aprobado','PRUEBA',:respuesta)")
            ->execute([
                ':presupuesto' => $presupuestoId,
                ':dueno' => $actual['dueno_id'],
                ':orden' => $buyOrder,
                ':sesion' => $sessionId,
                ':monto' => $montoActual,
                ':respuesta' => json_encode(['modo' => 'prueba_local', 'aprobado' => true]),
            ]);
        crearNotificacion(
            $pdo,
            (int)$actual['dueno_id'],
            null,
            'Pago de prueba confirmado',
            'Se registro el pago de prueba de ' . $actual['mascota'] . ' por $' . number_format($montoActual, 0, ',', '.') . '.',
            'pago'
        );
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'pago_automatico' => true,
            'message' => 'Pago de prueba registrado correctamente',
            'ambiente' => WEBPAY_ENV,
        ]);
        exit;
    }

    $returnUrl = urlPublicaActual('api/webpay_retorno.php');
    $respuesta = webpayRequest('POST', '/transactions', [
        'buy_order' => $buyOrder,
        'session_id' => $sessionId,
        'amount' => $monto,
        'return_url' => $returnUrl,
    ]);
    if (empty($respuesta['token']) || empty($respuesta['url'])) {
        throw new RuntimeException('Webpay no entrego una sesion de pago.');
    }

    $stmt = $pdo->prepare("INSERT INTO pagos_webpay
        (presupuesto_id,dueno_id,buy_order,session_id,token_ws,monto,estado,respuesta_json)
        VALUES (:presupuesto,:dueno,:orden,:sesion,:token,:monto,'iniciado',:respuesta)");
    $stmt->execute([
        ':presupuesto' => $presupuestoId,
        ':dueno' => $presupuesto['dueno_id'],
        ':orden' => $buyOrder,
        ':sesion' => $sessionId,
        ':token' => $respuesta['token'],
        ':monto' => $monto,
        ':respuesta' => json_encode($respuesta, JSON_UNESCAPED_UNICODE),
    ]);

    $checkoutUrl = urlPublicaActual('pagar_webpay.php') . '?token=' . rawurlencode($respuesta['token']);
    echo json_encode([
        'success' => true,
        'message' => 'Pago preparado',
        'checkout_url' => $checkoutUrl,
        'ambiente' => WEBPAY_ENV,
    ]);
} catch (RuntimeException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Inicio Webpay: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No fue posible iniciar el pago']);
}
