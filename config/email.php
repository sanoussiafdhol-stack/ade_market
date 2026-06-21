<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/load_env.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function envoyerEmail($destinataire, $sujet, $message_texte) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = defined('SMTP_USER') ? SMTP_USER : '';
        $mail->Password   = defined('SMTP_PASS') ? SMTP_PASS : '';
        $mail->SMTPSecure = defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;

        $fromEmail = defined('SMTP_FROM') ? SMTP_FROM : (defined('SMTP_USER') ? SMTP_USER : 'noreply@ademarket.bj');
        $fromName  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'ADE MARKET';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($destinataire);
        $mail->CharSet = 'UTF-8';

        $mail->isHTML(false);
        $mail->Subject = $sujet;
        $mail->Body    = $message_texte;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function envoyerEmailAdmin($sujet, $message) {
    $admin_email = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'sanoussiafdhol@gmail.com';
    return envoyerEmail($admin_email, "[ADMIN] " . $sujet, $message);
}
