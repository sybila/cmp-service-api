<?php
namespace Controllers\Endpoints;

use App\Exceptions\NonExistingAnalysisMethod;
use Controllers\Abstracts\AbstractController;
use DOMDocument;
use DOMXPath;
use ReflectionMethod;
use Slim\Http\Request;
use Slim\Http\Response;

class CopasiManager extends \Controllers\Endpoints\AnalysisManager
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
        $class_methods = get_class_methods(copasiImplementation::class);
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
            return new ReflectionMethod(copasiImplementation::class, $name);
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
        $result = $f->invokeArgs((object)copasiImplementation::class, $inputs);
        return ['result' => $result];
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
            $result[] = array('name' => $param->name, 'type' => '' . $param->getType()->__toString());
        }
        return ['name' => $name, 'inputs' => $result, 'output' => ''. $f->getReturnType()];
    }
}


class copasiImplementation
{

    private $binaryPath = "";

    protected function createCopasiSource()
    {
        $command = "\"{$this->binaryPath}\" --home \\ --nologo  -i {$output['sbmlSourceFile']} -s {$output['sourceFile']}";
        exec($command);
    }

    /**
     * @param float $sim_steps
     * @param float $sim_step_size
     * @param float $sim_end
     * @param float $sim_time_series
     * @param float $sim_start
     * @param float $sim_event
     * @param float $sim_reduced_model
     * @param float $sim_relative_tolerance
     * @param float $sim_absolute_tolerance
     * @param float $sim_max_internal_steps
     * @return array
     */
    static function simulation($sim_steps, $sim_step_size,  $sim_end, $sim_time_series,
                                  $sim_start, $sim_event, $sim_reduced_model, $sim_relative_tolerance,
                                  $sim_absolute_tolerance, $sim_max_internal_steps) {
        // zmena xml
        $cps = new DOMDocument();
        $cps->load($output['sourceFile']);

        $xpath = new DOMXpath($cps);


        $xpath->registerNamespace('x', "http://www.copasi.org/static/schema");
        $lTask = $xpath->query("/x:COPASI/x:ListOfTasks/x:Task[@name='Time-Course']");

        foreach($lTask as $item) {
            $item->appendChild($cps->createElement('Report'));
            $item->setAttribute("scheduled", "true");


            $bar = $item->replaceChild($item->lastChild, $item->firstChild);
            $item->appendChild($bar);
        }

        $lTask = $xpath->query("/x:COPASI/x:ListOfTasks/x:Task[@name='Time-Course']/Report");

        foreach($lTask as $item) {
            $item->setAttribute("reference", "rpt");
            $item->setAttribute("target", $output['outputFile']);
            $item->setAttribute("append", "0");
        }

        $lProblem = $xpath->query("/x:COPASI/x:ListOfTasks/x:Task[@name='Time-Course']/x:Problem/x:Parameter");

        foreach($lProblem as $item) {
            switch($item->getAttribute("name")) {
                case "StepNumber":
                    $item->setAttribute("value", "{$this->modelparams['sim_steps']}");
                    break;
                case "StepSize":
                    $item->setAttribute("value", "{$this->modelparams['sim_step_size']}");
                    break;
                case "Duration":
                    $item->setAttribute("value", "{$this->modelparams['sim_end']}");
                    break;
                case "TimeSeriesRequested":
                    $item->setAttribute("value", "{$this->modelparams['sim_time_series']}");
                    break;
                case "OutputStartTime":
                    $item->setAttribute("value", "{$this->modelparams['sim_start']}");
                    break;
                case "Output Event":
                    $item->setAttribute("value", "{$this->modelparams['sim_event']}");
                    break;
            }
        }

        $lMethod = $xpath->query("/x:COPASI/x:ListOfTasks/x:Task[@name='Time-Course']/x:Method/x:Parameter");

        foreach($lMethod as $item) {
            switch($item->getAttribute("name")) {
                case "Integrate Reduced Model":
                    $item->setAttribute("value", "{$this->modelparams['sim_reduced_model']}");
                    break;
                case "Relative Tolerance":
                    $item->setAttribute("value", "{$this->modelparams['sim_relative_tolerance']}");
                    break;
                case "Absolute Tolerance":
                    $item->setAttribute("value", "{$this->modelparams['sim_absolute_tolerance']}");
                    break;
                case "Max Internal Steps":
                    $item->setAttribute("value", "{$this->modelparams['sim_max_internal_steps']}");
                    break;
            }
        }


        $lCompartments = $xpath->query("/x:COPASI/x:Model/x:ListOfCompartments/x:Compartment");


        foreach($lCompartments as $item) {
            $key = $item->getAttribute("key");
            $name = $item->getAttribute("name");
            $Cs[$key] = $name;
        }


        $lMetabolites = $xpath->query("/x:COPASI/x:Model/x:ListOfMetabolites/x:Metabolite");

        foreach($lMetabolites as $item) {
            $name = $item->getAttribute("name");
            $compartment = $item->getAttribute("compartment");
            foreach($Cs as $k => $itemCs) {
                if($k==$compartment) {
                    $c = $itemCs;
                }
            }
            $Ms[$name] = $c;
        }

        $lReports = $xpath->query("/x:COPASI/x:ListOfReports");

        foreach($lReports as $item) {
            $rpt = $cps->createElement('Report');
            $rpt->setAttribute("key", "rpt");
            $rpt->setAttribute("name", "Time");
            $rpt->setAttribute("taskType", "timeCourse");
            $rpt->setAttribute("separator", " ");
            $rpt->setAttribute("precision", "6");

            $table = $cps->createElement('Table');
            $table->setAttribute("printTitle", "1");

            //smycka
            $contentCN.="CN=Root,Model=NoName,Reference=Time";
            $object = $cps->createElement('Object');
            $object->setAttribute("cn", "{$contentCN}");
            $table->appendChild($object);

            foreach($Ms as $k => $itemMs) {
                $contentCN="CN=Root,Model=NoName,Vector=Compartments[";
                $contentCN.=$itemMs;
                $contentCN.="],Vector=Metabolites[";
                $contentCN.=$k;
                $contentCN.="],Reference=Concentration";
                $object = $cps->createElement('Object');
                $object->setAttribute("cn", "{$contentCN}");
                $table->appendChild($object);
            }


            $rpt->appendChild($table);


            $item->appendChild($rpt);
        }

        $cps->save($output['sourceFile']);

        $command = "\"{$this->binaryPath}\" --home \\ --nologo  {$output['sourceFile']} 2> {$output['errorFile']}";
        $output['command'] = $command;

        $results = [];
        if(($loadCached == false) || !file_exists($output['outputFile']))
        {
            $res = exec($command, $results, $retvar);
            $output['result'] = $res;
            $output['retvar'] = $retvar;
        }

        $output['output'] = file_get_contents($output['outputFile']);
        $output['error'] = file_get_contents($output['errorFile']);


        chdir($cwd);
        $results = explode("\n", $output['output']);
        unset($results[0]);

        $data = [];
        foreach($results as $k => $row)
        {
            if(strlen(trim($row)) == 0)
            {
                continue;
            }

            $data[] = explode(" ", trim($row));
        }

        $output['data'] = $data;

        if($detail == true) {
            return $output;
        }

        return $data;
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
//            "graphsets"=>[
//                ["name"=>"All",
//                    "datasets"=>array_fill(0, count($this->variablesList), True)]
//            ],
            "graphsets" => $this->createGraphsets(),
            "legendItems"=>null,
            "datasetsVisibility"=>null];
    }
}