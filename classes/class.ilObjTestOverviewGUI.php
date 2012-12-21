<?php
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 *	@package	TestOverview repository plugin
 *	@category	GUI
 *	@author		Greg Saive <gsaive@databay.de>
 */

require_once 'Services/Repository/classes/class.ilObjectPluginGUI.php';
require_once 'Services/Form/classes/class.ilPropertyFormGUI.php';

/**
 * @ilCtrl_isCalledBy ilObjTestOverviewGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
 * @ilCtrl_Calls      ilObjTestOverviewGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilRepositorySearchGUI, ilPublicUserProfileGUI, ilCommonActionDispatcherGUI
 */
class ilObjTestOverviewGUI
	extends ilObjectPluginGUI
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
		$next_class = $this->ctrl->getNextClass($this);
		switch ($cmd) {
			case 'updateSettings':
			case 'updateTestList':
			case 'updateMemberships':
			case 'addTests':
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
				$this->checkPermission('read');
				$this->$cmd();
				break;
		}
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

	/**
	 *	Command for updating the tests added to the overview.
	 *
	 *	This command is executed when the TestListTableGUI
	 *	is submitted.
	 */
	protected function updateTestList()
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
		$overviewTests = $this->object->getTests(true);

		if (isset($_POST['test_ids'])
			|| ! empty($overviewTests)) {

			if (! isset($_POST['test_ids']))
				$_POST['test_ids'] = array();

			/* Executing the registered test retrieval again with the same filters
			   allows to determine which tests are really removed. */
			include_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . "/classes/mapper/class.ilTestMapper.php";
			$mapper = new ilTestMapper;
			$displayedIds   = array();
			$displayedTests = $mapper->getList(array(), $this->getTestList()->filter);
			foreach ($displayedTests['items'] as $test) {
				$displayedIds[] = $test->obj_fi;
			}
			$displayedIds = array_intersect($displayedIds, array_keys($overviewTests));

			/* Check for deleted/added IDs and execute corresponding routine. */
			$deletedIds = array_diff($displayedIds, $_POST['test_ids']);
			$addedIds   = array_diff($_POST['test_ids'], array_keys($overviewTests));

			foreach ($deletedIds as $testId) {
				$this->object
					 ->rmTest( $testId );
			}

			foreach ($addedIds as $testId) {
				$this->object
					 ->addTest( $testId );
			}

			ilUtil::sendSuccess($lng->txt('rep_robj_xtov_tests_updated_success'), true);
			$ilCtrl->redirect($this, 'editSettings');
		}

		ilUtil::sendFailure($lng->txt('rep_robj_xtov_min_one_check_test'), true);
		$tpl->setContent( $this->renderSettings() );
	}

	public function addTests()
	{
		/**
		 * @var $tpl    ilTemplate
		 * @var $lng    ilLanguage
		 * @var $ilCtrl ilCtrl
		 */
		global $tpl, $lng, $ilCtrl;

		$this->initSettingsForm();
		$this->populateSettings();

		if (isset($_POST['test_ids'])) {
			foreach ($_POST['test_ids'] as $testRefId) {
				$this->object
					->addTest( $testRefId );
			}

			ilUtil::sendSuccess($lng->txt('rep_robj_xtov_tests_updated_success'), true);
			$ilCtrl->redirect($this, 'editSettings');
		}

		ilUtil::sendFailure($lng->txt('rep_robj_xtov_min_one_check_test'), true);
		$tpl->setContent( $this->renderSettings() );
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

		if (isset($_POST['test_ids'])) {

			foreach ($_POST['test_ids'] as $testId) {
				$this->object
					->rmTest( $testId );
			}

			ilUtil::sendSuccess($lng->txt('rep_robj_xtov_tests_updated_success'), true);
			$ilCtrl->redirect($this, 'editSettings');
		}

		ilUtil::sendFailure($lng->txt('rep_robj_xtov_min_one_check_test'), true);
		$tpl->setContent( $this->renderSettings() );
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

		if (isset($_POST['membership_ids'])) {
			/* Executing the registered test retrieval again with the same filters
			   allows to determine which tests are really removed. */
			include_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
				->getDirectory() . "/classes/mapper/class.ilMembershipMapper.php";

			foreach ($_POST['membership_ids'] as $groupId) {
				$this->object
					->rmGroup( $groupId );
			}

			ilUtil::sendSuccess($lng->txt('rep_robj_xtov_memberships_updated_success'), true);
			$ilCtrl->redirect($this, 'editSettings');
		}
		
		ilUtil::sendFailure($lng->txt('rep_robj_xtov_min_one_check_membership'), true);
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

}
