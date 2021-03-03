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
    public static function responseListOfAnalysis(Response $response): \Slim\Http\Response
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
    public static function responsePrescription(Response $response, string $name): Response
    {
        return self::formatOk($response, self::getPrescription($name));
    }

    /**
     * @param Response $response
     * @param string $name
     * @return Response
     * @throws NonExistingAnalysisMethod
     */
    public static function responseAnnotation(Response $response, string $name): Response
    {
        return self::formatOk($response, self::getAnnotation($name));
    }

    /**
     * @return array
     */
    private static function getListOfAnalysis(): array
    {
        $class_methods = get_class_methods(Implementation::class);
        return ['analysis' => $class_methods];
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
        $f = self::getAnalysisMethod($name);
        $inputs = self::prepareInputs($request, $name);
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
        $f = self::getAnalysisMethod($name);
        $result = array();
        foreach ($f->getParameters() as $param) {
            if($param->name == "accessToken"){
                continue;
            }
            $result[] = array('name' => $param->name, 'type' => '' . $param->getType());
        }
        return ['name' => $name, 'inputs' => $result, 'output' => ''. $f->getReturnType()];
    }

    /**
     * @param string $name
     * @return array|string[]
     * @throws NonExistingAnalysisMethod
     */
    private static function getAnnotation(string $name): array
    {
        $f = self::getAnalysisMethod($name);
        $doc = $f->getDocComment();
        if(!$doc){
            return ['annotation' => "Doesn't have an annotation."];
        }
        $doc = str_replace("/","",$doc);
        $doc = str_replace("*","",$doc);
        return ['annotation' => $doc];
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
        foreach ($inputsPrescription["inputs"] as $input){
            array_push($inputs, $inputsBody[0][$input["name"]]);
        }
        return $inputs;
    }
}

namespace Controllers\Endpoints\AnalysisManager;
use App\Exceptions\AccessForbiddenException;
use Controllers\Endpoints\ExperimentAccess;
use Libs\AnalysisLib;

class Implementation {

    /**
     * Average of one variable.
     * @param string $accessToken
     * @param int $expId
     * @param int $varId
     * @return float
     * @throws AccessForbiddenException
     */
    static function variableAverage(string $accessToken, int $expId, int $varId)
    {
        $values = AnalysisLib::getVariableValues($accessToken, $expId, $varId);
        return number_format(array_sum($values)/count($values), 3, '.', '');
    }

    /**
     * Maximum of one variable.
     * @param string $accessToken
     * @param int $expId
     * @param int $varId
     * @return mixed
     * @throws AccessForbiddenException
     */
    static function variableMaximum(string $accessToken, int $expId, int $varId): float
    {
        $values = AnalysisLib::getVariableValues($accessToken, $expId, $varId);
        return number_format(max($values), 3, '.', '');
    }

    /**
     * Minimum of one variable.
     * @param string $accessToken
     * @param int $expId
     * @param int $varId
     * @return mixed
     * @throws AccessForbiddenException
     */
    static function variableMinimum(string $accessToken, int $expId, int $varId): float
    {
        $values = AnalysisLib::getVariableValues($accessToken, $expId, $varId);
        return number_format(min($values), 3, '.', '');
    }

    /**
     * @param string $accessToken
     * @param int $expId1
     * @param int $varId1
     * @param int $expId2
     * @param int $varId2
     * @throws AccessForbiddenException
     */
    static function twoVariablesMean(string $accessToken, int $expId1, int $varId1, int $expId2, int $varId2){
        list($timeSeries1, $timeSeries2) = AnalysisLib::alignTwoVariables($accessToken, $expId1, $varId1, $expId2, $varId2);
        $meanTimeSeries = array();
        foreach (array_keys($timeSeries1) as $time){
            $value = ($timeSeries1[$time] + $timeSeries2[$time]) / 2;
            $meanTimeSeries[] = array('time' => floatval($time), 'value' => $value);
        }
        return $meanTimeSeries;
    }

    /**
     * @param string $accessToken
     * @param int $expId1
     * @param int $varId1
     * @param int $expId2
     * @param int $varId2
     * @throws AccessForbiddenException
     */
    static function twoVariablesDiff(string $accessToken, int $expId1, int $varId1, int $expId2, int $varId2): array
    {
        list($timeSeries1, $timeSeries2) = AnalysisLib::alignTwoVariables($accessToken, $expId1, $varId1, $expId2, $varId2);
        $meanTimeSeries = array();
        foreach (array_keys($timeSeries1) as $time){
            $value = $timeSeries1[$time] - $timeSeries2[$time];
            $meanTimeSeries[] = array('time' => floatval($time), 'value' => $value);
        }
        return $meanTimeSeries;
    }

    /**
     * @param string $accessToken
     * @param int $expId
     * @param int $varId
     * @return array
     * @throws AccessForbiddenException
     */
    static function variableLinearRegression(string $accessToken, int $expId, int $varId): array
    {
        $timeSeries = AnalysisLib::getVariableTimeSeries($accessToken, $expId, $varId);
        $times = array_keys($timeSeries);
        $values = array_values($timeSeries);
        list($b0, $b1) = AnalysisLib::leastSquareMethod($times, $values);
        $linearRegressionTimeSeries = array();
        foreach($times as $time){
            $linearRegressionTimeSeries[] = array('time' => $time, 'value' => $b0 + ($b1 * $time));
        }
        return $linearRegressionTimeSeries;
    }

    /**
     * @param string $accessToken
     * @param int $expId1
     * @param int $varId1
     * @param int $expId2
     * @param int $varId2
     * @return array
     * @throws AccessForbiddenException
     */
    static function twoVariablesLinearRegression(string $accessToken, int $expId1, int $varId1, int $expId2, int $varId2){
        list($timeSeries1, $timeSeries2) = AnalysisLib::alignTwoVariables($accessToken, $expId1, $varId1, $expId2, $varId2);
        $x_values = array_values($timeSeries1);
        $y_values = array_values($timeSeries2);
        list($b0, $b1) = AnalysisLib::leastSquareMethod($x_values, $y_values);
        $linearRegression = array();
        $x_max = max($x_values);
        $step = $x_max / 100;
        $x = 0;
        while ($x < $x_max){
            $linearRegression[] = array('x' => $x, 'y' => $b0 + ($b1 * $x));
            $x += $step;
        }
        return $linearRegression;
    }

    /**
     * @param string $accessToken
     * @param int $expId
     * @param int $varId
     * @return string
     * @throws AccessForbiddenException
     */
    static function variableCourse(string $accessToken, int $expId, int $varId): string
    {
        $timeSeries = AnalysisLib::getVariableTimeSeries($accessToken, $expId, $varId);
        $times = array_keys($timeSeries);
        $values = array_values($timeSeries);
        list($b0, $b1) = AnalysisLib::leastSquareMethod($times, $values);
        $linearRegressionTimeSeries = array();
        $change_in_x = abs($times[1] - $times[0]);
        $change_in_y = ($b0 + ($b1 * $times[1])) - ($b0 + ($b1 * $times[0]));
        $slope = $change_in_y / $change_in_x;
        if($slope > 0){
            return "increasing";
        }
        return "decreasing";
    }

}
