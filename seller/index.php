<?php

// CORS Headers
function setCorsHeaders() {
    header('Access-Control-Allow-Origin: http://localhost:3000');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
    header('Content-Type: application/json');
    header('Access-Control-Allow-Credentials: true');
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

// Procesar la imagen subida
function processUploadedImage($image) {

    $target_dir = "../uploads/sellers/";
    $target_file = $target_dir . basename($image["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Validar si es una imagen
    $check = getimagesize($image["tmp_name"]);
    if ($check === false) return ['error' => 'El archivo no es una imagen.'];

    // Validar tamaño de archivo (ej: máximo 5MB)
    if ($image["size"] > 5000000) return ['error' => 'El archivo es demasiado grande.'];

    // Validar tipo de archivo
    $allowed_types = ['jpg', 'png', 'jpeg', 'gif'];
    if (!in_array($imageFileType, $allowed_types)) return ['error' => 'Solo se permiten archivos JPG, JPEG, PNG y GIF.'];

    // Intentar mover el archivo
    if (move_uploaded_file($image["tmp_name"], $target_file)) {
        return ['img_url' => '/uploads/sellers/' . basename($image["name"])];
    } else {
        return ['error' => 'Hubo un error al subir la imagen.'];
    }
}

// Respuesta estándar en JSON
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Crear un nuevo vendedor
function createSeller($conn, $title, $img_url, $description) {
    try {
        $conn->beginTransaction();

        // Insertar vendedor
        $sql = "INSERT INTO sellers (name, img) VALUES (:name, :img)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':name', $title);
        $stmt->bindParam(':img', $img_url);
        $stmt->execute();
        $seller_id = $conn->lastInsertId();

        // Insertar descripciones
        if (!empty($description)) {
            $sql_desc = "INSERT INTO descriptions (seller_id, title, text) VALUES (:seller_id, :title, :text)";
            $stmt_desc = $conn->prepare($sql_desc);
            foreach ($description as $desc) {
                $stmt_desc->bindParam(':seller_id', $seller_id);
                $stmt_desc->bindParam(':title', $desc['title']);
                $stmt_desc->bindParam(':text', $desc['text']);
                $stmt_desc->execute();
            }
        }

        $conn->commit();
        jsonResponse(['message' => 'Vendedor creado exitosamente.']);
    } catch (PDOException $e) {
        $conn->rollBack();
        jsonResponse(['error' => 'Error al crear el vendedor: ' . $e->getMessage()], 500);
    }
}

// Actualizar un vendedor existente
function updateSeller($conn, $seller_id, $title, $img_url, $description) {
    try {
        $conn->beginTransaction();

        $update_fields = [];
        $params = [];

        if ($title !== null) {
            $update_fields[] = 'name = :name';
            $params[':name'] = $title;
        }

        if ($img_url !== null) {
            $update_fields[] = 'img = :img';
            $params[':img'] = $img_url;
        }

        if (!empty($update_fields)) {
            $sql = "UPDATE sellers SET " . implode(', ', $update_fields) . " WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $params[':id'] = $seller_id;
            $stmt->execute($params);
        }

        if ($description !== null) {
            $stmt = $conn->prepare("DELETE FROM descriptions WHERE seller_id = :seller_id");
            $stmt->execute([':seller_id' => $seller_id]);

            $sql_desc = "INSERT INTO descriptions (seller_id, title, text) VALUES (:seller_id, :title, :text)";
            $stmt_desc = $conn->prepare($sql_desc);
            foreach ($description as $desc) {
                $stmt_desc->bindParam(':seller_id', $seller_id);
                $stmt_desc->bindParam(':title', $desc['title']);
                $stmt_desc->bindParam(':text', $desc['text']);
                $stmt_desc->execute();
            }
        }

        $conn->commit();
        jsonResponse(['message' => 'Vendedor actualizado exitosamente.']);
    } catch (PDOException $e) {
        $conn->rollBack();
        jsonResponse(['error' => 'Error al actualizar el vendedor: ' . $e->getMessage()], 500);
    }
}

// Manejar solicitud POST (creación de vendedores)
function handlePOST() {
    global $conn;

    $path = explode('/', $_SERVER['REQUEST_URI']);
    $seller_id = isset($path[2]) ? intval($path[2]) : null;

    if (!isset($_POST['title'])) {
        jsonResponse(['error' => 'Faltan campos obligatorios. [seller]'], 400);
    }

    $title = $_POST['title'];
    $description = isset($_POST['description']) ? json_decode($_POST['description'], true) : [];
    if (!is_array($description)) jsonResponse(['error' => 'El campo description debe ser un array JSON.'], 400);

    $imgResult = null;

    // Si se proporciona una imagen, procesarla
    if (isset($_FILES['img'])) {
        $imgResult = processUploadedImage($_FILES['img']);
        if (isset($imgResult['error'])) jsonResponse(['error' => $imgResult['error']], 400);
    }

    if ($seller_id) {
        // Actualizar el vendedor existente
        updateSeller($conn, $seller_id, $title, isset($imgResult['img_url']) ? $imgResult['img_url'] : null, $description);
    } else {
        // Crear un nuevo vendedor
        createSeller($conn, $title, $imgResult['img_url'], $description);
    }
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

// Manejar solicitud DELETE
function handleDELETE() {
    global $conn;

    $path = explode('/', $_SERVER['REQUEST_URI']);
    $seller_id = isset($path[2]) ? intval($path[2]) : null;
    if (!$seller_id) jsonResponse(['error' => 'Se requiere un ID de vendedor para eliminar.'], 400);

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("SELECT img FROM sellers WHERE id = :id");
        $stmt->bindParam(':id', $seller_id);
        $stmt->execute();
        $seller = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$seller) {
            $conn->rollBack();
            jsonResponse(['error' => 'Vendedor no encontrado'], 404);
        }

        $stmt = $conn->prepare("DELETE FROM descriptions WHERE seller_id = :seller_id");
        $stmt->execute([':seller_id' => $seller_id]);

        $stmt = $conn->prepare("DELETE FROM sellers WHERE id = :id");
        $stmt->execute([':id' => $seller_id]);

        if (file_exists(".." . $seller['img'])) unlink(".." . $seller['img']);

        $conn->commit();
        jsonResponse(['message' => 'Vendedor eliminado correctamente.']);
    } catch (PDOException $e) {
        $conn->rollBack();
        jsonResponse(['error' => 'Error al eliminar el vendedor: ' . $e->getMessage()], 500);
    }
}

// Manejar solicitud PUT (actualización de vendedores)
function handlePUT() {
    global $conn;

    $path = explode('/', $_SERVER['REQUEST_URI']);
    $seller_id = isset($path[2]) ? intval($path[2]) : null;
    if (!$seller_id) jsonResponse(['error' => 'Se requiere un ID de vendedor para actualizar.'], 400);

    parse_str(file_get_contents("php://input"), $_PUT);

    $title = isset($_PUT['title']) ? $_PUT['title'] : null;
    $description = isset($_PUT['description']) ? json_decode($_PUT['description'], true) : null;
    if ($description && !is_array($description)) jsonResponse(['error' => 'El campo description debe ser un array JSON.'], 400);

    $img_url = null;
    if (isset($_FILES['img'])) {
        $imgResult = processUploadedImage($_FILES['img']);
        if (isset($imgResult['error'])) jsonResponse(['error' => $imgResult['error']], 400);
        $img_url = $imgResult['img_url'];
    }

    updateSeller($conn, $seller_id, $title, $img_url, $description);
}

function checkSession() {

    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['error' => 'No estás autenticado.'], 401);
        exit;
    }
}
// Ejecutar el flujo principal
session_start();
setCorsHeaders();
handleOptionsRequest();

checkSession();

$conn = getDbConnection();
$request_method = $_SERVER['REQUEST_METHOD'];

switch ($request_method) {
    case 'GET':
        handleGET();
        break;
    case 'POST':
        handlePOST();
        break;
    case 'DELETE':
        handleDELETE();
        break;
    default:
        jsonResponse(['error' => 'Método no permitido.'], 405);
        break;
}