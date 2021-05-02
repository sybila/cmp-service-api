<?php
namespace Controllers\Endpoints;

use App\Exceptions\NonExistingAnalysisMethod;
use App\Exceptions\OperationFailedException;
use Controllers\Abstracts\AbstractController;
use DOMDocument;
use DOMXPath;
use Libs\DataApi;
use ModelChartData;
use ReflectionMethod;
use Slim\Http\Request;
use Slim\Http\Response;

class Copasi
{
    private $binaryPath = '../../.copasi/bin/CopasiSE';
    private $cpsPath;
    private $sbmlPath;
    private $reportPath;
    private $errors;
    private $modelAlias;

    public function createCopasiSource($modelId, $accessToken, $dataset)
    {
        $data = DataApi::getWithBody("models/". $modelId . '/SBML', $accessToken, json_encode($dataset));
        $name = md5($data);
        $this->modelAlias = $name;

        $fileSystem = getcwd() . '/../file_system/models/';
        if (!is_dir($fileSystem . "model_{$modelId}")) {
            mkdir($fileSystem . "model_{$modelId}");
        }
        $modelPath = $fileSystem . "model_{$modelId}/" . $name;
        file_put_contents($modelPath . '.sbml', $data);

        $this->cpsPath = $modelPath . '.cps';
        $this->sbmlPath = $modelPath . '.sbml';
        $this->errors = $modelPath . '_errors.txt';
        $this->reportPath = $modelPath;
        $command = "{$this->binaryPath} --home \\ --nologo  -i {$this->sbmlPath} -s {$this->cpsPath}";
        exec($command, $cmdOutput, $cmdReturnValue);
        return $cmdReturnValue;
    }

    public function timeCourse($timeCourseSettings, string $detSolver)
    {
        $this->reportPath = $this->reportPath . '_timeCourse.csv';

        $cps = new DOMDocument();
        $cps->load($this->cpsPath);
        $xpath = new DOMXpath($cps);

        $xpath->registerNamespace('x', "http://www.copasi.org/static/schema");
        $tasks = $xpath->query("/x:COPASI/x:ListOfTasks/x:Task[@name='Time-Course']");

        foreach($tasks as $item) {
            //$item->appendChild($cps->createElement('Report'));
            $item->setAttribute("scheduled", "true");

//            $bar = $item->replaceChild($item->lastChild, $item->firstChild);
//            $item->appendChild($bar);
        }

        $tasks = $xpath->query("/x:COPASI/x:ListOfTasks/x:Task[@name='Time-Course']/x:Report");


        foreach($tasks as $item) {
            /** @var \DOMElement $item */
            //$item->setAttribute("reference", "rpt");
            $item->setAttribute("target",  $this->reportPath);
            $item->setAttribute("append", "0");
        }
        $timeCourseAttributes = $xpath->query("/x:COPASI/x:ListOfTasks/x:Task[@name='Time-Course']/x:Problem/x:Parameter");

        foreach($timeCourseAttributes as $item) {
            switch($item->getAttribute("name")) {
                case "StepNumber":
                    $item->setAttribute("value", "{$timeCourseSettings['sim_steps']}");
                    break;
                case "StepSize":
                    $item->setAttribute("value", "{$timeCourseSettings['sim_step_size']}");
                    break;
                case "Duration":
                    $item->setAttribute("value", "{$timeCourseSettings['sim_end']}");
                    break;
                case "TimeSeriesRequested":
                    $item->setAttribute("value", "{$timeCourseSettings['sim_time_series']}");
                    break;
                case "OutputStartTime":
                    $item->setAttribute("value", "{$timeCourseSettings['sim_start']}");
                    break;
                case "Output Event":
                    $item->setAttribute("value", "{$timeCourseSettings['sim_event']}");
                    break;
            }
        }

        $timeCourseMethodAttributes = $xpath->query("/x:COPASI/x:ListOfTasks/x:Task[@name='Time-Course']/x:Method/x:Parameter");

        foreach($timeCourseMethodAttributes as $item) {
            switch($item->getAttribute("name")) {
                case "Integrate Reduced Model":
                    $item->setAttribute("value", "{$timeCourseSettings['sim_reduced_model']}");
                    break;
                case "Relative Tolerance":
                    $item->setAttribute("value", "{$timeCourseSettings['sim_relative_tolerance']}");
                    break;
                case "Absolute Tolerance":
                    $item->setAttribute("value", "{$timeCourseSettings['sim_absolute_tolerance']}");
                    break;
                case "Max Internal Steps":
                    $item->setAttribute("value", "{$timeCourseSettings['sim_max_internal_steps']}");
                    break;
            }
        }

        $solver = $xpath->query("/x:COPASI/x:ListOfTasks/x:Task[@name='Time-Course']/x:Method[@type='Deterministic(LSODA)']");
        if ($solver->length >= 1) {
            $solver = $solver->item(0);
            $solver->setAttribute('type', "Deterministic($detSolver)");
            $solver->setAttribute('name', "Deterministic ($detSolver)");
        }

//        $lCompartments = $xpath->query("/x:COPASI/x:Model/x:ListOfCompartments/x:Compartment");


//        foreach($lCompartments as $item) {
//            $key = $item->getAttribute("key");
//            $name = $item->getAttribute("name");
//            $Cs[$key] = $name;
//        }
//
//
//        $lMetabolites = $xpath->query("/x:COPASI/x:Model/x:ListOfMetabolites/x:Metabolite");
//
//        foreach($lMetabolites as $item) {
//            $name = $item->getAttribute("name");
//            $compartment = $item->getAttribute("compartment");
//            foreach($Cs as $k => $itemCs) {
//                if($k==$compartment) {
//                    $c = $itemCs;
//                }
//            }
//            $Ms[$name] = $c;
//        }

//        $lReports = $xpath->query("/x:COPASI/x:ListOfReports");
//
//        foreach($lReports as $item) {
//            $rpt = $cps->createElement('Report');
//            $rpt->setAttribute("key", "rpt");
//            $rpt->setAttribute("name", "Time");
//            $rpt->setAttribute("taskType", "timeCourse");
//            $rpt->setAttribute("separator", " ");
//            $rpt->setAttribute("precision", "6");
//
//            $table = $cps->createElement('Table');
//            $table->setAttribute("printTitle", "1");
//
//            //smycka
//            $contentCN="CN=Root,Model=NoName,Reference=Time";
//            $object = $cps->createElement('Object');
//            $object->setAttribute("cn", "{$contentCN}");
//            $table->appendChild($object);

//            foreach($Ms as $k => $itemMs) {
//                $contentCN="CN=Root,Model=NoName,Vector=Compartments[";
//                $contentCN.=$itemMs;
//                $contentCN.="],Vector=Metabolites[";
//                $contentCN.=$k;
//                $contentCN.="],Reference=Concentration";
//                $object = $cps->createElement('Object');
//                $object->setAttribute("cn", "{$contentCN}");
//                $table->appendChild($object);
//            }


//            $rpt->appendChild($table);
//
//
//            $item->appendChild($rpt);
//        }

        $cps->save($this->cpsPath);

        $command = "\"{$this->binaryPath}\" --home \\ --nologo  {$this->cpsPath} 2> {$this->errors}";

        exec($command, $results, $retVar);
        if ($retVar != 0) {
            $strErr = preg_replace('/\/.*\.cps/', $this->modelAlias, file_get_contents($this->errors));
            throw new OperationFailedException(str_replace(PHP_EOL, ' ', $strErr));
        }

        $this->trimCopasiReport('Time-Course Result');
        return $this->reportPath;
    }

    protected function trimCopasiReport(string $name)
    {
        $output = file_get_contents($this->reportPath);
        file_put_contents($this->reportPath,substr(strstr($output, $name), strlen($name) + 3)); //3 = : + \n + \n
    }

    /**
     * @return mixed
     */
    public function getCpsPath()
    {
        return $this->cpsPath;
    }

    /**
     * @param mixed $cpsPath
     */
    public function setCpsPath($cpsPath): void
    {
        $this->cpsPath = $cpsPath;
    }

    /**
     * @return mixed
     */
    public function getSbmlPath()
    {
        return $this->sbmlPath;
    }

    /**
     * @param mixed $sbmlPath
     */
    public function setSbmlPath($sbmlPath): void
    {
        $this->sbmlPath = $sbmlPath;
    }

}

class CopasiImplementation
{

    /**
     * @param string $accessToken
     * @param int $modelId
     * @param array $dataset
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
     * @param string|null $solver LSODA or RADAU5
     * @return array
     * @throws OperationFailedException
     */
    static function simulation(string $accessToken, int $modelId, array $dataset, float $sim_steps, float $sim_step_size, float  $sim_end, float $sim_time_series,
                               float $sim_start, float $sim_event, float $sim_reduced_model, float$sim_relative_tolerance,
                               float $sim_absolute_tolerance, float $sim_max_internal_steps, ?string $solver) {

        $time_course_settings = ['sim_steps' => $sim_steps, 'sim_step_size' => $sim_step_size, 'sim_end' =>  $sim_end,
            'sim_time_series' => $sim_time_series, 'sim_start' => $sim_start, 'sim_event' => $sim_event,
            'sim_reduced_model' => $sim_reduced_model, 'sim_relative_tolerance' => $sim_relative_tolerance,
            'sim_absolute_tolerance' => $sim_absolute_tolerance, 'sim_max_internal_steps' => $sim_max_internal_steps];

        $task = new Copasi();
        $task->createCopasiSource($modelId, $accessToken, $dataset);
        $solver = in_array($solver,['LSODA', 'RADAU5']) ? $solver : 'LSODA';
        $path = $task->timeCourse($time_course_settings, $solver);
        return (new ModelChartData($accessToken, $path, $modelId))
            ->getContentChart(key_exists('name', $dataset) ? $dataset['name'] : 'custom');
    }



}