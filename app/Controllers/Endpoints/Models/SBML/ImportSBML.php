<?php

namespace Controllers\Endpoints;

use App\Exceptions\AccessForbiddenException;
use App\Exceptions\BadFileFormat;
use App\Exceptions\DataAPIException;
use App\Exceptions\InvalidTypeException;
use App\Exceptions\MalformedInputException;
use App\Helpers\ArgumentParser;
use Controllers\Abstracts\AbstractController;
use Libs\DataApi;
use SimpleXMLElement;
use Slim\Http\Request;
use Slim\Http\Response;
use XSLTProcessor;

/**
 * Class ImportSBML
 * @package Controllers\Endpoints
 * @author Radoslav Doktor
 */
class ImportSBML extends AbstractController
{
    protected $notes = [];
    protected $noteAt = 0;

    protected $maths = [];
    protected $mathsAt = 0;

    protected static function checkAccess(Request $request): string
    {
        $access_token = $request->getHeader('HTTP_AUTHORIZATION');
        if (empty($access_token)) {
            throw new AccessForbiddenException("Guest can't import.");
        }
        return $access_token[0];
    }

    public function parseSBMLtoJson(Request $request, Response $response, $args): Response
    {
        $access_token = self::checkAccess($request);
        $body = $request->getBody()->__toString();
        preg_match_all('/(?<=<notes>).*?(?=<\/notes>)/s', $body, $this->notes);
        preg_match_all('/(<math).*?(\/math>)/s', $body, $this->maths);
        $body = preg_replace('/(?<=<notes>).*?(?=<\/notes>)/s', '<notes></notes>', $body);
        if ($body === null) {
            throw new BadFileFormat('SBML lvl >= 2');
        }
        $modelContent = json_decode($this->convert($body), true);
        //return self::formatOk($response, $modelContent['sbml']['model']['listOfRules']);
        if ($modelContent['sbml']['level'] >= 2) {
            $this->adjustAssArray($modelContent['sbml']['model']);
        } else {
            throw new BadFileFormat('SBML lvl >= 2');
        }
        $response = self::formatOk($response, $modelContent['sbml']['model']);
        $rsp = DataApi::post('models/import', json_encode(['data' => $modelContent['sbml']['model']]), $access_token);
        if ($rsp['code'] === 200) {
            unset($rsp['status']);
            unset($rsp['code']);
            return self::formatOk($response, $rsp);
        } else {
            throw new DataAPIException($rsp['message'],$rsp['code']);
        }
    }


    protected function adjustAssArray(&$content)
    {
        foreach ($content as $key => &$value) {
            if (is_array($value)) {
                if ((strcmp($key, 'notes') != 0) &&
                    (strcmp($key, 'annotation') != 0) &&
                    (strcmp($key, 'math') != 0)) {

                    if (strpos($key, 'listOf') !== false)
                    {
                        $newKey = lcfirst(substr($key, 6));
                        $listOfObjects = [];
                        foreach ($value as $oKey => $object){
                            if (array_key_exists(0, $object)){
                                foreach ($object as $nested) {
                                    $this->adjustAssArray($nested);
                                    if ($key === 'listOfRules') {
                                        $nested['type'] = str_replace('Rule','', $oKey);
                                    }
                                    array_push($listOfObjects, $nested);
                                }
                            } else {
                                $this->adjustAssArray($object);
                                if ($key === 'listOfRules') {
                                    $object['type'] = str_replace('Rule','', $oKey);
                                }
                                array_push($listOfObjects, $object);
                            }
                        }
                        $content[$newKey] = $listOfObjects;
                        unset($content[$key]);
                    } else {
                        $this->adjustAssArray($value);
                    }
                }
                elseif ($key === 'notes'){
                    $content[$key] = $this->notes[0][$this->noteAt];
                    $this->noteAt++;
                }
                elseif ($key === 'annotation'){
                    $content['annotations'] = $this->adjustAnnotations($value);
                    unset($content[$key]);
                }
                elseif ($key === 'math'){
                    $content['expression'] = $this->maths[0][$this->mathsAt];
                    unset($content[$key]);
                    $this->mathsAt++;
                }
            }
            if ($key === 'id') {
                $content['alias'] = $value;
                unset($content[$key]);
            }
            if ($key === 'metaid') {
                unset($content[$key]);
            }
            if ($key === 'initialAmount' || $key === 'initialConcentration') {
                $content['initialAmount'] = $value;
            }
        }
    }

    protected function adjustAnnotations($annotationsData)
    {
        $qualifiers = ["encodes","hasPart","hasProperty","hasVersion","is","isDescribedBy","isEncodedBy","isHomologTo",
            "isPartOf","isPropertyOf","isVersionOf","occursIn","hasTaxon", "isDerivedFrom","isInstanceOf","hasInstance"];
        $annotations = [];
        $encAnn = json_encode($annotationsData, JSON_UNESCAPED_SLASHES);
        preg_match_all('/(?<=bqmodel:|bqbiol:).*?(?=":)|(?<=rdf:resource":").*?(?="})/',
            $encAnn, $parsed);
        $qualifierNow = "";
        foreach ($parsed[0] as $item) {
            if (in_array($item, $qualifiers)) {
                $qualifierNow = $item;
            } else {
                $uriSplit = explode(':', $item);
                $splitSize = sizeof($uriSplit) - 1;
                if ($uriSplit[0] === 'urn') {
                    array_push($annotations, ["qualifier" => $qualifierNow,
                        "sourceIdentifier" => $uriSplit[$splitSize],
                        "sourceNamespace" => $uriSplit[$splitSize - 1]]);
                } else {
                    array_push($annotations, ["qualifier" => $qualifierNow,
                        "link" => $item]);
                }
            }
        }
        return $annotations;
    }

    public static function convert(string $input){
//        $start_time = microtime(true);
        $xml=simplexml_load_string($input);
        $xsl=simplexml_load_file('../app/Controllers/Endpoints/Models/SBML/xmlToJson.xsl');

        $proc = new XSLTProcessor;
        $proc->importStyleSheet($xsl); // attach the xsl rules
//        $end_time = microtime(true);
////
////// Calculate the script execution time
//        $execution_time = ($end_time - $start_time);
//        dump($execution_time,$proc->transformToXml($xml),libxml_get_last_error());exit;
        if (libxml_get_last_error()){
            dump(libxml_get_last_error());exit;
        }
        return $proc->transformToXml($xml);
    }
}