<?php
require_once '../helpers/ApiResponse.php';
require_once '../config/database.php'; // Asegúrate de que exista la conexión a la base de datos
require_once '../vendor/autoload.php'; // Para usar PHPMailer

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

    // Validar campos obligatorios en attributes
    if (
        empty($attributes['email']) ||
        empty($attributes['password']) ||
        empty($attributes['confirm_password']) ||
        empty($attributes['role']) ||
        !isset($attributes['confirmation'])
    ) {
        \helpers\ApiResponse::error(
            'Campos faltantes',
            'Todos los campos (email, password, confirm_password, role, confirmation) son obligatorios.',
            400
        );
        return;
    }

    $email = $attributes['email'];
    $password = $attributes['password'];
    $confirmPassword = $attributes['confirm_password'];
    $role = $attributes['role'];
    $confirmation = $attributes['confirmation'];

    // Validar formato de correo
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        \helpers\ApiResponse::error('Correo inválido', 'El correo proporcionado no es válido.', 400);
        return;
    }

    // Validar unicidad del correo
    $query = 'SELECT id FROM users WHERE email = :email';

    $stmt = $conn->prepare($query);
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        \helpers\ApiResponse::error('Correo ya registrado', 'El correo ya está registrado.', 400);
        return;
    }

    // Validar longitud y complejidad de la contraseña
    if (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[\W]/', $password)
    ) {
        \helpers\ApiResponse::error(
            'Contraseña débil',
            'La contraseña debe tener al menos 8 caracteres, incluyendo mayúsculas, minúsculas, números y un símbolo.',
            400
        );
        return;
    }

    // Validar confirmación de contraseña
    if ($password !== $confirmPassword) {
        \helpers\ApiResponse::error('Contraseña no coincide', 'La contraseña y su confirmación no coinciden.', 400);
        return;
    }

    // Validar rol
    $validRoles = ['user', 'admin', 'superAdmin'];
    if (!in_array($role, $validRoles)) {
        \helpers\ApiResponse::error('Rol inválido', 'El rol debe ser user, admin o superAdmin.', 400);
        return;
    }

    // Registrar usuario
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $confirmed = $confirmation ? 0 : 1; // 0 significa pendiente de confirmación
    $query = 'INSERT INTO users (email, password, role, confirmed) VALUES (:email, :password, :role, :confirmed)';
    $stmt = $conn->prepare($query);
    $stmt->execute([
        'email' => $email,
        'password' => $hashedPassword,
        'role' => $role,
        'confirmed' => $confirmed
    ]);

    // Si requiere confirmación, enviar correo
    if ($confirmation) {
        sendConfirmationEmail($email);
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
function sendConfirmationEmail($email)
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
                       <a href="http://example.com/api/auth/confirm?email=' . urlencode($email) . '">Confirmar cuenta</a>';

        $mail->send();
    } catch (Exception $e) {
        \helpers\ApiResponse::error(
            'Error al enviar correo',
            'No se pudo enviar el correo de confirmación. Intenta nuevamente.',
            500
        );
    }
}
