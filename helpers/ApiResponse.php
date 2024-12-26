<?php
namespace helpers;

class ApiResponse {
    /**
     * Respuesta exitosa.
     *
     * @param array|object $data Datos principales de la respuesta.
     * @param string $message Mensaje adicional para la meta.
     * @param int $statusCode Código HTTP, por defecto 200.
     * @param string|null $type Tipo de recurso (opcional).
     */
    public static function success($data, $message = '', $statusCode = 200, $type = null) {
        http_response_code($statusCode);

        $response = [
            'data' => is_array($data) && isset($data['id']) && $type
                ? [
                    'type' => $type,
                    'id' => $data['id'],
                    'attributes' => $data
                ]
                : $data,
            'meta' => [
                'message' => $message
            ]
        ];

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Respuesta de error.
     *
     * @param string $title Título corto del error.
     * @param string|null $detail Detalle específico del error.
     * @param int $statusCode Código HTTP, por defecto 400.
     */
    public static function error($title, $detail = null, $statusCode = 400) {
        http_response_code($statusCode);

        $response = [
            'errors' => [
                [
                    'status' => (string)$statusCode,
                    'title' => $title,
                    'detail' => $detail
                ]
            ]
        ];

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}