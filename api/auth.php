<?php
require_once '../config/database.php';
require_once '../helpers/ApiResponse.php';
require_once '../vendor/autoload.php';

use Firebase\JWT\JWT;

function handleLogin() {
    $db = new Database();
    $conn = $db->connect();

    // Leer y decodificar el cuerpo de la solicitud
    $input = json_decode(file_get_contents("php://input"), true);

    if (!isset($input['data']) || !isset($input['data']['attributes']['email']) || !isset($input['data']['attributes']['password'])) {
        \helpers\ApiResponse::error('Formato de solicitud inválido', 'El email y contraseña son requeridos.', 400);
        return;
    }

    $email = $input['data']['attributes']['email'];
    $password = $input['data']['attributes']['password'];

    $query = 'SELECT id, email, password, role, permissions, confirmed, disabled, token_version  FROM users WHERE email = :email LIMIT 1';
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':email', $email);

    if ($stmt->execute()) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Verificar si la cuenta está confirmada
            if ($user['confirmed'] == 0) {
                \helpers\ApiResponse::error(
                    'Cuenta no confirmada',
                    'Debes confirmar tu correo electrónico antes de iniciar sesión.',
                    403
                );
                return;
            }

            // Verificar si el usuario está deshabilitado
            if ($user['disabled'] == 1) {
                \helpers\ApiResponse::error(
                    'Cuenta deshabilitada',
                    'Tu cuenta ha sido deshabilitada. Contacta al administrador.',
                    403
                );
                return;
            }

            // Validar la contraseña
            if (password_verify($password, $user['password'])) {
                $permissions = [];
                if (!empty($user['role'])) {
                    $roleQuery = 'SELECT permissions FROM roles WHERE id = :roleId';
                    $roleStmt = $conn->prepare($roleQuery);
                    $roleStmt->bindParam(':roleId', $user['role']);
                    $roleStmt->execute();

                    $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
                    if ($role) {
                        $rolePermissions = json_decode($role['permissions'], true);
                        $permissions = array_merge($permissions, $rolePermissions);
                    }
                }

                if (!empty($user['permissions'])) {
                    $userPermissions = json_decode($user['permissions'], true);
                    $permissions = array_merge($permissions, $userPermissions);
                }

                $permissions = array_unique($permissions);

                if (!empty($permissions)) {
                    $placeholders = implode(',', array_fill(0, count($permissions), '?'));
                    $permQuery = "SELECT p.id, m.name AS module, p.actions 
                                  FROM permissions p 
                                  INNER JOIN modules m ON p.module_id = m.id 
                                  WHERE p.id IN ($placeholders)";
                    $permStmt = $conn->prepare($permQuery);
                    $permStmt->execute($permissions);

                    $detailedPermissions = $permStmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $detailedPermissions = [];
                }

                // Encriptar el array completo de permisos
                $encryptedPermissions = encryptPayload(json_encode($detailedPermissions));

                $config = include(__DIR__ . '/../config/jwt.php');

                $payload = [
                    'iss' => $config['issuer'],
                    'aud' => $config['audience'],
                    'iat' => time(),
                    'exp' => time() + $config['expiration'],
                    'userId' => $user['id'],
                    'email' => $user['email'],
                    'token_version' => $user['token_version'], // Versión del token
                    'permissions' => $encryptedPermissions // Enviar el array encriptado
                ];

                $secret_key = getenv('JWT_SECRET_KEY') ?: 'secret_key';

                $jwt = JWT::encode($payload, $config[$secret_key], 'HS256');

                \helpers\ApiResponse::success(
                    [
                        'type' => 'auth',
                        'id' => $user['id'],
                        'attributes' => [
                            'token' => $jwt,
                            'email' => $user['email'],
                            'permissions' => $encryptedPermissions
                        ]
                    ],
                    'Inicio de sesión exitoso',
                    200
                );
            } else {
                \helpers\ApiResponse::error('Credenciales inválidas', 'El email o la contraseña son incorrectos.', 401);
            }
        } else {
            \helpers\ApiResponse::error('Credenciales inválidas', 'El email o la contraseña son incorrectos.', 401);
        }
    } else {
        \helpers\ApiResponse::error('Error en el servidor', 'No se pudo procesar la solicitud.', 500);
    }
}