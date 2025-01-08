<?php
require_once '../helpers/ApiResponse.php';
require_once '../config/database.php';
require_once '../vendor/autoload.php';
require_once '../helpers/Encryption.php';

function handleDisableUser($user) {
    $db = new Database();
    $conn = $db->connect();

    // Verifica que el usuario tiene permisos administrativos
    if ($user->permissions ) {
         $permissions =  $user->permissions;
         $decryptedPayload = decryptPayload($permissions);
         $permissionsDecode = json_decode($decryptedPayload, true);

         $result = array_filter( $permissionsDecode, function ($item) {
            return $item["module"] === "user" && $item["actions"] === "inhabilitar";
        });

        // Sí hay coincidencias, obtener el primer resultado
        if (empty($result)) {
            \helpers\ApiResponse::error(
                'Acceso denegado',
                'No tienes permisos para realizar esta acción.',
                403
            );
            return;
        }
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['data']) || !isset($input['data']['attributes']['userId'])) {
        \helpers\ApiResponse::error(
            'Formato inválido',
            'El ID de usuario es requerido.',
            400
        );
        return;
    }

    $userId = $input['data']['attributes']['userId'];

    $query = 'UPDATE users SET disabled = 1 WHERE id = :userId';
    $stmt = $conn->prepare($query);
    $stmt->execute(['userId' => $userId]);

    \helpers\ApiResponse::success(
        ['message' => 'Usuario deshabilitado exitosamente'],
        'Usuario deshabilitado',
        200
    );
}
