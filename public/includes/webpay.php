<?php

require_once __DIR__ . '/../../config/webpay.php';

function webpayBaseUrl(): string
{
    return WEBPAY_ENV === 'production'
        ? 'https://webpay3g.transbank.cl/rswebpaytransaction/api/webpay/v1.2'
        : 'https://webpay3gint.transbank.cl/rswebpaytransaction/api/webpay/v1.2';
}
function webpayRequest(string $method, string $path, ?array $payload = null): array
{
    $curl = curl_init(webpayBaseUrl() . $path);
    $headers = [
        'Tbk-Api-Key-Id: ' . WEBPAY_COMMERCE_CODE,
        'Tbk-Api-Key-Secret: ' . WEBPAY_API_KEY,
        'Content-Type: application/json',
    ];
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($payload !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    $body = curl_exec($curl);
    $curlError = curl_error($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($body === false || $curlError !== '') {
        throw new RuntimeException('No fue posible conectar con Webpay.');
    }
    $data = json_decode($body, true);
    if ($status < 200 || $status >= 300 || !is_array($data)) {
        error_log('Webpay HTTP ' . $status . ': ' . $body);
        throw new RuntimeException('Webpay no pudo procesar la solicitud.');
    }
    return $data;
}

function urlPublicaActual(string $rutaDesdePublic): string
{
    $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    $scheme = ($forwarded === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/veterinaria/public/api/index.php');
    $publicPos = strpos($script, '/public/');
    $base = $publicPos === false ? '/veterinaria/public' : substr($script, 0, $publicPos + 7);
    return $scheme . '://' . $host . rtrim($base, '/') . '/' . ltrim($rutaDesdePublic, '/');
}

function asegurarCajaParaPago(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS caja (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo ENUM('ingreso','egreso') NOT NULL,
        concepto VARCHAR(200) NOT NULL,
        monto DECIMAL(10,2) UNSIGNED NOT NULL,
        documento ENUM('boleta','factura') NOT NULL DEFAULT 'boleta',
        medio_pago ENUM('efectivo','debito','credito','cheque','webpay') NOT NULL DEFAULT 'efectivo',
        mascota_id INT NULL,
        fecha DATE NOT NULL,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $medio = $pdo->query("SHOW COLUMNS FROM caja LIKE 'medio_pago'")->fetch(PDO::FETCH_ASSOC);
    if ($medio && stripos((string)$medio['Type'], 'webpay') === false) {
        $pdo->exec("ALTER TABLE caja MODIFY medio_pago ENUM('efectivo','debito','credito','cheque','webpay') NOT NULL DEFAULT 'efectivo'");
    }
}
