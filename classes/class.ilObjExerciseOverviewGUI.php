<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */
require_once 'Services/Repository/classes/class.ilObjectPluginListGUI.php';
require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/TestOverview/classes/GUI/class.ilTestOverviewTableGUI.php';

class ilObjExerciseOverviewListGUI extends ilTestOverviewTableGUI {

	public function __construct() {
		
	}

	/**
	 *  Gets the data from every student and their test results in a string seperated by "|"
	 * 
	 * @global type $ilDB
	 * @return type
	 */
	public function data() {
		global $ilDB;
		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/mapper/class.ilExerciseMapper.php';
		$mapper = new ilExerciseMapper;
		$data = $mapper->getArrayofObjects();

		$tpl = new ilTemplate("tpl.test_exerciseview_row.html", true, true, "Customizing/global/plugins/Services/Repository/RepositoryObject/TestOverview");

		foreach ($data as $set) {
			$tpl->setCurrentBlock("tests");
			$tpl->setVariable("TEST", $set->obj_id);
			$tpl->parseCurrentBlock();

			$tpl->setCurrentBlock("result");
			$tpl->setVariable("TEXT", $set->user_id);
			$tpl->setVariable("VALUE", $set->mark);
			$tpl->parseCurrentBlock();
		}

		return $tpl->get();
		return $mapper->getMarkforTest(6, 272);
	}

}

?>
