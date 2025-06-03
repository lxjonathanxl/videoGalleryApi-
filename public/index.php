<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/core/Router.php';

// Load environment variables
(Dotenv\Dotenv::createImmutable(__DIR__ . '/..'))->load();

// Initialize router
$router = new Router();

// Register routes
require __DIR__ . '/../routes/web.php';

// Dispatch the request
$router->dispatch();