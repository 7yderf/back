<?php

// CORS Headers
function setCorsHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
    header('Access-Control-Allow-Headers: Content-Type, Content-Range, Content-Disposition, Content-Description, Authorization, charset=utf-8');
    header('Content-Type: application/json');
}

// Verificar si la solicitud es OPTIONS
function handleOptionsRequest() {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// Conexión a la base de datos
function getDbConnection() {
    require '../src/db/bd.php';
    $db = new Database();
    return $db->connect();
}

// Obtener ruta de la solicitud
function getRequestPath() {
    return str_replace('/back', '', $_SERVER['REQUEST_URI']);
}

// Respuesta estándar en JSON
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Manejar solicitud GET
function handleGET() {
    global $conn;

    $path = explode('/', $_SERVER['REQUEST_URI']);
    $id = isset($path[2]) ? intval($path[2]) : null;

    if ($id) {
        $stmt = $conn->prepare("SELECT s.id, s.name, s.img, d.title AS description_title, d.text AS description_text 
                                FROM sellers s 
                                LEFT JOIN descriptions d ON s.id = d.seller_id 
                                WHERE s.id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $seller_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($seller_data) {
            $seller = [
                'id' => $seller_data[0]['id'],
                'name' => $seller_data[0]['name'],
                'img' => $seller_data[0]['img'],
                'description' => []
            ];

            foreach ($seller_data as $row) {
                if ($row['description_title']) {
                    $seller['description'][] = [
                        'description_title' => $row['description_title'],
                        'description_text' => $row['description_text']
                    ];
                }
            }

            jsonResponse($seller);
        } else {
            jsonResponse(['error' => 'Vendedor no encontrado'], 404);
        }
    } else {
        // Consulta para obtener todos los vendedores con sus descripciones
        $stmt = $conn->query("SELECT s.id, s.name, s.img, d.title AS description_title, d.text AS description_text
                              FROM sellers s
                              LEFT JOIN descriptions d ON s.id = d.seller_id
                              ORDER BY s.id");

        $sellers_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($sellers_data) {
            $sellers = [];
            $current_seller_id = null;
            $current_seller = null;

            foreach ($sellers_data as $row) {
                if ($current_seller_id !== $row['id']) {
                    if ($current_seller) {
                        $sellers[] = $current_seller;
                    }
                    $current_seller_id = $row['id'];
                    $current_seller = [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'img' => $row['img'],
                        'description' => []
                    ];
                }

                if ($row['description_title']) {
                    $current_seller['description'][] = [
                        'description_title' => $row['description_title'],
                        'description_text' => $row['description_text']
                    ];
                }
            }

            // Agregar el último vendedor a la lista
            if ($current_seller) {
                $sellers[] = $current_seller;
            }

            jsonResponse($sellers);
        } else {
            jsonResponse(['error' => 'No se encontraron vendedores'], 404);
        }
    }
}


setCorsHeaders();
handleOptionsRequest();


$conn = getDbConnection();
$request_method = $_SERVER['REQUEST_METHOD'];

switch ($request_method) {
    case 'GET':
        handleGET();
        break;
    default:
        jsonResponse(['error' => 'Método no permitido.'], 405);
        break;
}