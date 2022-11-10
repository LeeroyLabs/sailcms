<?php

use SailCMS\Contracts\AppController;
use SailCMS\Routing\Router;

class TestController extends AppController
{
    public function test() { }
}

beforeEach(function ()
{
    Router::init();
    \SailCMS\Locale::setCurrent('en', true);
});

test('Create a route', function ()
{
    $router = new Router();
    $router->get('/test/:id/:string/:any', 'fr', TestController::class, 'test', 'test');

    $routes = Router::getAll('get');
    expect($routes->length)->toBeGreaterThanOrEqual(1);
});

test('Get URL of route and replace dynamic parts', function ()
{
    $router = new Router();
    $router->get('/test/:id/:string/:any', 'fr', TestController::class, 'test', 'test');

    $routes = Router::getAll('get');
    $route = $routes->at(0);

    $url = $route->getURL('xxxxxx', 'super-string', 'whatever-here-just-testing');

    expect($url)->toBe('/test/xxxxxx/super-string/whatever-here-just-testing');
});

test('Get alternate route', function ()
{
    $router = new Router();
    $router->get('/test1/:id/:string/:any', 'fr', TestController::class, 'test', 'test');
    $router->get('/test2/:id/:string/:any', 'en', TestController::class, 'test', 'test');

    $routes = Router::getAll('get');
    $route = $routes->at(0);

    $routes = $router->alternate($route);
    expect($routes->length)->toBe(1);
});

test('Get all named routes', function ()
{
    $router = new Router();
    $router->get('/test1/:id/:string/:any', 'fr', TestController::class, 'test', 'test');
    $router->get('/test2/:id/:string/:any', 'en', TestController::class, 'test', 'test');

    $routes = $router->routesByName('test');

    expect($routes->length)->toBe(2);
});

test('Get by name, method and language', function ()
{
    $router = new Router();
    $router->get('/test1/:id/:string/:any', 'fr', TestController::class, 'test', 'test');
    $router->get('/test2/:id/:string/:any', 'en', TestController::class, 'test', 'test');

    $url = Router::getBy('test', 'get', 'fr', ['super-id', 'a-string', 'anything']);

    expect($url)->toBe('/test1/super-id/a-string/anything');
});

test('Get All by name, method', function ()
{
    $router = new Router();
    $router->get('/test1/:id/:string/:any', 'fr', TestController::class, 'test', 'test');
    $router->get('/test2/:id/:string/:any', 'en', TestController::class, 'test', 'test');

    $urls = Router::getAllBy('test', 'get', ['super-id', 'a-string', 'anything']);
    expect($urls->length)->toBe(2);
});