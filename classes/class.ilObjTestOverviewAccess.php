<?php

/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * 	@package	TestOverview repository plugin
 * 	@category	RBAC
 * 	@author		Greg Saive <gsaive@databay.de>
 */
require_once 'Services/Repository/classes/class.ilObjectPluginAccess.php';

class ilObjTestOverviewAccess extends ilObjectPluginAccess {

	/**
	 * @param string $a_cmd
	 * @param string $a_permission
	 * @param int    $a_ref_id
	 * @param int    $a_obj_id
	 * @param string $a_user_id
	 * @return bool
	 */
	public function _checkAccess($a_cmd, $a_permission, $a_ref_id, $a_obj_id, $a_user_id = '') {
		/**
		 * @var $ilUser ilObjUser
		 */
		global $ilUser;

		if (!$a_user_id) {
			$a_user_id = $ilUser->getId();
		}

		switch ($a_permission) {
			case 'read':
				return true;
		}

		return true;
	}

}
