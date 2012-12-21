<?php
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 *	@package	TestOverview repository plugin
 *	@category	Core
 *	@author		Greg Saive <gsaive@databay.de>
 */

/* Internal : */
require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
				->getDirectory() . '/classes/mapper/class.ilDataMapper.php';

class ilTestMapper
	extends ilDataMapper
{
	/**
	 *	@var string
	 */
	protected $tableName = "tst_tests test";

	/**
	 *	@see ilDataMapper::getSelectPart()
	 */
	public function getSelectPart()
	{
		$fields = array(
			"obj_fi",
			"ref_id",
			"test_id",
			"object.title",
			"object.description",);

		return implode(', ', $fields);
	}

	/**
	 *	@see ilDataMapper::getFromPart()
	 */
	public function getFromPart()
	{
		$joins = array(
			"JOIN object_data object
				ON (test.obj_fi = object.obj_id)",
			"JOIN object_reference ref
				ON (ref.obj_id = object.obj_id)",);

		return $this->tableName . " " . implode(' ', $joins);
	}

	/**
	 *	@see ilDataMapper::getWherePart()
	 */
	public function getWherePart(array $filters)
	{
		$conditions = array(
			"ref.deleted IS NULL",);

		if (! empty($filters['flt_tst_name'])) {
			$conditions[] = sprintf(
				"object.title LIKE %s",
				$this->db->quote("%" . $filters['flt_tst_name'] . "%", 'text'));
		}

		return implode(' AND ', $conditions);
	}
}
