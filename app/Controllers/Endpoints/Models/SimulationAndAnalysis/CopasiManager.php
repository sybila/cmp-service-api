<?php
namespace Controllers\Endpoints;

use App\Exceptions\NonExistingAnalysisMethod;
use App\Exceptions\OperationFailedException;
use Controllers\Abstracts\AbstractController;
use DOMDocument;
use DOMXPath;
use LaTeX;
use Libs\DataApi;
use ModelChartData;
use ReflectionMethod;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class Copasi
 * @package Controllers\Endpoints
 * @author Radoslav Doktor
 */
class Copasi
{
    private $binaryPath = '../external_analysis_tools/third_party/copasi/bin/CopasiSE';
    private $cpsPath;
    private $sbmlPath;
    private $reportPath;
    private $errors;
    private $modelAlias;

    public function createCopasiSource($modelId, $accessToken, $dataset = null)
    {
        $data = DataApi::postOutputXML("models/". $modelId . '/SBML', $accessToken, json_encode(['dataset' => $dataset]));
        if (!$data) {
            throw new OperationFailedException('Failed to get the model from the DATA api.');
        }
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
        $command = "{$this->binaryPath} --home \\ --nologo  -i {$this->sbmlPath} -s {$this->cpsPath} 2> {$this->errors}";
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

        if (!$timeCourseSettings['outputEvents']) {
            $events = $xpath->query("/x:COPASI/x:Model/x:ListOfEvents")->item(0);
            $events->parentNode->removeChild($events);
        }
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
                    $item->setAttribute("value", "{$timeCourseSettings['stepNumber']}");
                    break;
                case "StepSize":
                    $item->setAttribute("value", "{$timeCourseSettings['stepSize']}");
                    break;
                case "Duration":
                    $item->setAttribute("value", "{$timeCourseSettings['duration']}");
                    break;
                case "TimeSeriesRequested":
                    $item->setAttribute("value", "1");
                    break;
                case "OutputStartTime":
                    $item->setAttribute("value", "{$timeCourseSettings['outputStartTime']}");
                    break;
                case "Output Event":
                    $item->setAttribute("value", "{$timeCourseSettings['outputEvents']}");
                    break;
            }
        }

        $timeCourseMethodAttributes = $xpath->query("/x:COPASI/x:ListOfTasks/x:Task[@name='Time-Course']/x:Method/x:Parameter");

        foreach($timeCourseMethodAttributes as $item) {
            switch($item->getAttribute("name")) {
                case "Integrate Reduced Model":
                    $item->setAttribute("value", "{$timeCourseSettings['integrateReducedModel']}");
                    break;
                case "Relative Tolerance":
                    $item->setAttribute("value", "{$timeCourseSettings['relativeTolerance']}");
                    break;
                case "Absolute Tolerance":
                    $item->setAttribute("value", "{$timeCourseSettings['absoluteTolerance']}");
                    break;
                case "Max Internal Steps":
                    $item->setAttribute("value", "{$timeCourseSettings['maxInternalSteps']}");
                    break;
                case "Max Internal Step Size":
                    $item->setAttribute("value", "{$timeCourseSettings['maxInternalStepSize']}");
                    break;
            }
        }

        $solver = $xpath->query("/x:COPASI/x:ListOfTasks/x:Task[@name='Time-Course']/x:Method[@type='Deterministic(LSODA)']");
        if ($solver->length >= 1) {
            $solver = $solver->item(0);
            $solver->setAttribute('type', "Deterministic($detSolver)");
            $solver->setAttribute('name', "Deterministic ($detSolver)");
        }


        $cps->save($this->cpsPath);

        $command = "\"{$this->binaryPath}\" --home \\ --nologo  {$this->cpsPath} 2> {$this->errors}";

        exec($command, $results, $retVar);
        $this->checkCopasiError($retVar);
        $this->trimCopasiReport('Time-Course Result');
        return $this->reportPath;
    }

    protected function trimCopasiReport(string $name)
    {
        $output = file_get_contents($this->reportPath);
        file_put_contents($this->reportPath,substr(strstr($output, $name), strlen($name) + 3)); //3 = : + \n + \n
    }

    protected function checkCopasiError(int $returns)
    {
        if ($returns != 0) {
            $strErr = preg_replace('/\/.*\.cps/', $this->modelAlias, file_get_contents($this->errors));
            throw new OperationFailedException(str_replace(PHP_EOL, ' ', $strErr));
        }
    }

    public function stoichiometry()
    {
        $this->reportPath = $this->reportPath . '_stoichiometry.txt';
        $cps = new DOMDocument();

        if(!@$cps->load($this->cpsPath) || (filesize($this->cpsPath) < 100))
        {        }

        $xpath = new DOMXpath($cps);

        $xpath->registerNamespace('x', "http://www.copasi.org/static/schema");
        $lTask = $xpath->query("/x:COPASI/x:ListOfTasks/x:Task[@name='Moieties']");

        foreach($lTask as $item) {
            $item->setAttribute("scheduled", "true");
        }

        $lTask = $xpath->query("/x:COPASI/x:ListOfTasks/x:Task[@name='Moieties']/x:Report");

        foreach($lTask as $item) {
            $item->setAttribute("reference", "mass");
            $item->setAttribute("target", $this->reportPath);
            $item->setAttribute("append", "0");
        }


        $lMethod = $xpath->query("/x:COPASI/x:ListOfTasks/x:Task[@name='Moieties']/Method");

        foreach($lMethod as $item) {
            $item->setAttribute("name", "Householder Reduction");
            $item->setAttribute("type", "Householder");
        }


        $lReports = $xpath->query("/x:COPASI/x:ListOfReports");

        foreach($lReports as $item) {
            $rpt = $cps->createElement('Report');
            $rpt->setAttribute("key", "mass");
            $rpt->setAttribute("name", "mass");
            $rpt->setAttribute("taskType", "moieties");
            $rpt->setAttribute("separator", " ");
            $rpt->setAttribute("precision", "6");

            $header = $cps->createElement('Header');

            //smycka
            $contentCN ="CN=Root,Vector=TaskList[Moieties],Object=Result";
            $object = $cps->createElement('Object');
            $object->setAttribute("cn", "{$contentCN}");
            $header->appendChild($object);


            $rpt->appendChild($header);
            $item->appendChild($rpt);
        }

        $cps->save($this->cpsPath);

        $command = "\"{$this->binaryPath}\" --home \\ --nologo {$this->cpsPath} 2> {$this->errors}";

        exec($command, $cmdOutput, $retVar);
        $this->checkCopasiError($retVar);
        return file_get_contents($this->reportPath);
    }

    public function getZeroDeficiency($matrix)
    {
        $config = require getcwd() . '/../app/settings.local.php';
        $c = new Container($config);
        $python = $c['settings']['python'];
        $size = count($matrix);
        unset($matrix[$size-1]);
        unset($matrix[0]);
        $stringMx = '[';
        $mxSize = count($matrix);
        $rowCount = 0;
        foreach ($matrix as $row){
            $stringRow = '[';
            for ($i = 0; $i < count($row); $i++) {
                if ($i !== 0) {
                    $stringRow .= $row[$i];
                    $stringRow .= count($row) - 1 == $i ? '' : ',';
                }
            }
            $rowCount++;
            $stringMx .= $stringRow;
            $stringMx .= $rowCount == $mxSize ? ']]' : '],';
        }
        $bin = '../external_analysis_tools/ZeroDeficiency.py';
        $command = "$python {$bin} -m $stringMx";
        exec($command, $output, $retVar);
        return array_map(function ($string) {
           return $string;
        }, $output);
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

/**
 * Class CopasiImplementation prepares outputs from CopasiManager class and outputs them.
 * @package Controllers\Endpoints
 * @author Radoslav Doktor
 */
class CopasiImplementation
{

    /**
     * Deterministic time course simulation from COPASI.
     * @param string $accessToken
     * @param int $modelId
     * [group=automatic]
     * @param array $dataset
     * @param float $duration duration of the simulation
     * @param int $stepNumber number of calculated steps during the duration
     * [unsigned=true]
     * @param float $stepSize size of the steps
     * @param float $outputStartTime time when the output starts showing
     * @param bool $applyEvents Do you wish to trigger events in the simulation?
     * @param string|null $solver
     * [group=~advanced]
     * @param bool $integrateReducedModel Shall the integration be performed using the mass conservation laws?
     * [group=~advanced]
     * @param float $relativeTolerance
     * [group=~advanced] [unsigned=true]
     * @param float $absoluteTolerance
     * [group=~advanced] [unsigned=true]
     * @param float $maxInternalSteps maximal number of internal steps the integrator is allowed to take
     * [group=~advanced] [unsigned=true]
     * @param float $maxInternalStepSize maximal size of an internal steps the integrator is allowed to take
     * [group=~advanced]
     * @return array Returns chart information.
     * @throws OperationFailedException
     */
    static function simulation(string $accessToken, int $modelId, array $dataset, float $duration=500,
                               int $stepNumber=500,
                               float $stepSize=1, float $outputStartTime=0, bool $applyEvents=true, string $solver="LSODA",
                               bool $integrateReducedModel=true, float $relativeTolerance=1.0e-06, float $absoluteTolerance=1.0e-12,
                               float $maxInternalSteps=10000, float $maxInternalStepSize=0) :array
    {
        $time_course_settings = ['stepNumber' => $stepNumber, 'stepSize' => $stepSize, 'duration' =>  $duration,
            'outputStartTime' => $outputStartTime, 'outputEvents' => $applyEvents,
            'integrateReducedModel' => $integrateReducedModel, 'relativeTolerance' => $relativeTolerance,
            'absoluteTolerance' => $absoluteTolerance, 'maxInternalSteps' => $maxInternalSteps,
            'maxInternalStepSize' => $maxInternalStepSize];

        $task = new Copasi();
        $task->createCopasiSource($modelId, $accessToken, $dataset);
        $solver = in_array($solver,['LSODA', 'RADAU5']) ? $solver : 'LSODA';
        $path = $task->timeCourse($time_course_settings, $solver);
        return (new ModelChartData($accessToken, $path, $modelId))
            ->getContentChart(key_exists('name', $dataset) ? $dataset['name'] : 'custom');
    }

    /**
     * @param string $accessToken
     * @param int $modelId
     * [group=automatic]
     * @return LaTeX
     * @throws OperationFailedException
     */
    static function stoichiometry(string $accessToken, int $modelId): LaTeX
    {
        $task = new Copasi();
        $task->createCopasiSource($modelId, $accessToken);
        $data = $task->stoichiometry();
        //Transform to LaTEX
        $data = str_replace("\t",' & ', $data);
        preg_match_all('/(.* & ).*(?=\n)/', $data, $allTables);
        $tables = [];
        $table = '';
        $tableDefined = false;
        foreach ($allTables[0] as $row) {
            if (substr($row, 0, 3) === ' & ') {
                $table .= '\end{array}';
                array_push($tables, addcslashes($table, '_^'));
                $tableDefined = false;
            }
            if (!$tableDefined) {
                $rowDef = str_repeat('c|', substr_count($row, ' & '));
                $table = '\begin{array}{' . $rowDef . 'c} \hline ';
                $tableDefined = true;
            }
            $table .= $row . ' \\\\ \hline ';
        }
        $table .= '\end{array}';
        array_push($tables, addcslashes($table, '_^'));
        $result = '$';
        if (!is_null($tables[1]))
        {
            $result .= '\textbf{Stoichiometry Matrix} \\\\ \textit{Rows: Species that are controlled by reactions} \\\\ ' .
                '\textit{Columns: Reactions} \\\\ ' . $tables[1] . ' \\\\ \textbf{ } \\\\ ';
        }
        if (!is_null($tables[0]))
        {
            $result .= '\textbf{Moieties Result} \\\\ ' . $tables[0] . ' \\\\ \textbf{ } \\\\ ';
        }
        if (!is_null($tables[2]))
        {
            $result .= '\textbf{Link Matrix} \\\\ \textit{Rows: Species that are controlled by reactions (full system)} \\\\ ' .
                '\textit{Species (reduced system)} \\\\ ' . $tables[2] . ' \\\\ \textbf{ } \\\\ ';
        }
        if (!is_null($tables[3]))
        {
            $result .= '\textbf{Reduced stoichiometry Matrix} \newline \textit{Rows: Species (reduced system)} \newline ' .
                '\textit{Columns: Reactions} \\\\ ' . $tables[3] . ' \\\\ ';
        }
        $result .= '$';
        return new LaTeX($result);
    }

    /**
     * @param string $accessToken
     * @param int $modelId
     * [group=automatic]
     * @return LaTeX LaTeX
     * @throws OperationFailedException
     */
    static function deficiencyZero(string $accessToken, int $modelId): LaTeX
    {
        $task = new Copasi();
        $task->createCopasiSource($modelId, $accessToken);
        $data = $task->stoichiometry();
        //Transform to LaTEX
        $data = str_replace("\t",' & ', $data);
        preg_match_all('/(.* & ).*(?=\n)/', $data, $allTables);
        $tables = [];
        $table = '';
        $tableDefined = false;
        foreach ($allTables[0] as $row) {
            if (substr($row, 0, 3) === ' & ') {
                $table .= '\end{array}';
                array_push($tables, addcslashes($table, '_^'));
                $tableDefined = false;
            }
            if (!$tableDefined) {
                $rowDef = str_repeat('c|', substr_count($row, ' & '));
                $table = '\begin{array}{' . $rowDef . 'c} \hline ';
                $tableDefined = true;
            }
            $table .= $row . ' \\\\ \hline ';
        }

        $table .= '\end{array}';
        array_push($tables,  addcslashes($table, '_^'));

        $scriptResult = $task->getZeroDeficiency(array_map(function (string $row){
            return explode(' & ', $row);
        }, explode(' \\\\ \hline', $tables[1])));

        $result = '$ \textrm{Applying Deficiency Zero Theorem on:} \\\\ ';
        $result .= $tables[1] . ' \\\\ \textbf{ } \\\\ \textbf{' . implode("\n", $scriptResult) . '}$';
        return new LaTeX($result);
    }

}