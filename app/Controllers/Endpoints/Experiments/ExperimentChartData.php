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
    private $graphsets;

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

    //Get list of all variables
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
            $this->graphsets = $exp['data']['graphsets'];
            return $exp['data']['name'];
        }
    }

    function random_color($id) {
        return substr(md5($id), 0, 6);
    }

    private function createLegend(){
        $color = 0;
        foreach($this->variablesList as $var){
            $this->legend[] = ['name'=>$var['name'], 'color'=> $this->random_color($color)];
            $color+=2;
        }
    }

    private function createGraphsets(){
        $graphsets = [];
        array_push($graphsets, ["name" => "All",
            "datasets" => array_fill(0, count($this->variablesList), True)]);
        foreach ($this->graphsets as $gs) {
            $visualizedDataset = [];
            foreach ($this->variablesList as $var) {
                $found = false;
                foreach ($gs['variables'] as $gvar) {
                    if ($var['id'] === $gvar['id']) {
                        array_push($visualizedDataset, $gvar['visualize']);
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    array_push($visualizedDataset, false);
                }
            }
            array_push($graphsets, ["name" => $gs['name'],
                "datasets" => $visualizedDataset]);
        }
        return $graphsets;
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
            "yAxisName"=>"Species [people]",
            "datasets"=>[[
                "name" => $name,
                    "data"=>$data
            ]],
            "legend"=> $this->legend,
//            "graphsets"=>[
//                ["name"=>"All",
//                    "datasets"=>array_fill(0, count($this->variablesList), True)]
//            ],
            "graphsets" => $this->createGraphsets(),
            "legendItems"=>null,
            "datasetsVisibility"=>null];
    }
}