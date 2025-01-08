<?php
require_once '../helpers/ApiResponse.php';
require_once '../helpers/ApiResponse.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function authenticate() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        throw new Exception('Falta el token JWT.');
    }

    try{
        $authHeader = $headers['Authorization'];
        list(, $jwt) = explode(' ', $authHeader);
        $config = include(__DIR__ . '/../config/jwt.php');
        $headers1 = ['HS256'];
        $secret_key = getenv('JWT_SECRET_KEY') ?: 'secret_key';
        $decoded = JWT::decode($jwt, new Key($config['secret_key'], 'HS256'));
        $db = new Database();
        $conn = $db->connect();

        // Verificar el estado del usuario
        $query = 'SELECT token_version, disabled FROM users WHERE id = :userId LIMIT 1';
        $stmt = $conn->prepare($query);
        $stmt->execute(['userId' => $decoded->userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception('Usuario no encontrado.');
        }

        if ($user['disabled'] == 1) {
            throw new Exception('Cuenta deshabilitada.');
        }

        if ($user['token_version'] !== $decoded->token_version) {
            throw new Exception('Token inválido o desactualizado.');
        }

        return $decoded; // Retornar el payload del JWT
    } catch (Exception $e) {
        return null;
    }


}

