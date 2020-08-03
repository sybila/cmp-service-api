<?php

namespace Controllers\Endpoints;
use App\Helpers\ArgumentParser;
use Controllers\Abstracts\AbstractController;
use DOMDocument;
use Libs\DataApi;
use Libs\FileSystemManager;
use Libs\ReadFile;
use Slim\Http\Request;
use Slim\Http\Response;

class ExportExperimentController extends AbstractController
{
    public static function exportData(Request $request, Response $response, $expId)
    {
        //return self::formatOk($response, ReadFile::readJsonFile($path));
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
                $xml->save("exp_".$expId."/metadata.xml");
            } else{
                $xml->save("../file_system/experiments/exp_".$expId."/metadata.xml");
            }
            return self::formatOk($response, ['path' => "../file_system/experiments/exp_".$expId."/metadata.xml"]);
        }
        return self::formatError($response, $data['code'], $data['message']);
    }

}