<?php
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 *	@package	TestOverview repository plugin
 *	@category	GUI
 *	@author		Greg Saive <gsaive@databay.de>
 */

require_once 'Services/Repository/classes/class.ilObjectPluginGUI.php';
require_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
require_once 'Services/PersonalDesktop/interfaces/interface.ilDesktopItemHandling.php';

/**
 * @ilCtrl_isCalledBy ilObjTestOverviewGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
 * @ilCtrl_Calls      ilObjTestOverviewGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilRepositorySearchGUI, ilPublicUserProfileGUI, ilCommonActionDispatcherGUI
 * @ilCtrl_Calls      ilObjTestOverviewGUI: ilTestEvaluationGUI, ilMDEditorGUI
 */
class ilObjTestOverviewGUI
	extends ilObjectPluginGUI
	implements ilDesktopItemHandling
{
	/**
	 *	@var ilPropertyFormGUI
	 */
	protected $form;

	/**
	 *	@return string
	 */
	public function getType()
	{
		return 'xtov';
	}

	/**
	 *	@return string
	 */
	public function getAfterCreationCmd()
	{
		return 'showContent';
	}

	/**
	 *	@return string
	 */
	public function getStandardCmd()
	{
		return 'showContent';
	}

	/**
	 *	Plugin command execution runpoint.
	 *
	 *	The performCommand() method is called internally
	 *	by ilias to handle a specific request action.
	 *	The $cmd given as argument is used to identify
	 *	the command to be executed.
	 *
	 *	@param string $cmd	The command (method) to execute.
	 */
	public function performCommand($cmd)
	{
		/**
		 * @var $ilTabs ilTabsGUI
		 * @var $tpl    ilTemplate
		 */
		global $ilTabs, $tpl;

		$tpl->setDescription($this->object->getDescription());

		$next_class = $this->ctrl->getNextClass($this);
		switch($next_class)
		{
			case 'ilmdeditorgui':
				$this->checkPermission('write');
				require_once 'Services/MetaData/classes/class.ilMDEditorGUI.php';
				$md_gui = new ilMDEditorGUI($this->object->getId(), 0, $this->object->getType());
				$md_gui->addObserver($this->object, 'MDUpdateListener', 'General');
				$ilTabs->setTabActive('meta_data');
				$this->ctrl->forwardCommand($md_gui);
				return;
				break;
				
			default:
				switch($cmd)
				{
					case 'updateSettings':
					case 'updateMemberships':
					case 'initSelectTests':
					case 'selectTests':
					case 'performAddTests':
					case 'removeTests':
					case 'addMemberships':
					case 'removeMemberships':
					case 'editSettings':
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
						if(in_array($cmd, array('addToDesk', 'removeFromDesk')))
						{
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

	/**
	 *	Configure the plugin tabs
	 *
	 *	The setTabs() is called automatically by ILIAS
	 *	to render the given tabs to the GUI.
	 *	This is overloaded to set & configure
	 *	the plugin-specific tabs.
	 */
	protected function setTabs()
	{
		/**
		 * @var $ilTabs   ilTabsGUI
		 * @var $ilCtrl   ilCtrl
		 * @var $ilAccess ilAccessHandler
		 */
		global $ilTabs, $ilCtrl, $ilAccess;

		$this->addInfoTab();

		/* Check for read access (showContent available) */
		if ($ilAccess->checkAccess('read', '', $this->object->getRefId())) {
			$ilTabs->addTab('content', $this->txt('content'), $ilCtrl->getLinkTarget($this, 'showContent'));
		}

		/* Check for write access (editSettings available) */
		if ($ilAccess->checkAccess('write', '', $this->object->getRefId())) {
			$ilTabs->addTab('properties', $this->txt('properties'), $ilCtrl->getLinkTarget($this, 'editSettings'));
			$ilTabs->addTarget('meta_data', $this->ctrl->getLinkTargetByClass('ilmdeditorgui', ''), '', 'ilmdeditorgui');
		}

		$this->addPermissionTab();
	}

	/**
	 *	Command for rendering a Test Overview.
	 *
	 *	This command displays a test overview entry
	 *	and its data. This method is called by
	 *	@see self::performCommand().
	 */
	protected function showContent()
	{
		/**
		 * @var $tpl ilTemplate
		 * @var $ilTabs ilTabsGUI
		 */
		global $tpl, $ilTabs;

		$this->includePluginClasses(array(
			"ilTestOverviewTableGUI",
			"ilOverviewMapper"));

		$ilTabs->activateTab("content");

		/* Configure content UI */
		$table = new ilTestOverviewTableGUI( $this, 'showContent' );
		$table->setMapper(new ilOverviewMapper)
			  ->populate();

		/* Populate template */
		$tpl->setDescription($this->object->getDescription());
		$tpl->setContent( $table->getHTML() );
	}

	/**
	 *	Render the settings page.
	 *
	 *	The renderSettings() method can be passed directly
	 *	to $tpl->setContent() as it renders the whole
	 *	settings page.
	 *
	 *	@return string
	 */
	protected function renderSettings()
	{
		return $this->form->getHTML()
			 . "<hr />"
			 . $this->getTestList()->getHTML()
			 . "<hr />"
			 . $this->getMembershipList()->getHTML();
	}

	/**
	 *	Command for editing the settings of a Test Overview.
	 *
	 *	This command provides a HTML form to edit the settings
	 *	of the currently loaded Test Overview.
	 */
	protected function editSettings()
	{
		/**
		 * @var $tpl    ilTemplate
		 * @var $ilTabs ilTabsGUI
		 */
		global $tpl, $ilTabs;

		$ilTabs->activateTab('properties');

		/* Initialize form and populate values */
		$this->initSettingsForm();
		$this->populateSettings();

		/* Populate template */
		$tpl->setContent( $this->renderSettings() );
	}

	/**
 	 *	Command for saving the updated Test Overview settings.
	 *
	 *	This command saves the HTML form input into the Test Overview
	 *	currently selected.
	 */
	protected function updateSettings()
	{
		/**
		 * @var $tpl    ilTemplate
		 * @var $lng    ilLanguage
		 * @var $ilCtrl ilCtrl
		 */
		global $tpl, $lng, $ilCtrl;

		$this->initSettingsForm();

		if ($this->form->checkInput())
		{
			/* Form is sent and input validated,
			   now save settings. */
			$this->object->setTitle($this->form->getInput('title'));
			$this->object->setDescription($this->form->getInput('desc'));

			$this->object->update();
			ilUtil::sendSuccess($lng->txt('msg_obj_modified'), true);

			/* Back to editSettings */
			$ilCtrl->redirect($this, 'editSettings');
		}

		/* Form is sent but there is an input error.
		   Fill back the form and render again. */
		$this->form->setValuesByPost();

		$tpl->setContent( $this->renderSettings() );
	}

	public function initSelectTests()
	{
		/**
		 * @var $tree ilTree
		 */
		global $tree;

		// empty session on init
		$_SESSION['select_tovr_expanded'] = array();

		// copy opend nodes from repository explorer		
		$_SESSION['select_tovr_expanded'] = is_array($_SESSION['repexpand']) ? $_SESSION['repexpand'] : array();

		// open current position
		$path = $tree->getPathId((int)$_GET['ref_id']);
		foreach((array)$path as $node_id)
		{
			if(!in_array($node_id, $_SESSION['select_tovr_expanded']))
				$_SESSION['select_tovr_expanded'][] = $node_id;
		}

		$this->selectTests();
		return;
	}

	public function selectTests()
	{
		/**
		 * @var $tpl    ilTemplate
		 * @var $lng    ilLanguage
		 * @var $ilCtrl ilCtrl
		 * @var $ilTabs ilTabsGUI
		 * @var $ilToolbar ilToolbarGUI
		 */
		global $tpl, $lng, $ilCtrl, $ilTabs, $ilToolbar;

		$ilTabs->activateTab('properties');
		$ilToolbar->addButton($this->lng->txt('cancel'), $ilCtrl->getLinkTarget($this,'editSettings'));
		$tpl->addBlockfile('ADM_CONTENT', 'adm_content', 'tpl.paste_into_multiple_objects.html', 'Services/Object');

		$this->includePluginClasses(array('ilTestOverviewTestSelectionExplorer'));
		$exp = new ilTestOverviewTestSelectionExplorer('select_tovr_expanded');
		$exp->setExpandTarget($ilCtrl->getLinkTarget($this, 'selectTests'));
		$exp->setTargetGet('ref_id');
		$exp->setPostVar('nodes[]');
		$exp->highlightNode((int)$_GET['ref_id']);
		$exp->setCheckedItems(
			is_array($_POST['nodes']) ?  (array)$_POST['nodes'] : array()
		);

		$tpl->setVariable('FORM_TARGET', '_top');
		$tpl->setVariable('FORM_ACTION', $ilCtrl->getFormAction($this, 'performAddTests'));

		$exp->setExpand(
			isset($_GET['select_tovr_expanded']) && (int)$_GET['select_tovr_expanded'] ?
			(int) $_GET['select_tovr_expanded'] :
			$this->tree->readRootId()
		);
		$exp->setDefaultHiddenObjects($this->object->getUniqueTests(true));
		$exp->setOutput(0);

		$tpl->setVariable('OBJECT_TREE', $exp->getOutput());
		$tpl->setVariable('CMD_SUBMIT', 'performAddTests');
		$tpl->setVariable('TXT_SUBMIT', $lng->txt('select'));
	}
	
	public function performAddTests()
	{
		/**
		 * @var $lng      ilLanguage
		 * @var $ilCtrl   ilCtrl
		 * @var $ilAccess ilAccessHandler
		 */
		global $lng, $ilCtrl, $ilAccess;
		
		if(!isset($_POST['nodes']) || !is_array($_POST['nodes']) || !$_POST['nodes'])
		{
			ilUtil::sendFailure($lng->txt('select_one'));
			$this->selectTests();
			return;
		}

		$num_nodes = 0;
		foreach($_POST['nodes'] as $ref_id)
		{
			if($ilAccess->checkAccess('tst_statistics', '', $ref_id) || $ilAccess->checkAccess('write', '', $ref_id))
			{
				$this->object->addTest($ref_id);
				++$num_nodes;
			}
		}

		if(!$num_nodes)
		{
			ilUtil::sendFailure($lng->txt('select_one'));
			$this->selectTests();
			return;
		}

		ilUtil::sendSuccess($this->txt('tests_updated_success'), true);
		$ilCtrl->redirect($this, 'editSettings');
		
		$this->editSettings();
		return;
	}

	public function removeTests()
	{
		/**
		 * @var $tpl    ilTemplate
		 * @var $lng    ilLanguage
		 * @var $ilCtrl ilCtrl
		 */
		global $tpl, $lng, $ilCtrl;

		$this->initSettingsForm();
		$this->populateSettings();

		if (isset($_POST['test_ids']))
		{
			foreach ($_POST['test_ids'] as $testId)
			{
				$this->object->rmTest($testId);
			}

			ilUtil::sendSuccess($lng->txt('rep_robj_xtov_tests_updated_success'), true);
			$ilCtrl->redirect($this, 'editSettings');
		}

		ilUtil::sendFailure($lng->txt('rep_robj_xtov_min_one_check_test'), true);
		$tpl->setContent($this->renderSettings());
	}

	/**
	 *	Command for updating the participants groups added to the overview.
	 *
	 *	This command is executed when the ilMembershipListTableGUI
	 *	is submitted.
	 */
	protected function updateMemberships()
	{
		/**
		 * @var $tpl    ilTemplate
		 * @var $lng    ilLanguage
		 * @var $ilCtrl ilCtrl
		 */
		global $tpl, $lng, $ilCtrl;

		$this->initSettingsForm();
		$this->populateSettings();

		/* Get tests from DB to be able to notice deletions
		   and additions. */
		$overviewGroups = $this->object->getParticipantGroups(true);

		if (isset($_POST['membership_ids'])
			|| ! empty($overviewGroups)) {

			if (! isset($_POST['membership_ids']))
				$_POST['membership_ids'] = array();

			/* Executing the registered test retrieval again with the same filters
			   allows to determine which tests are really removed. */
			include_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . "/classes/mapper/class.ilMembershipMapper.php";
			$mapper = new ilMembershipMapper;
			$displayedIds    = array();
			$displayedGroups = $mapper->getList(array(), $this->getMembershipList()->filter);
			foreach ($displayedGroups['items'] as $grp) {
				$displayedIds[] = $grp->obj_id;
			}
			$displayedIds = array_intersect($displayedIds, array_keys($overviewGroups));

			/* Check for deleted/added IDs and execute corresponding routine. */
			$deletedIds = array_diff($displayedIds, $_POST['membership_ids']);
			$addedIds   = array_diff($_POST['membership_ids'], array_keys($overviewGroups));

			foreach ($deletedIds as $groupId) {
				$this->object
					 ->rmGroup( $groupId );
			}

			foreach ($addedIds as $groupId) {
				$this->object
					 ->addGroup( $groupId );
			}

			ilUtil::sendSuccess($lng->txt('rep_robj_xtov_memberships_updated_success'), true);
			$ilCtrl->redirect($this, 'editSettings');
		}

		ilUtil::sendFailure($lng->txt('rep_robj_xtov_min_one_check_membership'), true);
		$tpl->setContent( $this->renderSettings() );

	}

	public function addMemberships()
	{
		/**
		 * @var $tpl    ilTemplate
		 * @var $lng    ilLanguage
		 * @var $ilCtrl ilCtrl
		 */
		global $tpl, $lng, $ilCtrl;

		$this->initSettingsForm();
		$this->populateSettings();

		if (isset($_POST['membership_ids'])) {
			/* Executing the registered test retrieval again with the same filters
			   allows to determine which tests are really removed. */
			include_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
				->getDirectory() . "/classes/mapper/class.ilMembershipMapper.php";

			foreach ($_POST['membership_ids'] as $groupId) {
				$this->object
					->addGroup( $groupId );
			}

			ilUtil::sendSuccess($lng->txt('rep_robj_xtov_memberships_updated_success'), true);
			$ilCtrl->redirect($this, 'editSettings');
		}

		ilUtil::sendFailure($lng->txt('rep_robj_xtov_min_one_check_membership'), true);
		$tpl->setContent( $this->renderSettings() );
	}

	public function removeMemberships()
	{
		/**
		 * @var $tpl    ilTemplate
		 * @var $lng    ilLanguage
		 * @var $ilCtrl ilCtrl
		 */
		global $tpl, $lng, $ilCtrl;

		$this->initSettingsForm();
		$this->populateSettings();

		if (isset($_POST['membership_ids']))
		{
			/* Executing the registered test retrieval again with the same filters
			   allows to determine which tests are really removed. */
			include_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
				->getDirectory() . "/classes/mapper/class.ilMembershipMapper.php";

			foreach ($_POST['membership_ids'] as $containerId)
			{
				$this->object->rmGroup($containerId);
			}

			ilUtil::sendSuccess($lng->txt('rep_robj_xtov_memberships_updated_success'), true);
			$ilCtrl->redirect($this, 'editSettings');
		}
		
		ilUtil::sendFailure($lng->txt('rep_robj_xtov_min_one_check_membership'));
		$tpl->setContent( $this->renderSettings() );
	}

	/**
	 *	Retrieve the plugin's creations forms.
	 *
	 *	This method is called internally by ilias
	 *	to retrieve the creation-, cloning- [and
	 *	optionally the import-] forms.
	 *
	 *	@param	string	$a_new_type	Key name of the plugin
	 *	@return	array
	 */
	protected function initCreationForms($a_new_type)
	{
		$forms = array(
			self::CFORM_NEW   => $this->initCreateForm($a_new_type),
			self::CFORM_CLONE => $this->fillCloneTemplate(null, $a_new_type)
		);

		return $forms;
	}

	/**
	 *	Retrieve the creation form.
	 *
	 *	This method is only overloaded to provide the
	 *	extension possibility.
	 *
	 *	@param	string	$a_new_type	Key name of the plugin
	 *	@return ilFormGUI
	 */
	public function  initCreateForm($a_new_type)
	{
		$form = parent::initCreateForm($a_new_type);

		return $form;
	}

	/**
	 *	Configure the displayed form for Settings edition.
	 *
	 *	This method is called internally by @see
	 */
	protected function initSettingsForm()
	{
		/**
		 * @var $ilCtrl ilCtrl
		 */
		global $ilCtrl;

		/* Configure global form attributes */
		$this->form = new ilPropertyFormGUI();
		$this->form->setTitle($this->txt('edit_properties'));
		$this->form->setFormAction($ilCtrl->getFormAction($this, 'updateSettings'));

		/* Configure form objects */
		$ti = new ilTextInputGUI($this->txt('title'), 'title');
		$ti->setRequired(true);

		$ta = new ilTextAreaInputGUI($this->txt('description'), 'desc');

		$this->form->addItem( $ti );
		$this->form->addItem( $ta );
		$this->form->addCommandButton('updateSettings', $this->txt('save'));
	}

	/**
	 *	Populate the Test Overview settings.
	 *
	 *	This method is called internally by
	 *	@see self::editSettings() to fill the form
	 *	by the current settings' values.
	 */
	protected function populateSettings()
	{
		$values['title'] = $this->object->getTitle();
		$values['desc']  = $this->object->getDescription();
		$this->form->setValuesByArray($values);
	}

	/**
	 *	Apply a filter to the overview table.
	 *
	 *	The applyOverviewFilter() method is used as a command
	 *	to apply (re-populate) and save the filters.
	 */
	public function applyOverviewFilter()
	{
		$this->includePluginClasses(array(
			"ilTestOverviewTableGUI"));

		$table = new ilTestOverviewTableGUI( $this, 'showContent' );
		$table->resetOffset();
		$table->writeFilterToSession();

		$this->showContent();
	}

	/**
	 *	Apply a filter to the tests list table.
	 *
	 *	The applyTestsFilter() method is used as a command
	 *	to apply (re-populate) and save the filters.
	 */
	public function applyTestsFilter()
	{
		$this->includePluginClasses(array(
			"ilTestListTableGUI"));

		$table = new ilTestListTableGUI( $this, 'editSettings' );
		$table->resetOffset();
		$table->writeFilterToSession();

		$this->editSettings();
	}

	/**
	 *	Apply a filter to the groups list table.
	 *
	 *	The applyGroupFilter() method is used as a command
	 *	to apply (re-populate) and save the filters.
	 */
	public function applyGroupsFilter()
	{
		$this->includePluginClasses(array(
			"ilMembershipListTableGUI"));

		$table = new ilMembershipListTableGUI( $this, 'editSettings' );
		$table->resetOffset();
		$table->writeFilterToSession();

		$this->editSettings();
	}

	/**
	 *	Reset the overview filters
	 *
	 *	This method is used as a command (form submit handler)
	 *	to reset the filters set on the overview table.
	 */
	public function resetOverviewFilter()
	{
		$this->includePluginClasses(array(
			"ilTestOverviewTableGUI"));

		$table = new ilTestOverviewTableGUI( $this, 'editSettings' );
		$table->resetOffset();
		$table->resetFilter();

		$this->showContent();
	}

	/**
	 *	Reset the tests list filters
	 *
	 *	This method is used as a command (form submit handler)
	 *	to reset the filters set on the tests list table.
	 */
	public function resetTestsFilter()
	{
		$this->includePluginClasses(array(
			"ilTestListTableGUI"));

		$table = new ilTestListTableGUI( $this, 'editSettings' );
		$table->resetOffset();
		$table->resetFilter();

		$this->editSettings();
	}

	/**
	 *	Reset the groups list filters
	 *
	 *	This method is used as a command (form submit handler)
	 *	to reset the filters set on the groups list table.
	 */
	public function resetGroupsFilter()
	{
		$this->includePluginClasses(array(
			"ilMembershipListTableGUI"));

		$table = new ilMembershipListTableGUI( $this, 'editSettings' );
		$table->resetOffset();
		$table->resetFilter();

		$this->editSettings();
	}


	/**
	 *	Retrieve the tests list table.
	 *
	 *	The getTestList() method should be used to
	 *	retrieve the GUI object responsible for listing
	 *	the tests which can be added to the overview.
	 *
	 *	@return ilTestListTableGUI
	 */
	protected function getTestList()
	{
		$this->includePluginClasses( array(
			"ilTestListTableGUI",
			"ilTestMapper"));

		$testList = new ilTestListTableGUI( $this, 'editSettings' );
		$testList->setMapper(new ilTestMapper)
				 ->populate();

		return $testList;
	}

	/**
	 *	Retrieve the memberships list table.
	 *
	 *	The getMembershipList() method should be used to
	 *	retrieve the GUI object responsible for listing
	 *	the participants groups which can be added to
	 *	the overview.
	 *
	 *	@return ilMembershipListTableGUI
	 */
	protected function getMembershipList()
	{
		$this->includePluginClasses( array(
			"ilMembershipListTableGUI",
			"ilMembershipMapper"));

		$testList = new ilMembershipListTableGUI( $this, 'editSettings' );
		$testList->setMapper(new ilMembershipMapper)
				 ->populate();

		return $testList;
	}

	/**
	 *	Include a class implemented by this plugin.
	 *
	 *	The includePluginClasses() method can be used to
	 *	include classes which are located in the plugin's
	 *	directory.
	 *
	 *	@params	array	$classes	List of classes to be included
	 *
	 *	@throws InvalidArgumentException on invalid class name. (File not found)
	 */
	private function includePluginClasses( array $classes )
	{
		$plugin	= ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview');
		$pluginDirectory = $plugin->getDirectory();

		foreach ($classes as $class ) {
			if (class_exists($class))
				continue;

			$additionalFolder = "";
			if (strrpos($class, "Mapper") !== false) {
				/* Custom mapper classes */
				$additionalFolder = "mapper/";
			}
			elseif (strpos($class, "ilObj") === false
					&& strrpos($class, "GUI") !== false) {
				/* Custom GUI classes (Not a plugin GUI controller) */
				$additionalFolder = "GUI/";
			}

			$classFile       = $pluginDirectory . "/classes/{$additionalFolder}class.$class.php";

			if (! file_exists($classFile))
				throw new InvalidArgumentException;

			require_once $classFile;
		}
	}

	/**
	 * @param string $a_sub_type
	 * @param int    $a_sub_id
	 * @return ilObjectListGUI|ilObjTestOverviewListGUI
	 */
	protected function initHeaderAction($a_sub_type = null, $a_sub_id = null)
	{
		/**
		 * @var $ilUser ilObjUser
		 */
		global $ilUser;

		$lg = parent::initHeaderAction();
		if($lg instanceof ilObjTestOverviewListGUI)
		{
			if($ilUser->getId() != ANONYMOUS_USER_ID)
			{
				// Maybe handle notifications in future ...
			}
		}

		return $lg;
	}

	/**
	 * @see ilDesktopItemHandling::addToDesk()
	 */
	public function addToDeskObject()
	{
		/**
		 * @var $ilSetting ilSetting
		 * @var $lng       ilLanguage
		 */
		global $ilSetting, $lng;

		if((int)$ilSetting->get('disable_my_offers'))
		{
			$this->showContent();
			return;
		}

		include_once './Services/PersonalDesktop/classes/class.ilDesktopItemGUI.php';
		ilDesktopItemGUI::addToDesktop();
		ilUtil::sendSuccess($lng->txt('added_to_desktop'));
		$this->showContent();
	}

	/**
	 * @see ilDesktopItemHandling::removeFromDesk()
	 */
	public function removeFromDeskObject()
	{
		global $ilSetting, $lng;

		if((int)$ilSetting->get('disable_my_offers'))
		{
			$this->showContent();
			return;
		}

		include_once './Services/PersonalDesktop/classes/class.ilDesktopItemGUI.php';
		ilDesktopItemGUI::removeFromDesktop();
		ilUtil::sendSuccess($lng->txt('removed_from_desktop'));
		$this->showContent();
	}
}
