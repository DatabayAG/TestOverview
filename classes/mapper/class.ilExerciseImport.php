<?php

/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * 	@package	TestOverview repository plugin
 * 	@category	Core
 * 	@author		Jan Ruthardt <janruthardt@web.de>
 *
 * This class creates and deletes the given ExerciseIds and OverviewIds in the DB to create a relation between them
 */
class ExerciseImport {

	/**
	 *
	 * @global type $ilDB
	 * @param type $overviewId
	 * @param type $exerciseId
	 */
	public function createEntry($overviewId, $exerciseId) {
		global $ilDB;

		$obj_id = $this->getObjectRef($exerciseId);
		$query = "INSERT IGNORE INTO rep_robj_xtov_e2o (obj_id_overview,obj_id_exercise)
                  VALUES (" . $overviewId . "," . $obj_id . ")";

		$ilDB->manipulate($query);
	}

	/**
	 *
	 * @global type $ilDB
	 * @param type $refId
	 * @return Int
	 */
	public function getObjectRef($refId) {
		global $ilDB;

		$query = "SELECT obj_id FROM object_reference
                  WHERE ref_id = '" . $refId . "'";

		$result = $ilDB->query($query);
		$obj_id = $ilDB->fetchObject($result);
		return $obj_id->obj_id;
	}

	/**
	 *
	 * @param type $overviewId
	 * @param type $exerciseId
	 */
	public function deleteEntry($exerciseId) {
		global $ilDB;

		$query = "DELETE FROM rep_robj_xtov_e2o
                  WHERE obj_id_exercise = '" . $exerciseId . "'";

		$affected_rows = $ilDB->manipulate($query);
	}

}

?>
