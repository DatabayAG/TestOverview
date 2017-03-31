<?php

/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */
/**
*	@author		Jan Ruthardt <janruthardt@web.de>
*	@author		Benedict Steuerlein <st111340@stud.uni-stuttgart.de>
*	@author		Martin Dinkel <hmdinkel@web.de>
* 
*	Mapper for displaying all added exercises in the subtab Exercise Administration 
*/
class ilExerciseSettingsMapper extends ilDataMapper {

	/**
	 * @var string
	 */
	protected $tableName = 'rep_robj_xtov_e2o';
	/**
	 * 
	 * @return string
	 */
	protected function getSelectPart() {
		$fields = array(
			'DISTINCT (ref.obj_id)',
			'od.title',
			'od.description'
		);

		return implode(', ', $fields);
	}
	/**
	 * 
	 * @return string
	 */
	protected function getFromPart() {
		$joins = array(
			'INNER JOIN object_reference ref ON (rep_robj_xtov_e2o.obj_id_exercise = ref.obj_id AND ref.deleted IS NULL)',
			"INNER JOIN object_data od ON (ref.obj_id = od.obj_id) AND od.type = 'exc'"
		);

		return $this->tableName . ' ' . implode(' ', $joins);
	}
	/**
	 * 
	 * @param array $filters
	 * @return string
	 */
	protected function getWherePart(array $filters) {
		$conditions = array('rep_robj_xtov_e2o.obj_id_overview = ' . $this->db->quote($filters['overview_id'], 'integer'));

		if (isset($filters['flt_tst_name']) && !empty($filters['flt_tst_name'])) {
			$conditions[] = $this->db->like('od.title', 'text', '%' . $filters['flt_tst_name'] . '%', false);
		}

		return implode(' AND ', $conditions);
	}

}
