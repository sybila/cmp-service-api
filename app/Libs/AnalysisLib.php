<?php
namespace Libs;
use App\Exceptions\AccessForbiddenException;
use App\Exceptions\CountingError;
use App\Exceptions\OperationFailedException;
use ExperimentId;
use Kachkaev\PHPR\Engine\CommandLineREngine;
use Kachkaev\PHPR\RCore;
use MathPHP\LinearAlgebra\Matrix;
use MathPHP\LinearAlgebra\MatrixFactory;
use VariableId;

class AnalysisLib{
    /**
     * @param string $accessToken
     * @param ExperimentId $expId
     * @param VariableId $varId
     * @return array
     * @throws AccessForbiddenException
     * @throws OperationFailedException
     */
    public static function getVariableValues(string $accessToken, ExperimentId $expId, VariableId $varId): array
    {
        $response = DataApi::get("experiments/" . $expId . "/variables/" . $varId . "/values?sort[time]=asc", $accessToken);
        if($response['status'] != "ok"){
            throw new AccessForbiddenException("Not authorized.");
        }
        $data = $response['data'];
        $values = array();
        foreach($data as $value){
            array_push($values, $value['value']);
        }
        return $values;
    }

    /**
     * @param string $accessToken
     * @param ExperimentId $expId
     * @param VariableId $varId
     * @return array
     * @throws AccessForbiddenException
     * @throws OperationFailedException
     */
    public static function getVariableTimeSeries(string $accessToken, ExperimentId $expId, VariableId $varId): array
    {
        $response = DataApi::get("experiments/" . $expId . "/variables/" . $varId . "/values?sort[time]=asc", $accessToken);
        if($response['status'] != "ok"){
            throw new AccessForbiddenException("Not authorized.");
        }
        $data = $response['data'];
        foreach($data as $value){
            $timeSeries[''.$value['time']] = $value['value'];
        }
        return $timeSeries;
    }

    /**
     * Counts mean of list of values.
     * @param array $variable
     * @return float|int
     */
    public static function mean(array $variable){
        return array_sum($variable) / count($variable);
    }

    /**
     * Align two variables.
     *
     * Make imputation for two variables and return two align variables by times in format array(<time> => <value>).
     *
     * @param string $accessToken
     * @param ExperimentId $expId1
     * @param VariableId $varId1
     * @param ExperimentId $expId2
     * @param VariableId $varId2
     * @return array
     * @throws AccessForbiddenException
     * @throws OperationFailedException
     */
    public static function alignTwoVariables(string $accessToken, ExperimentId $expId1, VariableId $varId1, ExperimentId $expId2, VariableId $varId2): array
    {
        $timeSeries1 = AnalysisLib::getVariableTimeSeries($accessToken, $expId1, $varId1);
        $timeSeries2 = AnalysisLib::getVariableTimeSeries($accessToken, $expId2, $varId2);
        if(count($timeSeries1) <= 1 or count($timeSeries2) <= 1){
            return array($timeSeries1, $timeSeries2);
        }
        $pointer1 = 1;
        $pointer2 = 1;
        $times1 = array_keys($timeSeries1);
        $times2 = array_keys($timeSeries2);

        $newTimeSeries1 = array();
        $newTimeSeries2 = array();
        while($pointer1 < count($timeSeries1) and ($pointer2 < count($timeSeries2))){
            $time1 = floatval($times1[$pointer1]);
            $time2 = floatval($times2[$pointer2]);
            if($time1 == $time2){
                $newTimeSeries1[$time1] = $timeSeries1[$time1];
                $newTimeSeries2[$time2] = $timeSeries2[$time2];
                $pointer1 ++;
                $pointer2 ++;
            } elseif ($time1 > $time2){
                $prevTime = floatval($times1[$pointer1 - 1]);
                $time = $times2[$pointer2];
                $nextTime = $time1;
                $prevValue = $timeSeries1[$times1[$pointer1 - 1]];
                $nextValue = $timeSeries1[$times1[$pointer1]];
                $temp = (($time - $prevTime) * ($nextValue - $prevValue) / ($nextTime - $prevTime));
                $value = $temp + $prevValue;
                $newTimeSeries1[$time] = $value;
                $newTimeSeries2[$time] = $timeSeries2[$time];
                $pointer2 += 1;
            } elseif ($time1 < $time2){
                $prevTime = floatval($times2[$pointer2 - 1]);
                $time = $times1[$pointer1];
                $times[] = $time;
                $nextTime = $time2;
                $prevValue = $timeSeries2[$times2[$pointer2 - 1]];
                $nextValue = $timeSeries2[$times2[$pointer2]];
                $temp = (($time - $prevTime) * ($nextValue - $prevValue) / ($nextTime - $prevTime));
                $value = $temp + $prevValue;
                $newTimeSeries2[$time] = $value;
                $newTimeSeries1[$time] = $timeSeries1[$time];
                $pointer1 += 1;
            }
        }
        return array($newTimeSeries1, $newTimeSeries2);
    }

    /**
     * @param array $x
     * @param array $y
     * @return float[]|int[]
     */
    public static function leastSquareMethod(array $x, array $y): array
    {
        // Count mean of variables. The regression line has to go through [x_mean, y_mean].
        $x_mean = array_sum($x) / count($x);
        $y_mean = array_sum($y) / count($y);
        // Count b1 denominator.
        $b = 0;
        foreach($x as $x_value){
            $b += pow($x_value - $x_mean, 2);
        }
        // Count b1 factor.
        $a = 0;
        count($x) < count($y) ? $len = count($x): $len = count($y);
        for ($i = 0; $i < $len; $i++){
            $x_value = $x[$i];
            $y_value = $y[$i];
            $a += ($x_value - $x_mean) * ($y_value - $y_mean);
        }
        $b1 = $a / $b;
        // From equation (y_mean = b0 + b1 * x_mean) get b0.
        $b0 = $y_mean - ($b1 * $x_mean);
        return array($b0, $b1);
    }

    /**
     * @param array $x
     * @param array $y
     * @return float[]|int[]
     */
    public static function exponentialLeastSquareMethod(array $x, array $y): array
    {
        // transform to least square method
        $ln_y = array();
        foreach($y as $y_value){
            if($y_value != 0) {
                $ln_y[] = log($y_value);
            } else {
                $ln_y[] = 0;
            }
        }
        list($b0, $b1) = self::leastSquareMethod($x, $ln_y);
        // transform back
        $r = $b1;
        $a = exp($b0);
        // y = $a * e^($r * x)
        return array($a, $r);
    }

    public static function solveSystemOfEquations($A, $x): array
    {
        for ($i = 0; $i < count($A); $i++) {
            $A[$i][] = $x[$i];
        }
        $n = count($A);
        for ($i = 0; $i < $n; $i++) {
            $maxEl  = abs($A[$i][$i]);
            $maxRow = $i;
            for ($k = $i + 1; $k < $n; $k++) {
                if (abs($A[$k][$i]) > $maxEl) {
                    $maxEl  = abs($A[$k][$i]);
                    $maxRow = $k;
                }
            }
            for ($k = $i; $k < $n + 1; $k++) {
                $tmp            = $A[$maxRow][$k];
                $A[$maxRow][$k] = $A[$i][$k];
                $A[$i][$k]      = $tmp;
            }
            for ($k = $i + 1; $k < $n; $k++) {
                $c = -$A[$k][$i] / $A[$i][$i];
                for ($j = $i; $j < $n + 1; $j++) {
                    if ($i == $j) {
                        $A[$k][$j] = 0;
                    } else {
                        $A[$k][$j] += $c * $A[$i][$j];
                    }
                }
            }
        }
        $x = array_fill(0, $n, 0);
        for ($i = $n - 1; $i > -1; $i--) {
            $x[$i] = $A[$i][$n] / $A[$i][$i];
            for ($k = $i - 1; $k > -1; $k--) {
                $A[$k][$n] -= $A[$k][$i] * $x[$i];
            }
        }
        foreach($x as $coefficient){
            if(is_nan($coefficient)){
                throw new CountingError("Too large matrix degree.");
            }
        }
        return $x;
    }

    public static function visualizeResult(ExperimentId $expId, string $analysisName, array $data, array $legend): array
    {
        $graphsets = [];
        array_push($graphsets, ["name" => "All",
            "datasets" => array_fill(0, count($data), True)]);
        return [
            'model' => false,
            'id' => strval($expId),
            'name' => $analysisName,
            "xAxisName"=>"Time",
            "yAxisName"=>"Species [molecules/cell]",
            "datasets"=>[[
                "name" => $analysisName,
                "data"=>$data
            ]],
            "legend"=> $legend,
            "graphsets" => $graphsets,
            "legendItems" => null,
            "datasetsVisibility"=>null];
    }
}