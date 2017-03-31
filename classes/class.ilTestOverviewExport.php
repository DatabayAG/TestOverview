<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * @author Benedict Steuerlein <st111340@stud.uni-stuttgart.de>
 * 
 * Class that generates the export file 
 */
class ilTestOverviewExport extends ilObjTestOverviewGUI {

	/** @protected string extended/reduced (TestQuestions) */
	protected $type;

	/** @protected integer ID of current TestOverview  */
	protected $overviewID;

	/** @var integer, ID of parent object  */
	protected $parentID;

	/** @var string of resultsfile  */
	public $filename;

	/** @var array of integer, testIDs  */
	protected $testIDs;

	/** @var array of integer, exerciseIDs  */
	protected $exerciseIDs;

	/** @var array of integer, userIds  */
	private $users;

	/** @var array, association of users and their test results */
	private $assoc;

	public function __construct($parent, $id, $type, $a_main_object = null) {

		parent::__construct($parent, $a_main_object);
		$this->type = $type;
		$this->overviewID = $id;
		$this->ref_id = $parent->object->getRefId();
		$this->parentID = $parent->object->getParentId($this->ref_id);

		$this->testIDs = $this->getTestIDs();
		$this->exerciseIDs = $this->getUniqueExerciseIDs();
		$this->users = $this->getUniqueTestExeciseUserID();
		$this->assoc = $this->getAssociation();
		$date = date("Y-m-d");

		$this->filename = $parent->object->getTitle() . "_Export_" . $date . "_" . $type . ".csv";
	}

	function buildExportFile() {

		switch ($this->type) {
			case "reduced":
				if (empty($this->users)) {
					ilUtil::sendFailure("No users to export.");
					break;
				}
				return $this->buildReducedExportFile();
				break;
			case "extended":
				if (empty($this->users)) {
					ilUtil::sendFailure("No users to export.");
					break;
				}
				return $this->buildExtendedExportFile();
				break;
		}
	}

	/**
	 * 
	 * @private function, builds reduced Export file (Only TestNames, no Questions)
	 *  (lastname|firstname|matriculation-number|email|gender|Testname1|Testname2|...)
	 *
	 */
	private function buildReducedExportFile() {
		global $lng, $ilCtrl;

		$rows = array();
		$datarow = array();

		//Build the headrow
		array_push($datarow, $this->txt("name"));
		array_push($datarow, $this->txt("mat"));
		array_push($datarow, $this->txt("group"));
		array_push($datarow, $this->txt("mail"));
		array_push($datarow, $this->txt("gend"));

		//push all TestNames into the headrow
		$tCounter = 1;
		foreach ($this->testIDs as $key => $value) {
			$testName = "Test $tCounter";
			array_push($datarow, $testName);
			$tCounter++;
		}
		//push a name of every exercise into headrow
		$eCounter = 1;
		foreach ($this->exerciseIDs as $num => $exvalue) {
			$exName = "Exercise $eCounter";
			array_push($datarow, $exName);
			$eCounter++;
		}

		$headrow = $datarow;
		array_push($rows, $headrow);
		$inforow = array();
		array_push($inforow, "");
		array_push($inforow, "");
		array_push($inforow, "");
		array_push($inforow, "");
		array_push($inforow, "");
		//push all TestNames into the headrow
		foreach ($this->testIDs as $key => $value) {
			$testID = $value['test_id'];
			$testName = $this->getTestTitle($testID);
			array_push($inforow, $testName);
		}
		//push a name of every exercise into headrow
		foreach ($this->exerciseIDs as $num => $exvalue) {
			$exerciseID = $exvalue['obj_id'];

			$exName = $this->getExerciseName($exerciseID);
			array_push($inforow, $exName);
		}
		array_push($rows, $inforow);
		//push user specific info into every row (userInfo)
		foreach ($this->users as $num => $preValue) {
			$userID = $preValue['user_fi'];
			$userGroup = $this->participatedGroups($userID);
			$datarow2 = array();
			$userInfo = $this->getInfo($userID);
			array_push($datarow2, $userInfo['lastname'] . ', ' . $userInfo['firstname']);
			array_push($datarow2, $userInfo['matriculation']);
			array_push($datarow2, $userGroup);
			array_push($datarow2, $userInfo['email']);
			array_push($datarow2, $userInfo['gender']);
			//push test results into user row
			foreach ($this->testIDs as $num2 => $preValue2) {
				$testID = $preValue2['test_id'];
				$activeID = $this->getActiveID($userID, $testID);
				$testResult = $this->getTestResultsForActiveId($activeID);
				array_push($datarow2, $testResult);
			}
			//push exerciseResult into user row
			foreach ($this->exerciseIDs as $num3 => $preValue3) {
				$exerciseID = $preValue3['obj_id'];
				$exerciseResult = $this->getExerciseMark($userID, $exerciseID);
				array_push($datarow2, $exerciseResult);
			}
			array_push($rows, $datarow2);
		}

		$csv = "";
		$separator = ";";

		foreach ($rows as $evalrow) {
			$csvrow = $this->processCSVRow($evalrow, TRUE, $separator);
			$csv .= join($csvrow, $separator) . "\n";
		}
		ilUtil::deliverData($csv, ilUtil::getASCIIFilename($this->filename));
	}

	/**
	 * 
	 * @private function, builds extended export file (TestNames and Questions)
	 *  (lastname|firstname|matriculation-number|email|gender|Testname1|Question1|Question2|..|Testname2|Question1|Question2|..)

	 */
	function buildExtendedExportFile() {
		global $lng;
		$rows = array();
		$datarow = array();
		//build headrow        
		array_push($datarow, $this->txt("name"));
		array_push($datarow, $this->txt("mat"));
		array_push($datarow, $this->txt("group"));
		array_push($datarow, $this->txt("mail"));
		array_push($datarow, $this->txt("gend"));
		//push all TestNames into the headrow
		$tCounter = 1;
		foreach ($this->testIDs as $key => $value) {
			$testID = $value['test_id'];
			$testName = "Test $tCounter";
			array_push($datarow, $testName);

			$questionIDs = $this->getQuestionID($testID);
			$qCounter = 1;
			//push a name for every subtask a test has into the headrow
			foreach ($questionIDs as $question) {
				$questionName = "Tst$tCounter- " . $this->txt("qteil") . " $qCounter";
				array_push($datarow, $questionName);
				$qCounter++;
			}
			$tCounter++;
		}
		//push a name of every exercise into headrow
		$eCounter = 1;
		foreach ($this->exerciseIDs as $num => $exvalue) {
			$exerciseID = $exvalue['obj_id'];
			$exName = "Exercise $eCounter";
			array_push($datarow, $exName);


			$assignmentIDs = $this->getAssignments($exerciseID);
			$aCounter = 1;
			//push a name for every assignments a exercise has into the headrow
			foreach ($assignmentIDs as $assignment) {
				$assignmentName = "Ex$eCounter- " . $this->txt("assign") . " $aCounter";
				array_push($datarow, $assignmentName);
				$aCounter++;
			}
			$eCounter++;
		}

		array_push($rows, $datarow);

		$inforow = array();
		array_push($inforow, "");
		array_push($inforow, "");
		array_push($inforow, "");
		array_push($inforow, "");
		array_push($inforow, "");

		//push the test name into a new row
		foreach ($this->testIDs as $key => $value) {
			$testID = $value['test_id'];
			$testName = $this->getTestTitle($testID);
			array_push($inforow, $testName);
			$questions = $this->getQuestionID($testID);

			//push a name for every subtask a test has into the headrow
			foreach ($questions as $key => $questionInfo) {
				$questionID = $questionInfo['question_fi'];
				$questionName = $this->getQuestionTitle($questionID);
				array_push($inforow, $questionName);
			}
		}
		//push a name of every exercise into headrow
		foreach ($this->exerciseIDs as $num => $exvalue) {
			$exerciseID = $exvalue['obj_id'];
			$exName = $this->getExerciseName($exerciseID);
			array_push($inforow, $exName);
			$assignments = $this->getAssignments($exerciseID);

			//push a name for every assignments a exercise has into the headrow
			foreach ($assignments as $key => $assignmentInfo) {
				$assignmentIDs = $assignmentInfo['id'];
				$assignmentName = $assignmentInfo['title'];
				array_push($inforow, $assignmentName);
				$aCounter++;
			}
		}

		array_push($rows, $inforow);

		//push user specific info into every row (userInfo)
		foreach ($this->users as $num => $preValue) {
			$userID = $preValue['user_fi'];
			$userGroup = $this->participatedGroups($userID);
			$datarow = $headrow;
			$datarow2 = array();
			$userInfo = $this->getInfo($userID);
			array_push($datarow2, $userInfo['lastname'] . ', ' . $userInfo['firstname']);
			array_push($datarow2, $userInfo['matriculation']);
			array_push($datarow2, $userGroup);
			array_push($datarow2, $userInfo['email']);
			array_push($datarow2, $userInfo['gender']);
			//push test results into user row
			foreach ($this->testIDs as $num2 => $preValue2) {
				$testID = $preValue2['test_id'];
				$activeID = $this->getActiveID($userID, $testID);
				$testResult = $this->getTestResultsForActiveId($activeID);
				array_push($datarow2, $testResult);
				$questionIDs2 = $this->getQuestionID($testID);
				//push question results into user row
				foreach ($questionIDs2 as $key => $value) {
					$questionID2 = $value['question_fi'];
					$questionPoints = $this->getMark($activeID, $questionID2);
					array_push($datarow2, $questionPoints);
				}
			}

			//push exerciseResult into user row
			foreach ($this->exerciseIDs as $num3 => $preValue3) {
				$exerciseID = $preValue3['obj_id'];
				$exerciseResult = $this->getExerciseMark($userID, $exerciseID);
				array_push($datarow2, $exerciseResult);
				$assignmentIDs = $this->getAssignments($exerciseID);
				//push assignmentResults into user row
				foreach ($assignmentIDs as $key => $preValue4) {
					$assignmentIDs = $preValue4['id'];
					$assignmentPoints = $this->getAssignmentMark($userID, $assignmentIDs);
					array_push($datarow2, $assignmentPoints);
				}
			}
			array_push($rows, $datarow2);
			$datarow2 = array();
		}



		$csv = "";
		$separator = ";";
		foreach ($rows as $evalrow) {
			$csvrow = $this->processCSVRow($evalrow, TRUE, $separator);
			$csv .= join($csvrow, $separator) . "\n";
		}
		ilUtil::deliverData($csv, ilUtil::getASCIIFilename($this->filename));
	}

	function processCSVRow($row, $quoteAll = FALSE, $separator = ";") {
		$resultarray = array();
		foreach ($row as $rowindex => $entry) {
			$surround = FALSE;
			if ($quoteAll) {
				$surround = TRUE;
			}
			if (strpos($entry, "\"") !== FALSE) {
				$entry = str_replace("\"", "\"\"", $entry);
				$surround = TRUE;
			}
			if (strpos($entry, $separator) !== FALSE) {
				$surround = TRUE;
			}
			// replace all CR LF with LF (for Excel for Windows compatibility
			$entry = str_replace(chr(13) . chr(10), chr(10), $entry);

			if ($surround) {
				$entry = "\"" . $entry . "\"";
			}

			$resultarray[$rowindex] = $entry;
		}
		return $resultarray;
	}

	/**
	 * 
	 * @global type $ilDB
	 * @return array containing all testIDs associated with the Overview object
	 */
	protected function getTestIDs() {
		global $ilDB;
		$testIDs = array();
		$query = "SELECT DISTINCT
					tst_tests.test_id
				FROM
					rep_robj_xtov_t2o
				JOIN object_reference
				JOIN tst_tests
				ON (rep_robj_xtov_t2o.ref_id_test = object_reference.ref_id
				AND obj_id = obj_fi)
				WHERE object_reference.deleted IS NULL AND obj_id_overview = %s";

		$result = $ilDB->queryF($query, array('integer'), array($this->overviewID));
		while ($record = $ilDB->fetchAssoc($result)) {
			array_push($testIDs, $record);
		}
		return $testIDs;
	}

	/**
	 * 
	 * @global type $ilDB
	 * @param integer $userID
	 * @return string
	 */
	protected function participatedGroups($userID) {
		global $ilDB;
		$info = array();
		$query = "SELECT 
                    obd.title
                  FROM
                    rbac_ua ua
                  JOIN
                    rbac_fa fa ON ua.rol_id = fa.rol_id
                  JOIN 
                    tree t1 ON t1.child = fa.parent
                  JOIN
                    object_reference obr ON t1.child = obr.ref_id
                  JOIN
                    object_data obd ON obr.obj_id = obd.obj_id
                  WHERE
                 obr.deleted IS NULL AND ua.usr_id = %s AND fa.assign = 'y' AND obd.type in ('grp') AND t1.parent = %s";
		$result = $ilDB->queryF($query, array('integer', 'integer'), array($userID, $this->parentID));
		while ($record = $ilDB->fetchAssoc($result)) {
			array_push($info, $record);
		}

		foreach ($info as $key => $group) {
			$groups .= $group['title'] . ", ";
		}
		$groups = substr($groups, 0, strlen($groups) - 2);

		return $groups;
	}

	/**
	 * 
	 * @global type $ilDB
	 * @param type $questionID
	 * @return testID for a given questionID
	 */
	protected function getTest($questionID) {
		global $ilDB;
		$query = "  SELECT 
					DISTINCT test_fi
					FROM
					tst_test_question
					WHERE question_fi = %s";

		$result = $ilDB->queryF($query, array('integer'), array($questionID));
		$record = $ilDB->fetchAssoc($result);
		return $record['test_fi'];
	}

	/**
	 * 
	 * @global type $ilDB
	 * @param type $overviewID
	 * @return array filled with all existing question-ids for a given XTOV-instance
	 */
	private function getQuestionID($testID) {
		global $ilDB;
		$qIDs = array();

		$query = "SELECT 
					question_fi
				FROM
					rep_robj_xtov_t2o
				JOIN object_reference
				JOIN tst_tests
				JOIN tst_test_question ON (rep_robj_xtov_t2o.ref_id_test = object_reference.ref_id
				AND obj_id = obj_fi
				AND test_id = test_fi)
				WHERE obj_id_overview = %s AND test_id = %s";
		$result = $ilDB->queryF($query, array('integer', 'integer'), array($this->overviewID, $testID));
		while ($record = $ilDB->fetchAssoc($result)) {
			array_push($qIDs, $record);
		}
		return $qIDs;
	}

	/**
	 * 
	 * @global type $ilDB
	 * @param type $userID
	 * @param type $testID
	 * @return string 
	 */
	private function getActiveID($userID, $testID) {
		global $ilDB;
		$query = "SELECT tst_active.active_id FROM tst_active WHERE user_fi = %s AND test_fi = %s";
		$result = $ilDB->queryF($query, array('integer', 'integer'), array($userID, $testID));

		if ($result->numRows()) {
			$row = $ilDB->fetchAssoc($result);
			return $row["active_id"];
		} else {
			return "";
		}
	}

	/**
	 * 
	 * @global type $ilDB
	 * @param type $userID
	 * @return type 
	 */
	private function getInfo($userID) {
		global $ilDB;
		$query = "SELECT 
					lastname, 
					firstname, 
					matriculation, 
					email, 
					gender
				FROM
					usr_data
				WHERE usr_id = %s";
		$result = $ilDB->queryF($query, array('integer'), array($userID));
		$record = $ilDB->fetchAssoc($result);
		return $record;
	}

	/**
	 * 
	 * @global type $ilDB
	 * @param type $overviewID
	 * @return integer Reached points in a test for a given activeID
	 */
	public function getTestResultsForActiveId($active_id) {
		global $ilDB;

		$query = "
		SELECT		reached_points
		FROM		tst_result_cache
		WHERE		active_fi = %s
	";

		$result = $ilDB->queryF($query, array('integer'), array($active_id)
		);



		$row = $ilDB->fetchAssoc($result);

		return $row['reached_points'];
	}

	/**
	 * 
	 * @global type $ilDB
	 * @return array with all answered questions, the reached points for every activeID
	 *          activeID => (QuestionID->points)
	 */
	protected function getAssociation() {
		global $ilDB;
		$points = array();

		$query = "SELECT 
					active_fi, questionId.question_fi, points
				FROM
					tst_test_result
				JOIN
					(SELECT 
						question_fi
					FROM
						rep_robj_xtov_t2o
					JOIN object_reference
					JOIN tst_tests
					JOIN tst_test_question ON (rep_robj_xtov_t2o.ref_id_test = object_reference.ref_id
					AND obj_id = obj_fi
					AND test_id = test_fi)) AS questionId ON (tst_test_result.question_fi = questionId.question_fi)
					ORDER BY active_fi ASC, questionId.question_fi ASC, tstamp DESC";
		$result = $ilDB->query($query);

		while ($record = $ilDB->fetchObject($result)) {
			array_push($points, $record);
		}
		return $points;
	}

	/**
	 * 
	 * @global type $ilDB
	 * @param type $testID
	 * @return String TestTitle for given testID
	 */
	protected function getTestTitle($testID) {
		global $ilDB;

		$query = "SELECT 
					od.title
				FROM
					rep_robj_xtov_t2o
				INNER JOIN
					object_reference ref ON (ref.ref_id = rep_robj_xtov_t2o.ref_id_test
				AND deleted IS NULL)
				INNER JOIN
					object_data od ON (od.obj_id = ref.obj_id)
				AND od.type = 'tst'
				INNER JOIN
					tst_tests test ON (test.obj_fi = od.obj_id)
				WHERE test_id = %s";
		$result = $ilDB->queryF($query, array('integer'), array($testID));
		$record = $ilDB->fetchAssoc($result);

		return $record['title'];
	}

	/**
	 * 
	 * @global type $ilDB
	 * @param type $questionID
	 * @return string title of question
	 */
	protected function getQuestionTitle($questionID) {
		global $ilDB;


		$query = "SELECT title FROM qpl_questions WHERE question_id= %s";

		$result = $ilDB->queryF($query, array('integer'), array($questionID));
		$record = $ilDB->fetchObject($result);
		return $record->title;
	}

	/**
	 * 
	 * @global type $ilDB
	 * @return array of all users that participated on tests added
	 */
	protected function getUniqueTestExeciseUserID() {
		global $ilDB;
		$uniqueIDs = array();

		$query = "SELECT usr_id AS user_fi FROM (
					SELECT DISTINCT(user_fi) 
					FROM tst_active 
					JOIN 
					(SELECT 
						*
					FROM
						rep_robj_xtov_t2o
					JOIN
						object_reference
					JOIN
						tst_tests ON (rep_robj_xtov_t2o.ref_id_test = object_reference.ref_id
					AND obj_id = obj_fi)) as TestUsers ON (TestUsers.test_id=tst_active.test_fi)
					WHERE 
						obj_id_overview = %s
					UNION
					SELECT DISTINCT
						(exc_mem_ass_status.usr_id) as user_fi
					FROM
						rep_robj_xtov_e2o,
						exc_returned,
						exc_mem_ass_status
					JOIN
						usr_data ON (exc_mem_ass_status.usr_id = usr_data.usr_id)
					WHERE
						exc_returned.ass_id = exc_mem_ass_status.ass_id
					AND user_id = exc_mem_ass_status.usr_id
					AND obj_id_exercise = obj_id
					AND obj_id_overview = %s) t1
					INNER JOIN usr_data ON t1.user_fi= usr_data.usr_id";
		
		$result = $ilDB->queryF($query, array('integer', 'integer'), array($this->overviewID, $this->overviewID));

		while ($record = $ilDB->fetchAssoc($result)) {
			array_push($uniqueIDs, $record);
		}

		return $uniqueIDs;
	}

	/**
	 * 
	 * @param type $activeID
	 * @param type $questionID
	 * @return int Points for a given activeID (TestID-StudentID) and a questionID
	 */
	public function getMark($activeID, $questionID) {
		foreach ($this->assoc as $row => $value) {
			if ($value->active_fi == $activeID && $value->question_fi == $questionID) {

				if ($value->points != null) {
					return $value->points;
				}
			}
		}
		return 0;
	}

	/**
	 * 
	 * @global type $ilDB
	 * @return array of unique ExerciseIDs added to TO-Object 
	 */
	protected function getUniqueExerciseIDs() {

		global $ilDB;
		$uniqueIDs = array();

		$query = "SELECT DISTINCT
					obj_id_exercise AS obj_id
				FROM
					rep_robj_xtov_e2o e2o
				JOIN
					object_reference ref ON e2o.obj_id_exercise = ref.obj_id
				WHERE
					e2o.obj_id_overview = %s
				AND ref.deleted IS NULL";
		$result = $ilDB->queryF($query, array('integer'), array($this->overviewID));

		while ($record = $ilDB->fetchAssoc($result)) {
			array_push($uniqueIDs, $record);
		}

		return $uniqueIDs;
	}

	/**
	 * 
	 * @global type $ilDB
	 * @param type $exerciseID
	 * @return type title for a given object id
	 */
	public function getExerciseName($exerciseID) {
		global $ilDB;
		$query = "SELECT title FROM object_data WHERE obj_id = %s";
		$result = $ilDB->queryF($query, array('integer'), array($exerciseID));
		$record = $ilDB->fetchAssoc($result);
		return $record['title'];
	}

	/**
	 * 
	 * @global type $ilDB
	 * @return array with all userId that participated on Exercises in to object
	 */
	protected function getUniqueExerciseUserID() {

		global $ilDB;
		$uniqueIDs = array();

		$query = "SELECT DISTINCT
					(exc_mem_ass_status.usr_id)
				FROM
					rep_robj_xtov_e2o,
					exc_returned,
					exc_mem_ass_status
				JOIN
					usr_data ON (exc_mem_ass_status.usr_id = usr_data.usr_id)
				WHERE
					exc_returned.ass_id = exc_mem_ass_status.ass_id
				AND user_id = exc_mem_ass_status.usr_id
				AND obj_id_exercise = obj_id
				AND obj_id_overview = %s ";

		$result = $ilDB->queryF($query, array('integer'), array($this->overviewID));

		while ($record = $ilDB->fetchAssoc($result)) {
			array_push($uniqueIDs, $record);
		}

		return $uniqueIDs;
	}

	/**
	 * 
	 * @global type $ilDB
	 * @param type $exerciseID
	 * @return array with all assignments for a given excerciseID 
	 */
	public function getAssignments($exerciseID) {
		global $ilDB;
		$assignmentID = array();

		$query = "SELECT 
					id, title
				FROM
					exc_assignment
				WHERE
					exc_id = %s ";

		$result = $ilDB->queryF($query, array('integer'), array($exerciseID));

		while ($record = $ilDB->fetchAssoc($result)) {
			array_push($assignmentID, $record);
		}

		return $assignmentID;
	}

	/**
	 * 
	 * @global type $ilDB
	 * @param type $userID
	 * @param type $exerciseID
	 * @return string exercise mark for a given userID and exerciseID 
	 */
	protected function getExerciseMark($userID, $exerciseID) {
		global $ilDB;

		$query = "SELECT mark FROM ut_lp_marks WHERE obj_id = %s AND usr_id = %s ";

		$result = $ilDB->queryF($query, array('integer', 'integer'), array($exerciseID, $userID));

		$record = $ilDB->fetchAssoc($result);


		return $record['mark'];
	}

	/**
	 * 
	 * @global type $ilDB
	 * @param type $userID
	 * @param type $assignmentID
	 * @return string assignment mark for a given userID and assignmentID  
	 */
	protected function getAssignmentMark($userID, $assignmentID) {
		global $ilDB;


		$query = "SELECT mark FROM exc_mem_ass_status WHERE ass_id = %s AND usr_id = %s";

		$result = $ilDB->queryF($query, array('integer', 'integer'), array($assignmentID, $userID));

		$record = $ilDB->fetchAssoc($result);

		return $record['mark'];
	}

}
