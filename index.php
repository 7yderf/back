<?php
$requestPath = $_SERVER['REQUEST_URI'];

// Verificar si la ruta coincide con una de las rutas especificadas en 'paths'

    // Aplicar las cabeceras CORS
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Content-Type: application/json');
    //Autorization

    


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

//importar el archivo con la funcion de envio de correo
require 'src/contact/contact.php';
require 'vendor/autoload.php';
// Importar la clase GuzzleHttp\Client
use GuzzleHttp\Client;


// Obtener el método HTTP de la solicitud
$method = $_SERVER['REQUEST_METHOD'];

// Obtener la ruta de la solicitud
$request = $_SERVER['REQUEST_URI'];

// Enrutamiento básico para manejar las diferentes solicitudes
switch ($method) {
    case 'GET':
      $response = handleGET($request);
      // Imprime el resultado en la salida
      echo $response;
        break;
    case 'POST':
        handlePOST($request);
        break;
    default:
        // Método no permitido
        http_response_code(405);
        echo json_encode(array('error' => 'Method Not Allowed'));
}

// Función para manejar solicitudes GET
function handleGET($request): string
{
   $request = str_replace('/back/', '', $request);
    
   return 'Hello World';
}

// Función para manejar solicitudes POST
function handlePOST($request)
{
    
    $input = json_decode(file_get_contents("php://input"), true);
    
    $response = [];

    if (!isset($input['first_name']) || !isset($input['email'])) {

        http_response_code(400);
        $response['data'] =  ['ERROR' => 'el nombre y el correo son obligatorios'];
        $response['status'] = 442;
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return;
        
    }

    $name = $input['first_name'];
    $email = $input['email'];

    if (empty($name) || empty($email)) {
        http_response_code(400);
        $response['data'] =  ['ERROR' => 'Los campos no pueden estar vacíos'];
        $response['status'] = 442;
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return;
    }

    $sendResult = sendContactEmail($name, $email, $input);

    if ($sendResult === true) {
         echo json_encode(['message' => 'El correo se envió correctamente'], JSON_PRETTY_PRINT);
         return;
    } else {
        echo json_encode(['error' => 'Hubo un error al enviar el correo: ' . $sendResult], JSON_PRETTY_PRINT);
        return;
    }
}

