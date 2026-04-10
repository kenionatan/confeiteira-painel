<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'LandingController::index');
$routes->get('/assinar/(:segment)', 'CheckoutController::redirect/$1');

$routes->group('painel', static function ($routes) {
    $routes->get('login', 'AuthController::login');
    $routes->post('login', 'AuthController::authenticate');
    $routes->get('cadastro', 'AuthController::register');
    $routes->get('cadastro/obrigado', 'AuthController::registerSuccess');
    $routes->post('cadastro', 'AuthController::store');
    $routes->get('logout', 'AuthController::logout');
});

$routes->group('painel', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'PainelController::index');
    $routes->get('clientes', 'ClientesController::index');
    $routes->get('clientes/lista-ajax', 'ClientesController::listaAjax');
    $routes->post('clientes/salvar', 'ClientesController::salvar');
});
