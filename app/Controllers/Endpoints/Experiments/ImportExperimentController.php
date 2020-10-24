<?php

namespace Controllers\Endpoints;
use App\Exceptions\AccessForbiddenException;
use App\Exceptions\OperationFailedException;
use Controllers\Abstracts\AbstractController;
use Libs\DataApi;
use Libs\FileSystemManager;
use Slim\Http\Request;
use Slim\Http\Response;

class ImportExperimentController extends ExperimentAccess
{


    /**
     * Check access by access token
     *
     * Throw error if user doesn't have access else return access token
     *
     * @param Request $request
     * @param  $exp_id
     * @return string access token
     * @throws AccessForbiddenException
     * @throws OperationFailedException
     */
    protected static function checkAccess(Request $request, $exp_id)
    {
        $access_token = $request->getHeader('HTTP_AUTHORIZATION');
        if (empty($access_token)) {
            throw new AccessForbiddenException("Guest can't import.");
        }

        $access = DataApi::get('/experiments/'.$exp_id, $access_token[0]);
        if(!$access){
            throw new OperationFailedException("Authorization.");
        }
        if ($access['status'] !== 'ok') {
            throw new AccessForbiddenException('Not authorized.');
        }

        return $access_token[0];

    }//end checkAccess()


    /**
     * Upload file
     *
     * Upload csv file from body to experiment directory
     *
     * @param Request $request
     * @param Response $response
     * @param  $exp_id
     * @return Response
     * @throws AccessForbiddenException
     * @throws OperationFailedException
     */
    public static function uploadFile(Request $request, Response $response, $exp_id)
    {
        self::checkAccess($request, $exp_id);
        if (is_uploaded_file($_FILES['raw_data']['tmp_name'])) {
            $tmp_file    = $_FILES['raw_data']['tmp_name'];
            $target_file = $path = '../file_system/experiments/exp_'.$exp_id.'/raw_data.csv';
            $mimes       = [
                'application/vnd.ms-excel',
                'text/plain',
                'text/csv',
                'text/tsv',
            ];
            if (in_array($_FILES['raw_data']['type'], $mimes) && $_FILES['raw_data']['size'] < 100000
                && move_uploaded_file($tmp_file, $target_file)
            ) {
                return self::formatOk($response, ['path' => $target_file]);
            }
        }

        self::formatError($response, 406, 'File is not acceptable.');

    }//end uploadFile()


    /**
     * Read header
     *
     * Read variables names in header of raw_data.csv file
     *
     * @param Request $request
     * @param Response $response
     * @param integer $expId
     * @return Response
     * @throws AccessForbiddenException
     * @throws OperationFailedException
     */
    public static function readHeader(Request $request, Response $response, $expId)
    {
        self::checkAccess($request, $expId);
        $path = '../file_system/experiments/exp_'.$expId.'/raw_data.csv';
        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            if (($data = fgetcsv($handle, 1000, ',')) !== false) {
                return self::formatOk($response, ['variables' => $data]);
            }
        }

        return self::formatError($response, 404, "Can't read file raw_data.csv.");

    }//end readHeader()


    /**
     * Read raw_data.csv
     *
     * return data from raw_data.csv formatted in json
     *
     * @param Request $request
     * @param Response $response
     * @param  $exp_id
     * @param  $ref_var
     * @param  $count
     * @return Response
     * @throws AccessForbiddenException
     * @throws OperationFailedException
     */
    public static function getRawData(Request $request, Response $response, $exp_id, $ref_var, $count)
    {
        self::checkAccess($request, $exp_id);
        $path = '../file_system/experiments/exp_'.$exp_id.'/raw_data.csv';
        if (($handle = fopen($path, 'r')) !== false) {
            if (($header = fgetcsv($handle, 1000, ',')) !== false) {
                $ref_var_id = array_search($ref_var, $header);
                $data       = ['variables' => []];
                foreach ($header as $var) {
                    if ($var == $ref_var) {
                        continue;
                    }

                    array_push($data['variables'], ['name' => $var, 'values' => []]);
                }

                for ($i  = 1; $i <= $count; $i++) {
                    if (($vals = fgetcsv($handle, 1000, ',')) !== false) {
                        $counter     = 0;
                        $var_counter = 0;
                        foreach ($vals as $val) {
                            if ($counter != $ref_var_id) {
                                array_push(
                                    $data['variables'][$var_counter]['values'],
                                    ['time' => $vals[$ref_var_id], 'value' => $val]
                                );
                                $var_counter++;
                            }

                            $counter++;
                        }
                    } else {
                        break;
                    }
                }
            }//end if

            return self::formatOk($response, $data);
        }//end if

        return self::formatError($response, 404, "Can't read file raw_data.csv.");

    }//end getRawData()


    /**
     * Import variables
     *
     * return import variables from raw_data.csv
     *
     * @param Request $request
     * @param  $exp_id
     * @param $access_token
     * @return array
     * @throws \Exception
     */
    private static function importVariables(Request $request, $exp_id, $access_token)
    {
        $vars_to_send = [];
        foreach ($request->getParsedBody()['variables'] as $var) {
            $body = [
                'name' => $var['name'],
                'code' => $var['code'],
            ];
            $rsp  = DataApi::post('experiments/'.$exp_id.'/variables', json_encode($body), $access_token);
            $vars_to_send[$var['name_in_file']] = $rsp['id'];
        }

        return $vars_to_send;

    }//end importVariables()


    /**
     * Import values
     *
     * import values from raw_data.csv
     *
     * @param Request $request
     * @param Response $response
     * @param  $exp_id
     * @return Response
     * @throws AccessForbiddenException
     * @throws OperationFailedException
     */
    public static function importData(Request $request, Response $response, $exp_id)
    {
            ini_set('max_execution_time', '0');
            $access_token = self::checkAccess($request, $exp_id);
            $vars_to_send = self::importVariables($request, $exp_id, $access_token);
            $path         = '../file_system/experiments/exp_'.$exp_id.'/raw_data.csv';
            $rsp          = ['warnings' => []];
        if (($handle = fopen($path, 'r')) !== false) {
            if (($header = fgetcsv($handle, 1000, ',')) !== false) {
                $ref_var_id = array_search($request->getParsedBody()['ref_var'], $header);
                $var_ids    = [];
                foreach ($request->getParsedBody()['variables'] as $var) {
                     !($var_id = array_search($var['name_in_file'], $header)) ?: $var_ids[$var['name_in_file']] = $var_id;
                }

                $vals = fgetcsv($handle, 1000, ',');
                while ($vals !== false) {
                    $bodies = [];
                    $urls   = [];
                    for ($i = 0; $i < 30; $i++) {
                        foreach ($var_ids as $var_name => $var_id) {
                            if (array_key_exists($ref_var_id, $vals) && array_key_exists($var_id, $vals)) {
                                $body = [
                                    'time'  => $vals[$ref_var_id],
                                    'value' => $vals[$var_id],
                                ];
                                $url   = 'experiments/'.$exp_id.'/variables/'.$vars_to_send[$var_name].'/values';
                                DataApi::post($url, json_encode($body), $access_token);
                            }
                        }

                        if (($vals = fgetcsv($handle, 1000, ',')) === false) {
                            break;
                        }
                    }

                    //$rsp = DataApi::multiPost($urls, $bodies, $request->getParsedBody()['skip_errors'], $access_token);
                    if ($rsp['status'] != 'ok') {
                        DataApi::delete('experiment/'.$exp_id.'/data', $access_token);
                        return self::formatError(
                            $response,
                            500,
                            'On line '.$rsp['error']['line'].', time: '.$rsp['error']['data']['time'].' ,value: '.$rsp['error']['data']['value']
                        );
                    }
                }//end while

                ini_set('max_execution_time', '30');
                return self::formatOk($response, $rsp['warnings']);
            }//end if
        }//end if

            DataApi::delete('experiment/'.$exp_id.'/data', $access_token);
            return self::formatError($response, 404, "Can't read file");

    }//end importData()


    /**
     * Create folder
     *
     * create folder for experiment with id
     *
     * @param Request $request
     * @param Response $response
     * @param  $exp_id
     * @return Response
     * @throws AccessForbiddenException
     * @throws OperationFailedException
     */
    public static function createFolder(Request $request, Response $response, $exp_id)
    {
        self::checkAccess($request, $exp_id);
        $path = '../file_system/experiments/exp_'.$exp_id;
        if (!file_exists($path)) {
            FileSystemManager::mkdir('../file_system/experiments', 'exp_'.$exp_id);
            chdir('../../app');
        }

        return self::formatOk($response, ['path' => $path]);

    }//end createFolder()


}//end class
