<?php
namespace Controllers\Endpoints;
use App\Exceptions\MissingRequiredKeyException;
use App\Exceptions\NonExistingAnalysisMethod;
use Controllers\Abstracts\AbstractController;
use Controllers\Endpoints\AnalysisManager\Implementation;
use LaTeX;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use Slim\Http\Request;
use Slim\Http\Response;

class AnalysisManager extends AbstractController
{
    private static $analysisClass = Implementation::class;

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
        $classMethods = get_class_methods(self::$analysisClass);
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
            return new ReflectionMethod(self::$analysisClass, $name);
        } catch (ReflectionException $e) {
            throw new NonExistingAnalysisMethod($name);
        }

    }

    /**
     * @param Request $request
     * @param string $name
     * @return array
     * @throws NonExistingAnalysisMethod
     * @throws ReflectionException
     */
    private static function runAnalysis(Request $request, string $name): array
    {
        $methodName = self::convertAnalysisNameToMethodName($name);
        $f = self::getAnalysisMethod($methodName);
        $inputs = self::prepareInputs($request, $methodName);
        $result = $f->invokeArgs((object)self::$analysisClass, $inputs);
        if (!is_array($result)){
            return ['outputType' => ''. $f->getReturnType(), 'result' => (string) $result];
        } else {
            return ['outputType' => ''. $f->getReturnType(), 'result' => $result];
        }
    }


    /**
     * @param string $name
     * @return array
     * @throws NonExistingAnalysisMethod
     * @throws ReflectionException
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
        $inputGroups = [];
        foreach ($f->getParameters() as $param) {
            if($param->name == "accessToken"){
                continue;
            }
            $description = null;
            if($annotation != ['annotation' => "Doesn't have an annotation."]){
                $description = $annotation['params'][$param->name];
            }
            $tags = $description['iTags'];
            if (!is_null($tags)) {
                $group = 'nongrouped';
                if (key_exists('group', $tags)){
                    $group = $tags['group'];
                    unset($tags['group']);
                }
                if($param->isOptional()) {
                    $defaultValue = $param->getDefaultValue();
                    if ($defaultValue != null) {
                        $tags["defaultValue"] = $defaultValue;
                    }
                }
                $inputGroups[$group][] = array_merge($tags,
                    array('key' => $param->name,
                        'name' => self::convertMethodNameToAnalysisName($param->name),
                        'type' => '' . $param->getType(),
                        'description'=> $description['description']));
            }
        }
        foreach ($inputGroups as $key => $group) {
            $result[] = ['name' => $key,
                'inputs' => $group];
        }
        return array('name' => $name,
            'description' => $methodDescription,
            'inputGroups' => $result,
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
        $doc = preg_replace(['/\s+/',"/\*/", "/\//"], [" ","",""], $doc);
        $docSegments = explode("@", $doc);
        array_shift($docSegments);
        $docSegments = array_map(function ($paramAnn) {
                return '@' . $paramAnn;
            }, $docSegments);
        $description = null;
        $return = null;
        $params = array();
        foreach ($docSegments as $segment){
            if(strpos($segment, '@param') !== false){
                list($name, $type, $paramDescription, $iTag) = self::parseAnnotationLine($segment);
                $params[$name] = array('type' => $type, 'description' => $paramDescription, 'iTags' => $iTag);
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
        $paramAttributes = preg_split("/[\s,]+/", $line, 4);
        $name = str_replace("$", "", $paramAttributes[2]);
        $description = null;
        $internalTags = [];
        if(count($paramAttributes) > 3){
            $description = preg_replace_callback("/\[.*]/U", function ($matches) use (&$internalTags){
                $splitTag = preg_split("/=/", substr(current($matches), 1, -1), 2);
                $internalTags[$splitTag[0]] = $splitTag[1];
                return '';
            }, $paramAttributes[3]);
        }
        $type = $paramAttributes[1];
        return array($name, $type, $description, $internalTags);
    }

    /**
     * @param Request $request
     * @param string $name
     * @return string[]
     * @throws NonExistingAnalysisMethod
     * @throws MissingRequiredKeyException|ReflectionException
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
        foreach ($inputsPrescription["inputGroups"] as $group) {
            foreach ($group['inputs'] as $input){
                if(array_key_exists ( $input["name"], $inputsBody[0])){
                    $value = $inputsBody[0][$input["name"]];
                } else if(array_key_exists ( "defaultValue", $input)){
                    $value = $input["defaultValue"];
                } else{
                    throw new MissingRequiredKeyException($input["name"]);
                }
                if(!in_array($input["type"], ['int', 'string', 'float', 'bool', 'array']))
                {
                    $typeName = $input["type"];
                    $new_input = new $typeName($value);
                } else {
                    $new_input = $value;
                }
                array_push($inputs, $new_input);
            }
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

    /**
     * @return string
     */
    public static function getAnalysisClass(): string
    {
        return self::$analysisClass;
    }

    /**
     * @param string $analysisClass
     */
    public static function setAnalysisClass(string $analysisClass): void
    {
        self::$analysisClass = $analysisClass;
    }


}