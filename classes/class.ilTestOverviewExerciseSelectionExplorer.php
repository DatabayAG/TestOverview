<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */
require_once 'Services/Object/classes/class.ilPasteIntoMultipleItemsExplorer.php';

class ilTestOverviewExerciseSelectionExplorer extends ilPasteIntoMultipleItemsExplorer {

	protected $hidden_nodes = array();

	public function __construct($session_key) {
		parent::__construct(ilPasteIntoMultipleItemsExplorer::SEL_TYPE_CHECK, 'ilias.php?baseClass=ilRepositoryGUI&cmd=goto', $session_key);

		$this->removeFormItemForType('root');
		$this->removeFormItemForType('crs');
		$this->removeFormItemForType('grp');
		$this->removeFormItemForType('cat');
		$this->removeFormItemForType('fold');
		$this->addFormItemForType('exc');
		$this->addFilter('exc');
	}

	public function isVisible($a_ref_id, $a_type) {
		/**
		 * @var $ilAccess ilAccessHandler
		 */
		global $ilAccess;

		if (isset($this->hidden_nodes[$a_ref_id]) && $this->hidden_nodes[$a_ref_id]) {
			return false;
		}

		$visible = parent::isVisible($a_ref_id, $a_type);

		include_once './Services/Tracking/classes/class.ilLearningProgressAccess.php';
		if ('exc' == $a_type) {
			if (!ilLearningProgressAccess::checkAccess($a_ref_id) && !$ilAccess->checkAccess('write', '', $a_ref_id)) {
				return false;
			}
		}

		return $visible;
	}

	public function setDefaultHiddenObjects(array $objects) {
		foreach ($objects as $data) {
			$this->hidden_nodes[$data[0]] = true;
		}
	}

}

?>
