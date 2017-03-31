<?php

/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * 	@package	TestOverview repository plugin
 * 	@category	Core
 * 	@author		Greg Saive <gsaive@databay.de>
 */
/* Internal : */
require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
				->getDirectory() . '/classes/mapper/class.ilDataMapper.php';

class ilOverviewMapper extends ilDataMapper {

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

	/**
	 * 
	 * @param array $obj_ids
	 * @return array
	 */
	public function getUniqueTestParticipants(array $obj_ids) {
		$in_tst_std = $this->db->in('tst_std.obj_fi', $obj_ids, false, 'integer');
		$in_tst_fixed = $this->db->in('tst_fixed.obj_fi', $obj_ids, false, 'integer');
		$query = "
			(SELECT act.user_fi
			FROM tst_tests tst_std
			INNER JOIN object_data ON object_data.obj_id = tst_std.obj_fi AND object_data.type = 'tst'
			INNER JOIN tst_active act
				ON act.test_fi = tst_std.test_id
			INNER JOIN usr_data ud_std
			ON ud_std.usr_id = act.user_fi
			WHERE $in_tst_std)
			UNION
			(SELECT inv.user_fi
			FROM tst_tests tst_fixed
			INNER JOIN object_data ON object_data.obj_id = tst_fixed.obj_fi AND object_data.type = 'tst'
			INNER JOIN tst_invited_user inv
				ON inv.test_fi = tst_fixed.test_id
			INNER JOIN usr_data ud_fixed
				ON ud_fixed.usr_id = inv.user_fi
			WHERE $in_tst_fixed)
			";
		$res = $this->db->query($query);
		$usr_ids = array();
		while ($row = $this->db->fetchAssoc($res)) {
			$usr_ids[] = (int) $row['user_fi'];
		}
		$data = array('items' => array_unique($usr_ids));
		$data['cnt'] = 0;

		return $data;
	}

	function getVirtuellTableName() {
		return "rep_robj_xtov_overview_virtual";
	}

	/**
	 * This method is used to edit the ranking in the database 
	 * @param type $average
	 * @param type $studid
	 * @param type $toId
	 */
	public function setData2Rank($average, $studid, $toId) {
		$query = "REPLACE INTO 
                                    rep_robj_xtov_torank (stud_id, rank, to_id)
                            Values
                                    ($studid,$average,$toId);";
		$this->db->query($query);
	}

	/**
	 * Delete all rankings for a TestOverview Object
	 * @param type $id
	 */
	public function resetRanks($id) {
		$query = "DELETE FROM `rep_robj_xtov_torank` WHERE to_id=$id";
		$this->db->query($query);
	}

	/**
	 * Gets a list of students which is sorted by ranking
	 * @param type $id
	 * @return type
	 */
	public function getRankedList($id) {
		$query = "SELECT stud_id FROM rep_robj_xtov_torank WHERE to_id=$id ORDER BY rank ASC "
		;

		$result = $this->db->query($query);
		return $result;
	}

	/**
	 * Gets the ranking of a student
	 * @global type $ilDB
	 * @param type $id
	 * @param type $stdID
	 * @return int
	 */
	public function getRankedStudent($id, $stdID) {
		global $ilDB;
		$rank = 0;
		$query = "SELECT stud_id FROM rep_robj_xtov_torank WHERE to_id=$id ORDER BY rank DESC "
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
         * writes a Date to rep_robj_xtov_rankdate
         * 
         * @global type $ilDB
         * @param type $o_id
         */
	public function createDate($o_id) {
		global $ilDB;
		$timestamp = time();
		$datum = (float) date("YmdHis", $timestamp);

		$query = "REPLACE INTO 
                                    rep_robj_xtov_rankdate (rankdate, otype,o_id)
                            Values
                                    ($datum,'to',$o_id);";
		$this->db->query($query);
	}

        /**
         * 
         * @global type $ilDB
         * @param type $o_id
         * @return type
         */
	public function getDate($o_id) {
		global $ilDB;
		$query = "SELECT rankdate FROM rep_robj_xtov_rankdate WHERE o_id=$o_id AND otype='to'"
		;

		$result = $this->db->query($query);
		$rankDate = array();

		while ($date = $ilDB->fetchAssoc($result)) {
			$rankDate = $date['rankdate'];
		}

		return $rankDate;
	}

        /**
         * Get the count of entries in rep_robj_xtov_torank
         * 
         * @global type $ilDB
         * @param type $id
         * @return int
         */
	public function getCount($id) {

		global $ilDB;
		$count = 0;
		$query = "SELECT stud_id FROM rep_robj_xtov_torank WHERE to_id=$id "
		;

		$result = $this->db->query($query);
		while ($data = $ilDB->fetchAssoc($result)) {
			$count++;
		}

		return $count;
	}

}
