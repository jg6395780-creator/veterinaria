<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/modulos_clinica.php';

exigirRoles(['admin', 'recepcion']);
$pdo = getDB();
asegurarModulosClinica($pdo);
$mensaje = '';
$tipoMensaje = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfModulo()) {
        $mensaje = 'La sesión del formulario venció.';
        $tipoMensaje = 'danger';
    } else {
        try {
            $accion = $_POST['accion'] ?? '';
            if ($accion === 'crear') {
                $mascotaId = (int)($_POST['mascota_id'] ?? 0);
                $concepto = trim((string)($_POST['concepto'] ?? ''));
                $monto = (float)($_POST['monto'] ?? 0);
                $q = $pdo->prepare('SELECT nombre, dueno_id FROM mascotas WHERE id=:id');
                $q->execute([':id'=>$mascotaId]);
                $mascota = $q->fetch();
                if (!$mascota || !$concepto || $monto <= 0) throw new RuntimeException('Completa los datos del presupuesto.');
                $q = $pdo->prepare('INSERT INTO presupuestos (mascota_id,creado_por,concepto,detalle,monto,fecha_emision,fecha_vencimiento) VALUES (:m,:u,:c,:d,:monto,:fecha,:vence)');
                $q->execute([
                    ':m'=>$mascotaId, ':u'=>(int)$_SESSION['user_id'], ':c'=>$concepto,
                    ':d'=>trim((string)($_POST['detalle'] ?? '')) ?: null, ':monto'=>$monto,
                    ':fecha'=>date('Y-m-d'), ':vence'=>($_POST['fecha_vencimiento'] ?? '') ?: null,
                ]);
                $id = (int)$pdo->lastInsertId();
                crearNotificacion($pdo, (int)$mascota['dueno_id'], null, 'Nuevo presupuesto para '.$mascota['nombre'], $concepto.' por $'.number_format($monto, 0, ',', '.'), 'presupuesto');
                registrarAuditoria($pdo, 'presupuestos', 'crear', $id, $concepto);
                $mensaje = 'Presupuesto creado y notificado.';
            } elseif ($accion === 'actualizar') {
                $id = (int)($_POST['id'] ?? 0);
                $estado = (string)($_POST['estado'] ?? '');
                $abonado = (float)($_POST['abonado'] ?? 0);
                $documento = (string)($_POST['documento'] ?? 'boleta');
                $medioPago = (string)($_POST['medio_pago'] ?? 'efectivo');
                $fechaPago = (string)($_POST['fecha_pago'] ?? date('Y-m-d'));
                if (!$id || !in_array($estado, ['pendiente','aceptado','pagado','rechazado','vencido'], true) || $abonado < 0
                    || !in_array($documento, ['boleta','factura'], true)
                    || !in_array($medioPago, ['efectivo','debito','credito','cheque'], true)
                    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaPago) || strtotime($fechaPago) > strtotime(date('Y-m-d'))) {
                    throw new RuntimeException('Datos no válidos.');
                }

                $pdo->beginTransaction();
                $q = $pdo->prepare('SELECT p.*, m.nombre mascota, m.dueno_id FROM presupuestos p JOIN mascotas m ON m.id=p.mascota_id WHERE p.id=:id FOR UPDATE');
                $q->execute([':id'=>$id]);
                $presupuesto = $q->fetch();
                if (!$presupuesto) throw new RuntimeException('Presupuesto no encontrado.');
                if ($presupuesto['caja_id'] && $estado !== 'pagado') throw new RuntimeException('Este presupuesto ya fue pagado y registrado en Finanzas.');

                if ($estado === 'pagado' && !$presupuesto['caja_id']) {
                    if (!$pdo->query("SHOW TABLES LIKE 'caja'")->fetchColumn()) throw new RuntimeException('El módulo de Finanzas todavía no está inicializado.');
                    $q = $pdo->prepare("INSERT INTO caja (tipo,concepto,monto,documento,medio_pago,mascota_id,fecha) VALUES ('ingreso',:concepto,:monto,:documento,:medio,:mascota,:fecha)");
                    $q->execute([
                        ':concepto'=>'Presupuesto #'.$id.' · '.$presupuesto['concepto'],
                        ':monto'=>$presupuesto['monto'], ':documento'=>$documento,
                        ':medio'=>$medioPago, ':mascota'=>$presupuesto['mascota_id'], ':fecha'=>$fechaPago,
                    ]);
                    $cajaId = (int)$pdo->lastInsertId();
                    $pdo->prepare("UPDATE presupuestos SET estado='pagado', abonado=monto, caja_id=:caja WHERE id=:id")
                        ->execute([':caja'=>$cajaId, ':id'=>$id]);
                    crearNotificacion($pdo, (int)$presupuesto['dueno_id'], null, 'Pago registrado', 'El presupuesto de '.$presupuesto['mascota'].' por $'.number_format((float)$presupuesto['monto'], 0, ',', '.').' fue pagado.', 'pago');
                    registrarAuditoria($pdo, 'presupuestos', 'registrar_pago', $id, 'Movimiento de caja #'.$cajaId);
                    $mensaje = 'Pago registrado y enviado automáticamente a Finanzas.';
                } else {
                    $pdo->prepare('UPDATE presupuestos SET estado=:estado, abonado=LEAST(monto,:abonado) WHERE id=:id')
                        ->execute([':estado'=>$estado, ':abonado'=>$abonado, ':id'=>$id]);
                    registrarAuditoria($pdo, 'presupuestos', 'actualizar', $id, $estado);
                    $mensaje = 'Presupuesto actualizado.';
                }
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $mensaje = $e instanceof RuntimeException ? $e->getMessage() : 'No fue posible guardar.';
            $tipoMensaje = 'danger';
            error_log('Presupuestos: '.$e->getMessage());
        }
    }
}

$presupuestos = $pdo->query('SELECT p.*,m.nombre mascota,m.identificador,d.nombre dueno,c.fecha caja_fecha FROM presupuestos p JOIN mascotas m ON m.id=p.mascota_id JOIN duenos d ON d.id=m.dueno_id LEFT JOIN caja c ON c.id=p.caja_id ORDER BY p.fecha_emision DESC,p.id DESC')->fetchAll();
$mascotas = $pdo->query('SELECT m.id,m.nombre,m.identificador,d.nombre dueno FROM mascotas m JOIN duenos d ON d.id=m.dueno_id ORDER BY m.nombre')->fetchAll();
$conceptosBase = ['Consulta general','Consulta de urgencia','Vacunación','Cirugía','Desparasitación','Baño y peluquería','Hospitalización','Examen de laboratorio','Radiografía / Ecografía','Venta de medicamentos','Venta de alimentos','Otro'];
$tarifasPorConcepto = [];
foreach ($pdo->query("SELECT concepto,monto FROM caja WHERE tipo='ingreso' ORDER BY id DESC")->fetchAll() as $movimientoPrecio) {
    $clavePrecio = mb_strtolower(trim((string)$movimientoPrecio['concepto']));
    if (!isset($tarifasPorConcepto[$clavePrecio])) $tarifasPorConcepto[$clavePrecio] = (float)$movimientoPrecio['monto'];
    if (preg_match('/^Presupuesto #\d+ · (.+)$/u', (string)$movimientoPrecio['concepto'], $coincidencia)) {
        $clavePresupuesto = mb_strtolower(trim($coincidencia[1]));
        if (!isset($tarifasPorConcepto[$clavePresupuesto])) $tarifasPorConcepto[$clavePresupuesto] = (float)$movimientoPrecio['monto'];
    }
}
$pendiente = array_sum(array_map(fn($p)=>in_array($p['estado'], ['pendiente','aceptado'], true) ? max(0, (float)$p['monto']-(float)$p['abonado']) : 0, $presupuestos));
$pageTitle = 'Presupuestos - VetClinic Pro';
require_once __DIR__ . '/includes/header.php';
?>
<div class="page-header d-flex justify-content-between flex-wrap gap-3">
    <div><h3><i class="bi bi-receipt-cutoff text-primary me-2"></i>Presupuestos</h3><p>Cotizaciones, abonos y cuentas pendientes por paciente.</p></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#presupuestoModal"><i class="bi bi-plus-circle me-2"></i>Nuevo presupuesto</button>
</div>
<?php if ($mensaje): ?><div class="alert alert-<?= e($tipoMensaje) ?>"><?= e($mensaje) ?></div><?php endif; ?>
<div class="kpi-card kpi-warning mb-4"><div class="kpi-label">Total pendiente de cobro</div><div class="kpi-value">$<?= number_format($pendiente,0,',','.') ?></div></div>
<div class="card card-stat"><div class="table-responsive"><table class="table table-hover mb-0">
<thead><tr><th>Paciente</th><th>Concepto</th><th>Total</th><th>Abonado</th><th>Saldo</th><th>Estado</th><th>Actualizar</th></tr></thead><tbody>
<?php foreach ($presupuestos as $p): ?><tr>
    <td><strong><?= e($p['mascota']) ?></strong><div class="small text-muted"><?= e($p['dueno']) ?></div></td>
    <td><?= e($p['concepto']) ?><?php if ($p['caja_id']): ?><div class="small"><a class="text-success text-decoration-none" href="caja.php?mes=<?= e(date('Y-m', strtotime($p['caja_fecha']))) ?>"><i class="bi bi-check-circle me-1"></i>Finanzas #<?= (int)$p['caja_id'] ?> · <?= e(date('d/m/Y', strtotime($p['caja_fecha']))) ?></a></div><?php endif; ?></td>
    <td>$<?= number_format((float)$p['monto'],0,',','.') ?></td><td>$<?= number_format((float)$p['abonado'],0,',','.') ?></td>
    <td class="fw-bold">$<?= number_format(max(0,(float)$p['monto']-(float)$p['abonado']),0,',','.') ?></td>
    <td><span class="badge text-bg-primary"><?= e(ucfirst($p['estado'])) ?></span></td>
    <td><?php if ($p['caja_id']): ?><span class="text-success small fw-semibold"><i class="bi bi-lock-fill me-1"></i>Pago contabilizado</span><?php else: ?>
        <form method="post" class="d-flex gap-1 flex-wrap form-actualizar-presupuesto" data-total="<?= e((string)$p['monto']) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrfModulo()) ?>"><input type="hidden" name="accion" value="actualizar"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="number" min="0" name="abonado" value="<?= e((string)$p['abonado']) ?>" class="form-control form-control-sm input-abonado" style="width:105px" title="Monto abonado">
            <select name="estado" class="form-select form-select-sm select-estado-presupuesto" style="width:125px"><?php foreach (['pendiente','aceptado','pagado','rechazado','vencido'] as $estado): ?><option <?= $estado===$p['estado']?'selected':'' ?>><?= e($estado) ?></option><?php endforeach; ?></select>
            <input type="hidden" name="documento" value="boleta"><span class="badge bg-light text-dark border align-self-center">Boleta</span>
            <select name="medio_pago" class="form-select form-select-sm" style="width:110px"><option value="efectivo">Efectivo</option><option value="debito">Débito</option><option value="credito">Crédito</option><option value="cheque">Cheque</option></select>
            <input type="date" name="fecha_pago" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" class="form-control form-control-sm" style="width:135px" title="Fecha del pago">
            <button class="btn btn-sm btn-outline-primary" title="Guardar"><i class="bi bi-check"></i></button>
        </form><?php endif; ?></td>
</tr><?php endforeach; ?></tbody></table></div></div>
<div class="modal fade" id="presupuestoModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Nuevo presupuesto</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfModulo()) ?>"><input type="hidden" name="accion" value="crear"><div class="modal-body">
<div class="mb-3"><label class="form-label">Paciente</label><select name="mascota_id" class="form-select" required><option value="">Seleccionar...</option><?php foreach ($mascotas as $m): ?><option value="<?= (int)$m['id'] ?>"><?= e($m['nombre'].' · '.$m['dueno']) ?></option><?php endforeach; ?></select></div>
<div class="mb-3"><label class="form-label">Concepto</label><select name="concepto" id="conceptoPresupuesto" class="form-select" required><option value="">Seleccionar servicio...</option><?php foreach ($conceptosBase as $concepto): $precio=$tarifasPorConcepto[mb_strtolower($concepto)]??0; ?><option value="<?= e($concepto) ?>" data-monto="<?= e((string)$precio) ?>"><?= e($concepto) ?><?= $precio>0?' · $'.number_format($precio,0,',','.'):' · sin tarifa anterior' ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">Detalle</label><textarea name="detalle" class="form-control" rows="3"></textarea></div><div class="row"><div class="col-6"><label class="form-label">Monto automático</label><div class="input-group"><span class="input-group-text">$</span><input type="text" id="montoPresupuestoVisible" class="form-control" inputmode="numeric" placeholder="0" autocomplete="off" required><input type="hidden" name="monto" id="montoPresupuesto"></div><div class="form-text">Los miles se separan automáticamente con punto.</div></div><div class="col-6"><label class="form-label">Válido hasta</label><input type="date" name="fecha_vencimiento" class="form-control"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Crear</button></div></form></div></div></div>
<script>
document.getElementById('conceptoPresupuesto')?.addEventListener('change', function () {
    const precio = Number(this.selectedOptions[0]?.dataset.monto || 0);
    const montoReal = document.getElementById('montoPresupuesto');
    const montoVisible = document.getElementById('montoPresupuestoVisible');
    montoReal.value = precio > 0 ? String(precio) : '';
    montoVisible.value = precio > 0 ? precio.toLocaleString('es-CL') : '';
    if (!precio) montoVisible.focus();
});
document.getElementById('montoPresupuestoVisible')?.addEventListener('input', function () {
    const valor = this.value.replace(/\D/g, '');
    document.getElementById('montoPresupuesto').value = valor;
    this.value = valor ? Number(valor).toLocaleString('es-CL') : '';
});
document.querySelectorAll('.select-estado-presupuesto').forEach(function (select) {
    select.addEventListener('change', function () {
        if (this.value !== 'pagado') return;
        const form = this.closest('.form-actualizar-presupuesto');
        form.querySelector('.input-abonado').value = form.dataset.total;
    });
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
