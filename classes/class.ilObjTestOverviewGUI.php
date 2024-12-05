<?php

declare(strict_types=1);

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
 *  @ilCtrl_isCalledBy ilObjTestOverviewGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
 *  @ilCtrl_Calls      ilObjTestOverviewGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilRepositorySearchGUI, ilPublicUserProfileGUI, ilCommonActionDispatcherGUI
 *  @ilCtrl_Calls      ilObjTestOverviewGUI: ilTestEvaluationGUI, ilMDEditorGUI
 */
class ilObjTestOverviewGUI extends ilObjectPluginGUI implements ilDesktopItemHandling
{
    private ilSetting $ilSetting;
    private ilObjUser $ilUser;

    /**
     *	@var ilPropertyFormGUI
     */
    protected $form;

    public function getType(): string
    {
        return 'xtov';
    }

    public function getAfterCreationCmd(): string
    {
        return 'showContent';
    }

    public function getStandardCmd(): string
    {
        return 'showContent';
    }

    public function __construct(int $a_ref_id = 0, int $a_id_type = self::REPOSITORY_NODE_ID, int $a_parent_node_id = 0)
    {
        global $DIC;
        $this->ilSetting = $DIC->settings();
        $this->ilUser = $DIC->user();
        $this->request = $DIC->http()->request();

        parent::__construct($a_ref_id, $a_id_type, $a_parent_node_id);
    }

    public function performCommand($cmd): void
    {
        $this->tpl->setDescription($this->object->getDescription());

        $next_class = $this->ctrl->getNextClass($this);
        switch($next_class) {
            case 'ilmdeditorgui':
                $this->checkPermission('write');
                $md_gui = new ilMDEditorGUI($this->object->getId(), 0, $this->object->getType());
                $md_gui->addObserver($this->object, 'MDUpdateListener', 'General');
                $this->tabs->setTabActive('meta_data');
                $this->ctrl->forwardCommand($md_gui);
                return;
                break;

            case 'ilcommonactiondispatchergui':
                $this->ctrl->forwardCommand(ilCommonActionDispatcherGUI::getInstanceFromAjaxCall());
                break;

            default:
                switch($cmd) {
                    case 'updateSettings':
                    case 'updateMemberships':
                    case 'initSelectTests':
                    case 'selectTests':
                    case 'performAddTests':
                    case 'removeTests':
                    case 'addMemberships':
                    case 'removeMemberships':
                    case 'editSettings':
                    case 'initCourseTests':
                    case 'saveOrder':
                        $this->checkPermission('write');
                        $this->$cmd();
                        break;

                    case 'showContent':
                    case 'applyOverviewFilter':
                    case 'applyTestsFilter':
                    case 'applyGroupsFilter':
                    case 'resetOverviewFilter':
                    case 'resetTestsFilter':
                    case 'resetGroupsFilter':
                    case 'addToDesk':
                    case 'removeFromDesk':
                    case 'exportExcel':
                        if(in_array($cmd, array('addToDesk', 'removeFromDesk'))) {
                            $cmd .= 'Object';
                        }
                        $this->checkPermission('read');
                        $this->$cmd();
                        break;
                }
                break;
        }

        $this->addHeaderAction();
    }

    protected function setTabs(): void
    {
        $this->addInfoTab();

        /* Check for read access (showContent available) */
        if ($this->access->checkAccess('read', '', $this->object->getRefId())) {
            $this->tabs->addTab('content', $this->txt('content'), $this->ctrl->getLinkTarget($this, 'showContent'));
        }

        /* Check for write access (editSettings available) */
        if ($this->access->checkAccess('write', '', $this->object->getRefId())) {
            $this->tabs->addTab('properties', $this->txt('properties'), $this->ctrl->getLinkTarget($this, 'editSettings'));
            $this->tabs->addTarget('meta_data', $this->ctrl->getLinkTargetByClass('ilmdeditorgui', ''), '', 'ilmdeditorgui');
        }

        $this->addPermissionTab();
    }

    protected function showContent(): void
    {
        $this->tabs->activateTab("content");

        /* Configure content UI */
        $table = new ilTestOverviewTableGUI($this, 'showContent');
        $table->setMapper(new ilOverviewMapper())->populate();

        $legend = new ilTemplate("tpl.legend.html", true, true, "./Customizing/global/plugins/Services/Repository/RepositoryObject/TestOverview");
        $legend->setVariable('TXT_HEADER', $this->lng->txt('rep_robj_xtov_legend'));
        $legend->setVariable('TXT_NOT_STARTED', $this->lng->txt('rep_robj_xtov_not_started'));
        $legend->setVariable('TXT_IN_PROGRESS', $this->lng->txt('rep_robj_xtov_in_progress'));
        $legend->setVariable('TXT_COMPLETED', $this->lng->txt('rep_robj_xtov_completed'));
        $legend->setVariable('TXT_FAILED', $this->lng->txt('rep_robj_xtov_failed'));
        $legend->parseCurrentBlock();
        /* Populate template */
        $this->tpl->setDescription($this->object->getDescription());
        $this->tpl->setContent($table->getHTML() . $legend->get());
    }

    protected function exportExcel(): void
    {
        /* Configure content UI */
        $table = new ilTestOverviewTableGUI($this, 'showContent');
        $table->setMapper(new ilOverviewMapper())->populate();

        $table->getHTML(); // No cooler way to do it, sorry.

        $exporter = new ilTestOverviewExcelExporter();
        $exporter->export('TestOverview', $table->getExportHeaderData(), $table->getExportRowData(), 'TestOverview', true);
    }

    protected function renderSettings(): string
    {
        return $this->form->getHTML()
             . "<hr />"
             . $this->getTestList()->getHTML()
             . "<hr />"
             . $this->getMembershipList()->getHTML();
    }

    protected function editSettings(): void
    {
        $this->tabs->activateTab('properties');
        $this->initSettingsForm();
        $this->populateSettings();
        $this->tpl->setContent($this->renderSettings());
    }

    protected function updateSettings(): void
    {
        $this->initSettingsForm();

        if ($this->form->checkInput()) {
            $this->object->setTitle($this->form->getInput('title'));
            $this->object->setDescription($this->form->getInput('desc'));
            $this->object->setResultPresentation($this->form->getInput('result_presentation'));
            $this->object->setResultColumn((bool) $this->form->getInput('result_column'));
            $this->object->setPointsColumn((bool) $this->form->getInput('points_column'));
            $this->object->setAverageColumn((bool) $this->form->getInput('average_column'));
            $this->object->setEnableExcel((bool) $this->form->getInput('enable_excel'));
            $this->object->setHeaderPoints((bool) $this->form->getInput('header_points'));
            $this->object->update();
            $this->tpl->setOnScreenMessage(ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS, $this->lng->txt('msg_obj_modified'), true);

            /* Back to editSettings */
            $this->ctrl->redirect($this, 'editSettings');
        }

        /* Form is sent but there is an input error.
           Fill back the form and render again. */
        $this->form->setValuesByPost();

        $this->tpl->setContent($this->renderSettings());
    }

    public function initSelectTests(): void
    {
        // copy opend nodes from repository explorer
        ilSession::set('select_tovr_expanded', is_array(ilSession::get('repexpand')) ? ilSession::get('repexpand') : array());

        // open current position
        $path = $this->tree->getPathId((int) $this->request->getQueryParams()['ref_id']);
        foreach($path as $node_id) {
            if(!in_array($node_id, ilSession::get('select_tovr_expanded'), false)) {
                $sess_data = ilSession::get('select_tovr_expanded');
                $sess_data[] = $node_id;
                ilSession::set('select_tovr_expanded', $sess_data);
            }
        }
        $this->selectTests();
    }

    public function initCourseTests(): void
    {
        $pnode = $this->tree->getParentNodeData((int) $this->request->getQueryParams()['ref_id']);
        $otype = ilObject::_lookupType((int) $pnode['ref_id'], true); // Parent node is 'crs'
        $tsts = $this->tree->getFilteredSubTree((int) $pnode['ref_id'], ['tst']);  // and has 'tst's

        $refs = [];
        foreach($tsts as $tst) {
            $refs[] = $tst['child'];
        }

        $num_nodes = 0;
        foreach($refs as $ref_id) {
            if($this->access->checkAccess('tst_statistics', '', (int) $ref_id) || $this->access->checkAccess('write', '', (int) $ref_id)) {
                $this->object->addTest($ref_id);
                ++$num_nodes;
            }
        }

        if(!$num_nodes) {
            $this->tpl->setOnScreenMessage(ilGlobalTemplateInterface::MESSAGE_TYPE_FAILURE, $this->lng->txt('none_found'));
            $this->selectTests();
            return;
        }
        $this->tpl->setOnScreenMessage(ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS, $this->lng->txt('rep_robj_xtov_tests_updated_success'), true);
        $this->ctrl->redirect($this, 'editSettings');

        $this->editSettings();
    }

    public function selectTests(): void
    {
        $this->tabs->activateTab('properties');
        $this->toolbar->addButton($this->lng->txt('cancel'), $this->ctrl->getLinkTarget($this, 'editSettings'));
        $this->tpl->addBlockfile('ADM_CONTENT', 'adm_content', 'tpl.paste_into_multiple_objects.html', 'Services/Object');

        $exp = new ilTestOverviewTestSelectionExplorer('select_tovr_expanded');
        $exp->setExpandTarget($this->ctrl->getLinkTarget($this, 'selectTests'));
        $exp->setTargetGet('ref_id');
        $exp->setPostVar('nodes[]');
        $exp->highlightNode((string) $this->request->getQueryParams()['ref_id']);
        $post = $this->request->getParsedBody();
        $exp->setCheckedItems((isset($post['nodes']) && is_array($post['nodes'])) ? $post['nodes'] : array());

        $this->tpl->setVariable('FORM_TARGET', '_top');
        $this->tpl->setVariable('FORM_ACTION', $this->ctrl->getFormAction($this, 'performAddTests'));

        $query_params = $this->request->getQueryParams();
        $exp->setExpand(
            isset($query_params['select_tovr_expanded']) && (int) $query_params['select_tovr_expanded'] ?
            (int) $query_params['select_tovr_expanded'] :
            $this->tree->readRootId()
        );
        $exp->setDefaultHiddenObjects($this->object->getUniqueTests(true));
        $exp->setOutput(0);

        $this->tpl->setVariable('OBJECT_TREE', $exp->getOutput());
        $this->tpl->setVariable('CMD_SUBMIT', 'performAddTests');
        $this->tpl->setVariable('TXT_SUBMIT', $this->lng->txt('select'));
    }

    public function performAddTests(): void
    {
        $post = $this->request->getParsedBody();
        if(!isset($post['nodes']) || !is_array($post['nodes']) || !$post['nodes']) {
            $this->tpl->setOnScreenMessage(ilGlobalTemplateInterface::MESSAGE_TYPE_FAILURE, $this->lng->txt('select_one'));
            $this->selectTests();
            return;
        }

        $num_nodes = 0;
        foreach($post['nodes'] as $ref_id) {
            if($this->access->checkAccess('tst_statistics', '', (int) $ref_id) || $this->access->checkAccess('write', '', (int) $ref_id)) {
                $this->object->addTest((int) $ref_id);
                ++$num_nodes;
            }
        }

        if(!$num_nodes) {
            $this->tpl->setOnScreenMessage(ilGlobalTemplateInterface::MESSAGE_TYPE_FAILURE, $this->lng->txt('select_one'));
            $this->selectTests();
            return;
        }

        $this->tpl->setOnScreenMessage(ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS, $this->txt('rep_robj_xtov_tests_updated_success'), true);
        $this->ctrl->redirect($this, 'editSettings');

        $this->editSettings();
    }

    public function removeTests(): void
    {
        $this->initSettingsForm();
        $this->populateSettings();
        $post = $this->request->getParsedBody();
        if (isset($post['test_ids'])) {
            foreach ($post['test_ids'] as $testId) {
                $this->object->rmTest($testId);
            }
            $this->tpl->setOnScreenMessage(ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS, $this->lng->txt('rep_robj_xtov_tests_updated_success'), true);
            $this->ctrl->redirect($this, 'editSettings');
        }
        $this->tpl->setOnScreenMessage(ilGlobalTemplateInterface::MESSAGE_TYPE_FAILURE, $this->lng->txt('rep_robj_xtov_min_one_check_test'), true);
        $this->tpl->setContent($this->renderSettings());
    }

    protected function updateMemberships(): void
    {
        $this->initSettingsForm();
        $this->populateSettings();

        /* Get tests from DB to be able to notice deletions
           and additions. */
        $overviewGroups = $this->object->getParticipantGroups(true);
        $post = $this->request->getParsedBody();
        if (isset($post['membership_ids'])
            || ! empty($overviewGroups)) {

            if (! isset($post['membership_ids'])) {
                $post['membership_ids'] = array();
            }

            /* Executing the registered test retrieval again with the same filters
               allows to determine which tests are really removed. */
            $mapper = new ilMembershipMapper();
            $displayedIds    = array();
            $displayedGroups = $mapper->getList(array(), $this->getMembershipList()->filter);
            foreach ($displayedGroups['items'] as $grp) {
                $displayedIds[] = $grp->obj_id;
            }
            $displayedIds = array_intersect($displayedIds, array_keys($overviewGroups));

            /* Check for deleted/added IDs and execute corresponding routine. */
            $deletedIds = array_diff($displayedIds, $post['membership_ids']);
            $addedIds   = array_diff($post['membership_ids'], array_keys($overviewGroups));

            foreach ($deletedIds as $groupId) {
                $this->object->rmGroup($groupId);
            }

            foreach ($addedIds as $groupId) {
                $this->object->addGroup($groupId);
            }

            $this->tpl->setOnScreenMessage(ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS, $this->lng->txt('rep_robj_xtov_memberships_updated_success'), true);
            $this->ctrl->redirect($this, 'editSettings');
        }
        $this->tpl->setOnScreenMessage(ilGlobalTemplateInterface::MESSAGE_TYPE_FAILURE, $this->lng->txt('rep_robj_xtov_min_one_check_membership'), true);
        $this->tpl->setContent($this->renderSettings());
    }

    public function addMemberships(): void
    {
        $this->initSettingsForm();
        $this->populateSettings();
        $post = $this->request->getParsedBody();
        if (isset($post['membership_ids'])) {
            /* Executing the registered test retrieval again with the same filters
               allows to determine which tests are really removed. */

            foreach ($post['membership_ids'] as $groupId) {
                $this->object->addGroup($groupId);
            }
            $this->tpl->setOnScreenMessage(ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS, $this->lng->txt('rep_robj_xtov_memberships_updated_success'), true);
            $this->ctrl->redirect($this, 'editSettings');
        }
        $this->tpl->setOnScreenMessage(ilGlobalTemplateInterface::MESSAGE_TYPE_FAILURE, $this->lng->txt('rep_robj_xtov_min_one_check_membership'), true);
        $this->tpl->setContent($this->renderSettings());
    }

    public function removeMemberships(): void
    {
        $this->initSettingsForm();
        $this->populateSettings();
        $post = $this->request->getParsedBody();
        if (isset($post['membership_ids'])) {
            /* Executing the registered test retrieval again with the same filters
               allows to determine which tests are really removed. */

            foreach ($post['membership_ids'] as $containerId) {
                $this->object->rmGroup($containerId);
            }
            $this->tpl->setOnScreenMessage(ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS, $this->lng->txt('rep_robj_xtov_memberships_updated_success'), true);
            $this->ctrl->redirect($this, 'editSettings');
        }
        $this->tpl->setOnScreenMessage(ilGlobalTemplateInterface::MESSAGE_TYPE_FAILURE, $this->lng->txt('rep_robj_xtov_min_one_check_membership'));
        $this->tpl->setContent($this->renderSettings());
    }

    protected function initCreationForms(string $new_type): array
    {
        $forms = array(
            self::CFORM_NEW   => $this->initCreateForm($new_type),
            self::CFORM_CLONE => $this->fillCloneTemplate(null, $new_type)
        );

        return $forms;
    }

    protected function initSettingsForm(): void
    {
        /* Configure global form attributes */
        $this->form = new ilPropertyFormGUI();
        $this->form->setTitle($this->txt('edit_properties'));
        $this->form->setFormAction($this->ctrl->getFormAction($this, 'updateSettings'));

        /* Configure form objects */
        $ti = new ilTextInputGUI($this->txt('title'), 'title');
        $ti->setRequired(true);
        $this->form->addItem($ti);

        $ta = new ilTextAreaInputGUI($this->txt('description'), 'desc');
        $this->form->addItem($ta);

        $tp = new ilRadioGroupInputGUI($this->txt('result_presentation'), 'result_presentation');
        $tp->addOption(new ilRadioOption($this->txt('percentage'), 'percentage'));
        $tp->addOption(new ilRadioOption($this->txt('act_max'), 'act_max'));
        $this->form->addItem($tp);

        $result_column = new ilCheckboxInputGUI($this->txt('result_column'), 'result_column');
        $this->form->addItem($result_column);

        $points_column = new ilCheckboxInputGUI($this->txt('points_column'), 'points_column');
        $this->form->addItem($points_column);

        $header_points = new ilCheckboxInputGUI($this->txt('header_points'), 'header_points');
        $header_points->setInfo($this->txt('header_points_info'));
        $points_column->addSubItem($header_points);

        $average_column = new ilCheckboxInputGUI($this->txt('average_column'), 'average_column');
        $this->form->addItem($average_column);

        $enable_excel = new ilCheckboxInputGUI($this->txt('enable_excel'), 'enable_excel');
        $this->form->addItem($enable_excel);

        $this->form->addCommandButton('updateSettings', $this->txt('save'));
    }

    protected function populateSettings(): void
    {
        $values['title'] = $this->object->getTitle();
        $values['desc']  = $this->object->getDescription();
        $values['result_presentation'] = $this->object->getResultPresentation();
        $values['result_column'] = $this->object->getResultColumn();
        $values['points_column'] = $this->object->getPointsColumn();
        $values['average_column'] = $this->object->getAverageColumn();
        $values['enable_excel'] = $this->object->getEnableExcel();
        $values['header_points'] = $this->object->getHeaderPoints();

        $this->form->setValuesByArray($values);
    }

    public function applyOverviewFilter(): void
    {
        $table = new ilTestOverviewTableGUI($this, 'showContent');
        $table->resetOffset();
        $table->writeFilterToSession();

        $this->showContent();
    }

    public function applyTestsFilter(): void
    {
        $table = new ilTestListTableGUI($this, 'editSettings');
        $table->resetOffset();
        $table->writeFilterToSession();

        $this->editSettings();
    }

    public function applyGroupsFilter(): void
    {
        $table = new ilMembershipListTableGUI($this, 'editSettings');
        $table->resetOffset();
        $table->writeFilterToSession();

        $this->editSettings();
    }

    public function resetOverviewFilter(): void
    {
        $table = new ilTestOverviewTableGUI($this, 'editSettings');
        $table->resetOffset();
        $table->resetFilter();

        $this->showContent();
    }

    public function resetTestsFilter(): void
    {
        $table = new ilTestListTableGUI($this, 'editSettings');
        $table->resetOffset();
        $table->resetFilter();

        $this->editSettings();
    }

    public function resetGroupsFilter(): void
    {
        $table = new ilMembershipListTableGUI($this, 'editSettings');
        $table->resetOffset();
        $table->resetFilter();

        $this->editSettings();
    }

    protected function getTestList(): \ilTestListTableGUI
    {
        $testList = new ilTestListTableGUI($this, 'editSettings');
        $testList->setMapper(new ilTestMapper())->populate();
        return $testList;
    }

    protected function getMembershipList(): \ilMembershipListTableGUI
    {
        $testList = new ilMembershipListTableGUI($this, 'editSettings');
        $testList->setMapper(new ilMembershipMapper())->populate();
        return $testList;
    }

    public function addToDeskObject(): void
    {
        if((int) $this->ilSetting->get('disable_my_offers')) {
            $this->showContent();
            return;
        }

        $fm = new ilFavouritesManager();
        $fm->add($this->user->getId(), $this->object->getRefId());
        $this->tpl->setOnScreenMessage(ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS, $this->lng->txt('added_to_desktop'));
        $this->showContent();
    }

    public function removeFromDeskObject(): void
    {
        if((int) $this->ilSetting->get('disable_my_offers')) {
            $this->showContent();
            return;
        }

        $fm = new ilFavouritesManager();
        $fm->remove($this->ilUser->getId(), $this->object->getRefId());
        $this->tpl->setOnScreenMessage(ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS, $this->lng->txt('removed_from_desktop'));
        $this->showContent();
    }

    public function saveOrder(): void
    {
        $this->tabs->activateTab('properties');
        $post = $this->request->getParsedBody();
        if(isset($post['order']) && is_array($post['order'])) {
            foreach ($post['order'] as $ref_id => $order_value) {
                $this->object->setTestOrderValueForRef($ref_id, $order_value);
            }
        }
        $this->lng->loadLanguageModule('cntr');
        $this->tpl->setOnScreenMessage(ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS, $this->lng->txt('cntr_saved_sorting'));
        $this->editSettings();
    }
}
