<?php
class Router {
    private $routes = [];
    
    public function addRoute(string $method, string $path, callable $handler) {
        $this->routes[] = ['method' => $method, 'path' => $path, 'handler' => $handler];
    }
    
    public function dispatch() {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        foreach ($this->routes as $route) {
            if ($route['method'] === $requestMethod && $route['path'] === $requestUri) {
                return call_user_func($route['handler']);
            }
        }
        
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
    }
}