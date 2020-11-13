<?php
/*require '../vendor/autoload.php';

$app = new \Slim\App();
$app->get('/{name}', function (Slim\Http\Request $request, Slim\Http\Response $response, array $args) {
    $response->getBody()->write("Hello ". $args['name']);
    return $response;
});

$app->run();*/
use Slim\Http\Request;
use Slim\Http\Response;

require __DIR__ . '/../vendor/autoload.php';

$app = new \Slim\App(require __DIR__ . '/../app/dependecies.php');
(require __DIR__ . '/../app/routes.php')($app);

$app->add(function ($req, $res, $next) {
    $response = $next($req, $res);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'DNT, User-Agent, X-Requested-With, If-Modified-Since, Cache-Control, Content-Type, Range, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, HEAD');
});

$app->add(function (Request $request, Response $response, callable $next) {
    /** @var Response $response */
    $response = $next($request, $response);

    if (!\Tracy\Debugger::$productionMode) {
        $json = json_decode((string)$response->getBody());
        $body = new \Slim\Http\Body(fopen('php://temp', 'r+'));
        $body->write('<pre>' . json_encode($json, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-type', 'text/html')->withBody($body);
    } else {
        $runtime = round(\Tracy\Debugger::timer('execution') * 1000, 3);
        return $response->withHeader('X-Run-Time', $runtime . 'ms');
    }
});

$app->run();