<?php

namespace Controllers\Endpoints;
use App\Helpers\ArgumentParser;
use Controllers\Abstracts\AbstractController;
use Libs\DataApi;
use Libs\FileSystemManager;
use Libs\ReadFile;
use Slim\Http\Request;
use Slim\Http\Response;

class ImportExperimentController extends AbstractController
{
    public static function importData(Request $request, Response $response, $expId)
    {
        $path = "../file_system/experiments/exp_" . $expId . "/raw_data.csv";
        $rows = array();
        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line){
            $rows[] = str_getcsv($line);
        }
        print_r($rows);
        return self::formatOk($response,['path' => $path]);
    }

    public static function createFolder(Request $request, Response $response, $expId){
        $path = "../file_system/experiments/exp_".$expId;
        if(!file_exists($path)){
            FileSystemManager::mkdir("../file_system/experiments", "exp_".$expId);
            chdir("../../app");
        }
        return self::formatOk($response, ['path' => $path]);
    }

    /*
    public static function upload(Request $request, Response $response, $path){
        $file = $_FILES["file"];
        move_uploaded_file($file)

    }*/

    public static function readXml(Request $request, Response $response, $path)
    {
        return self::formatOk($response, ReadFile::readXmlFile($path));
    }
}
