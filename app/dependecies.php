<?php

use App\Exceptions\ApiException;
use App\Helpers;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

$c = new Container();
unset($c['errorHandler']);
unset($c['phpErrorHandler']);
unset($c['view']);
unset($c['logger']);

/*$c['foundHandler'] = function (Container $c) {
	return new Helpers\RequestResponseParsedArgs;
};*/

$c['notFoundHandler'] = function (Container $c) {
	return function (Request $request, Response $response) {
		return $response->withStatus(404)->withJson([
				'status' => 'error',
				'message' => 'Page not found',
				'code' => 404,
		]);
	};
};

$c['notAllowedHandler'] = function (Container $c) {
	return function (Request $request, Response $response, array $allowedHttpMethods) {
		return $response->withStatus(405)->withJson([
				'status' => 'error',
				'code' => 405,
				'message' => 'Allowed methods: ' . implode(', ', $allowedHttpMethods),
				'methods' => $allowedHttpMethods,
		]);
	};
};

$c['errorHandler'] = function (Container $c) {
	return function (Request $request, Response $response, \Throwable $exception) {
		if ($exception instanceof ApiException)
			return $response->withStatus($exception->getHttpCode())->withJson([
					'status' => 'error',
					'code' => $exception->getCode(),
					'message' => $exception->getMessage(),
					] + $exception->getAdditionalData());

		if (!\Tracy\Debugger::$productionMode)
			throw $exception;

		\Tracy\Debugger::log($exception);
		return $response->withStatus(500)->withJson([
				'status' => 'error',
				'code' => 500,
				'message' => '',
		]);
	};
};

return $c;
