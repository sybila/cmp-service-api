<?php

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

class RouteHelper
{

	const LIST = 0x01;
	const ADD = 0x04;
	const ALL = self::LIST | self::ADD;

	/** @var App */
	public static $app;

	/** @var string */
	private $path;

	/** @var string */
	private $className;

	/** @var int */
	private $mask = self::ALL;


	public function setRoute(string $className, string $path): RouteHelper
	{
		$this->className = $className;
		$this->path = $path;
		return $this;
	}


	public function setMask(int $mask): RouteHelper
	{
		$this->mask = $mask;
		return $this;
	}


	public function register(string $idName = 'id')
	{
		$routes = [];

		if ($this->mask & self::LIST) {
			$routes[] = $route = self::$app->get($this->path, $this->className . ':read');
		}

		if ($this->mask & self::ADD) {
			$routes[] = $route = self::$app->post($this->path, $this->className . ':add');
		}
	}

}

return function(App $app) {

	// version
	$app->get('/version', Controllers\Endpoints\VersionController::class);

    /**
     * Experiments
     */
    //Import
    $app->post('/experiment/importdata/{expId}', function (Request $request, Response $response, $args) {
        $expId = $args['expId'];
        return \Controllers\Endpoints\ImportExperimentController::importData($request, $response, $expId);
    });

    $app->post('/experiment/uploadfile/{expId}', function (Request $request, Response $response, $args) {
        $expId = $args['expId'];
        return \Controllers\Endpoints\ImportExperimentController::uploadFile($request, $response, $expId);
    });

    $app->get('/experiment/rawdata/{expId}/{referentialVar}/{count}', function (Request $request, Response $response, $args) {
        $expId = $args['expId'];
        $referentialVar = $args['referentialVar'];
        $count = $args['count'];
        return \Controllers\Endpoints\ImportExperimentController::getRawData($request, $response, $expId, $referentialVar, $count);
    });

    $app->get('/experiment/readheader/{expId}', function (Request $request, Response $response, $args) {
        $expId = $args['expId'];
        return \Controllers\Endpoints\ImportExperimentController::readHeader($request, $response, $expId);
    });

    $app->post('/experiment/createfolder/{expId}', function (Request $request, Response $response, $args) {
        $expId = $args['expId'];
        return \Controllers\Endpoints\ImportExperimentController::createFolder($request, $response, $expId);
    });

    //Export
    $app->get('/experiment/export/{id}', function (Request $request, Response $response, $args) {
        $id = $args['id'];
        return \Controllers\Endpoints\ExportExperimentController::exportExperiment($request, $response, $id);
    });

    $app->get('/experiment/exportdata/{id}', function (Request $request, Response $response, $args) {
        $id = $args['id'];
        return \Controllers\Endpoints\ExportExperimentController::exportData($request, $response, $id, true);
    });

    $app->get('/convertUnit/{unitFromName}/{unitToName}', function (Request $request, Response $response, $args) {
        return \Controllers\Endpoints\ConvertUnitsController::fromUnitToUnit($request, $response, $args);
    });

    $app->get('/visualizer/{model}/{id}', function (Request $request, Response $response, $args) {
        $id = $args['id'];
        $isModel = $args['model'];
        /*dump(\Controllers\Endpoints\Visualizer::getChart($request,
            $response->withHeader('Content-type', 'application/json'), $id, $isModel)->getHeader('Content-type')); exit;*/
        return \Controllers\Endpoints\Visualizer::getChart($request,
            $response->withHeader('Content-type', 'application/json'), $id, $isModel);
    });

    $app->get('/analysis', function (Request $request, Response $response, $args){
        return \Controllers\Endpoints\AnalysisManager::responseListofAnalysis($response);
    });

    $app->get('/analysisPrescription/{name}', function (Request $request, Response $response, $args){
        $name = $args['name'];
        return \Controllers\Endpoints\AnalysisManager::responsePrescription($response->withHeader('Content-type', 'application/json'),
            $name);
    });

    $app->post('/runAnalysis/{name}', function (Request $request, Response $response, $args){
        $name = $args['name'];
        return \Controllers\Endpoints\AnalysisManager::responseRunAnalysis($response, $request, $name);
    });
};
