<?php
namespace Libs;
use App\Exceptions\AccessForbiddenException;
use App\Exceptions\OperationFailedException;
use Phpml\Math\Statistic;
use Slim\Http\Request;

class AnalysisLib{
    public static function getVariableValues(string $accessToken, int $expId, int $varId): array
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

    public static function getVariableTimeSeries(string $accessToken, int $expId, int $varId){
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
     * Align two variables.
     *
     * Make imputation for two variables and return two align variables by times in format array(<time> => <value>).
     *
     * @param string $accessToken
     * @param int $expId1
     * @param int $varId1
     * @param int $expId2
     * @param int $varId2
     * @return array
     * @throws AccessForbiddenException
     */
    public static function alignTwoVariables(string $accessToken, int $expId1, int $varId1, int $expId2, int $varId2): array
    {
        $timeSeries1 = AnalysisLib::getVariableTimeSeries($accessToken, $expId1, $varId1);
        $timeSeries2 = AnalysisLib::getVariableTimeSeries($accessToken, $expId2, $varId2);
        if(count($timeSeries1) <= 1 or count($timeSeries2) <= 1){
            return array($timeSeries1, $timeSeries2);
        }
        $pointer1 = 0;
        $pointer2 = 0;
        $times1 = array_keys($timeSeries1);
        $times2 = array_keys($timeSeries2);
        if(floatval($times1[0]) < floatval($times2[0])){
            $pointer1 ++;
        } elseif (floatval($times1[0]) > floatval($times2[0])){
            $pointer2 ++;
        }

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
        // Count mean of variables. The regression line has to go through [x mean, mean].
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
}