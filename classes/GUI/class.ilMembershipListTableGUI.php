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
 */
class ilMembershipListTableGUI extends ilMappedTableGUI
{
    private ilDBInterface $ilDB;
    public array $filter = array();

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
        global $ilCtrl, $ilDB;
        $this->ilDB = $ilDB;

        /* Pre-configure table */
        $this->setId(sprintf(
            "xtob_mlist_%d",
            $a_parent_obj->getObject()->getId()
        ));

        $this->setDefaultOrderDirection('ASC');
        $this->setDefaultOrderField('title');
        $this->setExternalSorting(true);
        $this->setExternalSegmentation(true);

        parent::__construct($a_parent_obj, $a_parent_cmd);

        $this->setTitle(sprintf(
            $this->lng->txt('rep_robj_xtov_membership_list_table_title'),
            $a_parent_obj->getObject()->getTitle()
        ));

        //$plugin = ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview');
        $this->setRowTemplate('tpl.simple_object_row.html', 'Customizing/global/plugins/Services/Repository/RepositoryObject/TestOverview');

        $this->addColumn($this->lng->txt(""), '', '1px', true);
        $this->addColumn($this->lng->txt("rep_robj_xtov_membership_list_hdr_membership_title"), 'title');
        $this->addColumn($this->lng->txt("rep_robj_xtov_membership_list_hdr_membership_info"), '');
        $this->addColumn($this->lng->txt("rep_robj_xtov_item_chosen"), '');

        $this->setDescription($this->lng->txt("rep_robj_xtov_membership_list_description"));
        $this->setFormAction($ilCtrl->getFormAction($this->getParentObject(), 'updateSettings'));
        $this->addMultiCommand('addMemberships', $this->lng->txt('rep_robj_xtov_add_to_overview'));
        $this->addMultiCommand('removeMemberships', $this->lng->txt('rep_robj_xtov_remove_from_overview'));

        $this->setShowRowsSelector(true);

        /* Add 'Select All', configure filters */
        $this->setSelectAllCheckbox('membership_ids[]');
        $this->initFilter();
        $this->setFilterCommand("applyGroupsFilter");
        $this->setResetCommand("resetGroupsFilter");
    }

    /**
     *	Initialize the table filters.
     *
     *	This method is called internally to initialize
     *	the filters from present on the top of the table.
     */
    public function initFilter(): void
    {
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
     * @param stdClass $a_set
     * @internal param \ilObjTest $test
     *
     */
    protected function fillRow($a_set): void
    {
        $members = $this->getMembersObject($a_set)->getCountMembers();
        $label   = $this->lng->txt('rep_robj_xtov_membership_count_members');

        $this->tpl->setVariable('VAL_ID', $a_set->obj_id);
        $this->tpl->setVariable('ID_COL', 'membership_ids[]');
        $this->tpl->setVariable('OBJECT_TITLE', $a_set->title);
        $this->tpl->setVariable('OBJECT_INFO', sprintf("%d %s", $members, $label));
        $this->tpl->setVariable('OBJECT_IMG_PATH', $this->isAddedContainer($a_set) ?'icon_ok.svg' : 'icon_not_ok.svg');
    }

    private function isAddedContainer(stdClass $container): bool
    {
        $overviewId = $this->getParentObject()->getObject()->getId();
        $filter = array(
            'obj_id_overview = ' . $this->ilDB->quote($overviewId, 'integer'),
            'obj_id_grpcrs = ' . $this->ilDB->quote($container->obj_id, 'integer')
        );

        $res = $this->getMapper()->getValue('rep_robj_xtov_p2o', 'true', $filter);

        return !empty($res);
    }

}
