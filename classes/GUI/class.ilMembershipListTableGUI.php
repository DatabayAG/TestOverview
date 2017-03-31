<?php

/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * 	@package	TestOverview repository plugin
 * 	@category	GUI
 * 	@author		Greg Saive <gsaive@databay.de>
 */
/* Internal : */
require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
				->getDirectory() . '/classes/GUI/class.ilMappedTableGUI.php';

class ilMembershipListTableGUI extends ilMappedTableGUI {

	/**
	 * 	 @var	array
	 */
	public $filter = array();
	private $parent;
	private $objectParent;

	/**
	 * 	Constructor logic.
	 *
	 * 	This table GUI constructor method initializes the
	 * 	object and configures the table rendering.
	 */
	public function __construct(ilObjectGUI $a_parent_obj, $a_parent_cmd) {
		/**
		 * 	@var ilCtrl	$ilCtrl
		 */
		global $ilCtrl;

		/* Pre-configure table */
		$this->setId(sprintf(
						"test_overview_membership_list_%d", $a_parent_obj->object->getId()));
		$this->parent = $a_parent_obj;
		$this->objectParent = $this->parent->object->getParentId($this->parent->object->getRefId());
		$this->setDefaultOrderDirection('ASC');
		$this->setDefaultOrderField('title');
		$this->setExternalSorting(true);
		$this->setExternalSegmentation(true);

		parent::__construct($a_parent_obj, $a_parent_cmd);

		$this->setTitle(sprintf(
						$this->lng->txt('rep_robj_xtov_membership_list_table_title'), $a_parent_obj->object->getTitle()));

		$plugin = ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview');
		$this->setRowTemplate('tpl.simple_object_row.html', $plugin->getDirectory());

		$this->addColumn($this->lng->txt(""), '', '1px', true);
		$this->addColumn($this->lng->txt("rep_robj_xtov_membership_list_hdr_membership_title"), 'title');
		$this->addColumn($this->lng->txt("rep_robj_xtov_membership_list_hdr_membership_info"), '');
		$this->addColumn($this->lng->txt("rep_robj_xtov_item_chosen"), '');

		$this->setDescription($this->lng->txt("rep_robj_xtov_membership_list_description"));
		$this->setFormAction($ilCtrl->getFormAction($this->getParentObject(), 'updateSettings'));

		if (strcmp($a_parent_cmd, "subTabTO2") == 0) {
			$this->addMultiCommand('addMemberships', $this->lng->txt('rep_robj_xtov_add_to_overview'));
			$this->addMultiCommand('removeMemberships', $this->lng->txt('rep_robj_xtov_remove_from_overview'));
		}
		if (strcmp($a_parent_cmd, "subTabEO2") == 0) {
			$this->addMultiCommand('addMembershipsEx', $this->lng->txt('rep_robj_xtov_add_to_overview'));
			$this->addMultiCommand('removeMembershipsEx', $this->lng->txt('rep_robj_xtov_remove_from_overview'));
		}

		$this->setShowRowsSelector(true);

		/* Add 'Select All', configure filters */
		$this->setSelectAllCheckbox('membership_ids[]');
		$this->initFilter();
		if ($a_parent_cmd == "subTabTO2") {
			$this->setFilterCommand("applyGroupsFilter");
			$this->setResetCommand("resetGroupsFilter");
		}
		if ($a_parent_cmd == "subTabEO2") {
			$this->setFilterCommand("applyGroupsFilterEx");
			$this->setResetCommand("resetGroupsFilterEx");
		}
	}

	/**
	 * 	Initialize the table filters.
	 *
	 * 	This method is called internally to initialize
	 * 	the filters from present on the top of the table.
	 */
	public function initFilter() {
		include_once 'Services/Form/classes/class.ilTextInputGUI.php';
		$gname = new ilTextInputGUI($this->lng->txt('rep_robj_xtov_membership_list_flt_grp_name'), 'flt_grp_name');
		$gname->setSubmitFormOnEnter(true);

		$this->addFilterItem($gname);
		$gname->readFromSession();
		$this->filter['flt_grp_name'] = $gname->getValue();
	}

	/**
	 *    Fill a table row.
	 *
	 *    This method is called internally by ilias to
	 *    fill a table row according to the row template.
	 *
	 * @param stdClass $container
	 * @internal param \ilObjTest $test
	 *
	 */
	protected function fillRow($container) {
		global $tree;

		$refId = $container->ref_id;

		$parentRef = $this->parent->object->getParentId($refId);

		$members = $this->getMembersObject($container)->getCountMembers();
		$label = $this->lng->txt('rep_robj_xtov_membership_count_members');
		if ($this->objectParent == $parentRef) {
			$this->tpl->setVariable('VAL_CHECKBOX', ilUtil::formCheckbox(false, 'membership_ids[]', $container->obj_id));
			$this->tpl->setVariable('OBJECT_TITLE', $container->title);
			$this->tpl->setVariable('OBJECT_INFO', sprintf("%d %s", $members, $label));
			$this->tpl->setVariable('OBJECT_IMG_PATH', $this->isAddedContainer($container) ? ilUtil::getImagePath('icon_ok.svg') : ilUtil::getImagePath('icon_not_ok.svg'));
		} else {
			$this->tpl->setVariable('colapse', 'style="display:none;"');
		}
	}

	/**
	 *    Check wether a group is added to the current overview.
	 *
	 *    The isAddedGroup() method should be used to check
	 *    wether a participant group is added to the current
	 *    overview already or not.
	 *
	 * @params    stdClass    $container
	 * @param stdClass $container
	 * @return boolean
	 */
	private function isAddedContainer(stdClass $container) {
		/**
		 * @var $ilDB ilDB
		 */
		global $ilDB;

		// @todo: Move to application class!!!

		$overviewId = $this->getParentObject()->object->getId();
		$filter = array(
			'obj_id_overview = ' . $ilDB->quote($overviewId, 'integer'),
			'obj_id_grpcrs = ' . $ilDB->quote($container->obj_id, 'integer')
		);

		$res = $this->getMapper()->getValue('rep_robj_xtov_p2o', 'true', $filter);

		return !empty($res);
	}

}
