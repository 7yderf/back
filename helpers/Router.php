<?php

namespace helpers;

class Router
{
    private $routes = [];

    public function register($method, $path, $handler)
    {
        $this->routes[] = compact('method', 'path', 'handler');
    }

    public function resolve($method, $uri)
    {
        foreach ($this->routes as $route) {
            if ($method === $route['method'] && $uri === $route['path']) {
                require_once __DIR__ . '/../api/v1/' . $route['handler'];
                return;
            }
        }

        http_response_code(404);
        echo json_encode(['error' => 'Ruta no encontrada']);
    }
}
