<?php
require_once '../helpers/ApiResponse.php';
require_once '../config/database.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
function handleForgotPassword()
{
    $db = new Database();
    $conn = $db->connect();

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['data']['attributes']['email'])) {
        \helpers\ApiResponse::error(
            'Formato inválido',
            'El correo electrónico es requerido.',
            400
        );
        return;
    }

    $email = $input['data']['attributes']['email'];

    // Verificar si el correo existe
    $query = 'SELECT id FROM users WHERE email = :email AND disabled = 0 LIMIT 1';
    $stmt = $conn->prepare($query);
    $stmt->execute(['email' => $email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        \helpers\ApiResponse::error(
            'Correo no encontrado',
            'No se encontró una cuenta asociada con este correo.',
            404
        );
        return;
    }

    // Generar código de recuperación
    $randomCode = bin2hex(random_bytes(8)); // Genera un código alfanumérico
    $encryptedCode = encryptPayload($email . '|' . $randomCode); // Encripta el correo y el código juntos

    // Guardar el código en la base de datos
    $updateQuery = 'UPDATE users SET confirmation_code = :confirmation_code WHERE id = :id';
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->execute([
        'confirmation_code' => $randomCode,
        'id' => $user['id']
    ]);

    // Enviar correo
    sendRecoveryEmail($encryptedCode, $email);

    \helpers\ApiResponse::success(
        null,
        'Correo enviado exitosamente para la recuperación de contraseña.',
        200
    );
}

function sendRecoveryEmail($recoveryCode, $email)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = '7yderf@gmail.com';
        $mail->Password = 'rdyqtpuqvyhjsquk';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('no-reply@example.com', 'Mi API');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Recuperación de contraseña';
        $mail->Body = '<p>Has solicitado restablecer tu contraseña. Usa el siguiente código:</p>
                       <p><strong>' . htmlspecialchars($recoveryCode) . '</strong></p>
                       <p>O haz clic en este enlace para continuar:</p>
                       <a href="http://example.com/reset-password?code=' . urlencode($recoveryCode) . '">Restablecer contraseña</a>';

        $mail->send();
    } catch (Exception $e) {
        \helpers\ApiResponse::error(
            'Error al enviar correo',
            'No se pudo enviar el correo de recuperación. Intenta nuevamente.',
            500
        );
    }
}
