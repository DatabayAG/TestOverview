<?php
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 *	@package	TestOverview repository plugin
 *	@category	GUI
 *	@author		Greg Saive <gsaive@databay.de>
 */
/* Dependencies : */

require_once "Services/Tracking/classes/class.ilLPStatus.php";
require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
				->getDirectory() . '/classes/GUI/class.ilMappedTableGUI.php';

class ilTestOverviewTableGUI
	extends ilMappedTableGUI
{
	/**
	 *	 @var	array
	 */
	protected $filter = array();

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
		global $ilCtrl, $tpl;

		/* Pre-configure table */
		$this->setId(sprintf(
			"test_overview_%d", $a_parent_obj->object->getId()));

		$this->setDefaultOrderDirection('ASC');
		$this->setDefaultOrderField('obj_id');
		
		// ext ordering with db is ok, but ext limiting with db is not possible,
		// since the rbac filtering is downstream to the db query
		$this->setExternalSorting(true);
		$this->setExternalSegmentation(false);

        parent::__construct($a_parent_obj, $a_parent_cmd);

		$this->setTitle( sprintf(
			$this->lng->txt('rep_robj_xtov_test_overview_table_title'),
			$a_parent_obj->object->getTitle()));

		$overview = $this->getParentObject()->object;

		$this->addColumn($this->lng->txt('rep_robj_xtov_test_overview_hdr_user'));
		foreach ($overview->getTests() as $test) {
			$this->addColumn($test->getTitle());
		}
		$this->addColumn($this->lng->txt('rep_robj_xtov_test_overview_hdr_avg'));

		$plugin = ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview');
		$this->setRowTemplate('tpl.test_overview_row.html', $plugin->getDirectory());
		$this->setDescription($this->lng->txt("rep_robj_xtov_test_overview_description"));

		$cssFile = $plugin->getDirectory() . "/templates/css/testoverview.css";
		$tpl->addCss($cssFile);

		/* Configure table filter */
		$this->initFilter();
		$this->setFilterCommand("applyOverviewFilter");
		$this->setResetCommand("resetOverviewFilter");

		$this->setFormAction($ilCtrl->getFormAction($this->getParentObject(), 'showContent') );
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
        include_once 'Services/Form/classes/class.ilSelectInputGUI.php';
		include_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . "/classes/mapper/class.ilOverviewMapper.php";

		/* Configure participant name filter (input[type=text]) */
        $pname = new ilTextInputGUI($this->lng->txt('rep_robj_xtov_overview_flt_participant_name'), 'flt_participant_name');
        $pname->setSubmitFormOnEnter(true);

		/* Configure participant group name filter (select) */
		$mapper = new ilOverviewMapper;
		$groups = $mapper->getGroupPairs($this->getParentObject()->object->getId());
		$groups = array("" => "-- Select --") + $groups;

		$gname = new ilSelectInputGUI($this->lng->txt("rep_robj_xtov_overview_flt_group_name"), 'flt_group_name');
		$gname->setOptions($groups);

		/* Configure filter form */
        $this->addFilterItem($pname);
        $this->addFilterItem($gname);
        $pname->readFromSession();
        $gname->readFromSession();
        $this->filter['flt_participant_name'] = $pname->getValue();
        $this->filter['flt_group_name']		  = $gname->getValue();
    }

	/**
	 *	Fill a table row.
	 *
	 *	This method is called internally by ilias to
	 *	fill a table row according to the row template.
	 *
     *	@param ilObjTestOverview $overview
     */
    protected function fillRow(ilObjUser $member)
    {
		global $ilAccess;

 		$overview = $this->getParentObject()->object;

		/* Display user information */
		$this->tpl->setCurrentBlock( "test_results" );
		$this->tpl->setVariable( "TEST_PARTICIPANT", $member->getFullName() );
		$this->tpl->parseCurrentBlock();

		/* Now iterate through overview's tests to
		   print the results. */
		$results = array();
		// @todo: Greg, please use a static cache to prevent database request for every table row.
		// @todo: Furthermore each object (if we have mutiple reference) must only be listed once, if a user has access to multiple references (first one wins)
		foreach ($overview->getTests(true) as $idx => $test) {

			$result   = "";
			$progress = LP_STATUS_NOT_ATTEMPTED_NUM;

			if ( $ilAccess->checkAccess("tst_statistics", "", $test->getRefId())
				 || $ilAccess->checkAccess("write", "", $test->getRefId()) ) {
				$activeId  = $test->getActiveIdOfUser($member->getId());
				$result    = $test->getTestResult($activeId);

				$progress = new ilLPStatus( $test->getId() );
				$progress = $progress->_lookupStatus($test->getId(), $member->getId());

				if ((bool) $progress) {
					$result		= (float) sprintf("%.2f", (float) $result['pass']['percent'] * 100);
					$results[]  = $result;

					/* Format for display */
					$result	    = $result . " %";
				}
				else
					$result = $this->lng->txt("rep_robj_xtov_overview_test_not_passed") ;
			}

			$this->tpl->setCurrentBlock( "test_results" );
			$this->tpl->setVariable( "TEST_RESULT_CLASS", $this->getCSSByProgress($progress) );
			$this->tpl->setVariable( "TEST_RESULT_VALUE", $result);
			
			$handledObjects[$test->getId()] = true;

			$this->tpl->parseCurrentBlock();
		}

		if (count($results))
			$average = sprintf("%.2f", (array_sum($results) / count($results)));
		else
			$average = "";

		$this->tpl->setCurrentBlock( "test_results" );
		$this->tpl->setVariable( "TEST_AVERAGE_CLASS", "");
		$this->tpl->setVariable( "TEST_AVERAGE_VALUE", $average . (is_numeric($average) ? "%" : ""));
		$this->tpl->parseCurrentBlock();
    }

	/**
	 *	Format the fetched data.
	 *
	 *	This method is used internally to retrieve ilObjUser
	 *	objects from participant group ids (ilObjCourse || ilObjGroup).
	 *
	 *	@params	array	$data	array of IDs
	 *
	 *	@throws OutOfRangeException			on invalid ID
	 *	@throws InvalidArgumentException	on invalid obj_id (not grp|crs)
	 *	@return array
	 */
	protected function formatData( array $data )
	{
		/* For each group object we fetched, we need
		   to retrieve the members in order to have
		   a list of Participant. */
		$formatted = array(
			'items' => array(),
			'cnt'	=> 0);
		foreach ($data['items'] as $item) {
			$group = ilObjectFactory::getInstanceByObjId($item->obj_id, false);

			if ($group === false)
				throw new OutOfRangeException;
			elseif (! empty($this->filter['flt_group_name'])
					&& $group->getId() != $this->filter['flt_group_name'])
				/* Filter current group */
				continue;

			$grpMembers	  = array();
			$participants = $this->getGroupObject($group);

			/* Fetch member object by ID to avoid one-per-row
			   SQL queries. */
			foreach ($participants->getMembers() as $usrId) {
				if (! in_array($usrId, array_keys($formatted['items'])) ) {
					$user = ilObjectFactory::getInstanceByObjId($usrId, false);

					if ($user === false)
						throw new OutOfRangeException;

					if (! empty($this->filter['flt_participant_name'])) {
						$name   = strtolower($user->getFullName());
						$filter = strtolower($this->filter['flt_participant_name']);

						/* Simulate MySQL LIKE operator */
						if (false === strstr($name, $filter))
							/* User should be skipped. (Does not match filter) */
							continue;
					}

					$formatted['items'][$usrId] = $user;
				}
			}
		}
		// @todo: Greg, you have to adjust the max count value because of the filter above.
		$formatted['cnt'] = count($formatted['items']);

		return $this->sortByFullName($formatted);
	}

	/**
	 *	Get a CSS class name by the result
	 *
	 *	The getCSSByResult() method is used internally
	 *	to determine the CSS class to be set for a given
	 *	test result.
	 *
	 *	@params	int	$progress	Learning progress (0|1|2|3)
	 *							@see ilLPStatus
	 *
	 *	@return string
	 */
	private function getCSSByProgress( $progress )
	{
		switch ($progress) {
			case LP_STATUS_NOT_ATTEMPTED_NUM:
			default:
				return "no-result";

			case LP_STATUS_IN_PROGRESS_NUM:
				return "orange-result";

			case LP_STATUS_COMPLETED_NUM:
				return "green-result";

			case LP_STATUS_FAILED_NUM:
				return "red-result";
		}
	}

	/**
	 *	Sort the array of users by their full name.
	 *
	 *	This method had to be implemented in order to sort
	 *	the listed users by their full name. The overview
	 *	settings allows selecting participant groups rather
	 *	than users. This means the data fetched according
	 *	to a test overview, is the participant group's data.
	 *
	 *	@params	array	$data	Array with 'cnt' & 'items' indexes.
	 *
	 *	@return array
	 */
	protected function sortByFullName( array $data )
	{
		$azList = array();
		$sorted = array(
			'cnt'   => $data['cnt'],
			'items' => array());

		/* Initialize partition array. */
		for ($az = 'A'; $az <= 'Z'; $az++)
			$azList[$az] = array();

		/* Partition data. */
		foreach ($data['items'] as $userObj) {
			$name = $userObj->getFullName();
			$azList[$name{0}][] = $userObj;
		}

		/* Group all results. */
		foreach ($azList as $az => $userList) {
			if (! empty($userList))
				$sorted['items'] = array_merge($sorted['items'], $userList);
		}

		return $sorted;
	}
}

