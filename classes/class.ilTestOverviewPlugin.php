<?php
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 *	@package	TestOverview repository plugin
 *	@category	Core
 *	@author		Greg Saive <gsaive@databay.de>
 */

require_once 'Services/Repository/classes/class.ilRepositoryObjectPlugin.php';

class ilTestOverviewPlugin
	extends ilRepositoryObjectPlugin
{
	/**
	 * @return string
	 */
	public function getPluginName()
	{
		return 'TestOverview';
	}

	static function _getIcon($a_type, $a_size)
   {
       return ilPlugin::_getImagePath(IL_COMP_SERVICE, "Repository", "robj",
           	ilPlugin::lookupNameForId(IL_COMP_SERVICE, "Repository", "robj",$a_type),
           	"icon_".$a_type.".svg");
   }

	protected function uninstallCustom()
	{
		/**
		 * @var $ilDB ilDB
		 */
		global $ilDB;

		if($ilDB->tableExists('rep_robj_xtov_overview'))
		{
			$ilDB->dropTable('rep_robj_xtov_overview');
		}

		if($ilDB->tableExists('rep_robj_xtov_t2o'))
		{
			$ilDB->dropTable('rep_robj_xtov_t2o');
		}

		if($ilDB->tableExists('rep_robj_xtov_p2o'))
		{
			$ilDB->dropTable('rep_robj_xtov_p2o');
		}
	}

}
