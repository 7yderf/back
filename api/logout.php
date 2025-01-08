<?php
require_once '../helpers/ApiResponse.php';
require_once '../config/database.php';
require_once '../vendor/autoload.php';
require_once '../helpers/EnvLoader.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function handleLogout() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        \helpers\ApiResponse::error('Falta el token', 'No se proporcionó un token JWT válido.', 400);
        return;
    }

    $authHeader = $headers['Authorization'];
    list(, $jwt) = explode(' ', $authHeader);;
    $config = include(__DIR__ . '/../config/jwt.php');
    $decoded = JWT::decode($jwt, new Key($config['secret_key'], 'HS256'));

    $db = new Database();
    $conn = $db->connect();

    // Incrementar el `token_version`
    $query = 'UPDATE users SET token_version = token_version + 1 WHERE id = :userId';
    $stmt = $conn->prepare($query);
    $stmt->execute(['userId' => $decoded->userId]);

    \helpers\ApiResponse::success(
        ['message' => 'Sesión cerrada exitosamente'],
        'Logout exitoso',
        200
    );
}

