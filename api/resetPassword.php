<?php
function handleResetPassword()
{
    $db = new Database();
    $conn = $db->connect();

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['data']['attributes']['code']) ||
        !isset($input['data']['attributes']['new_password']) ||
        !isset($input['data']['attributes']['confirm_password'])) {
        \helpers\ApiResponse::error(
            'Formato inválido',
            'El código, la nueva contraseña y la confirmación son requeridos.',
            400
        );
        return;
    }

    $encryptedCode = $input['data']['attributes']['code'];
    $newPassword = $input['data']['attributes']['new_password'];
    $confirmPassword = $input['data']['attributes']['confirm_password'];

    // Validar contraseñas
    if ($newPassword !== $confirmPassword) {
        \helpers\ApiResponse::error(
            'Contraseñas no coinciden',
            'La nueva contraseña y su confirmación no coinciden.',
            400
        );
        return;
    }

    // Desencriptar el código
    $decryptedPayload = decryptPayload($encryptedCode);
    if (!$decryptedPayload) {
        \helpers\ApiResponse::error(
            'Código inválido',
            'El código proporcionado no es válido.',
            400
        );
        return;
    }

    // Separar email y código
    list($email, $randomCode) = explode('|', $decryptedPayload);

    // Verificar código y usuario
    $query = 'SELECT id FROM users WHERE email = :email AND confirmation_code = :confirmation_code LIMIT 1';
    $stmt = $conn->prepare($query);
    $stmt->execute([
        'email' => $email,
        'confirmation_code' => $randomCode
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        \helpers\ApiResponse::error(
            'Código no válido',
            'El código proporcionado no coincide con nuestros registros.',
            400
        );
        return;
    }

    // Actualizar contraseña
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    $updateQuery = 'UPDATE users SET password = :password, confirmation_code = NULL WHERE id = :id';
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->execute([
        'password' => $hashedPassword,
        'id' => $user['id']
    ]);

    \helpers\ApiResponse::success(
        null,
        'Contraseña restablecida exitosamente.',
        200
    );
}
