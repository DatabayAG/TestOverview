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

class ilOverviewMapper
	extends ilDataMapper
{
	/**
	 *	@var string
	 */
	protected $tableName = "rep_robj_xtov_overview overview";

	/**
	 *	@see ilDataMapper::getSelectPart()
	 */
	public function getSelectPart()
	{
		$fields = array(
			"participants.obj_id_grpcrs obj_id",);

		return implode(', ', $fields);
	}

	/**
	 *	@see ilDataMapper::getFromPart()
	 */
	public function getFromPart()
	{
		$joins = array(
			"JOIN rep_robj_xtov_p2o participants
				ON (overview.obj_id = participants.obj_id_overview)",);

		return $this->tableName . " " . implode(' ', $joins);
	}

	/**
	 *	@see ilDataMapper::getWherePart()
	 */
	public function getWherePart(array $filters)
	{
		$conditions = array("1 = 1");

		if (! empty($filters['overview_id'])) {
			$conditions[] = sprintf(
				"overview.obj_id = " . $this->db->quote($filters['overview_id'], 'integer'));
		}

		return implode(' AND ', $conditions);
	}

	/**
	 *	Get pairs of Participants groups.
	 *
	 *	This method can be used to list groups in a
	 *	HTML <select>. The index in the returned array
	 *	corresponds to the groups' obj_id and the value
	 *	is the groups' title.
	 *
	 *	@param	integer	$overviewId
	 *	@return array	Where index = obj_id and value = group title
	 */
	public function getGroupPairs($overviewId)
	{
		$pairs   = array();
		$rawData = $this->getList(array(), array("overview_id" => $overviewId));
		foreach ($rawData['items'] as $item) {
			$object = ilObjectFactory::getInstanceByObjId($item->obj_id, false);
			$pairs[$item->obj_id] = $object->getTitle();
		}

		return $pairs;
	}
}
