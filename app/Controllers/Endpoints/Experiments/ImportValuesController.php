<?php

namespace Controllers\Endpoints;
use App\Helpers\ArgumentParser;
use Controllers\Abstracts\AbstractController;
use Libs\DataApi;
use Libs\ReadFile;
use Slim\Http\Request;
use Slim\Http\Response;

class ImportValuesController extends AbstractController
{
    public static function readJson(Request $request, Response $response, $path)
    {
        return self::formatOk($response, DataApi::get($path));
        //return self::formatOk($response, ReadFile::readJsonFile($path));
    }

    public static function readXml(Request $request, Response $response, $path)
    {
        return self::formatOk($response, ReadFile::readXmlFile($path));
    }
}
