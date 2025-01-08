<?php
require_once '../helpers/EnvLoader.php';
require_once '../helpers/AuthMiddleware.php';
require_once '../helpers/ApiResponse.php';
require_once __DIR__ . '/../helpers/Encryption.php';
require_once 'auth.php';
require_once 'register.php';
require_once 'logout.php';
require_once 'disableUser.php';
require_once 'confirmEmail.php';
require_once 'forgotPassword.php';
require_once 'resetPassword.php';


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');


// Cargar el archivo .env
loadEnv('../.env');

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

switch ($uri) {
    case '/api/auth/login':
        if ($method === 'POST') {
            handleLogin();
        } else {
            \helpers\ApiResponse::error(
                'Método no permitido',
                'Solo se permite el método POST en esta ruta',
                405
            );
        }
        break;

    case '/api/auth/register':
        if ($method === 'POST') {
            handleRegister();
        } else {
            \helpers\ApiResponse::error(
                'Método no permitido',
                'Solo se permite el método POST en esta ruta',
                405
            );
        }
        break;

    case '/api/auth/confirm-email':
        if ($method === 'POST') {
            handleConfirmEmail();
        } else {
            \helpers\ApiResponse::error(
                'Método no permitido',
                'Solo se permite el método POST en esta ruta',
                405
            );
        }
        break;

    case '/api/auth/logout':
        if ($method === 'POST') {
            handleLogout();
        } else {
            \helpers\ApiResponse::error(
                'Método no permitido',
                'Solo se permite el método POST en esta ruta',
                405
            );
        }
        break;

    case '/api/auth/forgot-password':
        if ($method === 'POST') {
            handleForgotPassword();
        } else {
            \helpers\ApiResponse::error(
                'Método no permitido',
                'Solo se permite el método POST en esta ruta',
                405
            );
        }
        break;

    case '/api/auth/reset-password':
        if ($method === 'POST') {
            handleResetPassword();
        } else {
            \helpers\ApiResponse::error(
                'Método no permitido',
                'Solo se permite el método POST en esta ruta',
                405
            );
        }
        break;

    case '/api/admin/disable-user':
        if ($method === 'POST') {
            // Asume que `authenticate` devuelve el usuario autenticado
            $user = authenticate();

            if ($user){
                handleDisableUser($user);
            } else {
                \helpers\ApiResponse::error(
                    'Acceso denegado',
                    'Token no válido o expirado',
                    401
                );
            }

        } else {
            \helpers\ApiResponse::error(
                'Método no permitido',
                'Solo se permite el método POST en esta ruta',
                405
            );
        }
        break;

    case '/api/protected':
        if ($method === 'GET') {

            $user = authenticate(); // Middleware para validar el token

            if ($user){
                \helpers\ApiResponse::success(
                    [
                        'type' => 'protected',
                        'id' => uniqid(), // ID ficticio para la respuesta
                        'attributes' => [
                            'message' => 'Acceso permitido',
                            'user' => $user
                        ]
                    ],
                    'Acceso a la ruta protegida'
                );
            } else {
                \helpers\ApiResponse::error(
                    'Acceso denegado',
                    'Token no válido o expirado',
                    401
                );
            }

        } else {
            \helpers\ApiResponse::error(
                'Método no permitido',
                'Solo se permite el método GET en esta ruta',
                405
            );
        }
        break;

    default:
        \helpers\ApiResponse::error(
            'Ruta no encontrada',
            'La ruta solicitada no existe',
            404
        );
        break;
}