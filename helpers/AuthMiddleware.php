<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function authenticate() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Token no proporcionado']);
        exit;
    }

    $authHeader = $headers['Authorization'];
    list(, $jwt) = explode(' ', $authHeader);

    $config = include(__DIR__ . '/../config/jwt.php');

    try {
        $decoded = JWT::decode($jwt, new Key($config['secret_key'], 'HS256'));
        return $decoded;
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Token inválido: ' . $e->getMessage()]);
        exit;
    }
}
