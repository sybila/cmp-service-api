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
     * The mean of one variable.
     * @param string $accessToken
     * @param ExperimentId $experiment Experiment identifier
     * [group=Variable]
     * @param VariableId $variable Variable identifier
     * [group=Variable]
     * @return float Return decimal number mean of variable data.
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function variableMean(string $accessToken, ExperimentId $experiment, VariableId $variable): float
    {
        $values = AnalysisLib::getVariableValues($accessToken, $experiment, $variable);
        return AnalysisLib::mean($values);
    }

    /**
     * the maximum value of one variable.
     * @param string $accessToken
     * @param ExperimentId $experiment
     * [group=Variable]
     * @param VariableId $variable
     * [group=Variable]
     * @return mixed
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function variableMaximum(string $accessToken, ExperimentId $experiment, VariableId $variable): float
    {
        $values = AnalysisLib::getVariableValues($accessToken, $experiment, $variable);
        return number_format(max($values), 3, '.', '');
    }

    /**
     * the minimum value of one variable.
     * @param string $accessToken
     * @param ExperimentId $experiment
     * [group=Variable]
     * @param VariableId $variable
     * [group=Variable]
     * @return mixed
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function variableMinimum(string $accessToken, ExperimentId $experiment, VariableId $variable): float
    {
        $values = AnalysisLib::getVariableValues($accessToken, $experiment, $variable);
        return number_format(min($values), 3, '.', '');
    }

    /**
     * The median of one variable.
     * @param string $accessToken
     * @param ExperimentId $experiment
     * [group=Variable]
     * @param VariableId $variable
     * [group=Variable]
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
     * The variance of one variable.
     * @param string $accessToken
     * @param ExperimentId $experiment
     * [group=Variable]
     * @param VariableId $variable
     * [group=Variable]
     * @param bool $isWholePopulation Are data measured for whole population?
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
     * The standard deviation of one variable.
     * @param string $accessToken
     * @param ExperimentId $experiment
     * [group=Variable]
     * @param VariableId $variable
     * [group=Variable]
     * @param bool $isWholePopulation Are data measured for whole population?
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
     * The mean of two variables. Result is new time series.
     * @param string $accessToken
     * @param ExperimentId $experiment1 First experiment.
     * [group=Variable1]
     * @param VariableId $variable1 Variable of first experiment.
     * [group=Variable1]
     * @param ExperimentId $experiment2 Second experiment.
     * [group=Variable2]
     * @param VariableId $variable2 Variable of second experiment.
     * [group=Variable2]
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
     * The mean of two variables. Result is new time series.
     * @param string $accessToken
     * @param ExperimentId $experiment1 First experiment.
     * [group=Variable1]
     * @param VariableId $variable1 Variable of first experiment.
     * [group=Variable1]
     * @param ExperimentId $experiment2 Second experiment.
     * [group=Variable2]
     * @param VariableId $variable2 Variable of second experiment.
     * [group=Variable2]
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
     * The linear regression of one variable. Intersects the data with a straight line.
     * @param string $accessToken
     * @param ExperimentId $experiment
     * [group=Variable]
     * @param VariableId $variable
     * [group=Variable]
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
     * [group=Variable1]
     * @param VariableId $variable1
     * [group=Variable1]
     * @param ExperimentId $experiment2
     * [group=Variable2]
     * @param VariableId $variable2
     * [group=Variable2]
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
     * [group=Variable]
     * @param VariableId $variable
     * [group=Variable]
     * @return string
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function variableMonotonicity(string $accessToken, ExperimentId $experiment, VariableId $variable): string
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
     * [group=Variable]
     * @param VariableId $variable
     * [group=Variable]
     * @return array
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function variableExponentialRegression(string $accessToken, ExperimentId $experiment, VariableId $variable): array
    {
        $timeSeries = AnalysisLib::getVariableTimeSeries($accessToken, $experiment, $variable);
        $times = array_keys($timeSeries);
        $values = array_values($timeSeries);
        list($a, $r) = AnalysisLib::exponentialLeastSquareMethod($times, $values);
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
     * [group=Variable1]
     * @param VariableId $variable1
     * [group=Variable1]
     * @param ExperimentId $experiment2
     * [group=Variable2]
     * @param VariableId $variable2
     * [group=Variable2]
     * @return array
     * @throws AccessForbiddenException|OperationFailedException
     */
    static function twoVariablesExponentialRegression(string $accessToken, ExperimentId $experiment1, VariableId $variable1, ExperimentId $experiment2, VariableId $variable2): array
    {
        list($timeSeries1, $timeSeries2) = AnalysisLib::alignTwoVariables($accessToken, $experiment1, $variable1, $experiment2, $variable2);
        $xValues = array_values($timeSeries1);
        $yValues = array_values($timeSeries2);
        list($a, $r) = AnalysisLib::exponentialLeastSquareMethod($xValues, $yValues);
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
     * Find local extremes in variable. There is very important how the parameters are set.
     * @param string $accessToken
     * @param ExperimentId $experiment
     * @param VariableId $variable
     * @param int|null $lag  The lag parameter determines how much your data will be smoothed and how adaptive the algorithm is to changes in the long-term average of the data. The more stationary your data is, the more lags you should include (this should improve the robustness of the algorithm). If your data contains time-varying trends, you should consider how quickly you want the algorithm to adapt to these trends. i.e., if you put lag at 10, it takes 10 'periods' before the algorithm's threshold is adjusted to any systematic changes in the long-term average. So choose the lag parameter based on the trending behaviour of your data and how adaptive you want the algorithm to be.
     * @param float|null $influence This parameter determines the influence of signals on the algorithm's detection threshold. If put at 0, signals have no influence on the threshold, such that future signals are detected based on a threshold that is calculated with a mean and standard deviation that is not influenced by past signals. Another way to think about this is that if you put the influence at 0, you implicitly assume stationarity (i.e. no matter how many signals there are, the time series always returns to the same average over the long term). If this is not the case, you should put the influence parameter somewhere between 0 and 1, depending on the extent to which signals can systematically influence the time-varying trend of the data. e.g., if signals lead to a structural break of the long-term average of the time series, the influence parameter should be put high (close to 1) so the threshold can adjust to these changes quickly.
     * @param float|null $threshold The threshold parameter is the number of standard deviations from the moving mean above which the algorithm will classify a new datapoint as being a signal. For example, if a new datapoint is 4.0 standard deviations above the moving mean and the threshold parameter is set as 3.5, the algorithm will identify the datapoint as a signal. This parameter should be set based on how many signals you expect. For example, if your data is normally distributed, a threshold (or: z-score) of 3.5 corresponds to a signalling probability of 0.00047 (from this table), which implies that you expect a signal once every 2128 datapoints (1/0.00047). The threshold therefore directly influences how sensitive the algorithm is and thereby also how often the algorithm signals. Examine your own data and determine a sensible threshold that makes the algorithm signal when you want it to (some trial-and-error might be needed here to get to a good threshold for your purpose).
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
     * [group=Variable1]
     * @param VariableId $variable1
     * [group=Variable1]
     * @param ExperimentId $experiment2
     * [group=Variable2]
     * @param VariableId $variable2
     * [group=Variable2]
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

    /**
     * @param string $accessToken
     * @param ExperimentId $experiment
     * [group=Experiment]
     * @param VariableId $variable
     * [group=Experiment]
     * @param int $maximumDegree
     * [unsigned=true]
     * @return array
     * @throws AccessForbiddenException
     * @throws OperationFailedException
     */
    static function variablePolynomialRegression(string $accessToken, ExperimentId $experiment, VariableId $variable, int $maximumDegree=3): array {
        $timeSeries = AnalysisLib::getVariableTimeSeries($accessToken, $experiment, $variable);
        $times = array_keys($timeSeries);
        $values = array_values($timeSeries);
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
        $targets = [];
        for($i=0;$i<$maximumDegree;$i++){
            $y = 0;
            foreach ($timeSeries as $time => $value){
                $y += pow($time, $i) * $value;
            }
            $targets[$i] = $y;
        }
        $polynomialRegression = array();
        $coefficients = AnalysisLib::solveSystemOfEquations($matrix, $targets);
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