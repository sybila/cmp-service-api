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
            $result[] = array('name' => self::convertMethodNameToAnalysisName($param->name),
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

namespace Controllers\Endpoints\AnalysisManager;
use App\Exceptions\AccessForbiddenException;
use App\Exceptions\OperationFailedException;
use ExperimentId;
use Libs\AnalysisLib;
use JamesAusten\PhpZscore\ZScore;
use Phpml\Math\Kernel;
use Phpml\Regression\LeastSquares;
use Phpml\Regression\SVR;
use VariableId;

class Implementation {

    /**
     * Average of one variable.
     * @param string $accessToken
     * @param ExperimentId $expId Experiment identifier
     * @param VariableId $varId Variable identifier
     * @return float Return decimal number mean of variable data.
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function variableMean(string $accessToken, ExperimentId $expId, VariableId $varId): float
    {
        $values = AnalysisLib::getVariableValues($accessToken, $expId, $varId);
        return AnalysisLib::mean($values);
    }

    /**
     * Maximum of one variable.
     * @param string $accessToken
     * @param ExperimentId $expId
     * @param VariableId $varId
     * @return mixed
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function variableMaximum(string $accessToken, ExperimentId $expId, VariableId $varId): float
    {
        $values = AnalysisLib::getVariableValues($accessToken, $expId, $varId);
        return number_format(max($values), 3, '.', '');
    }

    /**
     * Minimum of one variable.
     * @param string $accessToken
     * @param ExperimentId $expId
     * @param VariableId $varId
     * @return mixed
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function variableMinimum(string $accessToken, ExperimentId $expId, VariableId $varId): float
    {
        $values = AnalysisLib::getVariableValues($accessToken, $expId, $varId);
        return number_format(min($values), 3, '.', '');
    }

    /**
     * Median of one variable.
     * @param string $accessToken
     * @param ExperimentId $expId
     * @param VariableId $varId
     * @return mixed
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function variableMedian(string $accessToken, ExperimentId $expId, VariableId $varId): float
    {
        $values = AnalysisLib::getVariableValues($accessToken, $expId, $varId);
        $sorted_values = sort($values);
        return $sorted_values[round(count($values) / 2)];
    }

    /**
     * @param string $accessToken
     * @param ExperimentId $expId
     * @param VariableId $varId
     * @param bool $isWholePopulation
     * @return float|int
     * @throws AccessForbiddenException
     * @throws OperationFailedException
     */
    static function variableVariance(string $accessToken, ExperimentId $expId, VariableId $varId, bool $isWholePopulation){
        $values = AnalysisLib::getVariableValues($accessToken, $expId, $varId);
        $count = count($values);
        $mean = AnalysisLib::mean($values);
        $sum = 0;
        foreach($values as $value){
            $sum += pow($value - $mean, 2);
        }
        if(!$isWholePopulation){
            return $sum / ($count - 1);
        }
        return  $sum / $count;
    }

    /**
     * @param string $accessToken
     * @param ExperimentId $expId
     * @param VariableId $varId
     * @param bool $isWholePopulation
     * @return float
     * @throws AccessForbiddenException
     * @throws OperationFailedException
     */
    static function variableStandardDeviation(string $accessToken, ExperimentId $expId, VariableId $varId, bool $isWholePopulation): float
    {
        $variance = self::variableVariance($accessToken, $expId, $varId, $isWholePopulation);
        return sqrt($variance);
    }

    /**
     * @param string $accessToken
     * @param ExperimentId $expId1
     * @param VariableId $varId1
     * @param ExperimentId $expId2
     * @param VariableId $varId2
     * @return array
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function twoVariablesMean(string $accessToken, ExperimentId $expId1, VariableId $varId1, ExperimentId $expId2, VariableId $varId2): array
    {
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
     * @param ExperimentId $expId1
     * @param VariableId $varId1
     * @param ExperimentId $expId2
     * @param VariableId $varId2
     * @return array
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function twoVariablesDifference(string $accessToken, ExperimentId $expId1, VariableId $varId1, ExperimentId $expId2, VariableId $varId2): array
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
     * @param ExperimentId $expId
     * @param VariableId $varId
     * @return array
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function variableLinearRegression(string $accessToken, ExperimentId $expId, VariableId $varId): array
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
     * @param ExperimentId $expId1
     * @param VariableId $varId1
     * @param ExperimentId $expId2
     * @param VariableId $varId2
     * @return array
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function twoVariablesLinearRegression(string $accessToken, ExperimentId $expId1, VariableId $varId1, ExperimentId $expId2, VariableId $varId2): array
    {
        list($timeSeries1, $timeSeries2) = AnalysisLib::alignTwoVariables($accessToken, $expId1, $varId1, $expId2, $varId2);
        $xValues = array_values($timeSeries1);
        $yValues = array_values($timeSeries2);
        list($b0, $b1) = AnalysisLib::leastSquareMethod($xValues, $yValues);
        $linearRegression = array();
        $x_max = max($xValues);
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
     * @param ExperimentId $expId
     * @param VariableId $varId
     * @return string
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function variableCourse(string $accessToken, ExperimentId $expId, VariableId $varId): string
    {
        $timeSeries = AnalysisLib::getVariableTimeSeries($accessToken, $expId, $varId);
        $times = array_keys($timeSeries);
        $values = array_values($timeSeries);
        list($b0, $b1) = AnalysisLib::leastSquareMethod($times, $values);
        $changeInX = abs($times[1] - $times[0]);
        $changeInY = ($b0 + ($b1 * $times[1])) - ($b0 + ($b1 * $times[0]));
        $slope = $changeInY / $changeInX;
        if($slope > 0){
            return "increasing";
        }
        return "decreasing";
    }

    /**
     * @param string $accessToken
     * @param ExperimentId $expId
     * @param VariableId $varId
     * @return array
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function variableExponentialRegression(string $accessToken, ExperimentId $expId, VariableId $varId): array
    {
        $timeSeries = AnalysisLib::getVariableTimeSeries($accessToken, $expId, $varId);
        $times = array_keys($timeSeries);
        $values = array_values($timeSeries);
        list($a, $r) = AnalysisLib::exponentialLeastSquareMethod($times, $values);
        $exponentialRegressionTimeSeries = array();
        foreach($times as $time){
            $value = $a * exp($r * $time);
            $exponentialRegressionTimeSeries[] = array('time' => $time, 'value' => $value);
        }
        return $exponentialRegressionTimeSeries;
    }

    /**
     * @param string $accessToken
     * @param ExperimentId $expId1
     * @param VariableId $varId1
     * @param ExperimentId $expId2
     * @param VariableId $varId2
     * @return array
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function twoVariablesExponentialRegression(string $accessToken, ExperimentId $expId1, VariableId $varId1, ExperimentId $expId2, VariableId $varId2): array
    {
        list($timeSeries1, $timeSeries2) = AnalysisLib::alignTwoVariables($accessToken, $expId1, $varId1, $expId2, $varId2);
        $xValues = array_values($timeSeries1);
        $yValues = array_values($timeSeries2);
        list($a, $r) = AnalysisLib::exponentialLeastSquareMethod($xValues, $yValues);
        $exponentialRegression = array();
        $xMax = max($xValues);
        $step = $xMax / 100;
        $x = 0;
        while ($x < $xMax){
            $y = $a * exp($r * $x);
            $exponentialRegression[] = array('x' => $x, 'y' => $y);
            $x += $step;
        }
        return $exponentialRegression;
    }

    /**
     * @param string $accessToken
     * @param ExperimentId $expId
     * @param VariableId $varId
     * @param int|null $lag
     * @param float|null $influence
     * @param float|null $threshold
     * @return array
     * @throws AccessForbiddenException
     * @throws OperationFailedException
     */
    static function variableFindPeaks(string $accessToken, ExperimentId $expId, VariableId $varId, ?int $lag, ?float $influence, ?float $threshold): array
    {
        // set default values
        if($lag == null){
            $lag = 5;
        }
        if($threshold == null){
            $threshold = 0.5;
        }
        if($influence == null){
            $influence = 3.5;
        }
        $zScore = new ZScore([
            'lag'       => $lag,
            'threshold' => $threshold,
            'influence' => $influence,
        ]);
        // get variable values
        $timeSeries = AnalysisLib::getVariableTimeSeries($accessToken, $expId, $varId);
        $values = array_values($timeSeries);
        // find peaks
        $highlightedPeaks = $zScore->calculate($values);
        $peaks = array();
        $times = array_keys($timeSeries);
        for($i = 0; $i < count($values); $i++){
            if($highlightedPeaks[$i] == 1 or $highlightedPeaks[$i] == -1){
                $peaks[] = array('time' => $times[$i], 'values' => $values[$i]);
            }
        }
        return $peaks;
    }

    /**
     * @param string $accessToken
     * @param ExperimentId $expId1
     * @param VariableId $varId1
     * @param ExperimentId $expId2
     * @param VariableId $varId2
     * @return float|int
     * @throws AccessForbiddenException
     * @throws OperationFailedException
     */
    static function twoVariablesCorrelation(string $accessToken, ExperimentId $expId1, VariableId $varId1, ExperimentId $expId2, VariableId $varId2){
        list($timeSeries1, $timeSeries2) = AnalysisLib::alignTwoVariables($accessToken, $expId1, $varId1, $expId2, $varId2);
        $values1 = array_values($timeSeries1);
        $values2 = array_values($timeSeries2);
        $length= count($values1);
        $mean1=array_sum($values1) / $length;
        $mean2=array_sum($values2) / $length;
        $axb=0;
        $a2=0;
        $b2=0;

        for($i=0;$i<$length;$i++)
        {
            $a=$values1[$i]-$mean1;
            $b=$values2[$i]-$mean2;
            $axb=$axb+($a*$b);
            $a2=$a2+ pow($a,2);
            $b2=$b2+ pow($b,2);
        }

        $corr= $axb / sqrt($a2*$b2);

        return $corr;
    }

    static function polynomialRegression(string $accessToken, ExperimentId $expId, VariableId $varId, int $maximumDegree){
        $timeSeries = AnalysisLib::getVariableTimeSeries($accessToken, $expId, $varId);
        $times = array_keys($timeSeries);
        $values = array_keys($timeSeries);
        /*$times = [0, 0.25, 0.5, 0.75, 1];
        $timeSeries = array();
        $timeSeries["0.0"] = 1;
        $timeSeries["0.25"] = 1.284;
        $timeSeries["0.5"] = 1.6487;
        $timeSeries["0.75"] = 2.1170;
        $timeSeries["1.0"] = 2.7183;*/
        $matrix = array();
        for($i=0;$i<$maximumDegree;$i++){
            $matrix[] = array();
            for($j=0;$j<$maximumDegree;$j++){
                $x_i_j = 0;
                foreach ($times as $time){
                    $x_i_j += (pow($time, $i) * pow($time, $j));
                }
                $matrix[$i][$j] = $x_i_j;
            }
        }
        //print_r($matrix);
        $targets = [];
        for($i=0;$i<$maximumDegree;$i++){
            $y = 0;
            foreach ($timeSeries as $time => $value){
                $y += pow($time, $i) * $value;
            }
            $targets[$i] = $y;
        }
        //AnalysisLib::cramersRule($matrix, $targets)); exit;
        return AnalysisLib::cramersRule($matrix, $targets);
    }

    static function sinusRegression(string $accessToken, ExperimentId $expId, VariableId $varId){

        AnalysisLib::fitSine();
    }

}