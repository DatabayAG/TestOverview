<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/Object/classes/class.ilPasteIntoMultipleItemsExplorer.php';

/**
 *
 */
class ilTestOverviewTestSelectionExplorer extends ilPasteIntoMultipleItemsExplorer
{
	protected $hidden_nodes = array();
	
	/**
	 * @param string $session_key
	 */
	public function __construct($session_key)
	{
		parent::__construct(ilPasteIntoMultipleItemsExplorer::SEL_TYPE_CHECK, 'ilias.php?baseClass=ilRepositoryGUI&cmd=goto', $session_key);
		$this->removeFormItemForType('root');
		$this->removeFormItemForType('crs');
		$this->removeFormItemForType('grp');
		$this->removeFormItemForType('cat');
		$this->removeFormItemForType('fold');
		$this->addFormItemForType('tst');
		$this->addFilter('tst');
	}

	/**
	 * @param int    $a_ref_id
	 * @param string $a_type
	 * @return bool
	 */
	public function isVisible($a_ref_id, $a_type)
	{
		/**
		 * @var $ilAccess ilAccessHandler
		 */
		global $ilAccess;

		if(isset($this->hidden_nodes[$a_ref_id]) && $this->hidden_nodes[$a_ref_id])
		{
			return false;
		}

		$visible = parent::isVisible($a_ref_id, $a_type);

		if('tst' == $a_type)
		{
			if(!$ilAccess->checkAccess('tst_statistics', '', $a_ref_id) && !$ilAccess->checkAccess('write', '', $a_ref_id))
			{
				return false;
			}
		}

		return $visible;
	}

	/**
	 * @param $objects
	 */
	public function setDefaultHiddenObjects(array $objects)
	{
		foreach($objects as $data)
		{
			$this->hidden_nodes[$data[0]] = true;
		}
	}
}
