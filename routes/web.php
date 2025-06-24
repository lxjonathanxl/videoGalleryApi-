<?php
require_once __DIR__ . '/../src/Controllers/AuthController.php';
require_once __DIR__ . '/../src/Controllers/DeviceController.php';


$router->addRoute('POST', '/register', function() {
    (new AuthController())->register();
});

$router->addRoute('POST', '/login', function() {
    (new AuthController())->login();
});

$router->addRoute('POST', '/devices/register',
 fn() => (new DeviceController())->registerDevice());

$router->addRoute('POST', '/devices/list',
 fn() => (new DeviceController())->getUserDevices());

$router->addRoute('POST', '/devices/videos',
 fn() => (new DeviceController())->getDeviceVideos());