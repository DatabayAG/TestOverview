<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

/**
 *	@package	TestOverview repository plugin
 *	@category	GUI
 *	@author		Greg Saive <gsaive@databay.de>
 */
class ilTestOverviewTableGUI extends ilMappedTableGUI
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
    public function __construct(ilObjectGUI $a_parent_obj, $a_parent_cmd)
    {
        /**
         *	@var ilCtrl	$ilCtrl
         */
        global $ilCtrl, $tpl, $ilAccess;

        /* Pre-configure table */
        $this->setId(sprintf(
            "test_overview_%d",
            $a_parent_obj->getObject()->getId()
        ));

        $this->sortable_fields = array(
            'firstname',
            'lastname',
            'login',
        );

        $this->setDefaultOrderDirection('ASC');
        $this->setDefaultOrderField('lastname');

        // ext ordering with db is ok, but ext limiting with db is not possible,
        // since the rbac filtering is downstream to the db query
        $this->setExternalSorting(true);
        $this->setExternalSegmentation(false);

        parent::__construct($a_parent_obj, $a_parent_cmd);

        $this->setTitle(sprintf(
            $this->lng->txt('rep_robj_xtov_test_overview_table_title'),
            $a_parent_obj->getObject()->getTitle()
        ));

        $this->overview = $this->getParentObject()->getObject();

        $this->addColumn($this->lng->txt('rep_robj_xtov_test_overview_hdr_firstname'), 'firstname');
        $this->addColumn($this->lng->txt('rep_robj_xtov_test_overview_hdr_lastname'), 'lastname');
        $this->addColumn($this->lng->txt('rep_robj_xtov_test_overview_hdr_login'), 'login');
        $this->export_header_data[] = $this->lng->txt('rep_robj_xtov_test_overview_hdr_firstname');
        $this->export_header_data[] = $this->lng->txt('rep_robj_xtov_test_overview_hdr_lastname');
        $this->export_header_data[] = $this->lng->txt('rep_robj_xtov_test_overview_hdr_login');

        foreach($this->overview->getUniqueTests() as $obj_id => $refs) {
            // Set default permissions based on statistics or write access
            $this->accessIndex[$obj_id] = false;
            $this->readIndex[$obj_id] = false;
            $valid_ref_id = null;
            $shows_all_users = false;
            foreach($refs as $ref_id) {
                switch(true) {
                    case $ilAccess->checkAccess("tst_statistics", "", (int) $ref_id):
                    case $ilAccess->checkAccess("write", "", (int) $ref_id):
                        $valid_ref_id =(int) $ref_id;
                        $this->accessIndex[$obj_id] = $valid_ref_id;
                        break 2;
                    case $ilAccess->checkAccess("read", "", (int)$ref_id):
                        $valid_ref_id = (int) $ref_id;
                        $this->readIndex[$obj_id] = $valid_ref_id;
                        break 2;
                }
            }

            $title_text = $this->overview->getTest($obj_id)->getTitle();
            /** @var ilObjTest $test_object */
            $test_object = $this->overview->getTest($obj_id);
            $evaluation = $test_object->getCompleteEvaluationData(false);
            $participants = $evaluation->getParticipants();
            if(count($participants)) {
                /** @var ilTestEvaluationUserData $participant */
                $participant = current($participants);
                $this->full_max = $this->full_max + $participant->getMaxpoints();
            }

            if($this->overview->getPointsColumn() && $this->overview->getHeaderPoints()) {
                if(count($participants)) {
                    /** @var ilTestEvaluationUserData $participant */
                    $participant = current($participants);
                    $title_text .= ' (' . $participant->getMaxpoints() . ' ' . $this->lng->txt('points') . ')';
                } else {
                    $title_text .= ' (? ' . $this->lng->txt('points') . ')';
                }
            }

            $ilCtrl->setParameterByClass("ilobjtestgui", 'ref_id', $valid_ref_id);
            $this->addTestColumn($title_text, $ilCtrl->getLinkTargetByClass('ilobjtestgui', 'infoScreen'));
            $this->export_header_data[] = $title_text;
            $ilCtrl->setParameterByClass("ilobjtestgui", 'ref_id', '');
            $this->overview->gatherTestData($this->overview->getTest($obj_id), $this->evalDataByTestId);
        }

        $this->setupEvaluationColumns();

        //$plugin = ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview');
        $this->setRowTemplate('tpl.test_overview_row.html', 'Customizing/global/plugins/Services/Repository/RepositoryObject/TestOverview');
        $this->setDescription($this->lng->txt("rep_robj_xtov_test_overview_description"));

        $cssFile = "Customizing/global/plugins/Services/Repository/RepositoryObject/TestOverview/templates/css/testoverview.css";
        $tpl->addCss($cssFile);

        /* Configure table filter */
        $this->initFilter();
        $this->setFilterCommand("applyOverviewFilter");
        $this->setResetCommand("resetOverviewFilter");

        $this->setFormAction($ilCtrl->getFormAction($this->getParentObject(), 'showContent'));

        if($this->overview->getEnableExcel()) {
            $button = ilLinkButton::getInstance();
            $button->setCaption('tbl_export_excel');
            $button->setUrl($ilCtrl->getLinkTarget($this->parent_obj, 'exportExcel'));
            /** @var ilToolbarGUI $ilToolbar */
            global $ilToolbar;
            $ilToolbar->items = [];
            $ilToolbar->addButtonInstance($button);
        }
    }

    /**
     * Execute command.
     */
    public function executeCommand(): bool
    {
        $ilCtrl = $this->ctrl;

        $next_class = $ilCtrl->getNextClass($this);
        $cmd = $ilCtrl->getCmd();

        switch($next_class) {
            case 'ilformpropertydispatchgui':
                include_once './Services/Form/classes/class.ilFormPropertyDispatchGUI.php';
                $form_prop_dispatch = new ilFormPropertyDispatchGUI();
                $this->initFilter();
                $item = $this->getFilterItemByPostVar($this->request->getQueryParams()['postvar']);
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
    public function initFilter(): void
    {

        /* Configure participant name filter (input[type=text]) */
        $pname = new ilTextInputGUI($this->lng->txt('rep_robj_xtov_overview_flt_participant_name'), 'flt_participant_name');
        $pname->setSubmitFormOnEnter(true);

        /* Configure participant group name filter (select) */
        $mapper = new ilOverviewMapper();
        $groups = $mapper->getGroupPairs((int)$this->getParentObject()->getObject()->getId());
        $groups = array("" => "-- Select --") + $groups;

        $gname = new ilSelectInputGUI($this->lng->txt("rep_robj_xtov_overview_flt_group_name"), 'flt_group_name');
        $gname->setOptions($groups);

        $own = new ilCheckboxInputGUI($this->lng->txt("rep_robj_xtov_overview_flt_own"), 'flt_own');

        /* Configure filter form */
        $this->addFilterItem($pname);
        $this->addFilterItem($gname);
        $this->addFilterItem($own);
        $pname->readFromSession();
        $gname->readFromSession();
        $own->readFromSession();
        $this->filter['flt_participant_name'] = $pname->getValue();
        $this->filter['flt_group_name']		  = $gname->getValue();
        $this->filter['flt_own']		  = $own->getChecked();
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
    protected function fillRow($row): void
    {
        $overview = $this->getParentObject()->getObject();

        $results = array();

        $max_points = 0;
        $reached_points = 0;
        foreach ($overview->getUniqueTests() as $obj_id => $refs) {
            $test = $overview->getTest($obj_id);
            $activeId  = $test->getActiveIdOfUser($row['member_id']);
            if($activeId === null) {
                $this->populateNoLinkCell(
                    '&nbsp;',
                    $this->getCSSByTestResult(null)
                );
                $this->tpl->setCurrentBlock('cell');
                $this->tpl->parseCurrentBlock();
            } else {
                $testResult = null;
                global $ilUser;
                $testResult = $test->getTestResult($activeId);
                if ($this->accessIndex[$obj_id] || ($this->readIndex[$obj_id] && $ilUser->getId() == $row['member_id'])) {
                    if ($testResult !== [] && strlen($testResult['pass']['percent'])) {
                        $max_points = $max_points + $testResult['pass']['total_max_points'];
                        $reached_points = $reached_points + $testResult['pass']['total_reached_points'];
                        if ($this->parent_obj->getObject()->getResultPresentation() == ilObjTestOverview::PRESENTATION_PERCENTAGE) {
                            $result = sprintf("%.2f %%", (float) $testResult['pass']['percent'] * 100);
                        } else {
                            if ($this->overview->getPointsColumn() && $this->overview->getHeaderPoints()) {
                                $result = $testResult['pass']['total_reached_points'];
                            } else {
                                $result = $testResult['pass']['total_reached_points'] . ' / ' . $testResult['pass']['total_max_points'];
                            }
                        }
                        $results[] = $result . '##' . $this->getCSSByTestResult($testResult, $activeId, $obj_id) . '##';
                    } else {
                        $result = $this->lng->txt("rep_robj_xtov_overview_test_not_passed");
                        $results[] = 0 . "##no-result##";
                    }

                    if ($activeId > 0) {
                        $resultLink = $this->buildMemberResultLinkTarget($this->accessIndex[$obj_id], $activeId);
                        $this->populateLinkedCell($resultLink, $result,
                            $this->getCSSByTestResult($testResult, $activeId, $obj_id));
                    } else {
                        $this->populateNoLinkCell(
                            $result,
                            $this->getCSSByTestResult(null)
                        );
                    }

                } else {
                    $this->populateNoLinkCell(
                        $this->lng->txt("rep_robj_xtov_overview_test_no_permission"),
                        $this->getCSSByTestResult(null)
                    );
                }
            }
            $this->tpl->setCurrentBlock('cell');
            $this->tpl->parseCurrentBlock();
        }

        $this->populateEvaluationColumns($results, $reached_points, $max_points);

        $row_data = array();
        $user = new ilObjUser($row['member_id']);
        $row_data[] = $user->getFirstname();
        $row_data[] = $user->getLastname();
        $row_data[] = $user->getLogin();
        foreach($results as $item) {
            $row_data[] = $item;
        }
        foreach($this->temp_results as $item) {
            $row_data[] = $item;
        }
        $this->export_row_data[] = $row_data;
        $this->temp_results = array();

        $this->tpl->setVariable('TEST_PARTICIPANT_FIRSTNAME', $user->getFirstname());
        $this->tpl->setVariable('TEST_PARTICIPANT_LASTNAME', $user->getLastname());
        $this->tpl->setVariable('TEST_LOGIN', $user->getLogin());
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
     */
    protected function formatData(array $data): array
    {
        /* For each group object we fetched, we need
           to retrieve the members in order to have
           a list of Participant. */
        $formatted = array(
            'items' => array(),
            'cnt'	=> 0);

        if(!$data['items']) {
            $formatted = $this->getMapper()->getUniqueTestParticipants(array_keys($this->accessIndex));
            $formatted['items'] = $this->fetchUserInformation($formatted['items']);
            return $this->sortByFullName($formatted);
        }

        foreach ($data['items'] as $item) {
            $container = ilObjectFactory::getInstanceByObjId((int)$item->obj_id, false);

            if ($container === false) {
                throw new OutOfRangeException();
            } elseif (! empty($this->filter['flt_group_name'])
                    && $container->getId() != $this->filter['flt_group_name']) {
                /* Filter current group */
                continue;
            }

            $participants = $this->getMembersObject($item);
            /* Fetch member object by ID to avoid one-per-row
               SQL queries. */
            foreach ($participants->getMembers() as $usrId) {
                $formatted['items'][$usrId] = $usrId;
            }
        }

        $formatted['items'] = $this->fetchUserInformation($formatted['items']);

        return $this->sortByFullName($formatted);
    }

    private function fetchUserInformation($usr_ids): array
    {
        global $ilDB, $ilUser;

        $usr_id__IN__usrIds = $ilDB->in('usr_id', $usr_ids, false, 'integer');

        $query = "
			SELECT usr_id, login, title, firstname, lastname FROM usr_data WHERE $usr_id__IN__usrIds
		";

        $res = $ilDB->query($query);

        $users = array();

        while($row = $ilDB->fetchAssoc($res)) {
            $user = new ilObjUser();

            $user->setId((int)$row['usr_id']);
            $user->setLogin((string) $row['login']);
            $user->setUTitle((string) $row['title']);
            $user->setFirstname((string) $row['firstname']);
            $user->setLastname((string) $row['lastname']);
            $user->setFullname();

            if (! empty($this->filter['flt_participant_name'])) {
                $name   = strtolower($user->getFullName());
                $filter = strtolower($this->filter['flt_participant_name']);

                /* Simulate MySQL LIKE operator */
                if (strpos($name, $filter) === false) {
                    /* User should be skipped. (Does not match filter) */
                    continue;
                }
            }

            if(!empty($this->filter['flt_own']) && $this->filter['flt_own'] == true && $user->getId() != $ilUser->getId()) {
                continue;
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

        $row = $this->evalDataByTestId[$testObjId][$activeId] ?? null;
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

        if(!$is_finished && !$passed) {
            $status = $this->determineLpStatus($passed);
        } else if (!$is_finished && $passed) {
            $status = 'green-result';
        } else if ($is_finished && !$passed) {
            $status = 'red-result';
        } else if ($is_finished && $passed) {
            $status = 'green-result';
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

        if($passed) {
            $status = 'green-result';
        }

        return $status;
    }

    public function setupEvaluationColumns()
    {
        if($this->overview->getResultColumn()) {
            if ($this->overview->getResultPresentation() == ilObjTestOverview::PRESENTATION_PERCENTAGE) {
                $this->addColumn($this->lng->txt('rep_robj_xtov_test_overview_hdr_avg'), 'avg');
                $this->export_header_data[] = $this->lng->txt('rep_robj_xtov_test_overview_hdr_avg');
            } else {
                $this->addColumn($this->lng->txt('rep_robj_xtov_test_overview_hdr_sum'), 'sum');
                $this->export_header_data[] = $this->lng->txt('rep_robj_xtov_test_overview_hdr_sum');
            }
        }

        if($this->overview->getPointsColumn()) {
            $points = "";
            if($this->full_max > 0) {
                $points = " (" . $this->full_max . " " . $this->lng->txt('points') . ")";
            }
            $this->addColumn($this->lng->txt('rep_robj_xtov_test_overview_hdr_points') . $points, 'points');
            $this->export_header_data[] = $this->lng->txt('rep_robj_xtov_test_overview_hdr_points') . $points;
        }

        if($this->overview->getAverageColumn()) {
            $this->addColumn($this->lng->txt('rep_robj_xtov_test_overview_hdr_avg'), 'avg');
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
        if($this->overview->getResultColumn()) {
            $this->populateResultCell($results, $reached_points, $max_points);
        }

        if($this->overview->getPointsColumn()) {
            if (count($results)) {
                $points = sprintf("%.2f", array_sum($results));
            } else {
                $points = "";
            }

            $this->tpl->setCurrentBlock('points');
            $this->tpl->setVariable("POINTS_VALUE", $points);
            $this->temp_results[] = $points;
            $this->tpl->parseCurrentBlock();
        }

        if($this->overview->getAverageColumn()) {
            if (count($results)) {
                if($this->overview->getResultPresentation() == ilObjTestOverview::PRESENTATION_PERCENTAGE) {
                    if(array_sum($results) == 0) {
                        $points = '0.00 %';
                    } else {
                        $points = sprintf("%.2f %%", (array_sum($results) / count($results)));
                    }
                } else {
                    if($this->full_max === 0 || array_sum($results) === 0) {
                        $points = '0.00 %';
                    } else {
                        $points = sprintf("%.2f %%", (array_sum($results) / $this->full_max) * 100);
                    }
                }
            } else {
                $points = "0.00 %";
            }

            $this->tpl->setCurrentBlock('avg');
            $this->tpl->setVariable("AVG_VALUE", $points);
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
        if (count($results)) {
            if ($this->parent_obj->getObject()->getResultPresentation() == ilObjTestOverview::PRESENTATION_PERCENTAGE) {
                $average = sprintf("%.2f", (array_sum($results) / count($results)));
            } else {
                $average = $reached_points . ' / ' . $max_points;
            }
        } else {
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

    private function getCSSByProgress($progress)
    {
        $map = $this->buildCssClassByProgressMap();

        $progress = (string)$progress;

        foreach($map as $lpNum => $cssClass) {
            if($progress === (string)$lpNum) { // we need identical check !!
                return $cssClass;
            }
        }

        return 'no-perm-result';
    }

    public function buildCssClassByProgressMap()
    {
        if(defined('ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM')) {
            return array(
                ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM => 'no-result',
                ilLPStatus::LP_STATUS_IN_PROGRESS_NUM => 'orange-result',
                ilLPStatus::LP_STATUS_COMPLETED_NUM => 'green-result',
                ilLPStatus::LP_STATUS_FAILED_NUM => 'red-result'
            );
        }
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
    protected function sortByFullName(array $data)
    {
        // ...or others.
        $order_field = $this->getOrderField();
        $order_direction = $this->getOrderDirection();

        $azList = array();
        $sorted = array(
            'cnt'   => $data['cnt'],
            'items' => array());

        /* Initialize partition array. */
        for ($az = 'A'; $az <= 'Z'; $az++) {
            $azList[$az] = array();
        }

        if($order_direction === 'desc') {
            krsort($azList);
        }

        /* Partition data. */
        foreach ($data['items'] as $userObj) {
            if($order_field === 'lastname') {
                $name = $userObj->getLastname();
            } elseif ($order_field === 'firstname') {
                $name = $userObj->getFirstname();
            } else {
                $name = $userObj->getLogin();
            }

            $azList[strtoupper($name[0])][] = $userObj;
        }

        /* Group all results. */
        foreach ($azList as $az => $userList) {
            if (! empty($userList)) {
                $sorted['items'] = array_merge($sorted['items'], $userList);
            }
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
    public function fillHeader(): void
    {
        global $lng;

        $allcolumnswithwidth = true;
        foreach ((array) $this->column as $idx => $column) {
            if (!strlen($column["width"])) {
                $allcolumnswithwidth = false;
            } elseif($column["width"] == "1") {
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
                include_once("./Services/UIComponent/Tooltip/classes/class.ilTooltipGUI.php");
                ilTooltipGUI::addTooltip("thc_" . $this->getId() . "_" . $ccnt, $column["tooltip"]);
            }
            if ((!$this->enabled["sort"] || $column["sort_field"] == "" || $column["is_checkbox_action_column"]) && !array_key_exists('link',$column)) {
                $this->tpl->setCurrentBlock("tbl_header_no_link");
                if (isset($column['width']) && $column["width"] != "") {
                    $this->tpl->setVariable("TBL_COLUMN_WIDTH_NO_LINK", " width=\"" . $column["width"] . "\"");
                }
                $this->tpl->setVariable(
                    "TBL_HEADER_CELL_NO_LINK",
                    $column["text"]
                );
                $this->tpl->setVariable("HEAD_CELL_NL_ID", "thc_" . $this->getId() . "_" . $ccnt);

                if (isset($column['class']) && $column["class"] != "") {
                    $this->tpl->setVariable("TBL_HEADER_CLASS", " " . $column["class"]);
                }
                $this->tpl->parseCurrentBlock();
                $this->tpl->touchBlock("tbl_header_th");
                continue;
            }
            if (isset($column['sort_field']) && ($column["sort_field"] == $this->order_field) && ($this->order_direction != "")) {
                $this->tpl->setCurrentBlock("tbl_order_image");
                $this->tpl->setVariable("IMG_ORDER_DIR", ilUtil::getImagePath($this->order_direction . "_order.png"));
                $this->tpl->setVariable("IMG_ORDER_ALT", $this->lng->txt("change_sort_direction"));
                $this->tpl->parseCurrentBlock();
            }

            $this->tpl->setCurrentBlock("tbl_header_cell");
            $this->tpl->setVariable("TBL_HEADER_CELL", $column["text"]);
            $this->tpl->setVariable("HEAD_CELL_ID", "thc_" . $this->getId() . "_" . $ccnt);

            // only set width if a value is given for that column
            if (isset($column["width"]) && $column["width"] != "") {
                $this->tpl->setVariable("TBL_COLUMN_WIDTH", " width=\"" . $column["width"] . "\"");
            }

            $lng_sort_column = $this->lng->txt("sort_by_this_column");
            $this->tpl->setVariable("TBL_ORDER_ALT", $lng_sort_column);

            $order_dir = "asc";

            if (isset($colum['sort_field']) && $column["sort_field"] == $this->order_field) {
                $order_dir = $this->sort_order;

                $lng_change_sort = $this->lng->txt("change_sort_direction");
                $this->tpl->setVariable("TBL_ORDER_ALT", $lng_change_sort);
            }

            if (isset($column["class"]) && $column['class'] != "") {
                $this->tpl->setVariable("TBL_HEADER_CLASS", " " . $column["class"]);
            }
            if(isset($column['link'])) {
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
     * @param string $link
     */
    public function setExternalLink($link)
    {
        $this->tpl->setVariable('TBL_ORDER_LINK', $link);
    }

    protected function buildTableRowsArray($data): array
    {
        $rows = array();

        foreach($data as $member) {
            $rows[] = array(
                'member_id' => $member->getId(),
                'member_firstname' => $member->getFirstname(),
                'member_lastname' => $member->getLastname()
            );
        }

        return $rows;
    }

    protected function buildMemberResultLinkTarget($refId, $activeId): string
    {
        global $ilCtrl;

        $link = $ilCtrl->getLinkTargetByClass(
            array('ilobjtestgui', 'iltestevaluationgui'),
            'outParticipantsPassDetails'
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

    /**
     *    Populate the TableGUI using the Mapper.
     *
     *    The populate() method should be called
     *    to fill the overview table with data.
     *    The getList() method is called on the
     *    registered mapper instance. The formatData()
     *    method should be overloaded to handle specific
     *    cases of displaying or ordering rows.
     *
     * @throws ilException
     */
    public function populate(): ilMappedTableGUI
    {
        if($this->getExternalSegmentation() && $this->getExternalSorting()) {
            $this->determineOffsetAndOrder();
        } elseif(!$this->getExternalSegmentation() && $this->getExternalSorting()) {
            $this->determineOffsetAndOrder(true);
        } else {
            throw new ilException('invalid table configuration: extSort=false / extSegm=true');
        }

        /* Configure query execution */
        $params = array();
        if($this->getExternalSegmentation()) {
            $params['limit'] = $this->getLimit();
            $params['offset'] = $this->getOffset();
        }
        if($this->getExternalSorting()) {
            $params['order_field'] = $this->getOrderField();
            $params['order_direction'] = $this->getOrderDirection();
        }

        $overview = $this->getParentObject()->getObject();
        $filters  = array("overview_id" => $overview->getId()) + $this->filter;

        /* Execute query. */
        $data = $this->getMapper()->getList($params, $filters);

        if(!count($data['items']) && $this->getOffset() > 0) {
            /* Query again, offset was incorrect. */
            $this->resetOffset();
            $data = $this->getMapper()->getList($params, $filters);
        }

        /* Post-query logic. Implement custom sorting or display
           in formatData overload. */
        $data = $this->formatData($data);

        $this->setData($this->buildTableRowsArray($data['items']));

        if($this->getExternalSegmentation()) {
            $this->setMaxCount($data['cnt']);
        }

        return $this;
    }
}
