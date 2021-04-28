<?php


namespace Controllers\Endpoints;


use Controllers\Abstracts\AbstractController;
use Slim\Http\Request;
use Slim\Http\Response;

//generate json for visualisation (model/ experiment)
class Visualizer extends AbstractController
{
    public static function getChart(Request $request, Response $response, $id, $model){
        $object = null;
//        if(!$model){
//            $object = new ExperimentChartData($request, $id);
//        } else{
//            return self::formatOk($response, ["Models not implemented."]);
//        }
        $object = new ExperimentChartData($request, $id);
        return self::formatOk($response, $object->getContentChart());
    }



}