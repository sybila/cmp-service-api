<?php

namespace Controllers\Endpoints;
use App\Exceptions\AccessForbiddenException;
use Controllers\Abstracts\AbstractController;
use DOMDocument;
use Libs\DataApi;
use Libs\FileSystemManager;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Slim\Http\Request;
use Slim\Http\Response;
use ZipArchive;

class ExportExperimentController extends ExperimentAccess
{
    /**
     * Prepare ZIP
     *
     * Upload csv file from body to experiment directory
     *
     * @param int $exp_id
     * @return void
     */
    private static function prepareZip(int $exp_id){
        $rootPath = realpath("../file_system/experiments/exp_".$exp_id."/images");
        $zip = new ZipArchive;
        if ($zip->open("../file_system/experiments/exp_".$exp_id."/cmp_exp" . $exp_id . ".zip", ZipArchive::CREATE) === TRUE) {
            $zip->addFile("../file_system/experiments/exp_".$exp_id."/metadata.xml", 'metadata.xml');
            $zip->addFile("../file_system/experiments/exp_".$exp_id."/data.csv", 'data.csv');
            if(file_exists($rootPath)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($rootPath),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($files as $name => $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($rootPath) + 1);
                        $zip->addFile($filePath, "images/" . $relativePath);
                    }
                }
            }
            $zip->close();
        }
    }

    /**
     * Export data
     *
     * create csv file with data
     *
     * @param Request $request
     * @param Response $response
     * @param $exp_id
     * @param $download
     * @return string access token
     * @throws AccessForbiddenException
     */
    public static function exportData(Request $request, Response $response, $exp_id, $download)
    {
        $header = [0 => 'time'];
        $data = [[]];
        $access_token = self::checkAccess($request, $exp_id);
        $vars = DataApi::get("experiments/". $exp_id. "/variables", $access_token);
        if($vars['status'] == 'ok') {
            $var_counter = 1;
            foreach ($vars['data'] as $var) {
                array_push($header, $var['name']);
                $vals = DataApi::get("experiments/" . $exp_id . "/variables/" . $var['id'] . "/values?sort[time]=asc", $access_token);
                foreach ($vals['data'] as $val) {
                    if(array_key_exists(strval($val['time']), $data)){
                        $data[strval($val['time'])][$var_counter] = $val['value'];
                    } else{
                        $row = array_fill(0, count($vars['data']), "");
                        $row[0] = $val['time'];
                        $row[$var_counter] =  $val['value'];
                        $data[strval($val['time'])] = $row;
                    }
                }
                $var_counter++;
            }
            $path = "../file_system/experiments";
            if(!file_exists($path . "/exp_".$exp_id)){
                FileSystemManager::mkdir($path, "exp_".$exp_id);
                chdir("../../app");
            }
            $fh = fopen($path . "/exp_" . $exp_id . "/data.csv", "w");
            fputcsv($fh, $header);
            foreach ($data as $key => $fields) {
                fputcsv($fh, $fields);
            }
            fclose($fh);
            if($download){
                FileSystemManager::downloadFile($path . "/exp_" . $exp_id . "/data.csv");
            }
            return self::formatOk($response, ['path' => $path . "/exp_" . $exp_id . "/data.csv"]);
        }
        return self::formatError($response, $data['code'][0], $data['message'][0]);
    }

    /**
     * Export all data about experiment
     *
     * create xml and csv file with data about experiment
     *
     * @param Request $request
     * @param Response $response
     * @param $exp_id
     * @return Response
     * @throws AccessForbiddenException
     */
    public static function exportExperiment(Request $request, Response $response, $exp_id)
    {
        $access_token = self::checkAccess($request, $exp_id);
        $data = DataApi::get("experiments/". $exp_id, $access_token);
        if($data['status'] == 'ok'){
            $xml= new DomDocument('0.1');
            $xml->formatOutput = true;
            $experiment=$xml->createElement("experiment");
            $xml->appendChild($experiment);
            $name=$xml->createElement("name", $data['data']['name']);
            $experiment->appendChild($name);
            $description=$xml->createElement("description", $data['data']['description']);
            $experiment->appendChild($description);
            $protocol=$xml->createElement("protocol", $data['data']['protocol']);
            $experiment->appendChild($protocol);
            $inserted=$xml->createElement("inserted", $data['data']['inserted']);
            $experiment->appendChild($inserted);
            $started=$xml->createElement("started", $data['data']['started']);
            $experiment->appendChild($started);
            $status=$xml->createElement("status", $data['data']['status']);
            $experiment->appendChild($status);
            $organism=$xml->createElement("organism");
            $organism->setAttribute('id', $data['data']['organism']['id']);
            $orgName=$xml->createElement("name", $data['data']['organism']['name']);
            $organism->appendChild($orgName);
            $orgCode=$xml->createElement("code", $data['data']['organism']['code']);
            $organism->appendChild($orgCode);
            $experiment->appendChild($organism);
            $devices=$xml->createElement("devices");
            $experiment->appendChild($devices);
            foreach ($data['data']['devices'] as $dev){
                $device = $xml->createElement("device");
                $device->setAttribute("id", $dev['id']);
                $devName = $xml->createElement("name", $dev['name']);
                $device->appendChild($devName);
                $devices->appendChild($device);

            }
            if(!file_exists("../file_system/experiments/exp_".$exp_id)){
                FileSystemManager::mkdir("../file_system/experiments", "exp_".$exp_id);
                chdir("../../app");
            }
            $xml->save("../file_system/experiments/exp_".$exp_id."/metadata.xml");
            self::exportData($request, $response, $exp_id, false);
            self::prepareZip($exp_id);
            FileSystemManager::downloadFile("../file_system/experiments/exp_" . $exp_id ."/cmp_exp" . $exp_id . ".zip");
            return self::formatOk($response, ['path' => "../file_system/experiments/exp_".$exp_id."/cmp_exp" . $exp_id . ".zip"]);
        }
        return self::formatError($response, $data['code'], $data['message']);
    }
}