<?php

/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * 	@package	TestOverview repository plugin
 * 	@category	Core
 * 	@author		Jan Ruthardt <janruthardt@web.de>
 * The class is responsible for the Bar Charts of Exercises and Tests
 */
/* Dependencies : */
require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/TestOverview/classes/GUI/class.ilTestOverviewTableGUI.php';
require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/TestOverview/classes/mapper/class.ilOverviewMapper.php';

class BinDiagrammMapper extends ilTestOverviewTableGUI {

	public $average = array();

	public function createAverageDia($type) {
		$this->data();
		$averageObj = new AverageDiagramm($this->average);
		return $averageObj->initDia();

	}

	/*
	 * Gets the data from every student and their Testresults in a String saperated by "|"
	 *
	 */

	public function data() {


		global $lng, $ilCtrl, $ilUser;
		/* Initalise the Mapper */
		$this->setMapper(new ilOverviewMapper)
				->populate();

		$data = $this->getData();

		foreach ($data as $set) {
			array_push($this->average, $this->fillRow($set));
		}
	}

	/**
	 * Gets
	 * @param type $row
	 * @return type
	 */
	public function fillRow($row) {
		$overview = $this->getParentObject()->object;

		$results = array();
		foreach ($overview->getUniqueTests() as $obj_id => $refs) {
			$test = $overview->getTest($obj_id);
			$activeId = $test->getActiveIdOfUser($row['member_id']);
			$result = $progress = null;
			$result = $test->getTestResult($activeId);
			$result = (float) $result['pass']['percent'] * 100;
			$results[] = $result;
		}
		if (count($results)) {
			$average = (array_sum($results) / count($results));
		}
		return $average;
	}

	public function getResult() {

		return $result;
	}

}

/**
 * Creates a Bar Diagramm that shows the number of students with their points in a range of 10%
 */
class AverageDiagramm {

	private $average;
	public $diaPoints = array();
	private $buckets = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);

	function __construct($Obj) {
		$this->average = $Obj;
		$this->getAverage();
	}

	function initDia() {
		global $lng;
		require_once 'Services/Chart/classes/class.ilChartGrid.php';
		require_once 'Services/Chart/classes/class.ilChartLegend.php';
		require_once 'Services/Chart/classes/class.ilChartSpider.php';
		require_once 'Services/Chart/classes/class.ilChartLegend.php';
		$chart = ilChart::getInstanceByType(ilChart::TYPE_GRID, $a_id);
		$chart->setsize(900, 400);
		$data = $chart->getDataInstance(ilChartGrid::DATA_BARS);




		/* Creation of the Legend */
		$legend = new ilChartLegend();
		$legend->setOpacity(50);
		$chart->setLegend($legend);
		$chart->setYAxisToInteger(true);
		/* Width of the colums */
		$data->setBarOptions(0.5, "center");
		$tpl = new ilTemplate("tpl.DigramLegend.html", true, true, "Customizing/global/plugins/Services/Repository/RepositoryObject/TestOverview");

		//////LEGEND////////
		$lng->loadLanguageModule("bibitem");
		$lng->loadLanguageModule("assessment");
		$tpl->setVariable("number", $lng->txt("bibitem_number"));
		$tpl->setVariable("percent", $lng->txt("points"));
		for ($i = 1; $i <= 10; $i++) {
			$tpl->setCurrentBlock("buckets");
			$tpl->setVariable("Numbers", $i);
			$tpl->setVariable("Percents", "&le; " . $i * 10 . " %");
			$tpl->parseCurrentBlock();
		}

		$data->addPoint(1, $this->buckets[0]);
		$data->addPoint(2, $this->buckets[1]);
		$data->addPoint(3, $this->buckets[2]);
		$data->addPoint(4, $this->buckets[3]);
		$data->addPoint(5, $this->buckets[4]);
		$data->addPoint(6, $this->buckets[5]);
		$data->addPoint(7, $this->buckets[6]);
		$data->addPoint(8, $this->buckets[7]);
		$data->addPoint(9, $this->buckets[8]);
		$data->addPoint(10, $this->buckets[9]);
		$chart->addData($data);
		$tpl->setVariable("diagram", $chart->getHTML());
		return $tpl->get();
	}

	function getAverage() {
		foreach ($this->average as $student) {
			$this->fillBuckets($student);
		}
	}

	function fillBuckets($average) {
		if ($average <= 10.00) {
			$this->buckets[0] ++;
		} else if ($average > 10.00 && $average <= 20.00) {
			$this->buckets[1] ++;
		} else if ($average > 20.00 && $average <= 30.00) {
			$this->buckets[2] ++;
		} else if ($average > 30.00 && $average <= 40.00) {
			$this->buckets[3] ++;
		} else if ($average > 40.00 && $average <= 50.00) {
			$this->buckets[4] ++;
		} else if ($average > 50.00 && $average <= 60.00) {
			$this->buckets[5] ++;
		} else if ($average > 60.00 && $average <= 70.00) {
			$this->buckets[6] ++;
		} else if ($average > 70.00 && $average <= 80.00) {
			$this->buckets[7] ++;
		} else if ($average > 80.00 && $average <= 90.00) {
			$this->buckets[8] ++;
		} else if ($average > 90.00 && $average <= 100.00) {
			$this->buckets[9] ++;
		} else {
			//last index checks vor errors
			$this->buckets[10] ++;
		}
	}

}

/**
 * Creates a Pie Diagramm that shows the number of students with their points in a range of 10%
 */
class PieAverageDiagramm extends AverageDiagramm {

	private $average;
	public $diaPoints = array();
	private $buckets = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);

	function __construct($Obj) {
		$this->average = $Obj;
		$this->getAverage();
	}

	function initDia() {
		require_once 'Services/Chart/classes/class.ilChartGrid.php';
		require_once 'Services/Chart/classes/class.ilChartLegend.php';
		require_once 'Services/Chart/classes/class.ilChartSpider.php';
		require_once 'Services/Chart/classes/class.ilChartLegend.php';
		require_once 'Services/Chart/classes/class.ilChartDataPie.php';
		$chart = ilChart::getInstanceByType(ilChart::TYPE_PIE, $a_id);
		$chart->setsize(900, 400);
		$data = $chart->getDataInstance();




		/* Creation of the Legend */
		$legend = new ilChartLegend();
		$legend->setOpacity(50);
		$chart->setLegend($legend);
		$legend = $this->legend();
		/* Width of the colums */

		if ($this->buckets[0] > 0) {
			$data->addPoint(10, $this->buckets[0]);
		}
		if ($this->buckets[1] > 0) {
			$data->addPoint(20, $this->buckets[1]);
		}
		if ($this->buckets[2] > 0) {
			$data->addPoint(30, $this->buckets[2]);
		}
		if ($this->buckets[3] > 0) {
			$data->addPoint(40, $this->buckets[3]);
		}
		if ($this->buckets[4] > 0) {
			$data->addPoint(50, $this->buckets[4]);
		}
		if ($this->buckets[5] > 0) {
			$data->addPoint(60, $this->buckets[5]);
		}
		if ($this->buckets[6] > 0) {
			$data->addPoint(70, $this->buckets[6]);
		}
		if ($this->buckets[7] > 0) {
			$data->addPoint(80, $this->buckets[7]);
		}
		if ($this->buckets[8] > 0) {
			$data->addPoint(90, $this->buckets[8]);
		}
		if ($this->buckets[9] > 0) {
			$data->addPoint(100, $this->buckets[9]);
		}
		$chart->addData($data);
		return $chart->getHTML();
	}

	function fillBuckets($average) {
		if ($average > 0.0 && $average <= 10.00) {
			$this->buckets[0] ++;
		} else if ($average > 10.00 && $average <= 20.00) {
			$this->buckets[1] ++;
		} else if ($average > 20.00 && $average <= 30.00) {
			$this->buckets[2] ++;
		} else if ($average > 30.00 && $average <= 40.00) {
			$this->buckets[3] ++;
		} else if ($average > 40.00 && $average <= 50.00) {
			$this->buckets[4] ++;
		} else if ($average > 50.00 && $average <= 60.00) {
			$this->buckets[5] ++;
		} else if ($average > 60.00 && $average <= 70.00) {
			$this->buckets[6] ++;
		} else if ($average > 70.00 && $average <= 80.00) {
			$this->buckets[7] ++;
		} else if ($average > 80.00 && $average <= 90.00) {
			$this->buckets[8] ++;
		} else if ($average > 90.00 && $average <= 100.00) {
			$this->buckets[9] ++;
		} else {
			//last index checks vor errors
			$this->buckets[10] ++;
		}
	}

	function getAverage() {
		foreach ($this->average as $student) {
			$this->fillBuckets($student);
		}
	}

}

/**
 * Creates a bar diagramm for exercises.
 * The Bucketsize is calculated by a given value from the user
 */
class exerciseCharts {

	private $buckets = array();
	private $bucketSize = array("", "", "", "", "", "", "", "", "", "");
	private $data = null;
	private $diagramSize = 0;
	private $sizeOfBucket = 0;
	private $overviewId;
	private $error = false;

	function __construct($diagramSize, $overviewId, $sizeOfBucket) {
		$this->diagramSize = $diagramSize;
		$this->overviewId = $overviewId;
		$this->sizeOfBucket = $sizeOfBucket;
		$this->bucketsToSmall = false;

		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/mapper/class.ilExerciseMapper.php';
		$excMapper = new ilExerciseMapper();
		$this->data = $excMapper->getTotalScores($overviewId);
		if (empty($this->data)) {
			$this->data = array();
		}
		sort($this->data);
		if ($this->data[0] == 0) {
			$this->data = array();
			$this->bucketsToSmall = true;
		}
		if (!(($this->getMaxValue() / $sizeOfBucket) <= 100)) {
			$this->sizeOfBucket = $this->getMaxValue() / 100;
			$this->bucketsToSmall = true;
		}
		$this->fillArray();
		if ($this->diagramSize > 0 && !empty($this->data)) {
			$this->fillBuckets();
		}
	}

	function checkData() {
		return ($this->getMaxValue / $this->sizeOfBucket);
	}

	function getMaxValue() {
		return end($this->data);
	}

	function fillArray() {
		for ($i = 1; $i <= $this->getMaxValue() / $this->sizeOfBucket; $i++) {
			array_push($this->buckets, 0);
		}
	}

	function fillBuckets() {
		foreach ($this->data as $value) {
			$this->buckets[(ceil($value / $this->sizeOfBucket)) - 1] ++;
		}
	}

	function getHTML() {
		global $lng;
		require_once 'Services/Chart/classes/class.ilChartGrid.php';
		require_once 'Services/Chart/classes/class.ilChartLegend.php';
		require_once 'Services/Chart/classes/class.ilChartSpider.php';
		require_once 'Services/Chart/classes/class.ilChartLegend.php';
		require_once 'Services/Chart/classes/class.ilChartDataPie.php';

		$chart = ilChart::getInstanceByType(ilChart::TYPE_GRID, 1);
		$chart->setsize(900, 400);
		$data = $chart->getDataInstance(ilChartGrid::DATA_BARS);
		$lng->loadLanguageModule("bibitem");
		$lng->loadLanguageModule("assessment");


		$data->setBarOptions(0.5, "center");
		/* Creation of the Legend */
		$tpl = new ilTemplate("tpl.DigramLegend.html", true, true, "Customizing/global/plugins/Services/Repository/RepositoryObject/TestOverview");
		$tpl->setVariable("number", $lng->txt("bibitem_number"));
		$tpl->setVariable("percent", $lng->txt("points"));
		$i = 1;
		foreach ($this->buckets as $bucket) {
			$tpl->setCurrentBlock("buckets");
			$tpl->setVariable("Numbers", $i);
			$tpl->setVariable("Percents", "&le; " . $this->sizeOfBucket * $i);
			$tpl->parseCurrentBlock();
			$i += 1;
		}
		if ($i <= 20) {
			$height = $i * 20;
			$height .= "px";
			$tpl->setVariable("Height", $height);
		} else {
			$tpl->setVariable("Height", "400px");
		}
		$legend = new ilChartLegend();
		$legend->setOpacity(50);
		$chart->setLegend($legend);
		$chart->setYAxisToInteger(true);
		/* Null Point set to let the diageram start of at (0,0) */
		$data->addPoint(0, 0);
		$i = 1;
		/* Adding the Diagram Points */
		foreach ($this->buckets as $bucketValue) {
			if ($bucketValue > 0) {
				$data->addPoint($i, $bucketValue);
			}
			$i = $i + 1;
		}
		$chart->addData($data);
		$tpl->setVariable("diagram", $chart->getHTML());
		if ($this->bucketsToSmall) {
			$tpl->setVariable("overSize", $lng->txt("rep_robj_xtov_bucketFail"));
		}
		return $tpl->get();
	}

}

?>
