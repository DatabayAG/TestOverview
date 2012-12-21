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
}
