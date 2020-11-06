<?php


namespace Controllers\Endpoints;


use Libs\DataApi;
use Slim\Http\Request;
use Slim\Http\Response;

class ExperimentChartData extends ExperimentAccess
{
    private $expId;
    private $legend = [];
    private $xMin;
    private $xMax = 0;
    private $step = 0;
    private $lines = [];
    private $variablesList = [];
    private $accessToken = "";

    public function __construct(Request $request, $expId){
        $this->xMin = PHP_FLOAT_MAX;
        $this->expId = $expId;
        // check user can access experiment
        $this->accessToken = self::checkAccess($request, $expId);
    }

    //find the nearest value before time
    private function binarySearchTime($arr, $key)
    {
        $n = count($arr);
        $left = 0;
        $right = count($arr);
        $mid = (int)(($right + $left) / 2);
        while ($left < $right)
        {
            $mid = (int)(($right + $left) / 2);
            if ($arr[$mid]['time'] == $key)
            {
                while ($mid + 1 < $n && $arr[$mid + 1]['time'] == $key)
                    $mid++;
                break;
            }
            else if ($mid > -1 && $arr[$mid]['time'] > $key)
                $right = $mid;
            else
                $left = $mid + 1;
        }

        while ($mid >= 0 and $arr[$mid]['time'] > $key){
            $mid--;
        }
        if($mid < 0){
            return null;
        }
        return $arr[$mid]['value'];
    }

    private function getExperimentVariables(){
        $vars = DataApi::get("experiments/". $this->expId. "/variables", $this->accessToken);
        if($vars['status'] == 'ok'){
            $this->variablesList = $vars['data'];
        }
    }

    private function addLines(){
        foreach ($this->variablesList as $var){
            $vals = DataApi::get("experiments/". $this->expId. "/variables/" . $var['id'] . "/values?sort[time]=asc" , $this->accessToken);
            $vals = $vals['data'];
            if(!empty($vals)) {
                if ($this->xMin > current($vals)['time']) {
                    $this->xMin = current($vals)['time'];
                }
                if ($this->xMax < end($vals)['time']) {
                    $this->xMax = end($vals)['time'];
                }
                $this->lines[] = $vals;
            }
        }
        $this->step = (($this->xMax - $this->xMin) / 100.0);
    }

    private function dataInterpolation(){
        $interpolatedLines = [];
        $times = [];
        $first = false;
        foreach($this->lines as $line){
            $step = $this->xMin;
            $interpolatedLine = [];
            while($step <= $this->xMax){
                $first ?: $times[] = $step;
                $interpolatedLine[] = $this->binarySearchTime($line, $step);
                $step += $this->step;
            }
            $first ?: $interpolatedLines[] = $times;
            $first = true;
            $interpolatedLines[] = $interpolatedLine;
        }
        return $interpolatedLines;
    }

    private function getExperimentName(){
        $exp = DataApi::get("experiments/". $this->expId, $this->accessToken);
        if($exp['status'] == 'ok'){
            return $exp['data']['name'];
        }
    }

    function random_color_part() {
        return str_pad( dechex( mt_rand( 0, 255 ) ), 2, '0', STR_PAD_LEFT);
    }

    function random_color() {
        return $this->random_color_part() . $this->random_color_part() . $this->random_color_part();
    }

    private function createLegend(){
        foreach($this->variablesList as $var){
            $this->legend[] = ['name'=>$var['name'], 'color'=> $this->random_color()];
        }
    }

    public function getContentChart(){
        $this->getExperimentVariables();
        $this->addLines();
        $this->createLegend();
        $data = $this->dataInterpolation();
        $name = $this->getExperimentName();
        return [
            'model' => false,
            'id' => $this->expId,
            'name' => $name,
            "xAxisName"=>"Time",
            "yAxisName"=>"Species [molecules/cell]",
            "datasets"=>[[
                "name" => $name,
                    "data"=>$data
            ]],
            "legend"=> $this->legend,
            "graphsets"=>[
                ["name"=>"All",
                    "datasets"=>array_fill(0, count($this->variablesList), True)]
            ],
            "legendItems"=>null,
            "datasetsVisibility"=>null];
    }
}