<?php

/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * 	@package	TestOverview repository plugin
 * 	@category	Core
 * 
 * 	@author		Jan Ruthardt <janruthardt@web.de>
 *  @author		Benedict Steuerlein <st111340@stud.uni-stuttgart.de>
 *  @author		Martin Dinkel <hmdinkel@web.de>
 * 
 *  Maps the selected exercises, the students that participated in them (and the selected groups from the table p2o) 
 *	into the ExerciseOverview Table
 * 
 *  @ilCtrl_Calls ilExerciseMapper: ilObjExerciseGUI
 * */
require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
				->getDirectory() . '/classes/mapper/class.ilDataMapper.php';

class ilExerciseMapper extends ilDataMapper {

	public $lng;

	public function setParent($lng) {
		$this->lng = $lng;
	}

	/**
	 * 	@var string
	 */
	protected $tableName = "rep_robj_xtov_overview overview";

	/**
	 * 	@see ilDataMapper::getSelectPart()
	 */
	public function getSelectPart() {
		$fields = array(
			"participants.obj_id_grpcrs obj_id",);

		return implode(', ', $fields);
	}

	/**
	 * 	@see ilDataMapper::getFromPart()
	 */
	public function getFromPart() {
		$joins = array(
			"JOIN rep_robj_xtov_p2o participants
				ON (overview.obj_id = participants.obj_id_overview)",);


		return $this->tableName . " " . implode(' ', $joins);
	}

	/**
	 * 	@see ilDataMapper::getWherePart()
	 */
	public function getWherePart(array $filters) {
		$conditions = array("1 = 1");

		if (!empty($filters['overview_id'])) {
			$conditions[] = sprintf(
					"overview.obj_id = " . $this->db->quote($filters['overview_id'], 'integer'));
		}

		return implode(' AND ', $conditions);
	}

	/**
	 * Get all exercise participants and their results
	 */
	public function getArrayofObjects($overviewID) {
		global $ilDB;
		$DbObject = array();

		$query = "SELECT 
					ut_lp_marks.usr_id AS user_id,
					firstname,
					lastname,
					obj_id,
					mark
				FROM
					rep_robj_xtov_e2o
				JOIN
					ut_lp_marks
				JOIN
					usr_data ON (rep_robj_xtov_e2o.obj_id_exercise = ut_lp_marks.obj_id
				AND ut_lp_marks.usr_id = usr_data.usr_id)
				WHERE
					obj_id_overview = %s
				ORDER BY obj_id DESC";

		$result = $ilDB->queryF($query, array('integer'), array($overviewID));
		while ($record = $ilDB->fetchObject($result)) {
			array_push($DbObject, $record);
		}

		return $DbObject;
	}

	/**
	 * Returns the name of an exercise
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
	 * @param integer $studID
	 * @return String Name of a stundent for a given studId
	 */
	public function getStudName($studID) {
		global $ilDB;
		$query = "SELECT usr_id ,firstname ,lastname FROM usr_data
                WHERE usr_id = '" . $studID . "'";
		$result = $ilDB->query($query);
		$record = $ilDB->fetchObject($result);

		return $record->firstname . " " . $record->lastname;
	}

	/**
	 * Renders the HTML code into the given template
	 */
	public function getHtml($overviewID) {

		global $lng, $ilCtrl;
		$matrix = $this->buildMatrix($overviewID);
		$tests = $this->getUniqueExerciseId($overviewID);
		$tpl = new ilTemplate("tpl.exercise_view.html", true, true, "Customizing/global/plugins/Services/Repository/RepositoryObject/TestOverview");
		$lng->loadLanguageModule("assessment");
		$tpl->setCurrentBlock("user_colum");
		$tpl->setVariable("user", $lng->txt("eval_search_users"));
		$tpl->parseCurrentBlock();
		foreach ($exercises as $exercise) {

			$ilCtrl->setParameterByClass('ilobjexercisegui', 'ref_id', $this->getRefId($exercise));
			//URL to exercise
			$txt = "<th><a href='" . $ilCtrl->getLinkTargetByClass('ilobjexercisegui', "infoScreen") . "'>" . $this->getExerciseName($exercise) . "</a></th>";
			$tpl->setCurrentBlock("exercise_colum");
			$tpl->setVariable("colum", $txt);
			$tpl->parseCurrentBlock();
		}
		$tpl->setCurrentBlock("score_colum");
		$tpl->setVariable("score", $lng->txt("tst_mark"));
		$tpl->parseCurrentBlock();
		foreach ($matrix as $row) {
			$txt = "<tr>";
			$subString = "";
			$totalScore = 0;
			foreach ($row as $field) {
				$subString .= "<th>";
				$subString .= $field;
				$subString .= "</th>";
				$totalScore += $field;
			}
			$txt .= $subString;
			$txt .= "<th>";
			$txt .= $totalScore;
			$txt .= "</th>";
			$totalScore = 0;
			$txt .= "</tr>";
			$tpl->setCurrentBlock("exercise_row");
			$tpl->setVariable("data", $txt);
			$tpl->parseCurrentBlock();
		}
		return $tpl->get();
	}

	/**
	 *
	 * @param type $overviewID
	 * @return array Is an Array of array
	 * the inner arrays have as the first element the studID, all elements after the studID are marks for the exercise
	 */
	public function buildMatrix($overviewID) {

		$DbObject = $this->getArrayofObjects($overviewID);
		$tests = $this->getUniqueExerciseId($overviewID);
		$users = $this->getUniqueUserId($overviewID);
		$outerArray = array();
		for ($i = 0; $i < count($users); $i++) {
			$innerArray = array();
			$user = $this->getStudName($users[$i]);
			array_push($innerArray, $user);
			for ($j = 0; $j < count($tests); $j++) {
				$mark = $this->getMark($users[$i], $tests[$j], $DbObject);
				if ($mark > 0) {
					//array_push($innerArray, $mark);
					array_push($innerArray, $mark);
				} else {
					array_push($innerArray, $mark);
				}
			}
			array_push($outerArray, $innerArray);
		}

		return $outerArray;
	}

	/**
	 * 
	 * @param int $userID
	 * @param int $testID
	 * @param type $Db
	 * @return int Mark of a user for a specific exercise 
	 */
	public function getMark($userID, $testID, $Db) {
		foreach ($Db as $row) {
			if ($row->user_id == $userID AND $row->obj_id == $testID) {
				if ($row->mark != null) {
					return $row->mark;
				} else {
					return 0;
				}
			}
		}
		return 0;
	}

	/**
	 *
	 * @global type $ilDB
	 * @param type $overviewID
	 * @return array of Integer Exercise ID's
	 */
	public function getUniqueExerciseId($overviewID) {
		global $ilDB;
		$UniqueIDs = array();
		$query = "SELECT 
                    DISTINCT (exr.obj_id)
                FROM
                    rep_robj_xtov_e2o e2o
                JOIN  
                    exc_returned exr ON e2o.obj_id_exercise = exr.obj_id
                JOIN 
                    exc_mem_ass_status exmem  ON exr.user_id = exmem.usr_id
                JOIN
                    usr_data ud ON exmem.usr_id = ud.usr_id
                JOIN 
                    object_reference ref ON ref.obj_id = e2o.obj_id_exercise
                WHERE
                    exr.ass_id = exmem.ass_id
                    AND e2o.obj_id_overview = %s
                    AND ref.deleted IS NULL
                    ORDER BY exr.obj_id";
		$result = $ilDB->queryF($query, array('integer'), array($overviewID));
		while ($record = $ilDB->fetchObject($result)) {
			array_push($UniqueIDs, $record->obj_id);
		}
		return $UniqueIDs;
	}

	/**
	 *
	 * @global type $ilDB
	 * @param type $overviewID
	 * @return array of Integer User ID's
	 */
	public function getUniqueUserId($overviewID) {
		global $ilDB;
		$UniqueIDs = array();
		$query = "SELECT 
					  usr_id
				FROM
					(SELECT DISTINCT
					  (exc_mem_ass_status.usr_id), exc_returned.obj_id
					FROM
					  rep_robj_xtov_e2o, exc_returned, exc_mem_ass_status
					JOIN usr_data ON (exc_mem_ass_status.usr_id = usr_data.usr_id)
					WHERE
				  exc_returned.ass_id = exc_mem_ass_status.ass_id
					AND user_id = exc_mem_ass_status.usr_id
					AND obj_id_exercise = obj_id
					AND obj_id_overview = %s
					ORDER BY exc_mem_ass_status.usr_id) users
				  JOIN
					object_reference ON (users.obj_id = object_reference.obj_id)
				  WHERE
					deleted IS NULL";
		$result = $ilDB->queryF($query,array('integer'),array($overviewID));
		while ($record = $ilDB->fetchObject($result)) {
			array_push($UniqueIDs, $record->usr_id);
		}
		return $UniqueIDs;
	}

	/**
	 * 
	 * @param type $overviewID
	 * @return array containing the sum of points for every student in all exercises
	 */
	public function getTotalScores($overviewID) {
		$data = array();
		$results = $this->buildMatrix($overviewID);
		if (!empty($results)) {
			foreach ($results as $row) {
				$totalScore = 0;
				foreach ($row as $colum) {
					$totalScore += $colum;
				}
				array_push($data, $totalScore);
			}
			return $data;
		}
	}
	/**
	 * 
	 * @global type $ilDB
	 * @param type $ObjId
	 * @return int ref_id for a given obj_id 
	 */
	public function getRefId($ObjId) {
		global $ilDB;
		$query = "SELECT
					ref_id 
				FROM 
					object_reference 
				WHERE 
					obj_id = %s";
		$result = $ilDB->queryF($query, array('integer'), array($ObjId));

		$record = $ilDB->fetchAssoc($result);

		return $record['ref_id'];
	}

	/**
	 * This method is used to edit the ranking in the database
	 * @param type $average
	 * @param type $studid
	 * @param type $toId
	 */
	public function setData2Rank($average, $studid, $eoId) {
		$query = "REPLACE INTO
                                    rep_robj_xtov_eorank(stud_id, rank, eo_id)
                            Values
                                    ($studid,$average,$eoId);";
		$this->db->query($query);
	}

	/**
	 * Delete all rankings for a TestOverview object
	 * @param type $id
	 */
	public function resetRanks($id) {
		$query = "DELETE FROM rep_robj_xtov_eorank WHERE eo_id=$id";
		$this->db->query($query);
	}

	/**
	 * 
	 * @param type $id
	 * @return  a list of students which is sorted by rank
	 */
	public function getRankedList($id) {
		$query = "SELECT 
					stud_id 
				FROM 
					rep_robj_xtov_eorank 
				WHERE 
					eo_id= %s 
				ORDER BY rank ASC "
		;

		$result = $this->db->queryF($query,array('integer'), array($id));
		return $result;
	}

	/**
	 * Gets the rank of a student
	 * @global type $ilDB
	 * @param type $id
	 * @param type $stdID
	 * @return int
	 */
	public function getRankedStudent($id, $stdID) {
		global $ilDB;
		$rank = 0;
		$query = "SELECT stud_id FROM `rep_robj_xtov_eorank` WHERE eo_id=$id ORDER BY rank DESC "
		;

		$result = $this->db->query($query);
		$index = 1;
		while ($student = $ilDB->fetchAssoc($result)) {
			if ($student[stud_id] === $stdID) {
				$rank = $index;
				break;
			}
			$index++;
		}
		return $rank;
	}
	/**
	 * 
	 * @global type $ilDB
	 * @param type $id
	 * @return int counts the database entries in rep_robj_xtov_eorank
	 */
	public function getCount($id) {

		global $ilDB;
		$count = 0;
		$query = "SELECT stud_id FROM `rep_robj_xtov_eorank` WHERE eo_id=$id "
		;

		$result = $this->db->query($query);
		while ($data = $ilDB->fetchAssoc($result)) {
			$count++;
		}

		return $count;
	}
	/**
	 * 
	 * @global type $ilDB
	 * 
	 * writes the current date into the database
	 */
	public function createDate($o_id) {
		global $ilDB;
		$timestamp = time();
		$datum = (float) date("YmdHis", $timestamp);

		$query = "REPLACE INTO
                                    rep_robj_xtov_rankdate (rankdate, otype,o_id)
                            Values
                                    ($datum,'eo',$o_id);";
		$this->db->query($query);
	}
	
	public function getDate($o_id) {
		global $ilDB;
		$query = "SELECT rankdate FROM rep_robj_xtov_rankdate WHERE o_id= %s AND otype='eo'";

		$result = $this->db->queryF($query, array('integer'), array($o_id));
		$rankDate = array();

		while ($date = $ilDB->fetchAssoc($result)) {
			$rankDate = $date['rankdate'];
		}

		return $rankDate;
	}

	/**
	 * 	Get pairs of Participants groups
	 *
	 * 	This method can be used to list groups in a
	 * 	HTML <select>. The index in the returned array
	 * 	corresponds to the groups' obj_id and the value
	 * 	is the groups' title.
	 *
	 * 	@param	integer	$overviewId
	 * 	@return array	Where index = obj_id and value = group title
	 */
	public function getGroupPairs($overviewId) {
		$pairs = array();
		$rawData = $this->getList(array(), array("overview_id" => $overviewId));
		foreach ($rawData['items'] as $item) {
			$object = ilObjectFactory::getInstanceByObjId($item->obj_id, false);
			$pairs[$item->obj_id] = $object->getTitle();
		}

		return $pairs;
	}

}

?>
