<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')->getDirectory() . '/classes/mapper/class.ilDataMapper.php';

/**
 * @author Greg Saive <gsaive@databay.de>
 */
class ilTestMapper extends ilDataMapper
{
	/**
	 * @var string
	 */
	protected $tableName = 'rep_robj_xtov_t2o';

	/**
	 * @return string
	 */
	public function getSelectPart()
	{
		$fields = array(
			'obj_fi',
			'test_id',
			'ref.ref_id',
			'od.title',
			'od.description',
			'ordering'
		);

		return implode(', ', $fields);
	}

	/**
	 * @return string
	 */
	public function getFromPart()
	{
		$joins = array(
			'INNER JOIN object_reference ref ON (ref.ref_id = rep_robj_xtov_t2o.ref_id_test AND deleted IS NULL)',
			"INNER JOIN object_data od ON (od.obj_id = ref.obj_id) AND od.type = 'tst'",
			'INNER JOIN tst_tests test ON (test.obj_fi = od.obj_id)'
		);

		return $this->tableName . ' ' . implode(' ', $joins);
	}

	/**
	 * @param array $filters
	 * @return string
	 */
	public function getWherePart(array $filters)
	{
		$conditions = array('rep_robj_xtov_t2o.obj_id_overview = ' . $this->db->quote($filters['overview_id'], 'integer'));

		if(isset($filters['flt_tst_name']) && !empty($filters['flt_tst_name']))
		{
			$conditions[] = $this->db->like('od.title', 'text', '%' . $filters['flt_tst_name'] . '%', false);
		}

		return implode(' AND ', $conditions);
	}
}
