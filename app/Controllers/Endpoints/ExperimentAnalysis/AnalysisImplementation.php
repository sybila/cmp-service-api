<?php
namespace Controllers\Endpoints\AnalysisManager;
use App\Exceptions\AccessForbiddenException;
use App\Exceptions\OperationFailedException;
use ExperimentId;
use Libs\AnalysisLib;
use JamesAusten\PhpZscore\ZScore;
use VariableId;

class Implementation {

    /**
     * Average of one variable.
     * @param string $accessToken
     * @param ExperimentId $experiment Experiment identifier
     * @param VariableId $variable Variable identifier
     * @return float Return decimal number mean of variable data.
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function variableMean(string $accessToken, ExperimentId $experiment, VariableId $variable): float
    {
        $values = AnalysisLib::getVariableValues($accessToken, $experiment, $variable);
        return AnalysisLib::mean($values);
    }

    /**
     * Maximum of one variable.
     * @param string $accessToken
     * @param ExperimentId $experiment
     * @param VariableId $variable
     * @return mixed
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function variableMaximum(string $accessToken, ExperimentId $experiment, VariableId $variable): float
    {
        $values = AnalysisLib::getVariableValues($accessToken, $experiment, $variable);
        return number_format(max($values), 3, '.', '');
    }

    /**
     * Minimum of one variable.
     * @param string $accessToken
     * @param ExperimentId $experiment
     * @param VariableId $variable
     * @return mixed
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function variableMinimum(string $accessToken, ExperimentId $experiment, VariableId $variable): float
    {
        $values = AnalysisLib::getVariableValues($accessToken, $experiment, $variable);
        return number_format(min($values), 3, '.', '');
    }

    /**
     * Median of one variable.
     * @param string $accessToken
     * @param ExperimentId $experiment
     * @param VariableId $variable
     * @return mixed
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function variableMedian(string $accessToken, ExperimentId $experiment, VariableId $variable): float
    {
        $values = AnalysisLib::getVariableValues($accessToken, $experiment, $variable);
        $sorted_values = sort($values);
        return $sorted_values[round(count($values) / 2)];
    }

    /**
     * @param string $accessToken
     * @param ExperimentId $experiment
     * @param VariableId $variable
     * @param bool $isWholePopulation
     * @return float|int
     * @throws AccessForbiddenException
     * @throws OperationFailedException
     */
    static function variableVariance(string $accessToken, ExperimentId $experiment, VariableId $variable, bool $isWholePopulation){
        $values = AnalysisLib::getVariableValues($accessToken, $experiment, $variable);
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
     * @param ExperimentId $experiment
     * @param VariableId $variable
     * @param bool $isWholePopulation
     * @return float
     * @throws AccessForbiddenException
     * @throws OperationFailedException
     */
    static function variableStandardDeviation(string $accessToken, ExperimentId $experiment, VariableId $variable, bool $isWholePopulation): float
    {
        $variance = self::variableVariance($accessToken, $experiment, $variable, $isWholePopulation);
        return sqrt($variance);
    }

    /**
     * @param string $accessToken
     * @param ExperimentId $experiment1
     * @param VariableId $variable1
     * @param ExperimentId $experiment2
     * @param VariableId $variable2
     * @return array
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function twoVariablesMean(string $accessToken, ExperimentId $experiment1, VariableId $variable1, ExperimentId $experiment2, VariableId $variable2): array
    {
        list($timeSeries1, $timeSeries2) = AnalysisLib::alignTwoVariables($accessToken, $experiment1, $variable1, $experiment2, $variable2);
        $mean = array();
        $times = array_keys($timeSeries1);
        $values1 = array_values($timeSeries1);
        $values2 = array_values($timeSeries2);
        foreach ($times as $time){
            $value = ($timeSeries1[$time] + $timeSeries2[$time]) / 2;
            $mean[] = $value;
        }
        $legend = array(
            array("name"=> "Variable 1", "color"=> "6364d3"),
            array("name"=> "Variable 2", "color"=> "9163d3"),
            array("name"=> "Mean", "color"=> "f07058"));
        $data = array(
            $times,
            $values1,
            $values2,
            $mean,
        );
        return AnalysisLib::visualizeResult($experiment1, "Two Variables Mean", $data, $legend);
    }

    /**
     * @param string $accessToken
     * @param ExperimentId $experiment1
     * @param VariableId $variable1
     * @param ExperimentId $experiment2
     * @param VariableId $variable2
     * @return array
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function twoVariablesDifference(string $accessToken, ExperimentId $experiment1, VariableId $variable1, ExperimentId $experiment2, VariableId $variable2): array
    {
        list($timeSeries1, $timeSeries2) = AnalysisLib::alignTwoVariables($accessToken, $experiment1, $variable1, $experiment2, $variable2);
        $times = array_keys($timeSeries1);
        $values1 = array_values($timeSeries1);
        $values2 = array_values($timeSeries2);
        $difference = array();
        foreach (array_keys($timeSeries1) as $time){
            $value = $timeSeries1[$time] - $timeSeries2[$time];
            $difference[] = $value;
        }
        $legend = array(
            array("name"=> "Variable 1", "color"=> "6364d3"),
            array("name"=> "Variable 2", "color"=> "9163d3"),
            array("name"=> "Difference", "color"=> "f07058"));
        $data = array(
            $times,
            $values1,
            $values2,
            $difference,
        );
        return AnalysisLib::visualizeResult($experiment1, "Two Variables Difference", $data, $legend);
    }

    /**
     * @param string $accessToken
     * @param ExperimentId $experiment
     * @param VariableId $variable
     * @return array
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function variableLinearRegression(string $accessToken, ExperimentId $experiment, VariableId $variable): array
    {
        $timeSeries = AnalysisLib::getVariableTimeSeries($accessToken, $experiment, $variable);
        $times = array_keys($timeSeries);
        $values = array_values($timeSeries);
        list($b0, $b1) = AnalysisLib::leastSquareMethod($times, $values);
        $linearValues = [];
        foreach($times as $time){
            $linearValues[] = $b0 + ($b1 * $time);
        }
        $legend = array(
                    array("name"=> "Time series", "color"=> "6364d3"),
                    array("name"=> "Linear regression", "color"=> "f07058"));
        $data = array($times, $values, $linearValues);
        return AnalysisLib::visualizeResult($experiment, "Linear regression", $data, $legend);
    }

    /**
     * @param string $accessToken
     * @param ExperimentId $experiment1
     * @param VariableId $variable1
     * @param ExperimentId $experiment2
     * @param VariableId $variable2
     * @return array
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function twoVariablesLinearRegression(string $accessToken, ExperimentId $experiment1, VariableId $variable1, ExperimentId $experiment2, VariableId $variable2): array
    {
        list($timeSeries1, $timeSeries2) = AnalysisLib::alignTwoVariables($accessToken, $experiment1, $variable1, $experiment2, $variable2);
        $xValues = array_values($timeSeries1);
        $yValues = array_values($timeSeries2);
        list($b0, $b1) = AnalysisLib::leastSquareMethod($xValues, $yValues);
        $linearRegression = array();
        foreach($xValues as $x){
            $linearRegression[] = $b0 + ($b1 * $x);
        }
        $legend = array(
                    array("name"=> "Variable 1", "color"=> "6364d3"),
                    array("name"=> "Variable 2", "color"=> "f07058"));
        $data = array(
            $xValues, $yValues, $linearRegression
        );
        return AnalysisLib::visualizeResult($experiment1,"Linear regression", $data, $legend);
    }

    /**
     * @param string $accessToken
     * @param ExperimentId $experiment
     * @param VariableId $variable
     * @return string
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function variableCourse(string $accessToken, ExperimentId $experiment, VariableId $variable): string
    {
        $timeSeries = AnalysisLib::getVariableTimeSeries($accessToken, $experiment, $variable);
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
     * @param ExperimentId $experiment
     * @param VariableId $variable
     * @return array
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function variableExponentialRegression(string $accessToken, ExperimentId $experiment, VariableId $variable): array
    {
        $timeSeries = AnalysisLib::getVariableTimeSeries($accessToken, $experiment, $variable);
        $times = array_keys($timeSeries);
        $values = array_values($timeSeries);
        list($a, $r) = AnalysisLib::exponentialLeastSquareMethod($times, $values);
        /*$time = min($times);
        $maxTime = max($times);
        $step = ($maxTime - $time) / 100;
        while($time <= $maxTime){
            $value = $a * exp($r * $time);
            $exponentialRegressionTimeSeries[] = array('time' => $time, 'value' => $value);
            $time += $step;
        }*/
        $exponentialValues = [];
        foreach($times as $time){
            $exponentialValues[] = $a * exp($r * $time);;
        }
        $legend = array(
                    array("name"=> "Time series", "color"=> "6364d3"),
                    array("name"=> "Exponential Regression", "color"=> "f07058"));
        $data = array(
            $times, $values, $exponentialValues
        );
        return AnalysisLib::visualizeResult($experiment, "Exponential regression", $data, $legend);
    }

    /**
     * @param string $accessToken
     * @param ExperimentId $experiment1
     * @param VariableId $variable1
     * @param ExperimentId $experiment2
     * @param VariableId $variable2
     * @return array
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function twoVariablesExponentialRegression(string $accessToken, ExperimentId $experiment1, VariableId $variable1, ExperimentId $experiment2, VariableId $variable2): array
    {
        list($timeSeries1, $timeSeries2) = AnalysisLib::alignTwoVariables($accessToken, $experiment1, $variable1, $experiment2, $variable2);
        $xValues = array_values($timeSeries1);
        $yValues = array_values($timeSeries2);
        list($a, $r) = AnalysisLib::exponentialLeastSquareMethod($xValues, $yValues);
        /*$exponentialRegression = array();
        $xMax = max($xValues);
        $step = $xMax / 100;
        $x = 0;
        while ($x < $xMax){
            $y = $a * exp($r * $x);
            $exponentialRegression[] = array('x' => $x, 'y' => $y);
            $x += $step;
        }*/
        $exponentialValues = [];
        foreach($xValues as $time){
            $exponentialValues[] = $a * exp($r * $time);;
        }
        $legend = array(
            array("name"=> "Variable 1", "color"=> "6364d3"),
            array("name"=> "Variable 2", "color"=> "f07058"));
        $data = array(
            $xValues, $yValues, $exponentialValues
        );
        return AnalysisLib::visualizeResult($experiment1, "Exponential regression", $data, $legend);
    }

    /**
     * @param string $accessToken
     * @param ExperimentId $experiment
     * @param VariableId $variable
     * @param int|null $lag
     * @param float|null $influence
     * @param float|null $threshold
     * @return array
     * @throws AccessForbiddenException
     * @throws OperationFailedException
     */
    static function variableFindPeaks(string $accessToken, ExperimentId $experiment, VariableId $variable, ?int $lag, ?float $influence, ?float $threshold): array
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
        $timeSeries = AnalysisLib::getVariableTimeSeries($accessToken, $experiment, $variable);
        $values = array_values($timeSeries);
        // find peaks
        $highlightedPeaks = $zScore->calculate($values);
        $peaks = array();
        $times = array_keys($timeSeries);
        for($i = 0; $i < count($values); $i++){
            if($highlightedPeaks[$i] == 1 or $highlightedPeaks[$i] == -1){
                $peaks[] = $values[$i];
            } else {
                $peaks[] = null;
            }
        }
        $legend = array(
            array("name"=> "Variable", "color"=> "6364d3"),
            array("name"=> "Peaks", "color"=> "f07058"));
        $data = array(
            $times, $values, $peaks
        );
        return AnalysisLib::visualizeResult($experiment, "Peaks", $data, $legend);
    }

    /**
     * @param string $accessToken
     * @param ExperimentId $experiment1
     * @param VariableId $variable1
     * @param ExperimentId $experiment2
     * @param VariableId $variable2
     * @return float|int
     * @throws AccessForbiddenException
     * @throws OperationFailedException
     */
    static function twoVariablesCorrelation(string $accessToken, ExperimentId $experiment1, VariableId $variable1, ExperimentId $experiment2, VariableId $variable2): float{
        list($timeSeries1, $timeSeries2) = AnalysisLib::alignTwoVariables($accessToken, $experiment1, $variable1, $experiment2, $variable2);
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

    static function polynomialRegression(string $accessToken, ExperimentId $experiment, VariableId $variable, int $maximumDegree): array {
        $timeSeries = AnalysisLib::getVariableTimeSeries($accessToken, $experiment, $variable);
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
        $polynomialRegression = array();
        $coefficients = AnalysisLib::cramersRule($matrix, $targets);
        foreach($times as $time){
            $value = 0;
            for($i = 0; $i < $maximumDegree; $i++){
                $value += pow($time, $i) * $coefficients[$i];
            }
            $polynomialRegression[] = $value;
        }
        $legend = array(
            array("name"=> "Variable", "color"=> "6364d3"),
            array("name"=> "Polynomial Regression", "color"=> "f07058"));
        $data = array(
            $times, $values, $polynomialRegression
        );
        return AnalysisLib::visualizeResult($experiment, "Polynomial Regression", $data, $legend);
    }
}