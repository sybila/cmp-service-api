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
    public static function uploadFile(Request $request, Response $response, $expId){

        if(is_uploaded_file($_FILES["raw_data"]["tmp_name"])){
            $tmp_file = $_FILES["raw_data"]["tmp_name"];
            $target_dir = $path = "../file_system/experiments/exp_" . $expId;
            $target_file = $target_dir . "/raw_data.csv";
            $mimes = array('application/vnd.ms-excel','text/plain','text/csv','text/tsv');
            if (in_array($_FILES['raw_data']['type'],$mimes) && $_FILES['raw_data']['size'] < 100000
                && move_uploaded_file($tmp_file, $target_file)) {
                return self::formatOk($response, ['path' => $target_file]);
            }
        }
        self::formatError($response, 406, "File is not acceptable.");
    }

    public static function chooseReferentialVariable(Request $request, Response $response, $expId){
        $path = "../file_system/experiments/exp_" . $expId . "/raw_data.csv";
        $rows = array();
        if (($handle = fopen($path, "r")) !== FALSE){
            if(($data = fgetcsv($handle, 1000, ",")) !== FALSE){
                return self::formatOk($response, ["variables" => $data]);
            }
        }
        self::formatError($response, 404, "Can't read file raw_data.csv.");
    }

    public static function getRawData(Request $request, Response $response, $expId, $referentialVar, $count){
        $path = "../file_system/experiments/exp_" . $expId . "/raw_data.csv";
        if (($handle = fopen($path, "r")) !== FALSE){
            if(($header = fgetcsv($handle, 1000, ",")) !== FALSE){
                $refVarId = array_search($referentialVar, $header);
                $data = array("variables" => array());
                foreach ($header as $var){
                    if($var == $referentialVar) continue;
                    array_push($data["variables"], array("name" => $var, "values" => array()));
                }
                for($i  = 1; $i <= $count; $i++){
                    if(($vals = fgetcsv($handle, 1000, ",")) !== FALSE){
                        $counter = 0;
                        $var_counter = 0;
                        foreach ($vals as $val){
                            if($counter != $refVarId){
                                array_push($data["variables"][$var_counter]["values"], array("time" => $vals[$refVarId], "value" => $val));
                                $var_counter++;
                            }
                            $counter++;
                        }
                    } else{
                        break;
                    }
                }
            }
            return self::formatOk($response, $data);
        }
        return self::formatError($response, 404, "Can't read file raw_data.csv.");
    }

    public static function importData(Request $request, Response $response, $expId){
            $bodies = array();
            $urls = array();
            $vars_to_send = array();
            foreach ($request->getParsedBody()["variables"] as $var){
                $body = array("name" => $var["name"], "code" => $var["code"]);
                $rsp = DataApi::post("experiments/" . $expId . "/variables", json_encode($body));
                $vars_to_send[$var["name_in_file"]] = $rsp["id"];
            }
            $path = "../file_system/experiments/exp_" . $expId . "/raw_data.csv";
            if (($handle = fopen($path, "r")) !== FALSE){
                if(($header = fgetcsv($handle, 1000, ",")) !== FALSE){
                    $refVarId = array_search($request->getParsedBody()["ref_var"], $header);
                    ob_start();
                    ini_set('max_execution_time', '0');
                    while(($vals = fgetcsv($handle, 1000, ",")) !== FALSE){
                        foreach($request->getParsedBody()["variables"] as $var){
                            if(array_key_exists($refVarId, $vals) && array_key_exists($id = array_search($var["name_in_file"], $header), $vals)) {
                                $body = array("time" => $vals[$refVarId], "value" => $vals[$id]);
                                $bodies[] = $body;
                                $urls[] = "experiments/" . $expId . "/variables/" . $vars_to_send[$var["name_in_file"]] . "/values";
                                ob_flush();
                            }
                        }
                    }
                }
                $rsp = DataApi::multiPost($urls, $bodies, $request->getParsedBody()["skip_errors"]);
                ini_set('max_execution_time', '30');
                ob_end_flush();
                if($rsp['status'] == 'ok'){
                    return self::formatOk($response, $rsp['warnings']);
                }
                DataApi::delete("experiment/". $expId . "/data");
                return self::formatError($response, 500, "On line " . $rsp['error']['line']. ", time: "
                    .$rsp['error']['data']['time']. " ,value: " .$rsp['error']['data']['value']);
            }
            return self::formatError($response, 404, "Can't read file");
    }

    public static function createFolder(Request $request, Response $response, $expId){
        $path = "../file_system/experiments/exp_".$expId;
        if(!file_exists($path)){
            FileSystemManager::mkdir("../file_system/experiments", "exp_".$expId);
            chdir("../../app");
        }
        return self::formatOk($response, ['path' => $path]);
    }
}
