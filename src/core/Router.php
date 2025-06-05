<?php
class Router {
    private $routes = [];
    
    public function addRoute(string $method,
     string $path, callable $handler,
     bool $requireAuth = false) {
        $this->routes[] = ['method' => $method,
         'path' => $path,
          'handler' => $handler,
        'requireAuth' => $requireAuth];
    }
    
    public function dispatch() {
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Remove base folder if needed
    $scriptName = dirname($_SERVER['SCRIPT_NAME']);
    if (strpos($requestUri, $scriptName) === 0) {
        $requestUri = substr($requestUri, strlen($scriptName));
    }
    $requestUri = '/' . trim($requestUri, '/');

    foreach ($this->routes as $route) {
        if ($route['method'] === $requestMethod && $route['path'] === $requestUri) {
            // Handle authentication
            if ($route['requireAuth']) {
                $userId = \App\Middleware\AuthMiddleware::authenticate();
                // Pass user ID to handler
                return call_user_func($route['handler'], $userId);
            }
            return call_user_func($route['handler']);
        }
    }

    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
    }
}