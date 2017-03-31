<?php

/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * 	@package	TestOverview repository plugin
 * 	@category	GUI
 * 	@author		Greg Saive <gsaive@databay.de>
 */
require_once 'Services/Repository/classes/class.ilObjectPluginListGUI.php';

class ilObjTestOverviewListGUI extends ilObjectPluginListGUI {

	/**
	 * @return string
	 */
	public function getGuiClass() {
		return 'ilObjTestOverviewGUI';
	}

	/**
	 * @return array
	 */
	public function initCommands() {
		return array
			(
			array(
				'permission' => 'read',
				'cmd' => 'UserResults',
				'default' => true
			),
			array(
				'permission' => 'write',
				'cmd' => 'editSettings',
				'txt' => $this->txt('edit'),
				'default' => false
			),
		);
	}

	/**
	 * 	Set the plugin type.
	 */
	public function initType() {
		$this->setType('xtov');
	}

}
