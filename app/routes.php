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
	// main
	/*$app->get('/', function (Request $request, Response $response, $args) {
        return $response->withRedirect('/version');
	});*/

	// version
	$app->get('/version', Controllers\Endpoints\VersionController::class);


    $app->get('/importexperimentdata/{expId}', function (Request $request, Response $response, $args) {
        $expId = $args['expId'];
        return \Controllers\Endpoints\ImportExperimentController::importData($request, $response, $expId);
    });

    $app->get('/createfolder/{expId}', function (Request $request, Response $response, $args) {
        $expId = $args['expId'];
        return \Controllers\Endpoints\ImportExperimentController::createFolder($request, $response, $expId);
    });

    $app->get('/exportexperiment/{id}', function (Request $request, Response $response, $args) {
        $id = $args['id'];
        return \Controllers\Endpoints\ExportExperimentController::exportExperiment($request, $response, $id);
    });

    $app->get('/exportexperimentdata/{id}', function (Request $request, Response $response, $args) {
        $id = $args['id'];
        return \Controllers\Endpoints\ExportExperimentController::exportData($request, $response, $id, true);
    });

    $app->get('/download/{id}', function (Request $request, Response $response, $args) {
        $id = $args['id'];
        //$path = "../file_system/experiments/exp_".$id."/cmp_exp" . $id . ".zip";
        $path = "../file_system/experiments/exp_".$id."/data.csv";
        return \Controllers\Endpoints\ExportExperimentController::downloadFile($request, $response, $path);
    });
};
