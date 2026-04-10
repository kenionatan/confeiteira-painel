<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'LandingController::index');
$routes->get('/assinar/(:segment)', 'CheckoutController::redirect/$1');
$routes->post('webhooks/stripe', 'StripeWebhookController::stripe');
$routes->post('provisioning/callback', 'ProvisioningController::callback');

$routes->group('painel', static function ($routes) {
    $routes->get('login', 'AuthController::login');
    $routes->post('login', 'AuthController::authenticate');
    $routes->get('cadastro', 'AuthController::register');
    $routes->get('cadastro/obrigado', 'AuthController::registerSuccess');
    $routes->post('cadastro', 'AuthController::store');
    $routes->post('cadastro/pagamento', 'AuthController::paymentPrepare');
    $routes->post('cadastro/confirmar', 'AuthController::paymentConfirm');
    $routes->get('logout', 'AuthController::logout');
});

$routes->group('painel', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'PainelController::index');
    $routes->get('clientes', 'ClientesController::index');
    $routes->get('clientes/lista-ajax', 'ClientesController::listaAjax');
    $routes->get('clientes/(:num)/detalhes', 'ClientesController::detalhes/$1');
});
