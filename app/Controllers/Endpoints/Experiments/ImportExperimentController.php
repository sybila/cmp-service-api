<?php

namespace Controllers\Endpoints;
use App\Exceptions\AccessForbiddenException;
use Controllers\Abstracts\AbstractController;
use Libs\DataApi;
use Libs\FileSystemManager;
use Slim\Http\Request;
use Slim\Http\Response;

class ImportExperimentController extends AbstractController
{

    /**
     * Check access by access token
     *
     * Throw error if user doesn't have access else return access token
     *
     * @param Request $request
     * @param $exp_id
     * @return string access token
     * @throws AccessForbiddenException
     */
    private static function checkAccess(Request $request, $exp_id){
        $access_token = $request->getHeader("HTTP_AUTHORIZATION");
        if(empty($access_token)){
            throw new AccessForbiddenException("Guest can't import.");
        }
        $access = DataApi::get("/experiments/". $exp_id, $access_token[0]);
        if($access['status'] !== 'ok'){
            throw new AccessForbiddenException("Not authorized.");
        }
        return $access_token[0];
    }

    /**
     * Upload file
     *
     * Upload csv file from body to experiment directory
     *
     * @param Request $request
     * @param Response $response
     * @param $exp_id
     * @return Response
     * @throws AccessForbiddenException
     */
    public static function uploadFile(Request $request, Response $response, $exp_id){
        self::checkAccess($request, $exp_id);
        if(is_uploaded_file($_FILES["raw_data"]["tmp_name"])){
            $tmp_file = $_FILES["raw_data"]["tmp_name"];
            $target_file = $path = "../file_system/experiments/exp_" . $exp_id . "/raw_data.csv";
            $mimes = ['application/vnd.ms-excel','text/plain','text/csv','text/tsv'];
            if (in_array($_FILES['raw_data']['type'],$mimes) && $_FILES['raw_data']['size'] < 100000
                && move_uploaded_file($tmp_file, $target_file)) {
                return self::formatOk($response, ['path' => $target_file]);
            }
        }
        self::formatError($response, 406, "File is not acceptable.");
    }

    /**
     * Read header
     *
     * Read variables names in header of raw_data.csv file
     *
     * @param Request $request
     * @param Response $response
     * @param $exp_id
     * @return Response
     * @throws AccessForbiddenException
     */
    public static function readHeader(Request $request, Response $response, $exp_id){
        self::checkAccess($request, $exp_id);
        $path = "../file_system/experiments/exp_" . $exp_id . "/raw_data.csv";
        $rows = array();
        if (($handle = fopen($path, "r")) !== FALSE){
            if(($data = fgetcsv($handle, 1000, ",")) !== FALSE){
                return self::formatOk($response, ["variables" => $data]);
            }
        }
        return self::formatError($response, 404, "Can't read file raw_data.csv.");
    }

    /**
     * Read raw_data.csv
     *
     * return data from raw_data.csv formatted in json
     *
     * @param Request $request
     * @param Response $response
     * @param $exp_id
     * @param $ref_var
     * @param $count
     * @return Response
     * @throws AccessForbiddenException
     */
    public static function getRawData(Request $request, Response $response, $exp_id, $ref_var, $count){
        self::checkAccess($request, $exp_id);
        $path = "../file_system/experiments/exp_" . $exp_id . "/raw_data.csv";
        if (($handle = fopen($path, "r")) !== FALSE){
            if(($header = fgetcsv($handle, 1000, ",")) !== FALSE){
                $ref_var_id = array_search($ref_var, $header);
                $data = array("variables" => array());
                foreach ($header as $var){
                    if($var == $ref_var) continue;
                    array_push($data["variables"], array("name" => $var, "values" => array()));
                }
                for($i  = 1; $i <= $count; $i++){
                    if(($vals = fgetcsv($handle, 1000, ",")) !== FALSE){
                        $counter = 0;
                        $var_counter = 0;
                        foreach ($vals as $val){
                            if($counter != $ref_var_id ){
                                array_push($data["variables"][$var_counter]["values"], array("time" => $vals[$ref_var_id ], "value" => $val));
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

    /**
     * Import variables
     *
     * return import variables from raw_data.csv
     *
     * @param Request $request
     * @param Response $response
     * @param $exp_id
     * @return array
     * @throws AccessForbiddenException
     */
    private static function importVariables(Request $request, Response $response, $exp_id){
        $vars_to_send = [];
        $access_token = self::checkAccess($request, $exp_id);
        foreach ($request->getParsedBody()["variables"] as $var){
            $body = ["name" => $var["name"], "code" => $var["code"]];
            $rsp = DataApi::post("experiments/" . $exp_id . "/variables", json_encode($body), $access_token);
            $vars_to_send[$var["name_in_file"]] = $rsp["id"];
        }
        return $vars_to_send;
    }


    /**
     * Import values
     *
     * import values from raw_data.csv
     *
     * @param Request $request
     * @param Response $response
     * @param $exp_id
     * @return Response
     * @throws AccessForbiddenException
     */
    public static function importData(Request $request, Response $response, $exp_id){
            ini_set('max_execution_time', '0');
            $access_token = self::checkAccess($request, $exp_id);
            $vars_to_send = self::importVariables($request, $response, $exp_id);
            $path = "../file_system/experiments/exp_" . $exp_id . "/raw_data.csv";
            $rsp = ['warnings'=>[]];
            if (($handle = fopen($path, "r")) !== FALSE){
                if(($header = fgetcsv($handle, 1000, ",")) !== FALSE){
                    $ref_var_id = array_search($request->getParsedBody()["ref_var"], $header);
                    $var_ids =[];
                    foreach($request->getParsedBody()["variables"] as $var) {
                         !($var_id = array_search($var["name_in_file"], $header)) ?: $var_ids[$var["name_in_file"]] = $var_id;
                    }
                    $vals = fgetcsv($handle, 1000, ",");
                    while($vals !== FALSE){
                        $bodies = []; $urls = [];
                        for($i = 0; $i < 200; $i++) {
                            foreach ($var_ids as $var_name => $var_id) {
                                if (array_key_exists($ref_var_id, $vals) && array_key_exists($var_id, $vals)) {
                                    $bodies[] = ["time" => $vals[$ref_var_id], "value" => $vals[$var_id]];
                                    $urls[] = "experiments/" . $exp_id . "/variables/" . $vars_to_send[$var_name] . "/values";
                                }
                            }
                            if (($vals = fgetcsv($handle, 1000, ",")) === false){
                                break;
                            }
                        }
                        $rsp = DataApi::multiPost($urls, $bodies, $request->getParsedBody()["skip_errors"], $access_token);
                        if($rsp['status'] != 'ok'){
                            DataApi::delete("experiment/". $exp_id . "/data", $access_token);
                            return self::formatError($response, 500, "On line " . $rsp['error']['line']. ", time: "
                                .$rsp['error']['data']['time']. " ,value: " .$rsp['error']['data']['value']);
                        }
                    }
                    ini_set('max_execution_time', '30');
                    return self::formatOk($response, $rsp['warnings']);
                }
            }
            DataApi::delete("experiment/". $exp_id . "/data", $access_token);
            return self::formatError($response, 404, "Can't read file");
    }

    /**
     * Create folder
     *
     * create folder for experiment with id
     *
     * @param Request $request
     * @param Response $response
     * @param $exp_id
     * @return Response
     * @throws AccessForbiddenException
     */
    public static function createFolder(Request $request, Response $response, $exp_id){
        self::checkAccess($request, $exp_id);
        $path = "../file_system/experiments/exp_".$exp_id;
        if(!file_exists($path)){
            FileSystemManager::mkdir("../file_system/experiments", "exp_".$exp_id);
            chdir("../../app");
        }
        return self::formatOk($response, ['path' => $path]);
    }
}
