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

class ilMembershipMapper extends ilDataMapper {

	/**
	 * 	@var string
	 */
	protected $tableName = "rbac_ua ua";

	/**
	 * 	@see ilDataMapper::getSelectPart()
	 */
	public function getSelectPart() {
		$fields = array(
			"DISTINCT obd.obj_id",
			"obd.type",
			"obd.title",
			"obr.ref_id",);

		return implode(', ', $fields);
	}

	/**
	 * 	@see ilDataMapper::getFromPart()
	 */
	public function getFromPart() {
		if (version_compare(ILIAS_VERSION_NUMERIC, '4.5.0') >= 0) {
			$joins = array(
				"JOIN rbac_fa fa ON ua.rol_id = fa.rol_id",
				"JOIN tree t1 ON t1.child = fa.parent",
				"JOIN object_reference obr ON t1.child = obr.ref_id",
				"JOIN object_data obd ON obr.obj_id = obd.obj_id"
			);
		} else {
			$joins = array(
				"JOIN rbac_fa fa ON ua.rol_id = fa.rol_id",
				"JOIN tree t1 ON t1.child = fa.parent",
				"JOIN object_reference obr ON t1.parent = obr.ref_id",
				"JOIN object_data obd ON obr.obj_id = obd.obj_id"
			);
		}

		return $this->tableName . " " . implode(' ', $joins);
	}

	/**
	 * 	@see ilDataMapper::getWherePart()
	 */
	public function getWherePart(array $filters) {
		global $ilUser;

		$conditions = array(
			"obr.deleted IS NULL",
			"fa.assign = 'y'",
			$this->db->in('obd.type', array('grp'), false, 'text'),
			"ua.usr_id = " . $this->db->quote($ilUser->getId(), 'integer'),);

		if (!empty($filters['flt_grp_name'])) {
			$conditions[] = sprintf(
					"obd.title LIKE %s", $this->db->quote("%" . $filters['flt_grp_name'] . "%", 'text'));
		}

		return implode(' AND ', $conditions);
	}

}
