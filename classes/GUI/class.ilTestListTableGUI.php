<?php
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 *	@package	TestOverview repository plugin
 *	@category	GUI
 *	@author		Greg Saive <gsaive@databay.de>
 */

/* Internal : */
require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
				->getDirectory() . '/classes/GUI/class.ilMappedTableGUI.php';

class ilTestListTableGUI
	extends ilMappedTableGUI
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
	public function __construct( ilObjectGUI $a_parent_obj, $a_parent_cmd )
	{
		/**
		 *	@var ilCtrl	$ilCtrl
		 */
		global $ilCtrl;

		/* Pre-configure table */
		$this->setId(sprintf(
			"test_overview_test_list_%d", $a_parent_obj->object->getId()));

		$this->setDefaultOrderDirection('ASC');
		$this->setDefaultOrderField('title');
		$this->setExternalSorting(true);
		$this->setExternalSegmentation(true);

        parent::__construct($a_parent_obj, $a_parent_cmd);

		$this->setTitle( sprintf(
			$this->lng->txt('rep_robj_xtov_test_list_table_title'),
			$a_parent_obj->object->getTitle()));

		$plugin = ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview');
		$this->setRowTemplate('tpl.simple_object_row.html', $plugin->getDirectory());

		$this->addColumn($this->lng->txt(""), '', '1px', true);
		$this->addColumn($this->lng->txt("rep_robj_xtov_test_list_hdr_test_title"), 'title');
		$this->addColumn($this->lng->txt("rep_robj_xtov_test_list_hdr_test_info"), '');
		$this->addColumn($this->lng->txt("rep_robj_xtov_item_chosen"), '');

		$this->setDescription($this->lng->txt("rep_robj_xtov_test_list_description"));
		$this->setFormAction($ilCtrl->getFormAction($this->getParentObject(), 'updateSettings') );
		$this->addMultiCommand('addTests', $this->lng->txt('rep_robj_xtov_add_to_overview'));
		$this->addMultiCommand('removeTests', $this->lng->txt('rep_robj_xtov_remove_from_overview'));

		$this->setShowRowsSelector(true);

		/* Add 'Select All', configure filters */
		$this->setSelectAllCheckbox('test_ids[]');
		$this->initFilter();
		$this->setFilterCommand("applyTestsFilter");
		$this->setResetCommand("resetTestsFilter");
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
     *	@param ilObjTest $test
     */
    protected function fillRow(ilObjTest $test)
    {
		/* Configure template rendering. */
		$this->tpl->setVariable('VAL_CHECKBOX',
				ilUtil::formCheckbox(false, 'test_ids[]', $test->getRefId()));
		$this->tpl->setVariable('OBJECT_TITLE', $test->getTitle());
		$this->tpl->setVariable('OBJECT_INFO', $this->getTestPath($test));
		$this->tpl->setVariable('OBJECT_STAUTS_IMG_PATH', $this->isAddedTest($test) ? ilUtil::getImagePath('icon_ok.png') : ilUtil::getImagePath('icon_not_ok.png'));
    }

	/**
	 *	Format the test data.
	 *
	 *	The formatData() method creates ilObjTest instances
	 *	out of the obj_id gotten from the query execution.
	 *
	 *	@params	array	$data	unformatted data.
	 *	@return array	formatted data.
	 */
	protected function formatData( array $data )
	{
		/**
		 * @var $ilAccess ilAccessHandler
		 */
		global $ilAccess;

		$formatted = array(
			'items'	=> array(),
			'cnt'	=> 0,);

		foreach ($data['items'] as $item) {
			$test = ilObjectFactory::getInstanceByRefId($item->ref_id, false);
			if ($test === false)
				throw new OutOfRangeException;

			/* Check access permissions. */
			if ( $ilAccess->checkAccess("tst_statistics", "", $test->getRefId())
				 || $ilAccess->checkAccess("write", "", $test->getRefId()) )
				$formatted['items'][] = $test;
		}
		// @todo: Greg, you have to adjust the max count value because of the filter above.
		$formatted['cnt'] = $data['cnt'];

		return $formatted;
	}

	/**
	 *	Check wether a test is added to the current overview.
	 *
	 *	The isAddedTest() method can be used to check wether
	 *	an ilObjTest object has been added to the current
	 *	overview already or not.
	 *
	 *	@params	ilObjTest	$test
	 *	@return boolean
	 */
	private function isAddedTest( ilObjTest $test )
	{
		/**
		 * @var $ilDB ilDB
		 */
		global $ilDB;

		$overviewId = $this->getParentObject()
						   ->object->getId();
		$filter = array(
			"obj_id_overview = " . $ilDB->quote($overviewId, 'integer'),
			"ref_id_test = " . $ilDB->quote($test->getRefId(), 'integer'),);

		$res = $this->getMapper()
	 		  	    ->getValue( "rep_robj_xtov_t2o", "TRUE", $filter );
		return !empty($res);
	}

	/**
	 *	Retrieve the tree path to an ilObjTest.
	 *
	 *	The getTestPath() method should be used to
	 *	retrieve the full path to a test node in the
	 *	ilias tree.
	 *
	 *	@params	ilObjTest	$test
	 *	@return string
	 */
	private function getTestPath( ilObjTest $test )
	{
		/**
		 * @var $tree ilTree
		 */
		global $tree;
		
		$path_str = '';

		$path = $tree->getNodePath($test->getRefId());
		while ($node = current($path)) {
			$prepend  = empty($path_str) ? '' : "{$path_str} > ";
			$path_str = $prepend . $node['title'];

			next($path);
		}

		return $path_str;
	}

}

