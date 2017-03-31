<?php
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 *  @package	TestOverview repository plugin
 *	@category	Core
 *	@author		Martin Dinkel <hmdinkel@web.de>
 * 
 *	Exercise Overview Gui
 */
class ilExerciseOverviewTableGUI extends ilMappedTableGUI {

	private $accessIndex = array();

	/**
	 * 	 @var	array
	 */
	protected $filter = array();

	/**
	 * @var array
	 */
	protected $linked_tst_column_targets = array();

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
		global $ilCtrl, $tpl, $ilAccess, $lng;
		$lng->loadLanguageModule("administration");

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

		$this->setTitle("ExerciseOverview");

		$overview = $this->getParentObject()->object;


		$this->addColumn($this->lng->txt('rep_robj_xtov_test_overview_hdr_user'));

		//get Object_reference mapper from Exercise Overview
		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/mapper/class.ilExerciseMapper.php';
		$excMapper = new ilExerciseMapper();

		$dataArray = $excMapper->getUniqueExerciseId($overview->getID());
		for ($index = 0; $index < count($dataArray); $index++) {
			$obj_id = $dataArray[$index];
			$refId = $this->getRefId($obj_id);
			$this->addColumn("<a href='ilias.php?baseClass=ilExerciseHandlerGUI&ref_id=$refId&cmd=showOverview'>" . $excMapper->getExerciseName($obj_id) . "</a>");
		}
		$this->lng->loadLanguageModule("trac");
		$this->addColumn($this->lng->txt('stats_summation'));
		$plugin = ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview');
		$this->setRowTemplate('tpl.test_overview_row.html', $plugin->getDirectory());
		$this->setDescription($this->lng->txt("rep_robj_xtov_test_overview_description"));

		$cssFile = $plugin->getDirectory() . "/templates/css/testoverview.css";
		$tpl->addCss($cssFile);

		/* Configure table filter */
		$this->initFilter();
		$this->setFilterCommand("applyExerciseFilterRanking");
		$this->setResetCommand("resetExerciseFilterRanking");

		$this->setFormAction($ilCtrl->getFormAction($this->getParentObject(), 'subTabEoRanking'));
	}

	/**
	 * 	Initialize the table filters.
	 *
	 * 	This method is called internally to initialize
	 * 	the filters from present on the top of the table.
	 */
	public function initFilter() {

		include_once 'Services/Form/classes/class.ilTextInputGUI.php';
		include_once 'Services/Form/classes/class.ilSelectInputGUI.php';
		include_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . "/classes/mapper/class.ilOverviewMapper.php";

		/* Configure participant name filter (input[type=text]) */
		$pname = new ilTextInputGUI($this->lng->txt('rep_robj_xtov_overview_flt_participant_name'), 'flt_participant_name');
		$pname->setSubmitFormOnEnter(true);
		$pgender = new ilSelectInputGUI("Gender", 'flt_participant_gender');

		$genderArray = array("" => "-- Select --", "f" => "female", "m" => "male");
		$pgender->setOptions($genderArray);

		/* Configure participant group name filter (select) */
		$mapper = new ilOverviewMapper();
		$groups = $mapper->getGroupPairs($this->getParentObject()->object->getId());
		$groups = array("" => "-- Select --") + $groups;

		$gname = new ilSelectInputGUI($this->lng->txt("rep_robj_xtov_overview_flt_group_name"), 'flt_group_name');
		$gname->setOptions($groups);


		/* Configure filter form */
		$this->addFilterItem($pname);

		$this->addFilterItem($gname);

		$this->addFilterItem($pgender);
		$pgender->readFromSession();
		$pname->readFromSession();

		$gname->readFromSession();

		$this->filter['flt_participant_gender'] = $pgender->getValue();
		$this->filter['flt_participant_name'] = $pname->getValue();
		$stringN = $pname->getValue();
		$this->filter['flt_group_name'] = $gname->getValue();
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
	protected function fillRow($row) {
		$overview = $this->getParentObject()->object;
		$dataArray = $this->getMapper()->getUniqueExerciseId($overview->getID());
		$results = array();
		$rowID = $row['member_id'];
		$flagIsNumeric = true;
		for ($index = 0; $index < count($dataArray); $index++) {
			$obj_id = $dataArray[$index];
			$this->getMapper()->getExerciseName($obj_id);
			$DbObject = $this->getMapper()->getArrayofObjects($overview->getID());
			$mark = $this->getMapper()->getMark($row['member_id'], $obj_id, $DbObject);
			$result = $mark;
			$memID = $obj_id;

			if (!is_numeric($result) == 1 && !empty($result)) {
				$flagIsNumeric = false;
			}

			$results[] = $result;
			$progress = '2';
			$state = $this->isPassed($obj_id, $row['member_id']);
			/*
			 * Colors the results if they are graded 
			 */
			if ($state == "passed") {
				$this->populateNoLinkCell($mark, "green-result");
			} else if ($state == "failed") {
				$this->populateNoLinkCell($mark, "red-result");
			} else {
				$this->populateNoLinkCell($mark, "no-prem-result");
			}

			$this->tpl->setCurrentBlock('cell');
			$this->tpl->parseCurrentBlock();
		}

		if (count($results)) {
			$average = array_sum($results);
		} else {
			$average = "";
		}
		if (!$flagIsNumeric) {
			$average = $this->lng->txt('rep_robj_xtov_notAvaiable');
		}

		$this->tpl->setVariable("AVERAGE_CLASS", "");
		$this->tpl->setVariable("AVERAGE_VALUE", $average);
		$this->tpl->setVariable('TEST_PARTICIPANT', $row['member_fullname']);
	}


	private function populateNoLinkCell($resultValue, $cssClass) {
		$this->tpl->setCurrentBlock('result_nolink');
		$this->tpl->setVariable('RESULT_VALUE_NOLINK', $resultValue);
		$this->tpl->setVariable('RESULT_CSSCLASS_NOLINK', $cssClass);
		$this->tpl->parseCurrentBlock();
	}

	
	public function buildCssClassByProgressMap() {
		if (defined('ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM')) {
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
	protected function formatData(array $data, $b = false) {
		/* For each group object we fetched, we need
		  to retrieve the members in order to have
		  a list of Participant. */
		$formatted = array(
			'items' => array(),
			'cnt' => 0);


		$index = 0;
		$count = $data['cnt'];

		if (!$data['items']) {
			$overview = $this->getParentObject()->object;
			$uniqueUsers = $this->getMapper()->getUniqueUserId($overview->getID());

			foreach ($uniqueUsers as $user) {
				$formatted['items'][$user] = $user;
			}
		}
		foreach ($data['items'] as $item) {


			$container = ilObjectFactory::getInstanceByObjId($item->obj_id, false);

			if ($container === false)
				throw new OutOfRangeException;
			elseif (!empty($this->filter['flt_group_name']) && $container->getId() != $this->filter['flt_group_name'])
			/* Filter current group */
				continue;

			$participants = $this->getMembersObject($item);
			/* Fetch member object by ID to avoid one-per-row
			  SQL queries. */
			foreach ($participants->getMembers() as $usrId) {
				$formatted['items'][$usrId] = $usrId;
			}
		}
		$formatted['items'] = $this->fetchUserInformation($formatted['items']);

		if ($b == false) {
			return $this->sortByAveragePoints($formatted);
		} else {
			return $this->sortByFullName($formatted);
		}
	}

	/**
	* This method fetchs User Information and is used to filter the UserIds
	* 
	* @global type $ilDB
	* @global type $tpl
	* @param type $usr_ids
	* @return \ilObjUser
	*/
	public function fetchUserInformation($usr_ids) {
		global $ilDB, $tpl;

		$usr_id__IN__usrIds = $ilDB->in('usr_id', $usr_ids, false, 'integer');

		$query = "
			SELECT usr_id, title, firstname, lastname, gender FROM usr_data WHERE $usr_id__IN__usrIds
		";

		$res = $ilDB->query($query);

		$users = array();

		while ($row = $ilDB->fetchAssoc($res)) {
			$user = new ilObjUser();
			$user->setId($row['usr_id']);
			$user->setUTitle($row['title']);
			$user->setFirstname($row['firstname']);
			$user->setLastname($row['lastname']);
			$user->setFullname();
			$user->setGender($row['gender']);

			if (!empty($this->filter['flt_participant_name'])) {
				$name = strtolower($user->getFullName());
				$filter = strtolower($this->filter['flt_participant_name']);
				/* Simulate MySQL LIKE operator */
				if (false === strstr($name, $filter)) {
					$ausgabe = strstr($name, $filter);
					/* User should be skipped. (Does not match filter) */
					continue;
				}
			}
			if (!empty($this->filter['flt_participant_gender'])) {

				$gender = $user->getGender();
				$filterGender = $this->filter['flt_participant_gender'];


				/* Simulate MySQL LIKE operator */
				if (false === strstr($gender, $filterGender)) {
					/* User should be skipped. (Does not match filter) */
					continue;
				}
			}
			$users[$row['usr_id']] = $user;
		}
		$test = count($users);
		return $users;
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
	protected function sortByFullName(array $data) {
		$azList = array();
		$sorted = array(
			'cnt' => $data['cnt'],
			'items' => array());

		/* Initialize partition array. */
		for ($az = 'A'; $az <= 'Z'; $az++)
			$azList[$az] = array();

		/* Partition data. */
		foreach ($data['items'] as $userObj) {
			$name = $userObj->getFullName();
			$name = strtoupper($name);
			$azList[$name{0}][] = $userObj;
		}

		/* Group all results. */
		foreach ($azList as $az => $userList) {
			if (!empty($userList))
				$sorted['items'] = array_merge($sorted['items'], $userList);
		}

		return $sorted;
	}

	/**
	 *    Sort the array of users by their average points.
	 *
	 *    This method had to be implemented in order to sort
	 *    the listed users by their average points. The overview
	 *    settings allows selecting participant groups rather
	 *    than users. This means the data fetched according
	 *    to a test overview, is the participant group's data.
	 *
	 * @params    array    $data    Array with 'cnt' & 'items' indexes.
	 *
	 * @param array $data
	 * @return array
	 */
	protected function sortByAveragePoints(array $data) {
		global $ilDB, $tpl;
		$overviewMapper = $this->getMapper();
		// array which contains the user information
		$rankList = array();
		// array which contains the sum of points
		$sumList = array();
		$sorted = array(
			'cnt' => $data['cnt'],
			'items' => array());

		/* Initialize partition array. */
		for ($rank = '1'; $rank <= count($data['items']); $rank++) {
			$rankList[$rank] = array();
			$sumList[$rank] = array();
		}

		$studentIndex = 1;
		/* Partition data. */
		foreach ($data['items'] as $userObj) {

			$stdID = $userObj->getId();
			$overview = $this->getParentObject()->object;
			$dataArray = $this->getMapper()->getUniqueExerciseId($overview->getID());
			$results = array();

			for ($index = 0; $index < count($dataArray); $index++) {
				$obj_id = $dataArray[$index];
				$this->getMapper()->getExerciseName($obj_id);
				$DbObject = $this->getMapper()->getArrayofObjects($overview->getID());
				$mark = $this->getMapper()->getMark($stdID, $obj_id, $DbObject);

				$result = $mark;
				$results[] = $result;
			}
			$sum = array_sum($results);
			$rankList[$studentIndex][] = $userObj;
			$sumList[$studentIndex][] = $sum;
			$studentIndex++;
		}
		asort($sumList);
		$arraySorted = array_keys($sumList);
		/* Group all results. */
		for ($i = '1'; $i <= count($rankList); $i++) {
			$position = array_pop($arraySorted);
			$userList = $rankList[$position];
			if (!empty($userList)) {
				$sorted['items'] = array_merge($sorted['items'], $userList);
			}
		}
		return $sorted;
	}

	/**
	 * Function to rank all students and save their result in the database
	 * @throws ilException
	 */
	public function getStudentsRanked() {
		if ($this->getExternalSegmentation() && $this->getExternalSorting()) {
			$this->determineOffsetAndOrder();
		} elseif (!$this->getExternalSegmentation() && $this->getExternalSorting()) {
			$this->determineOffsetAndOrder(true);
		} else {
			throw new ilException('invalid table configuration: extSort=false / extSegm=true');
		}

		/* Configure query execution */
		$params = array();
		if ($this->getExternalSegmentation()) {
			$params['limit'] = $this->getLimit();
			$params['offset'] = $this->getOffset();
		}
		if ($this->getExternalSorting()) {
			$params['order_field'] = $this->getOrderField();
			$params['order_direction'] = $this->getOrderDirection();
		}

		$overview = $this->getParentObject()->object;
		$filters = array("overview_id" => $overview->getId()) + $this->filter;

		/* Execute query. */
		$data = $this->getMapper()->getList($params, $filters);


		if (!count($data) && $this->getOffset() > 0) {
			/* Query again, offset was incorrect. */
			$this->resetOffset();
			$data = $this->getMapper()->getList($params, $filters);
		}

		/* Post-query logic. Implement custom sorting or display
		  in formatData overload. */
		$data = $this->formatData($data, FALSE);


		$this->getMapper()->resetRanks($this->getParentObject()->object->getID());
		foreach ($data['items'] as $userObj) {
			$stdID = $userObj->getId();
			$overview = $this->getParentObject()->object;
			$dataArray = $this->getMapper()->getUniqueExerciseId($overview->getID());
			$results = array();

			for ($index = 0; $index < count($dataArray); $index++) {
				$obj_id = $dataArray[$index];
				$this->getMapper()->getExerciseName($obj_id);
				$DbObject = $this->getMapper()->getArrayofObjects($overview->getID());
				$mark = $this->getMapper()->getMark($stdID, $obj_id, $DbObject);
				$result = $mark;
				$results[] = $result;
			}
			$average = array_sum($results);
			$ilMapper = $this->getMapper();
			$ilMapper->setData2Rank($average, $stdID, $this->getParentObject()->object->getId());
		}

		$this->getMapper()->createDate($this->getParentObject()->object->getId());
	}

	/**
	 * Fills the exercise names and the urls into the headrow
	 * @global type $lng
	 */
	public function fillHeader() {
		global $lng;

		$allcolumnswithwidth = true;
		foreach ((array) $this->column as $idx => $column) {
			if (!strlen($column["width"])) {
				$allcolumnswithwidth = false;
			} else if ($column["width"] == "1") {
				// IE does not like 1 but seems to work with 1%
				$this->column[$idx]["width"] = "1%";
			}
		}
		if ($allcolumnswithwidth) {
			foreach ((array) $this->column as $column) {
				$this->tpl->setCurrentBlock("tbl_colgroup_column");
				$this->tpl->setVariable("COLGROUP_COLUMN_WIDTH", $column["width"]);
				$this->tpl->parseCurrentBlock();
			}
		}
		$ccnt = 0;
		foreach ((array) $this->column as $column) {
			$ccnt++;

			//tooltip
			if ($column["tooltip"] != "") {
				include_once("./Services/integerUIComponent/Tooltip/classes/class.ilTooltipGUI.php");
				ilTooltipGUI::addTooltip("thc_" . $this->getId() . "_" . $ccnt, $column["tooltip"]);
			}
			if ((!$this->enabled["sort"] || $column["sort_field"] == "" || $column["is_checkbox_action_column"]) && !$column['link']) {
				$this->tpl->setCurrentBlock("tbl_header_no_link");
				if ($column["width"] != "") {
					$this->tpl->setVariable("TBL_COLUMN_WIDTH_NO_LINK", " width=\"" . $column["width"] . "\"");
				}
				if (!$column["is_checkbox_action_column"]) {
					$this->tpl->setVariable("TBL_HEADER_CELL_NO_LINK", $column["text"]);
				} else {
					$this->tpl->setVariable("TBL_HEADER_CELL_NO_LINK", ilUtil::img(ilUtil::getImagePath("spacer.png"), $lng->txt("action")));
				}
				$this->tpl->setVariable("HEAD_CELL_NL_ID", "thc_" . $this->getId() . "_" . $ccnt);

				if ($column["class"] != "") {
					$this->tpl->setVariable("TBL_HEADER_CLASS", " " . $column["class"]);
				}
				$this->tpl->parseCurrentBlock();
				$this->tpl->touchBlock("tbl_header_th");
				continue;
			}
			if (($column["sort_field"] == $this->order_field) && ($this->order_direction != "")) {
				$this->tpl->setCurrentBlock("tbl_order_image");
				$this->tpl->setVariable("IMG_ORDER_DIR", ilUtil::getImagePath($this->order_direction . "_order.png"));
				$this->tpl->setVariable("IMG_ORDER_ALT", $this->lng->txt("change_sort_direction"));
				$this->tpl->parseCurrentBlock();
			}

			$this->tpl->setCurrentBlock("tbl_header_cell");
			$this->tpl->setVariable("TBL_HEADER_CELL", $column["text"]);
			$this->tpl->setVariable("HEAD_CELL_ID", "thc_" . $this->getId() . "_" . $ccnt);

			// only set width if a value is given for that column
			if ($column["width"] != "") {
				$this->tpl->setVariable("TBL_COLUMN_WIDTH", " width=\"" . $column["width"] . "\"");
			}

			$lng_sort_column = $this->lng->txt("sort_by_this_column");
			$this->tpl->setVariable("TBL_ORDER_ALT", $lng_sort_column);

			$order_dir = "asc";

			if ($column["sort_field"] == $this->order_field) {
				$order_dir = $this->sort_order;

				$lng_change_sort = $this->lng->txt("change_sort_direction");
				$this->tpl->setVariable("TBL_ORDER_ALT", $lng_change_sort);
			}

			if ($column["class"] != "") {
				$this->tpl->setVariable("TBL_HEADER_CLASS", " " . $column["class"]);
			}
			if ($column['link']) {
				$this->setExternalLink($column['link']);
			} else {
				$this->setOrderLink($column["sort_field"], $order_dir);
			}
			$this->tpl->parseCurrentBlock();
			$this->tpl->touchBlock("tbl_header_th");
		}

		$this->tpl->setCurrentBlock("tbl_header");
		$this->tpl->parseCurrentBlock();
	}

	/**
	 * overwrite this method for ungregging the object data structures
	 * since ilias tables support arrays only
	 * 
	 * @param mixed $data
	 * @return array
	 */
	protected function buildTableRowsArray($data) {
		$rows = array();

		foreach ($data as $member) {
			if (!($member->getId() == null)) {
				$rows[] = array(
					'member_id' => $member->getId(),
					'member_fullname' => $member->getFullName()
				);
			} else {
				$rows[] = array(
					'member_id' => '0',
					'member_fullname' => $member->getFullName()
				);
			}
		}

		return $rows;
	}


	public function isPassed($objId, $usrId) {
		global $ilDB;

		$query = "SELECT exc_members.status FROM exc_members WHERE obj_id = %s AND usr_id = %s";
		$result = $ilDB->queryF($query, array('integer', 'integer'), array($objId,$usrId));
		$state = $ilDB->fetchObject($result);
		return $state->status;
	}

	public function getRefId($ObjId) {
		global $ilDB;
		$query = "SELECT ref_id FROM object_reference WHERE obj_id = %s";
		$result = $ilDB->queryF($query, array('integer'), array($ObjId));

		$record = $ilDB->fetchAssoc($result);

		return $record['ref_id'];
	}

}
