<?php
require_once __DIR__ . '/../../config/mail_credenciales.php';
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

function enviarCorreoRestablecimiento(string $destinatario, string $enlace): bool {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->SMTPDebug  = 0;

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($destinatario);
        $mail->Subject = 'Restablecimiento de contraseña — VetClinic Pro';
        $mail->Body = "Recibimos una solicitud para restablecer tu contraseña.\n\nAbre este enlace dentro de 30 minutos:\n{$enlace}\n\nSi no solicitaste el cambio, ignora este correo.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('No fue posible enviar correo de restablecimiento: ' . $e->getMessage());
        return false;
    }
}
