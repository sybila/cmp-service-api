<?php


use App\Exceptions\AccessForbiddenException;
use Libs\DataApi;
use Slim\Http\Request;

class ModelChartData
{
    /** @var string
     * Model charts come from csv.
     */
    private $csvPath;

    private $modelId;
    private $modelName;
    private $legend = [];
    private $variablesList = [];
    private $accessToken = "";
    private $graphsets;

    public function __construct($accessToken, $csvPath, $modelId){
        $this->xMin = PHP_FLOAT_MAX;
        $this->csvPath = $csvPath;
        $this->modelId = $modelId;
        // check user can access experiment
        $this->modelName = $this->checkModel($accessToken, $modelId);
    }

    private function checkModel($accessToken, $modelId)
    {
        $access = DataApi::get("models/". $modelId, $accessToken);
        if($access['status'] != 'ok'){
            throw new AccessForbiddenException("Not authorized.");
        } else {
            $this->graphsets = $access['data']['graphsets'];
            return $access['data']['name'];
        }
    }

    function random_color($id) {
        return substr(md5($id), 0, 6);
    }

    private function createLegend(){
        $color = 0;
        $at = 0;
        foreach($this->variablesList as $key => $var){
            if ($at === 0) {
                unset($this->variablesList[$key]);
            } else {
                $this->legend[] = ['name'=> $var, 'color'=> $this->random_color($color)];
                $color+=2;
            }
            $at++;
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

    public function getContentChart($datasetName){
        $data = $this->parseCSV();
        return [
            'model' => true,
            'id' => $this->modelId,
            'name' => $this->modelName,
            "xAxisName"=>"Time",
            "yAxisName"=>"Species [people]",
            "datasets"=>[[
                "name" => $datasetName,
                "data"=> $data
            ]],
            "legend"=> $this->legend,
            "graphsets" => $this->createGraphsets(),
            "legendItems"=>null,
            "datasetsVisibility"=>null];
    }

    public function parseCSV()
    {
        $legendLoaded = false;
        $completeData = [];
        $countVars = 0;
        if (($handle = fopen($this->csvPath, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, "\t")) !== FALSE) {
                if (!$legendLoaded) {
                    $legendLoaded = true;
                    $this->variablesList = $data;
                    foreach ($this->variablesList as $key => $var)
                    {
                        if ($var !== "") {
                            array_push($completeData, []);
                        } else {
                            unset($this->variablesList[$key]);
                        }
                    }
                    $this->createLegend();
                    $countVars = count($this->variablesList);
                } else {
                    for ($pos=0; $pos < $countVars; $pos++) {
                        if (key_exists($pos, $data)) {
                            array_push($completeData[$pos], $data[$pos]);
                        }
                    }
                }
            }
            fclose($handle);
        }
        return $completeData;
    }

}