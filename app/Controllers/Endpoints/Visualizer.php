<?php


namespace Controllers\Endpoints;


use Controllers\Abstracts\AbstractController;
use ModelChartData;
use Slim\Http\Request;
use Slim\Http\Response;

//generate json for visualisation (model/ experiment)
class Visualizer extends AbstractController
{
    public static function getChart(Request $request, Response $response, $id, $model){
        $object = null;
        if(!$model){
            $object = new ExperimentChartData($request, $id);
        } else {
            $modelCh = new ModelChartData($request, 0);
            return self::formatOk($response, $modelCh->getContentChart());
        }
        return self::formatOk($response, $object->getContentChart());
    }



}