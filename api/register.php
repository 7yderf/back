<?php
require_once '../helpers/ApiResponse.php';
require_once '../config/database.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function handleRegister()
{
    $db = new Database();
    $conn = $db->connect();

    $input = json_decode(file_get_contents('php://input'), true);

    // Validar estructura JSON:API
    if (!isset($input['data']['type']) || $input['data']['type'] !== 'users') {
        \helpers\ApiResponse::error(
            'Formato inválido',
            'El tipo de recurso debe ser "users".',
            400
        );
        return;
    }

    $attributes = isset($input['data']['attributes']) ? $input['data']['attributes'] : [];

    // Validar campos obligatorios
    // (omitir las validaciones ya cubiertas en versiones previas)

    $email = $attributes['email'];
    $password = $attributes['password'];
    $confirmPassword = $attributes['confirm_password'];
    $role = $attributes['role'];
    $confirmation = $attributes['confirmation'];
    $permissions = isset($attributes['permissions']) ? $attributes['permissions'] : null;

    // Validar y procesar (ya cubierto en el código previo)

    // Generar código de confirmación
    $randomCode = bin2hex(random_bytes(8)); // Genera un código alfanumérico de 16 caracteres
    $encryptedCode = encryptPayload($email . '|' . $randomCode); // Encriptar email y código juntos

    // Registrar usuario
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $confirmed = $confirmation ? 0 : 1;
    $query = 'INSERT INTO users (email, password, role, confirmed, permissions, confirmation_code) 
              VALUES (:email, :password, :role, :confirmed, :permissions, :confirmation_code)';
    $stmt = $conn->prepare($query);
    $stmt->execute([
        'email' => $email,
        'password' => $hashedPassword,
        'role' => $role,
        'confirmed' => $confirmed,
        'permissions' => $permissions ? json_encode($permissions) : null,
        'confirmation_code' => $randomCode
    ]);

    // Si requiere confirmación, enviar correo
    if ($confirmation) {
        sendConfirmationEmail($encryptedCode, $email);
    }

    // Respuesta JSON:API
    \helpers\ApiResponse::success(
        [
            'type' => 'users',
            'id' => $conn->lastInsertId(),
            'attributes' => [
                'email' => $email,
                'role' => $role,
                'confirmation_required' => $confirmation
            ]
        ],
        'Usuario registrado exitosamente.',
        201
    );
}
function sendConfirmationEmail($confirmationCode, $email)
{
    $mail = new PHPMailer(true);

    try {
        // Configurar el correo
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com' ; // Cambiar por tu host SMTP google smtp
        $mail->SMTPAuth = true;
        $mail->Username = '7yderf@gmail.com'; // Cambiar por tu usuario SMTP
        $mail->Password = 'rdyqtpuqvyhjsquk'; // Cambiar por tu contraseña SMTP
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Configurar destinatario y contenido
        $mail->setFrom('no-reply@example.com', 'Mi API');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Confirma tu cuenta';
        $mail->Body = '<p>Gracias por registrarte. Haz clic en el enlace para confirmar tu cuenta:</p>
                       <a href="http://example.com/api/auth/confirm?code=' .  urlencode($confirmationCode) . '">Confirmar cuenta</a>';

        $mail->send();
    } catch (Exception $e) {
        \helpers\ApiResponse::error(
            'Error al enviar correo',
            'No se pudo enviar el correo de confirmación. Intenta nuevamente.',
            500
        );
    }
}
