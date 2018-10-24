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
	private $accessIndex = array();
	private $readIndex = array();

	private $temp_results = array();

	/**
	 *	 @var	array
	 */
	protected $filter = array();

	/**
	 * @var array
	 */
	protected $linked_tst_column_targets = array();

	/**
	 * @var array 
	 */
	protected $evalDataByTestId = array();

	/**
	 * @var \ilObjTestOverview
	 */
	protected $overview;
	protected $full_max;
	private $export_header_data;
	private $export_row_data;

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
		global $ilCtrl, $tpl, $ilAccess;

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

		$this->overview = $this->getParentObject()->object;

		$this->addColumn($this->lng->txt('rep_robj_xtov_test_overview_hdr_user'));
		$this->export_header_data[] = $this->lng->txt('rep_robj_xtov_test_overview_hdr_user');

		foreach( $this->overview->getUniqueTests() as $obj_id => $refs )
		{
			// Set default permissions based on statistics or write access
			$this->accessIndex[$obj_id] = false;
			$this->readIndex[$obj_id] = false;
			$valid_ref_id = null;
			$shows_all_users = false;
			foreach( $refs as $ref_id )
			{
				switch( true )
				{
					case $ilAccess->checkAccess("tst_statistics", "", $ref_id):
					case $ilAccess->checkAccess("write", "", $ref_id):
						$valid_ref_id = $ref_id; 
						$this->accessIndex[$obj_id] = $valid_ref_id;
						break 2;
					case $ilAccess->checkAccess("read", "", $ref_id):
						$valid_ref_id = $ref_id;
						$this->readIndex[$obj_id] = $valid_ref_id;
						break 2;
				}
			}

			$title_text = $this->overview->getTest($obj_id)->getTitle();
			if($this->overview->getPointsColumn() && $this->overview->getHeaderPoints())
			{
				/** @var ilObjTest $test_object */
				$test_object = $this->overview->getTest($obj_id);
				$evaluation = $test_object->getCompleteEvaluationData(false);
				$participants = $evaluation->getParticipants();
				if(count($participants))
				{
					/** @var ilTestEvaluationUserData $participant */
					$participant = current($participants);
					$title_text .= ' (' . $participant->getMaxpoints() . ' ' . $this->lng->txt('points'). ')';
					$this->full_max = $this->full_max + $participant->getMaxpoints();
				}
				else
				{
					$title_text .= ' (? ' . $this->lng->txt('points'). ')';
				}
			}

			$ilCtrl->setParameterByClass("ilobjtestgui", 'ref_id', $valid_ref_id);
			$this->addTestColumn( $title_text, $ilCtrl->getLinkTargetByClass('ilobjtestgui', 'infoScreen'));
			$this->export_header_data[] = $title_text;
			$ilCtrl->setParameterByClass("ilobjtestgui", 'ref_id', '');
			$this->overview->gatherTestData($this->overview->getTest($obj_id), $this->evalDataByTestId);
		}

		$this->setupEvaluationColumns();
		// TODO: Add Toolbar Button for Excel Export

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

		if($this->overview->getEnableExcel())
		{
			$button = ilLinkButton::getInstance();
			$button->setCaption('tbl_export_excel');
			$button->setUrl($ilCtrl->getLinkTarget($this->parent_obj, 'exportExcel'));
			/** @var ilToolbarGUI $ilToolbar */
			global $ilToolbar;
			$ilToolbar->addButtonInstance($button);
		}
	}

	/**
	 * Execute command.
	 */
	function executeCommand()
	{
		$ilCtrl = $this->ctrl;

		$next_class = $ilCtrl->getNextClass($this);
		$cmd = $ilCtrl->getCmd();

		switch($next_class)
		{
			case 'ilformpropertydispatchgui':
				include_once './Services/Form/classes/class.ilFormPropertyDispatchGUI.php';
				$form_prop_dispatch = new ilFormPropertyDispatchGUI();
				$this->initFilter();
				$item = $this->getFilterItemByPostVar($_GET["postvar"]);
				$form_prop_dispatch->setItem($item);
				return $ilCtrl->forwardCommand($form_prop_dispatch);
				break;

		}
		return false;
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
	 *    Fill a table row.
	 *
	 *    This method is called internally by ilias to
	 *    fill a table row according to the row template.
	 *
	 * @param array $row
	 * @internal param \ilObjTestOverview $overview
	 */
    protected function fillRow($row)
    {
 		$overview = $this->getParentObject()->object;

		$results = array();

		$max_points = 0;
		$reached_points = 0;
		foreach ($overview->getUniqueTests() as $obj_id => $refs)
		{
			$test = $overview->getTest($obj_id);
			$activeId  = $test->getActiveIdOfUser($row['member_id']);

			$testResult = null;
			global $ilUser;
			if( $this->accessIndex[$obj_id] || ( $this->readIndex[$obj_id] && $ilUser->getId() == $row['member_id']) )
			{
				$testResult    = $test->getTestResult($activeId);
				$max_points = $max_points + $testResult['pass']['total_max_points'];
				$reached_points = $reached_points + $testResult['pass']['total_reached_points'];

				if (strlen($testResult['pass']['percent']))
				{
					if($this->parent_obj->object->getResultPresentation() == ilObjTestOverview::PRESENTATION_PERCENTAGE)
					{
						$result		= sprintf("%.2f %%", (float) $testResult['pass']['percent'] * 100);
					}
					else
					{
						if($this->overview->getPointsColumn() && $this->overview->getHeaderPoints())
						{
							$result	= $testResult['pass']['total_reached_points'];
						}
						else
						{
							$result = $testResult['pass']['total_reached_points'] . ' / ' . $testResult['pass']['total_max_points'];
						}
					}
					$results[]  = $result;
				}
				else
				{
					$result = $this->lng->txt("rep_robj_xtov_overview_test_not_passed");
					$results[]  = 0;
				}

				if ($activeId > 0)
				{
					$resultLink = $this->buildMemberResultLinkTarget($this->accessIndex[$obj_id], $activeId);
					$this->populateLinkedCell($resultLink, $result, $this->getCSSByTestResult($testResult, $activeId, $obj_id));
				}
				else
				{
					$this->populateNoLinkCell(
						$result, $this->getCSSByTestResult(null)
					);
				}
			}
			else
			{
				$this->populateNoLinkCell(
					$this->lng->txt("rep_robj_xtov_overview_test_no_permission"), $this->getCSSByTestResult(null)
				);
			}
			
			$this->tpl->setCurrentBlock('cell');
			$this->tpl->parseCurrentBlock();
		}

		$this->populateEvaluationColumns($results, $reached_points, $max_points);

		$row_data = array();
		$row_data[] = $row['member_fullname'];
		foreach($results as $item)
		{
			$row_data[] = $item;
		}
		foreach($this->temp_results as $item)
		{
			$row_data[] = $item;
		}
		$this->export_row_data[] = $row_data;
		$this->temp_results = array();

		$this->tpl->setVariable('TEST_PARTICIPANT', $row['member_fullname']);
    }

	private function populateLinkedCell($resultLink, $resultValue, $cssClass)
	{
		$this->tpl->setCurrentBlock('result');
		$this->tpl->setVariable('RESULT_LINK', $resultLink);
		$this->tpl->setVariable('RESULT_VALUE', $resultValue);
		$this->tpl->setVariable('RESULT_CSSCLASS', $cssClass);
		$this->tpl->parseCurrentBlock();
	}

	private function populateNoLinkCell($resultValue, $cssClass)
	{
		$this->tpl->setCurrentBlock('result_nolink');
		$this->tpl->setVariable('RESULT_VALUE_NOLINK', $resultValue);
		$this->tpl->setVariable('RESULT_CSSCLASS_NOLINK', $cssClass);
		$this->tpl->parseCurrentBlock();
	}

	/**
	 *    Format the fetched data.
	 *
	 *    This method is used internally to retrieve ilObjUser
	 *    objects from participant group ids (ilObjCourse || ilObjGroup).
	 *
	 * @params    array    $data    array of IDs
	 *
	 * @param array $data
	 * @throws OutOfRangeException
	 * @return array
	 */
	protected function formatData( array $data )
	{
		/* For each group object we fetched, we need
		   to retrieve the members in order to have
		   a list of Participant. */
		$formatted = array(
			'items' => array(),
			'cnt'	=> 0);
		
		if(!$data['items'])
		{
			$formatted = $this->getMapper()->getUniqueTestParticipants(array_keys($this->accessIndex));
			$formatted['items'] = $this->fetchUserInformation($formatted['items']);
			return $this->sortByFullName($formatted);
		}
		
		foreach ($data['items'] as $item)
		{
			$container = ilObjectFactory::getInstanceByObjId($item->obj_id, false);

			if ($container === false)
				throw new OutOfRangeException;
			elseif (! empty($this->filter['flt_group_name'])
					&& $container->getId() != $this->filter['flt_group_name'])
				/* Filter current group */
				continue;

			$participants = $this->getMembersObject($item);
			/* Fetch member object by ID to avoid one-per-row
			   SQL queries. */
			foreach ($participants->getMembers() as $usrId)
			{
				$formatted['items'][$usrId] = $usrId;
			}
		}
		
		$formatted['items'] = $this->fetchUserInformation($formatted['items']);

		return $this->sortByFullName($formatted);
	}
	
	private function fetchUserInformation($usr_ids)
	{
		global $ilDB;
		
		$usr_id__IN__usrIds = $ilDB->in('usr_id', $usr_ids, false, 'integer');
		
		$query = "
			SELECT usr_id, title, firstname, lastname FROM usr_data WHERE $usr_id__IN__usrIds
		";
		
		$res = $ilDB->query($query);
		
		$users = array();
		
		while( $row = $ilDB->fetchAssoc($res) )
		{
			$user = new ilObjUser();
			
			$user->setId($row['usr_id']);
			$user->setUTitle($row['title']);
			$user->setFirstname($row['firstname']);
			$user->setLastname($row['lastname']);
			$user->setFullname();

			if (! empty($this->filter['flt_participant_name']))
			{
				$name   = strtolower($user->getFullName());
				$filter = strtolower($this->filter['flt_participant_name']);

				/* Simulate MySQL LIKE operator */
				if (false === strstr($name, $filter))
				{
					/* User should be skipped. (Does not match filter) */
					continue;
				}
			}
			
			$users[ $row['usr_id'] ] = $user;
		}
		
		return $users;
	}

	/**
	 * @param array|null $result
	 * @param int $activeId
	 * @param int $testObjId
	 * @return string
	 */
	private function getCSSByTestResult($result, $activeId = null, $testObjId = null)
	{
		if (null === $result) {
			return 'no-result';
		}

		$row = $this->evalDataByTestId[$testObjId][$activeId];
		if (!$row) {
			return 'no-result';
		}

		$is_passed = false;
		if (isset($result['test']) && isset($result['test']['passed']) && (bool)$result['test']['passed']) {
			$is_passed = true;
		}

		if ($this->overview->getTest($testObjId)->getPassScoring() == SCORE_LAST_PASS) {
			$status = $this->determineStatusForScoreLastPassTests((bool)$row['is_finished'], $is_passed);
		} else {
			$status = 'orange-result';

			if ($row['last_finished_pass'] != null) {
				$status = $this->determineLpStatus($is_passed);
			}

			if (!$row['is_last_pass'] && $status == 'red-result') {
				$status = 'orange-result';
			}
		}

		return $status ;
	}

	/**
	 * @param $is_finished
	 * @param $passed
	 * @return int
	 */
	protected function determineStatusForScoreLastPassTests($is_finished, $passed)
	{
		$status = 'orange-result';

		if($is_finished)
		{
			$status = $this->determineLpStatus($passed);
		}

		return $status;
	}

	/**
	 * @param $passed
	 * @return int
	 */
	protected function determineLpStatus($passed)
	{
		$status = 'red-result';

		if($passed)
		{
			$status = 'green-result';
		}

		return $status;
	}

	public function setupEvaluationColumns()
	{
		if($this->overview->getResultColumn())
		{
			if ($this->overview->getResultPresentation() == ilObjTestOverview::PRESENTATION_PERCENTAGE)
			{
				$this->addColumn($this->lng->txt('rep_robj_xtov_test_overview_hdr_avg'));
				$this->export_header_data[] = $this->lng->txt('rep_robj_xtov_test_overview_hdr_avg');
			}
			else
			{
				$this->addColumn($this->lng->txt('rep_robj_xtov_test_overview_hdr_sum'));
				$this->export_header_data[] = $this->lng->txt('rep_robj_xtov_test_overview_hdr_sum');
			}
		}

		if($this->overview->getPointsColumn())
		{
			$points = "";
			if($this->full_max > 0)
			{
				$points = " ( " . $this->full_max . " )";
			}
			$this->addColumn($this->lng->txt('rep_robj_xtov_test_overview_hdr_points') . $points);
			$this->export_header_data[] = $this->lng->txt('rep_robj_xtov_test_overview_hdr_points');
		}

		if($this->overview->getAverageColumn())
		{
			$this->addColumn($this->lng->txt('rep_robj_xtov_test_overview_hdr_avg'));
			$this->export_header_data[] = $this->lng->txt('rep_robj_xtov_test_overview_hdr_avg');
		}
	}

	/**
	 * @param $results
	 * @param $reached_points
	 * @param $max_points
	 */
	protected function populateEvaluationColumns($results, $reached_points, $max_points)
	{
		if($this->overview->getResultColumn())
		{
			$this->populateResultCell($results, $reached_points, $max_points);
		}

		if($this->overview->getPointsColumn())
		{
			if (count($results))
			{
				$points = sprintf("%.2f", array_sum($results));
			}
			else
			{
				$points = "";
			}

			$this->tpl->setCurrentBlock('points');
			$this->tpl->setVariable("POINTS_VALUE", $points );
			$this->temp_results[] = $points;
			$this->tpl->parseCurrentBlock();
		}

		if($this->overview->getAverageColumn())
		{
			if (count($results))
			{
				$points = sprintf("%.2f", (array_sum($results) / count($results)));
			}
			else
			{
				$points = "";
			}

			$this->tpl->setCurrentBlock('avg');
			$this->tpl->setVariable("AVG_VALUE", $points );
			$this->temp_results[] = $points;
			$this->tpl->parseCurrentBlock();
		}
	}

	/**
	 * @param $results
	 * @param $reached_points
	 * @param $max_points
	 */
	protected function populateResultCell($results, $reached_points, $max_points)
	{
		if (count($results))
		{
			if ($this->parent_obj->object->getResultPresentation() == ilObjTestOverview::PRESENTATION_PERCENTAGE)
			{
				$average = sprintf("%.2f", (array_sum($results) / count($results)));
			}
			else
			{
				$average = $reached_points . ' / ' . $max_points;
			}
		}
		else
		{
			$average = "";
		}
		$this->tpl->setCurrentBlock('sum');
		$this->tpl->setVariable("SUM_VALUE", $average . (is_numeric($average) ? "%" : ""));
		$this->temp_results[] = $average . (is_numeric($average) ? "%" : "");
		$this->tpl->parseCurrentBlock();
	}

	/**
	 *    Get a CSS class name by the result
	 *
	 *    The getCSSByResult() method is used internally
	 *    to determine the CSS class to be set for a given
	 *    test result.
	 *
	 * @params    int    $progress    Learning progress (0|1|2|3)
	 * @see       ilLPStatus
	 *
	 * @param $progress
	 * @return string
	 */

	private function getCSSByProgress( $progress )
	{
		$map = $this->buildCssClassByProgressMap();

		$progress = (string)$progress;

		foreach($map as $lpNum => $cssClass)
		{
			if( $progress === (string)$lpNum ) // we need identical check !!
			{
				return $cssClass;
			}
		}

		return 'no-perm-result';
	}

	public function buildCssClassByProgressMap()
	{
		if( defined('ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM') )
		{
			return array(
				ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM => 'no-result',
				ilLPStatus::LP_STATUS_IN_PROGRESS_NUM => 'orange-result',
				ilLPStatus::LP_STATUS_COMPLETED_NUM => 'green-result',
				ilLPStatus::LP_STATUS_FAILED_NUM => 'red-result'
			);
		}

		return array(
			LP_STATUS_NOT_ATTEMPTED_NUM => 'no-result',
			LP_STATUS_IN_PROGRESS_NUM => 'orange-result',
			LP_STATUS_COMPLETED_NUM => 'green-result',
			LP_STATUS_FAILED_NUM => 'red-result'
		);
	}

	/**
	 *    Sort the array of users by their full name.
	 *
	 *    This method had to be implemented in order to sort
	 *    the listed users by their full name. The overview
	 *    settings allows selecting participant groups rather
	 *    than users. This means the data fetched according
	 *    to a test overview, is the participant group's data.
	 *
	 * @params    array    $data    Array with 'cnt' & 'items' indexes.
	 *
	 * @param array $data
	 * @return array
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

	/**
	 * @param string $a_text
	 * @param string $link
	 */
	public function addTestColumn($a_text, $link)
	{
		$this->addColumn($a_text, '');
		$this->column[count($this->column) - 1]['link'] = $link;
	}

	/**
	 * 
	 */
	public function fillHeader()
	{
		global $lng;

		$allcolumnswithwidth = true;
		foreach ((array) $this->column as $idx => $column)
		{
			if (!strlen($column["width"]))
			{
				$allcolumnswithwidth = false;
			}
			else if($column["width"] == "1")
			{
				// IE does not like 1 but seems to work with 1%
				$this->column[$idx]["width"] = "1%";
			}
		}
		if ($allcolumnswithwidth)
		{
			foreach ((array) $this->column as $column)
			{
				$this->tpl->setCurrentBlock("tbl_colgroup_column");
				$this->tpl->setVariable("COLGROUP_COLUMN_WIDTH", $column["width"]);
				$this->tpl->parseCurrentBlock();
			}
		}
		$ccnt = 0;
		foreach ((array) $this->column as $column)
		{
			$ccnt++;

			//tooltip
			if ($column["tooltip"] != "")
			{
				include_once("./Services/UIComponent/Tooltip/classes/class.ilTooltipGUI.php");
				ilTooltipGUI::addTooltip("thc_".$this->getId()."_".$ccnt, $column["tooltip"]);
			}
			if ((!$this->enabled["sort"] || $column["sort_field"] == "" || $column["is_checkbox_action_column"]) && !$column['link'])
			{
				$this->tpl->setCurrentBlock("tbl_header_no_link");
				if ($column["width"] != "")
				{
					$this->tpl->setVariable("TBL_COLUMN_WIDTH_NO_LINK"," width=\"".$column["width"]."\"");
				}
				if (!$column["is_checkbox_action_column"])
				{
					$this->tpl->setVariable("TBL_HEADER_CELL_NO_LINK",
						$column["text"]);
				}
				else
				{
					$this->tpl->setVariable("TBL_HEADER_CELL_NO_LINK",
						ilUtil::img(ilUtil::getImagePath("spacer.png"), $lng->txt("action")));
				}
				$this->tpl->setVariable("HEAD_CELL_NL_ID", "thc_".$this->getId()."_".$ccnt);

				if ($column["class"] != "")
				{
					$this->tpl->setVariable("TBL_HEADER_CLASS"," " . $column["class"]);
				}
				$this->tpl->parseCurrentBlock();
				$this->tpl->touchBlock("tbl_header_th");
				continue;
			}
			if (($column["sort_field"] == $this->order_field) && ($this->order_direction != ""))
			{
				$this->tpl->setCurrentBlock("tbl_order_image");
				$this->tpl->setVariable("IMG_ORDER_DIR",ilUtil::getImagePath($this->order_direction."_order.png"));
				$this->tpl->setVariable("IMG_ORDER_ALT", $this->lng->txt("change_sort_direction"));
				$this->tpl->parseCurrentBlock();
			}

			$this->tpl->setCurrentBlock("tbl_header_cell");
			$this->tpl->setVariable("TBL_HEADER_CELL", $column["text"]);
			$this->tpl->setVariable("HEAD_CELL_ID", "thc_".$this->getId()."_".$ccnt);

			// only set width if a value is given for that column
			if ($column["width"] != "")
			{
				$this->tpl->setVariable("TBL_COLUMN_WIDTH"," width=\"".$column["width"]."\"");
			}

			$lng_sort_column = $this->lng->txt("sort_by_this_column");
			$this->tpl->setVariable("TBL_ORDER_ALT",$lng_sort_column);

			$order_dir = "asc";

			if ($column["sort_field"] == $this->order_field)
			{
				$order_dir = $this->sort_order;

				$lng_change_sort = $this->lng->txt("change_sort_direction");
				$this->tpl->setVariable("TBL_ORDER_ALT",$lng_change_sort);
			}

			if ($column["class"] != "")
			{
				$this->tpl->setVariable("TBL_HEADER_CLASS"," " . $column["class"]);
			}
			if($column['link'])
			{
				$this->setExternalLink($column['link']);
			}
			else
			{
				$this->setOrderLink($column["sort_field"], $order_dir);
			}
			$this->tpl->parseCurrentBlock();
			$this->tpl->touchBlock("tbl_header_th");
		}

		$this->tpl->setCurrentBlock("tbl_header");
		$this->tpl->parseCurrentBlock();
	}

	/**
	 * @param string $link
	 */
	public function setExternalLink($link)
	{
		$this->tpl->setVariable('TBL_ORDER_LINK', $link);
	}
	
	/**
	 * overwrite this method for ungregging the object data structures
	 * since ilias tables support arrays only
	 * 
	 * @param mixed $data
	 * @return array
	 */
	protected function buildTableRowsArray($data)
	{
		$rows = array();
		
		foreach($data as $member)
		{
			$rows[] = array(
				'member_id' => $member->getId(),
				'member_fullname' => $member->getFullName()
			);
		}
		
		return $rows;
	}
	
	protected function buildMemberResultLinkTarget($refId, $activeId)
	{
		global $ilCtrl;

		$link = $ilCtrl->getLinkTargetByClass(
			array('ilObjTestOverviewGUI', 'ilobjtestgui', 'iltestevaluationgui'), 'outParticipantsPassDetails'
		);
		
		$link = ilUtil::appendUrlParameterString($link, "ref_id=$refId");
		$link = ilUtil::appendUrlParameterString($link, "active_id=$activeId");
		
		return $link;
	}

	/**
	 * @return mixed
	 */
	public function getExportHeaderData()
	{
		return $this->export_header_data;
	}

	/**
	 * @return mixed
	 */
	public function getExportRowData()
	{
		return $this->export_row_data;
	}
}

