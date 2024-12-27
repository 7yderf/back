<?php
require_once '../helpers/ApiResponse.php';
require_once '../config/database.php';
require_once '../vendor/autoload.php';
function handleConfirmEmail()
{
$db = new Database();
$conn = $db->connect();

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['data']) || !isset($input['data']['attributes']['code'])) {
\helpers\ApiResponse::error(
'Formato inválido',
'El código de confirmación es requerido.',
400
);
return;
}

$encryptedCode = $input['data']['attributes']['code'];

// Desencriptar el código
$decryptedPayload = decryptPayload($encryptedCode);
if (!$decryptedPayload) {
\helpers\ApiResponse::error(
'Código inválido',
'El código de confirmación no es válido.',
400
);
return;
}

// Separar email y código
list($email, $randomCode) = explode('|', $decryptedPayload);

// Verificar que el código exista en la base de datos
$query = 'SELECT id, confirmed FROM users WHERE email = :email AND confirmation_code = :confirmation_code LIMIT 1';
$stmt = $conn->prepare($query);
$stmt->execute([
'email' => $email,
'confirmation_code' => $randomCode
]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
\helpers\ApiResponse::error(
'Código no encontrado',
'El código de confirmación es incorrecto o ya ha sido usado.',
400
);
return;
}

// Verificar si ya está confirmado
if ($user['confirmed'] == 1) {
\helpers\ApiResponse::error(
'Cuenta ya confirmada',
'Esta cuenta ya ha sido confirmada.',
400
);
return;
}

// Marcar como confirmada
$updateQuery = 'UPDATE users SET confirmed = 1, confirmation_code = NULL WHERE id = :id';
$updateStmt = $conn->prepare($updateQuery);
$updateStmt->execute(['id' => $user['id']]);

// Respuesta exitosa
\helpers\ApiResponse::success(
[
'type' => 'confirmation',
'id' => $user['id'],
'attributes' => [
'email' => $email,
'confirmed' => true
]
],
'Cuenta confirmada exitosamente.',
200
);
}
