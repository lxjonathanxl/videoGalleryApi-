<?php
require_once __DIR__ . '/../src/Controllers/AuthController.php';

$router->addRoute('POST', '/register', function() {
    (new AuthController())->register();
});