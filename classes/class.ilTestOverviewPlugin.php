<?php

/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * 	@package	TestOverview repository plugin
 * 	@category	Core
 * 	@author		Greg Saive <gsaive@databay.de>
 */
require_once 'Services/Repository/classes/class.ilRepositoryObjectPlugin.php';

class ilTestOverviewPlugin extends ilRepositoryObjectPlugin {

	/**
	 * @return string
	 */
	public function getPluginName() {
		return 'TestOverview';
	}

	static function _getIcon($a_type, $a_size) {
		return ilPlugin::_getImagePath(IL_COMP_SERVICE, "Repository", "robj", ilPlugin::lookupNameForId(IL_COMP_SERVICE, "Repository", "robj", $a_type), "icon_" . $a_type . ".svg");
	}

	protected function uninstallCustom() {
		/**
		 * @var $ilDB ilDB
		 */
		global $ilDB;
		$ilDB->query('DROP TABLE IF EXISTS	rep_robj_xtov_overview, rep_robj_xtov_t2o, 
                                                        rep_robj_xtov_p2o, rep_robj_xtov_torank, rep_robj_xtov_rankdate,
                                                        rep_robj_xtov_eorank, rep_robj_xtov_e2o, rep_robj_xtov_exview ');
	}

}
