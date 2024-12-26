<?php
require_once '../helpers/EnvLoader.php';
require_once '../helpers/AuthMiddleware.php';
require_once '../helpers/ApiResponse.php';
require_once 'auth.php';
require_once 'register.php';


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

    case '/api/protected':
        if ($method === 'GET') {
            try {
                $user = authenticate(); // Middleware para validar el token
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
            } catch (Exception $e) {
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