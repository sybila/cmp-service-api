<?php

namespace Controllers\Endpoints;
use App\Helpers\ArgumentParser;
use Controllers\Abstracts\AbstractController;
use DOMDocument;
use Libs\DataApi;
use Libs\FileSystemManager;
use Libs\ReadFile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Slim\Http\Request;
use Slim\Http\Response;
use ZipArchive;

class ExportExperimentController extends AbstractController
{
    private static function prepareZip(int $expId){
        $rootPath = realpath("../file_system/experiments/exp_".$expId."/images");
        $zip = new ZipArchive;
        if ($zip->open("../file_system/experiments/exp_".$expId."/cmp_exp" . $expId . ".zip", ZipArchive::CREATE) === TRUE) {
            $zip->addFile("../file_system/experiments/exp_".$expId."/metadata.xml", 'metadata.xml');
            $zip->addFile("../file_system/experiments/exp_".$expId."/data.csv", 'data.csv');
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

    public static function exportData(Request $request, Response $response, $expId, $download)
    {
        $header = array(0 => 'time');
        $data = array(array());
        $vars = DataApi::get("experiments/". $expId. "/variables");
        if($vars['status'] == 'ok') {
            $var_counter = 1;
            foreach ($vars['data'] as $var) {
                array_push($header, $var['name']);
                $vals = DataApi::get("experiments/" . $expId . "/variables/" . $var['id'] . "/values?sort[time]=asc");
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
            if(!file_exists($path . "/exp_".$expId)){
                FileSystemManager::mkdir($path, "exp_".$expId);
                chdir("../../app");
            }
            $fh = fopen($path . "/exp_" . $expId . "/data.csv", "w");
            fputcsv($fh, $header);
            foreach ($data as $key => $fields) {
                fputcsv($fh, $fields);
            }
            fclose($fh);
            if($download){
                FileSystemManager::downloadFile($path . "/exp_" . $expId . "/data.csv");
            }
            return self::formatOk($response, ['path' => $path . "/exp_" . $expId . "/data.csv"]);
        }
        return self::formatError($response, $data['code'], $data['message']);
    }

    public static function exportExperiment(Request $request, Response $response, $expId)
    {
        $data = DataApi::get("experiments/". $expId);
        if($data['status'] == 'ok'){
            $xml= new DomDocument('0.1');
            $xml->formatOutput = true;
            $experiment=$xml->createElement("experiment");
            $xml->appendChild($experiment);
            $name=$xml->createElement("name", $data['data'][0]['name']);
            $experiment->appendChild($name);
            $description=$xml->createElement("description", $data['data'][0]['description']);
            $experiment->appendChild($description);
            $protocol=$xml->createElement("protocol", $data['data'][0]['protocol']);
            $experiment->appendChild($protocol);
            $inserted=$xml->createElement("inserted", $data['data'][0]['inserted']);
            $experiment->appendChild($inserted);
            $started=$xml->createElement("started", $data['data'][0]['started']);
            $experiment->appendChild($started);
            $status=$xml->createElement("status", $data['data'][0]['status']);
            $experiment->appendChild($status);
            $organism=$xml->createElement("organism");
            $organism->setAttribute('id', $data['data'][0]['organism']['id']);
            $orgName=$xml->createElement("name", $data['data'][0]['organism']['name']);
            $organism->appendChild($orgName);
            $orgCode=$xml->createElement("code", $data['data'][0]['organism']['code']);
            $organism->appendChild($orgCode);
            $experiment->appendChild($organism);
            $devices=$xml->createElement("devices");
            $experiment->appendChild($devices);
            foreach ($data['data'][0]['devices'] as $dev){
                $device = $xml->createElement("device");
                $device->setAttribute("id", $dev['id']);
                $devName = $xml->createElement("name", $dev['name']);
                $device->appendChild($devName);
                $devices->appendChild($device);

            }
            if(!file_exists("../file_system/experiments/exp_".$expId)){
                FileSystemManager::mkdir("../file_system/experiments", "exp_".$expId);
                chdir("../../app");
            }
            $xml->save("../file_system/experiments/exp_".$expId."/metadata.xml");
            self::exportData($request, $response, $expId, false);
            self::prepareZip($expId);
            FileSystemManager::downloadFile("../file_system/experiments/exp_" . $expId ."/cmp_exp" . $expId . ".zip");
            return self::formatOk($response, ['path' => "../file_system/experiments/exp_".$expId."/cmp_exp" . $expId . ".zip"]);
        }
        return self::formatError($response, $data['code'], $data['message']);
    }
}