<?php
namespace Controllers\Endpoints;
use App\Exceptions\NonExistingAnalysisMethod;
use Controllers\Abstracts\AbstractController;
use Controllers\Endpoints\AnalysisManager\Implementation;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Slim\Http\Request;
use Slim\Http\Response;

class AnalysisManager extends AbstractController
{
    /**
     * Response list of all available analysis.
     * @param $response
     * @return Response
     */
    public static function responseListOfAnalysis(Response $response  ): \Slim\Http\Response
    {
        return self::formatOk($response, self::getListOfAnalysis());
    }

    /**
     * @param Response $response
     * @param Request $request
     * @param string $name
     * @return Response
     */
    public static function responseRunAnalysis(Response $response, Request $request, string $name): \Slim\Http\Response
    {
        print_r($response);
        exit;
        return self::formatOk($response, self::runAnalysis($request, $name));
    }

    /**
     * @param Response $response
     * @param string $name
     * @return Response
     * @throws NonExistingAnalysisMethod
     */
    public static function responsePrescription(Response $response, string $name): \Slim\Http\Response
    {
        return self::formatOk($response, self::getPrescription($name));
    }

    /**
     * @return array
     */
    private static function getListOfAnalysis(): array
    {
        $classMethods = get_class_methods(Implementation::class);
        $methodsNames = array();
        foreach($classMethods as $methodName){
            $methodsNames[] = self::convertMethodNameToAnalysisName($methodName);
        }
        return ['analysis' => $methodsNames];
    }

    /**
     * @param string $name
     * @return ReflectionMethod
     * @throws NonExistingAnalysisMethod
     */
    private static function getAnalysisMethod(string $name): ReflectionMethod
    {
        try {
            return new ReflectionMethod(Implementation::class, $name);
        } catch (\ReflectionException $e) {
            throw new NonExistingAnalysisMethod($name);
        }

    }

    /**
     * @param Request $request
     * @param string $name
     * @return array
     * @throws NonExistingAnalysisMethod
     * @throws \ReflectionException
     */
    private static function runAnalysis(Request $request, string $name): array
    {
        $methodName = self::convertAnalysisNameToMethodName($name);
        $f = self::getAnalysisMethod($methodName);
        $inputs = self::prepareInputs($request, $methodName);
        $result = $f->invokeArgs((object)Implementation::class, $inputs);
        return ['result' => $result];
    }

    /**
     * @param string $name
     * @return array
     * @throws NonExistingAnalysisMethod
     */
    private static function getPrescription(string $name): array
    {
        $methodName = self::convertAnalysisNameToMethodName($name);
        $f = self::getAnalysisMethod($methodName);
        $annotation = self::getAnnotation($methodName);
        $result = array();
        $methodDescription = null;
        $outputDescription = null;
        if($annotation != ['annotation' => "Doesn't have an annotation."]){
            $methodDescription = $annotation['description'];
            $outputDescription = $annotation['output']['description'];
        }
        foreach ($f->getParameters() as $param) {
            if($param->name == "accessToken"){
                continue;
            }
            $description = null;
            if($annotation != ['annotation' => "Doesn't have an annotation."]){
                $description = $annotation['params'][$param->name]['description'];
            }
            $result[] = array('key' => $param->name,
                              'name' => self::convertMethodNameToAnalysisName($param->name),
                              'type' => '' . $param->getType(),
                              'description'=> $description);
        }
        return array('name' => $name,
                     'description' => $methodDescription,
                     'inputs' => $result,
                     'output' => array('type'=>''. $f->getReturnType(),
                                       'description'=>$outputDescription));
    }

    /**
     * @param string $methodName
     * @return array|string[]
     * @throws NonExistingAnalysisMethod
     */
    private static function getAnnotation(string $methodName): array
    {
        $f = self::getAnalysisMethod($methodName);
        $doc = $f->getDocComment();
        if(!$doc){
            return ['annotation' => "Doesn't have an annotation."];
        }
        $doc = str_replace("/","",$doc);
        $doc = str_replace("*","",$doc);
        $docSegments = explode("\n", $doc);
        $description = null;
        $return = null;
        $params = array();
        foreach ($docSegments as $segment){
            if(strpos($segment, '@param') !== false){
                list($name, $type, $paramDescription) = self::parseAnnotationLine($segment);
                $params[$name] = array('type' => $type, 'description' => $paramDescription);
            } elseif(strpos($segment, '@return') !== false){
                $outputAttributes = preg_split("/[\s,]+/", $segment, 4);
                $outputDescription = null;
                if(count($outputAttributes) > 3){
                    $outputDescription = $outputAttributes[3];
                }
                $type = $outputAttributes[2];
                $return =  array('type' => $type, 'description' => $outputDescription);
            } elseif(strpos($segment, '@throw') !== false){
                continue;
            } elseif(!ctype_space($segment)){
                $description = $segment;
            }
        }
        return array('description' => $description, 'params' => $params, 'output' => $return);
    }

    private static function parseAnnotationLine($line):array
    {
        $paramAttributes = preg_split("/[\s,]+/", $line, 5);
        $name = str_replace("$", "", $paramAttributes[3]);
        $description = null;
        if(count($paramAttributes) > 4){
            $description = $paramAttributes[4];
        }
        $type = $paramAttributes[2];
        return array($name, $type, $description);
    }

    /**
     * @param Request $request
     * @param string $name
     * @return string[]
     * @throws NonExistingAnalysisMethod
     */
    private static function prepareInputs(Request $request, string $name): array
    {
        $accessTokenArray = $request->getHeader("HTTP_AUTHORIZATION");
        if(!empty($accessTokenArray)) {
            $accessToken = $accessTokenArray[0];
        } else{
            $accessToken = "";
        }
        $inputs = array($accessToken);
        $inputsBody =  $request->getParsedBody()['inputs'];
        $inputsPrescription = self::getPrescription($name);
        foreach ($inputsPrescription["inputs"] as $input) {
            if($input["type"] != "int" and $input["type"] != "string" and $input["type"] != "float" and $input["type"] != "bool")
            {
                $typeName = $input["type"];
                $new_input = new $typeName($inputsBody[0][$input["name"]]);
            } else{
                $new_input = $inputsBody[0][$input["name"]];
            }
            array_push($inputs, $new_input);
        }
        return $inputs;
    }

    private static function convertAnalysisNameToMethodName(string $name): string
    {
        $wordsInName = explode(" ", $name);
        $methodName = implode("", $wordsInName);
        $methodName = lcfirst($methodName);
        return $methodName;
    }

    private static function convertMethodNameToAnalysisName(string $name): string
    {
        $split = implode(" ", preg_split('/(?=[A-Z])/',$name));
        #$lower = strtolower($split);
        return ucfirst($split);
    }
}