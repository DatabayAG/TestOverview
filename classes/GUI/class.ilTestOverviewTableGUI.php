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
	
	/**
	 *	 @var	array
	 */
	protected $filter = array();

	/**
	 * @var array
	 */
	protected $linked_tst_column_targets = array();

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

		$overview = $this->getParentObject()->object;

		$this->addColumn($this->lng->txt('rep_robj_xtov_test_overview_hdr_user'));
		
		foreach( $overview->getUniqueTests() as $obj_id => $refs )
		{
			$this->accessIndex[$obj_id] = false;
			$valid_ref_id = null;
			foreach( $refs as $ref_id )
			{
				switch( true )
				{
					case $ilAccess->checkAccess("tst_statistics", "", $ref_id):
					case $ilAccess->checkAccess("write", "", $ref_id):
						$valid_ref_id = $ref_id; 
						$this->accessIndex[$obj_id] = $valid_ref_id;
						break 2;
				}
			}
			$ilCtrl->setParameterByClass("ilobjtestgui", 'ref_id', $valid_ref_id);
			$this->addTestColumn( $overview->getTest($obj_id)->getTitle(), $ilCtrl->getLinkTargetByClass('ilobjtestgui', 'infoScreen'));
			$ilCtrl->setParameterByClass("ilobjtestgui", 'ref_id', '');
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

		foreach ($overview->getUniqueTests() as $obj_id => $refs)
		{
			$test = $overview->getTest($obj_id);
			$activeId  = $test->getActiveIdOfUser($row['member_id']);

			$result = $progress = null;
						
			if( $this->accessIndex[$obj_id] )
			{
				$result    = $test->getTestResult($activeId);

				$lpStatus = new ilLPStatus( $test->getId() );
				$progress = $lpStatus->_lookupStatus($test->getId(), $row['member_id']);

				if ((bool) $progress)
				{
					$result		= sprintf("%.2f %%", (float) $result['pass']['percent'] * 100);
					
					$results[]  = $result;
				}
				else
				{
					$result = $this->lng->txt("rep_robj_xtov_overview_test_not_passed");
					
					$results[]  = 0;
				}
				
				if( $activeId > 0 )
				{
					$resultLink = $this->buildMemberResultLinkTarget($this->accessIndex[$obj_id], $activeId);

					$this->populateLinkedCell($resultLink, $result, $this->getCSSByProgress($progress));
				}
				else
				{
					$this->populateNoLinkCell(
						$result, $this->getCSSByProgress($progress)
					);
				}
			}
			else
			{
				$this->populateNoLinkCell(
					$this->lng->txt("rep_robj_xtov_overview_test_no_permission"), $this->getCSSByProgress($progress)
				);
			}
		}

		if (count($results))
		{
			$average = sprintf("%.2f", (array_sum($results) / count($results)));
		}
		else
		{
			$average = "";
		}
		
		$this->tpl->setVariable( "AVERAGE_CLASS", "");
		$this->tpl->setVariable( "AVERAGE_VALUE", $average . (is_numeric($average) ? "%" : ""));
		
		$this->tpl->setVariable('TEST_PARTICIPANT', $row['member_fullname']);
    }
	
	private function populateLinkedCell($resultLink, $resultValue, $cssClass)
	{
		$this->tpl->setCurrentBlock('result_cell');
		$this->tpl->setVariable('RESULT_LINK', $resultLink);
		$this->tpl->setVariable('RESULT_VALUE', $resultValue);				
		$this->tpl->setVariable('RESULT_CSSCLASS', $cssClass);
		$this->tpl->parseCurrentBlock();
	}
	
	private function populateNoLinkCell($resultValue, $cssClass)
	{
		$this->tpl->setCurrentBlock('result_cell_nolink');
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
}

