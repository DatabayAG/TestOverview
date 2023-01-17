<?php
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 *	@package	TestOverview repository plugin
 *	@category	GUI
 *	@author		Greg Saive <gsaive@databay.de>
 */

/* Internal : */
require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')->getDirectory() . '/classes/GUI/class.ilMappedTableGUI.php';

class ilTestListTableGUI extends ilMappedTableGUI
{
	/**
	 *	 @var	array
	 */
	public $filter = array();

	/**
	 *	Constructor logic.
	 *
	 *	This table GUI constructor method initializes the
	 *	object and configures the table rendering.
	 */
	public function __construct(ilObjectGUI $a_parent_obj, $a_parent_cmd)
	{
		/**
		 *	@var ilCtrl $ilCtrl
         *  @var ilTree $tree
		 */
		global $ilCtrl, $tree;

		/* Pre-configure table */
		$this->setId(
			sprintf(
				'test_overview_test_list_%d',
				$a_parent_obj->object->getId()
			)
		);

		$this->setDefaultOrderDirection('ASC');
		$this->setDefaultOrderField('title');
		
		// ext ordering with db is ok, but ext limiting with db is not possible,
		// since the rbac filtering is downstream to the db query
		$this->setExternalSorting(true);
		$this->setExternalSegmentation(true);

		parent::__construct($a_parent_obj, $a_parent_cmd);

		$this->setTitle(
			sprintf(
				$this->lng->txt('rep_robj_xtov_test_list_table_title'),
				$a_parent_obj->object->getTitle()
			)
		);

		$plugin = ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview');
		$this->setRowTemplate('tpl.simple_object_row.html', $plugin->getDirectory());

		$this->addColumn($this->lng->txt(''), '', '1px', true);
		$this->addColumn('#', 'ordering', '20px');
		$this->addColumn($this->lng->txt('rep_robj_xtov_test_list_hdr_test_title'), 'title');
		$this->addColumn($this->lng->txt('rep_robj_xtov_test_list_hdr_test_info'), '');

		$this->setDescription($this->lng->txt('rep_robj_xtov_test_list_description'));
		$this->setFormAction($ilCtrl->getFormAction($this->getParentObject(), 'updateSettings'));
		$this->addCommandButton('saveOrder', $this->lng->txt('sorting_save'));
		$this->addCommandButton('initSelectTests', $this->lng->txt('rep_robj_xtov_add_tsts_to_overview'));

        $pnode = $tree->getParentNodeData((int)$_GET['ref_id']);
        $otype = ilObject::_lookupType($pnode['ref_id'],true); // Parent node is 'crs'
        $tsts = $tree->getFilteredSubTree($pnode['ref_id'], ['tst']);  // and has 'tst's
        if($otype == 'crs' && count($tsts) > 0) {
            $this->addCommandButton('initCourseTests', $this->lng->txt('rep_robj_xtov_add_tsts_from_course'));
        }
        $this->addMultiCommand('removeTests', $this->lng->txt('rep_robj_xtov_remove_from_overview'));

		$this->setShowRowsSelector(true);
		$this->setSelectAllCheckbox('test_ids[]');

		$this->initFilter();
		$this->setFilterCommand('applyTestsFilter');
		$this->setResetCommand('resetTestsFilter');
	}

	/**
	 *	Initialize the table filters.
	 *
	 *	This method is called internally to initialize
	 *	the filters from present on the top of the table.
	 */
	public function initFilter()
    {
        include_once 'Services/Form/classes/class.ilTextInputGUI.php';
        $tname = new ilTextInputGUI($this->lng->txt('rep_robj_xtov_test_list_flt_tst_name'), 'flt_tst_name');
        $tname->setSubmitFormOnEnter(true);
        $this->addFilterItem($tname);
        $tname->readFromSession();
        $this->filter['flt_tst_name'] = $tname->getValue();
    }

	/**
	 *	Fill a table row.
	 *
	 *	This method is called internally by ilias to
	 *	fill a table row according to the row template.
	 *
     *	@param stdClass $item
     */
    protected function fillRow($item)
    {
		/* Configure template rendering. */
		$this->tpl->setVariable('VAL_CHECKBOX', ilUtil::formCheckbox(false, 'test_ids[]', $item->ref_id));
		$this->tpl->setVariable('VAL_ORDER_ID', 'order['.$item->ref_id.']' );
		$this->tpl->setVariable('VAL_ORDERING', (int)$item->ordering);
		$this->tpl->setVariable('OBJECT_TITLE', $item->title);
		$this->tpl->setVariable('OBJECT_INFO', $this->getTestPath($item));
    }

	/**
	 *    Retrieve the tree path to an ilObjTest.
	 *
	 *    The getTestPath() method should be used to
	 *    retrieve the full path to a test node in the
	 *    ilias tree.
	 *
	 * @params    stdClass    $item
	 * @param stdClass $item
	 * @return string
	 */
	private function getTestPath( stdClass $item )
	{
		/**
		 * @var $tree ilTree
		 */
		global $tree;
		
		$path_str = '';

		$path = $tree->getNodePath($item->ref_id);
		while ($node = current($path))
		{
			$prepend  = empty($path_str) ? '' : "{$path_str} > ";
			$path_str = $prepend . $node['title'];
			next($path);
		}

		return $path_str;
	}

}

