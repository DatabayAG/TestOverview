<?php

/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */
/**
 * 	@package	TestOverview repository plugin
 * 	@category	GUI
 * 	@author		Greg Saive <gsaive@databay.de>
 *      @author         Jan Ruthardt <janruthardt@web.de>
 */
require_once 'Services/Repository/classes/class.ilObjectPluginGUI.php';
require_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
require_once 'Services/PersonalDesktop/interfaces/interface.ilDesktopItemHandling.php';

require_once 'Services/Chart/classes/class.ilChartPie.php';
require_once 'Services/Chart/classes/class.ilChartGrid.php';
require_once 'Services/Chart/classes/class.ilChartLegend.php';
require_once 'Services/Chart/classes/class.ilChartSpider.php';
include_once 'Services/jQuery/classes/class.iljQueryUtil.php';

/**
 * @ilCtrl_isCalledBy ilObjTestOverviewGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
 * @ilCtrl_Calls      ilObjTestOverviewGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilRepositorySearchGUI, ilPublicUserProfileGUI, ilCommonActionDispatcherGUI
 * @ilCtrl_Calls      ilObjTestOverviewGUI: ilTestEvaluationGUI, ilObjExerciseGUI, ilMDEditorGUI, ilTestOverviewExportGUI
 */
class ilObjTestOverviewGUI extends ilObjectPluginGUI implements ilDesktopItemHandling {

	/**
	 * 
	 */
	public $exerciseDeleteChecks;

	/**
	 * 	@return string
	 */
	public function getType() {
		return 'xtov';
	}

	/**
	 * 	@return string
	 */
	public function getAfterCreationCmd() {
		return 'showContent';
	}

	/**
	 * 	@return string
	 */
	public function getStandardCmd() {
		return 'showContent';
	}

	/**
	 * 	Plugin command execution runpoint.
	 *
	 * 	The performCommand() method is called internally
	 * 	by ilias to handle a specific request action.
	 * 	The $cmd given as argument is used to identify
	 * 	the command to be executed.
	 *
	 * 	@param string $cmd	The command (method) to execute.
	 */
	public function performCommand($cmd) {
		/**
		 * @var $ilTabs ilTabsGUI
		 * @var $tpl    ilTemplate
		 */
		global $ilTabs, $tpl;
		$tpl->setDescription($this->object->getDescription());
		$next_class = $this->ctrl->getNextClass($this);
		switch ($next_class) {
			case 'ilmdeditorgui':
				$this->checkPermission('write');
				require_once 'Services/MetaData/classes/class.ilMDEditorGUI.php';
				$md_gui = new ilMDEditorGUI($this->object->getId(), 0, $this->object->getType());
				$md_gui->addObserver($this->object, 'MDUpdateListener', 'General');
				$ilTabs->setTabActive('meta_data');
				$this->ctrl->forwardCommand($md_gui);
				return;
				break;
			case 'ilcommonactiondispatchergui':
				require_once 'Services/Object/classes/class.ilCommonActionDispatcherGUI.php';
				$gui = ilCommonActionDispatcherGUI::getInstanceFromAjaxCall();
				$this->ctrl->forwardCommand($gui);
				break;
			case 'iltestoverviewexportgui':
				require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/TestOverview/classes/GUI/class.ilTestOverviewExportGUI.php';
				$csvMapper = new ilTestOverviewExportGUI($this, $this->object->getId());
				$ilTabs->setTabActive('export');
				$this->ctrl->forwardCommand($csvMapper);
				break;
			default:
				switch ($cmd) {
					case 'updateSettings':
					case 'updateMemberships':
					case 'initSelectTests':
					case 'initSelectExercise';
					case 'selectTests':
					case 'performAddTests':
					case 'performAddExcercise':
					case 'removeTests':
					case 'addMemberships':
					case 'removeMemberships':
					case 'addMembershipsEx':
					case 'removeMembershipsEx':
					case 'exportRedirect':
					case 'TestOverview':
					case 'ExerciseOverview':
					case 'excDiagramm':
					case 'editSettings':
						$this->checkPermission('write');
						$this->$cmd();
						break;
					case 'showContent':
					case 'applyOverviewFilter':
					case 'applyTestsFilter':
					case 'uebersicht':
					case 'subTabTO':
					case 'testPieChart':
					case 'subTabTO2':
					case 'subTabEO':
					case 'subTabEO1':
					case 'subTabEO2':
					case 'subTabEORanking':
					case 'rights':
					case 'applyGroupsFilter':
					case 'applyGroupsFIlterEx':
					case 'resetOverviewFilter':
					case 'resetTestsFilter':
					case 'applyGroupsFilterEx':
					case 'resetGroupsFilterEx':
					case 'resetGroupsFilter':
					case 'resetGroupsFilterEx':
					case 'applyExerciseFilter':
					case 'resetExerciseFilter':
					case 'applyExerciseFilterRanking':
					case 'resetExerciseFilterRanking':
					case 'addToDesk':
					case 'removeExercises':
					case 'triggerExport':
					case 'allLocalTests':
					case 'UserResults':
					case 'updateStudentView':
					case 'updateStudentViewEO':
					case 'resetStudentView':
					case 'resetStudentViewEO':
					case 'showRanking':
						$this->checkPermission('read');
						$this->UserResults();
					case 'removeFromDesk':

						if (in_array($cmd, array('addToDesk', 'removeFromDesk'))) {
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
	 * 	Configure the plugin tabs
	 *
	 * 	The setTabs() is called automatically by ILIAS
	 * 	to render the given tabs to the GUI.
	 * 	This is overloaded to set & configure
	 * 	the plugin-specific tabs.
	 */
	protected function setTabs() {


		/**
		 * @var $ilTabs   ilTabsGUI
		 * @var $ilCtrl   ilCtrl
		 * @var $ilAccess ilAccessHandler
		 */
		global $ilTabs, $ilCtrl, $ilAccess;
		$this->addInfoTab();

		/* Check for write access (editSettings available) */
		if ($ilAccess->checkAccess('write', '', $this->object->getRefId())) {
			$ilTabs->addTab('properties', $this->txt('properties'), $ilCtrl->getLinkTarget($this, 'editSettings'));
		}

		/* Check for read access */
		if ($ilAccess->checkAccess('read', '', $this->object->getRefId())) {
			$ilTabs->addTab('UserResults', $this->txt('userResults'), $ilCtrl->getLinkTarget($this, 'UserResults'));
		}

		/* Check for write access (editSettings available) */
		if ($ilAccess->checkAccess('write', '', $this->object->getRefId())) {
			$ilTabs->addTab('TestOverview', $this->txt('TestOverview'), $this->ctrl->getLinkTarget($this, 'TestOverview'));
			$ilTabs->addTab('ExerciseOverview', $this->txt('ExerciseOverview'), $this->ctrl->getLinkTarget($this, 'subTabEO'));
			$ilTabs->addTarget('meta_data', $this->ctrl->getLinkTargetByClass('ilmdeditorgui', ''), '', 'ilmdeditorgui');
		}
		// export
		if ($ilAccess->checkAccess('write', '', $this->object->getRefId())) {
			$ilTabs->addTarget('export', $this->ctrl->getLinkTargetByClass('iltestoverviewexportgui', 'export'), '', 'iltestoverviewexportgui');
		}

		$this->addPermissionTab();
	}

	/**
	 * 	Command for rendering a Test Overview.
	 *
	 * 	This command displays a test overview entry
	 * 	and its data. This method is called by
	 * 	@see self::performCommand().
	 */
	protected function showContent() {
		/**
		 * @var $tpl ilTemplate
		 * @var $ilTabs ilTabsGUI
		 */
		global $tpl, $lng, $ilTabs, $ilToolbar, $ilCtrl;
		$this->includePluginClasses(array(
			"ilTestOverviewTableGUI",
			"ilOverviewMapper"));
		/* Darstellung der Tabs */
		$this->subTabs("Test");
		$ilTabs->activateSubTab('content');
		$ilTabs->activateTab('TestOverview');
		$this->includePluginClasses(array(
			"ilTestOverviewTableGUI",
			"ilOverviewMapper"));
		/* Configure content UI */
		$ilMapper = new ilOverviewMapper;
		$table = new ilTestOverviewTableGUI($this, 'showContent');
		$table->setMapper($ilMapper)
				->populate();
		/* Populate template */
		$tpl->setDescription($this->object->getDescription());
		$data = array_slice($table->getData(), $table->getOffset(), $table->getLimit());
		$tpl->setContent($table->getHTML());
		$ilToolbar->addButton($this->txt('order_ranking'), $ilCtrl->getLinkTarget($this, 'showRanking'));
		$ilToolbar->addButton($this->txt('update_rank'), $ilCtrl->getLinkTarget($this, 'updateStudentView'));
		$ilToolbar->addButton($this->txt('delete_rank'), $ilCtrl->getLinkTarget($this, 'resetStudentView'));
	}

	/**
	 * Command for rendering the TestOverview 
	 * Table ordered by ranks.
	 * 
	 * @global type $tpl
	 * @global type $lng
	 * @global type $ilTabs
	 * @global type $ilToolbar
	 * @global type $ilCtrl
	 */
	protected function showRanking() {
		/**
		 * @var $tpl ilTemplate
		 * @var $ilTabs ilTabsGUI
		 */
		global $tpl, $lng, $ilTabs, $ilToolbar, $ilCtrl;
		$this->includePluginClasses(array(
			"ilTestOverviewTableGUI",
			"ilOverviewMapper"));
		/* Display of the tabs */
		$this->subTabs("Test");
		$ilTabs->activateSubTab('content');
		$ilTabs->activateTab('TestOverview');
		$this->includePluginClasses(array(
			"ilTestOverviewTableGUI",
			"ilOverviewMapper"));

		/* Get data to populate table */
		$ilMapper = new ilOverviewMapper;
		$table = new ilTestOverviewTableGUI($this, 'showRanking');
		$table->setMapper($ilMapper)->populate(true);
		/* Populate template */
		$tpl->setDescription($this->object->getDescription());
		$data = array_slice($table->getData(), $table->getOffset(), $table->getLimit());
		$tpl->setContent($table->getHTML());
		$table->getData();
		$ilToolbar->addButton($this->txt('orderName'), $ilCtrl->getLinkTarget($this, 'showContent'));
		$ilToolbar->addButton($this->txt('update_rank'), $ilCtrl->getLinkTarget($this, 'updateStudentView'));
		$ilToolbar->addButton($this->txt('delete_rank'), $ilCtrl->getLinkTarget($this, 'resetStudentView'));
	}

	/**
	 * This method is called to update the ranking of the User Result Tab.
	 * This method updates Test- and Exercise- Overview.  
	 * 
	 * @param type $view
	 */
	protected function updateStudentView() {
		global $ilCtrl;
		$this->includePluginClasses(array(
			"ilTestOverviewTableGUI",
			"ilOverviewMapper"));
		$ilMapper = new ilOverviewMapper();
		$table = new ilTestOverviewTableGUI($this, 'updateStudentView');
		$table->setMapper($ilMapper);
		$table->getStudentsRanked();
		ilUtil::sendSuccess($this->txt('success_update'), true);
		$ilCtrl->redirect($this, 'showContent');
	}

	/**
	 * Resets the TestOverview ranking. 
	 * 
	 * To reset the ExerciseOverview ranking use resetStudentViewEo() 
	 * 
	 * @global type $ilCtrl
	 */
	protected function resetStudentView() {
		global $ilCtrl;
		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/mapper/class.ilOverviewMapper.php';
		$ilMapper = new ilOverviewMapper();
		$ilMapper->resetRanks($this->object->getId());
		ilUtil::sendSuccess($this->txt('success_update'), true);

		$ilCtrl->redirect($this, 'showContent');
	}

	/**
	 * 	Render the settings page.
	 *
	 * 	The renderSettings() method can be passed directly
	 * 	to $tpl->setContent() as it renders the whole
	 * 	settings page.
	 *
	 * 	@return string
	 */
	protected function renderSettings() {

		return $this->form->getHTML();

	}

	/**
	 * 	Command for editing the settings of a Test Overview.
	 *
	 * 	This command provides a HTML form to edit the settings
	 * 	of the currently loaded Test Overview.
	 */
	protected function editSettings() {
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
		$tpl->setContent($this->renderSettings());
	}

	/**
	 * This method is called to render the User Results Tab.
	 * 
	 * @global type $tpl
	 * @global type $tpl
	 * @global type $ilTabs
	 * @global type $ilDB
	 * @global type $ilUser
	 */
	protected function UserResults() {
		global $tpl;
		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/mapper/class.ilOverviewStudent.php';

		global $tpl, $ilTabs, $ilDB, $ilUser;
		$ilTabs->activateTab('UserResults');
		$dataMapper = new studentMapper ();
		$tpl->setContent($dataMapper->getResults($ilUser->getId(), $this->object->getId()));
	}

	/* TABS FOR TEST OVERVIEW */

	protected function TestOverview() {
		global $tpl, $ilTabs, $ilCtrl, $ilToolbar;
		$this->showContent();
	}

	/**
	 * This method is called to render the diagramm tab.
	 * 
	 * @global type $tpl
	 * @global type $ilTabs
	 * @global type $ilCtrl
	 * @global type $lng
	 * @global type $ilToolbar
	 * @global type $ilDB
	 */
	protected function subTabTO() {
		global $tpl, $ilTabs, $ilCtrl, $lng, $ilToolbar, $ilDB;

		$this->subTabs("Test");
		$ilTabs->activateTab('TestOverview');
		$ilTabs->activateSubTab('subTabTO');

		require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/TestOverview/classes/mapper/class.ilBinDiagrammMapper.php';
		try {
			$Obj = new BinDiagrammMapper($this, 'showContent');
			$tpl->setContent($Obj->createAverageDia("BARS"));
		} catch (Exception $ex) {
			$tpl->setContent("Diagramm can not be Created");
		}
	}

	/**
	 * Renders the TestOverview Administration
	 * 
	 * @global type $tpl
	 * @global type $ilTabs
	 * @global type $ilCtrl
	 * @global type $ilToolbar
	 */
	protected function subTabTO2() {

		global $tpl, $ilTabs, $ilCtrl, $ilToolbar;

		$this->subTabs("Test");
		$ilTabs->activateTab('TestOverview');
		$ilTabs->activateSubTab('subTabTO2');

		$tpl->setContent($this->getTestList()->getHTML() . $this->getMembershipList()->getHTML());
	}

	/* TABS FOR EXERCISE OVERVIEW */

	/**
	 * Render the Exercise Diagramms
	 */
	protected function ExerciseOverview() {
		global $tpl, $ilTabs, $ilCtrl, $ilToolbar;
		$ilTabs->activateTab('ExerciseOverview');
		$this->subTabs("Exercise");
		$ilTabs->activateSubTab('subTabEO1');

		require_once 'Services/Form/classes/class.ilTextInputGUI.php';
		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/mapper/class.ilBinDiagrammMapper.php';

		$attachment2 = new ilTextInputGUI("SizeBucket", "sizeBucket");
		$ilToolbar->setFormAction($ilCtrl->getLinkTarget($this, 'ExerciseOverview'), true);

		$ilToolbar->addInputItem($attachment2);
		$ilToolbar->addFormButton($this->txt("make_diagram"), "");

		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/mapper/class.ilExerciseMapper.php';
		if ($_POST["sizeBucket"] != null && $_POST["sizeBucket"] != 0) {
			$Obj = new exerciseCharts(30000, $this->object->getId(), $_POST["sizeBucket"]);
			$tpl->setContent($Obj->getHTML());
		} else {
			$tpl->setContent($this->txt("max_diagram_value") . " &ne; 0");
		}
	}

	/**
	 * This methode is called to render the Exercise Overview Table.
	 * 
	 * @global type $tpl
	 * @global type $ilTabs
	 * @global type $ilCtrl
	 * @global type $ilToolbar
	 */
	protected function subTabEO() {
		global $tpl, $ilTabs, $ilCtrl, $ilToolbar;
		$this->subTabs("Exercise");
		$ilTabs->activateTab('ExerciseOverview');
		$ilTabs->activateSubTab('subTabEO');
		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/GUI/class.ilMappedTableGUI.php';
		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/GUI/class.ilExerciseOverviewTableGUI.php';
		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/mapper/class.ilExerciseMapper.php';

		$ilExerciseMapper = new ilExerciseMapper;
		$table = new ilExerciseOverviewTableGUI($this, 'subTabEO');
		$table->setMapper($ilExerciseMapper)->populateE(true);
		/* Populate template */
		$tpl->setDescription($this->object->getDescription());
		$data = array_slice($table->getData(), $table->getOffset(), $table->getLimit());
		$tpl->setContent($table->getHTML());
		$ilToolbar->addButton($this->txt('order_ranking'), $ilCtrl->getLinkTarget($this, 'subTabEORanking'));
		$ilToolbar->addButton($this->txt('update_rank'), $ilCtrl->getLinkTarget($this, 'updateStudentViewEO'));
		$ilToolbar->addButton($this->txt('delete_rank'), $ilCtrl->getLinkTarget($this, 'resetStudentViewEO'));
	}

	/**
	 * This method redirects to the ExerciseOverview table
	 * 
	 * @global type $tpl
	 * @global type $ilTabs
	 * @global type $ilCtrl
	 * @global type $ilToolbar
	 */
	protected function subTabEO1() {
		global $tpl, $ilTabs, $ilCtrl, $ilToolbar;

		$this->subTabs("Exercise");
		$ilTabs->activateTab('ExerciseOverview');
		$ilTabs->activateSubTab('subTabEO1');
		$ilCtrl->redirect($this, 'ExerciseOverview');
	}

	/**
	 * Renders the tab for Exercise Settings
	 */
	protected function subTabEO2() {
		global $tpl, $ilTabs, $ilCtrl, $ilToolbar;

		$this->subTabs("Exercise");
		$ilTabs->activateTab('ExerciseOverview');
		$ilTabs->activateSubTab('subTabEO2');

		$tpl->setContent($this->getExerciseList()->getHtml() . $this->getMembershipListEx()->getHTML());
	}

	/**
	 * Renders the Exercise Table ordered by the rank of the students
	 * 
	 * @global type $tpl
	 * @global type $ilTabs
	 * @global type $ilCtrl
	 * @global type $ilToolbar
	 */
	protected function subTabEORanking() {
		global $tpl, $ilTabs, $ilCtrl, $ilToolbar;
		$this->subTabs("Exercise");
		$ilTabs->activateTab('ExerciseOverview');
		$ilTabs->activateSubTab('subTabEO');
		//$tpl->setContent("ranking stuff");
		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/GUI/class.ilMappedTableGUI.php';
		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/GUI/class.ilExerciseOverviewTableGUI.php';
		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/mapper/class.ilExerciseMapper.php';

		$ilExerciseMapper = new ilExerciseMapper;
		$table = new ilExerciseOverviewTableGUI($this, 'subTabEO');
		$table->setMapper($ilExerciseMapper)->populateE(false);
		/* Populate template */
		$tpl->setDescription($this->object->getDescription());
		$data = array_slice($table->getData(), $table->getOffset(), $table->getLimit());
		$tpl->setContent($table->getHTML());
		$ilToolbar->addButton($this->txt('orderName'), $ilCtrl->getLinkTarget($this, 'subTabEO'));
		$ilToolbar->addButton($this->txt('update_rank'), $ilCtrl->getLinkTarget($this, 'updateStudentViewEO'));
		$ilToolbar->addButton($this->txt('delete_rank'), $ilCtrl->getLinkTarget($this, 'resetStudentViewEO'));
	}

	/**
	 * Update UserResults. This method retrieve data from the table and saves it in the database.
	 * 
	 * @global type $ilCtrl
	 */
	protected function updateStudentViewEO() {
		global $ilCtrl;
		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/GUI/class.ilMappedTableGUI.php';
		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/GUI/class.ilExerciseOverviewTableGUI.php';
		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/mapper/class.ilExerciseMapper.php';
		$ilExerciseMapper = new ilExerciseMapper;
		$table = new ilExerciseOverviewTableGUI($this, 'updateStudentViewEO');
		$table->setMapper($ilExerciseMapper);
		$table->getStudentsRanked();
		ilUtil::sendSuccess($this->txt('success_update'), true);
		$ilCtrl->redirect($this, 'subTabEO');
	}

	/**
	 * Resets the ranking for exercises. 
	 * 
	 * To reset ranking for Tests use resetStudentView()
	 * 
	 * @global type $ilCtrl
	 */
	protected function resetStudentViewEO() {
		global $ilCtrl;
		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/mapper/class.ilExerciseMapper.php';
		$ilMapper = new ilExerciseMapper;
		$ilMapper->resetRanks($this->object->getId());
		ilUtil::sendSuccess($this->txt('success_update'), true);
		$ilCtrl->redirect($this, 'subTabEO');
	}

	/**
	 * Adds subtabs.
	 * 
	 * @global type $ilTabs
	 * @global type $ilCtrl
	 * @param type $type
	 */
	protected function subTabs($type) {
		global $ilTabs, $ilCtrl;
		switch ($type) {
			case 'Exercise':
				$ilTabs->addSubTab('subTabEO', $this->txt("exercise_overview"), $ilCtrl->getLinkTarget($this, 'subTabEO'));
				$ilTabs->addSubTab('subTabEO1', $this->txt("diagram"), $ilCtrl->getLinkTarget($this, 'subTabEO1'));
				$ilTabs->addSubTab('subTabEO2', $this->txt("exercise_administration"), $ilCtrl->getLinkTarget($this, 'subTabEO2'));
				break;

			case 'Test':
				$ilTabs->addSubTab('content', $this->txt("test_overview"), $this->ctrl->getLinkTarget($this, 'showContent'));
				$ilTabs->addSubTab('subTabTO', $this->txt("diagram"), $this->ctrl->getLinkTarget($this, 'subTabTO'));
				$ilTabs->addSubTab('subTabTO2', $this->txt("test_administration"), $this->ctrl->getLinkTarget($this, 'subTabTO2'));
				break;
		}
	}

	/**
	 * 	Command for saving the updated Test Overview settings.
	 *
	 * 	This command saves the HTML form input into the Test Overview
	 * 	currently selected.
	 */
	protected function updateSettings() {
		/**
		 * @var $tpl    ilTemplate
		 * @var $lng    ilLanguage
		 * @var $ilCtrl ilCtrl
		 */
		global $tpl, $lng, $ilCtrl;
		$this->initSettingsForm();
		if ($this->form->checkInput()) {
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
		$tpl->setContent($this->renderSettings());
	}

	/**
	 * Initializeses the XML for Exercise Selection 
	 * Writes the information in a Session Parameter
	 */
	public function initSelectExercise() {

		global $tree;
		// empty session on init
		$_SESSION['select_exercise'] = array();
		// copy opend nodes from repository explorer
		$_SESSION['select_exercise'] = is_array($_SESSION['repexpand']) ? $_SESSION['repexpand'] : array();
		// open current position
		$path = $tree->getPathId((int) $_GET['ref_id']);

		foreach ((array) $path as $node_id) {
			if (!in_array($node_id, $_SESSION['select_exercise'])) {
				$_SESSION['select_exercise'][] = $node_id;
			}
		}
		$this->selectExercises(); //(int)$_GET['select_exercise']);
		return;
	}

	/**
	 * Creates the exercise select GUI with the nodes representing all course-objects, folders, groups and exercises 
	 *
	 */
	public function selectExercises() {

		global $tpl, $lng, $ilCtrl, $ilTabs, $ilToolbar;
		$ilTabs->activateTab('ExerciseOverview');
		$ilToolbar->addButton($this->lng->txt('cancel'), $ilCtrl->getLinkTarget($this, 'subTabEO2'));
		$tpl->addBlockfile('ADM_CONTENT', 'adm_content', 'tpl.paste_into_multiple_objects.html', 'Services/Object');
		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/class.ilTestOverviewExerciseSelectionExplorer.php';
		$exp = new ilTestOverviewExerciseSelectionExplorer('select_exercise');
		$exp->setExpandTarget($ilCtrl->getLinkTarget($this, 'initSelectExercise'));
		$exp->setTargetGet('ref_id');
		$exp->setPostVar('nodes[]');
		$exp->highlightNode((int) $_GET['ref_id']);
		$exp->setCheckedItems(
				is_array($_POST['nodes']) ? (array) $_POST['nodes'] : array()
		);
		$tpl->setVariable('FORM_TARGET', '_top');
		$tpl->setVariable('FORM_ACTION', $ilCtrl->getFormAction($this, 'performAddExercise'));
		$exp->setExpand(
				isset($_GET['select_exercise']) && (int) $_GET['select_exercise'] ?
						(int) $_GET['select_exercise'] :
						$this->tree->readRootId()
		);

		$exp->setDefaultHiddenObjects($this->object->getUniqueExercises(true));
		$exp->setOutput(0);
		$tpl->setVariable('OBJECT_TREE', $exp->getOutput());
		$tpl->setVariable('CMD_SUBMIT', 'performAddExcercise');
		$tpl->setVariable('TXT_SUBMIT', $lng->txt('select'));
	}

	public function initSelectTests() {
		/**
		 * @var $tree ilTree
		 */
		global $tree;
		// empty session on init
		$_SESSION['select_tovr_expanded'] = array();
		// copy opend nodes from repository explorer
		$_SESSION['select_tovr_expanded'] = is_array($_SESSION['repexpand']) ? $_SESSION['repexpand'] : array();
		// open current position
		$path = $tree->getPathId((int) $_GET['ref_id']);
		foreach ((array) $path as $node_id) {
			if (!in_array($node_id, $_SESSION['select_tovr_expanded']))
				$_SESSION['select_tovr_expanded'][] = $node_id;
		}
		$this->selectTests();
		return;
	}

	public function selectTests() {
		/**
		 * @var $tpl    ilTemplate
		 * @var $lng    ilLanguage
		 * @var $ilCtrl ilCtrl
		 * @var $ilTabs ilTabsGUI
		 * @var $ilToolbar ilToolbarGUI
		 */
		global $tpl, $lng, $ilCtrl, $ilTabs, $ilToolbar;
		$ilTabs->activateTab('TestOverview');
		$ilToolbar->addButton($this->lng->txt('cancel'), $ilCtrl->getLinkTarget($this, 'subTabTO2'));
		$tpl->addBlockfile('ADM_CONTENT', 'adm_content', 'tpl.paste_into_multiple_objects.html', 'Services/Object');
		$this->includePluginClasses(array('ilTestOverviewTestSelectionExplorer'));
		$exp = new ilTestOverviewTestSelectionExplorer('select_tovr_expanded');
		$exp->setExpandTarget($ilCtrl->getLinkTarget($this, 'selectTests'));
		$exp->setTargetGet('ref_id');
		$exp->setPostVar('nodes[]');
		$exp->highlightNode((int) $_GET['ref_id']);
		$exp->setCheckedItems(
				is_array($_POST['nodes']) ? (array) $_POST['nodes'] : array()
		);
		$tpl->setVariable('FORM_TARGET', '_top');
		$tpl->setVariable('FORM_ACTION', $ilCtrl->getFormAction($this, 'performAddTests'));
		$exp->setExpand(
				isset($_GET['select_tovr_expanded']) && (int) $_GET['select_tovr_expanded'] ?
						(int) $_GET['select_tovr_expanded'] :
						$this->tree->readRootId()
		);
		$exp->setDefaultHiddenObjects($this->object->getUniqueTests(true));
		$exp->setOutput(0);
		$tpl->setVariable('OBJECT_TREE', $exp->getOutput());
		$tpl->setVariable('CMD_SUBMIT', 'performAddTests');
		$tpl->setVariable('TXT_SUBMIT', $lng->txt('select'));
	}
	/**
	 *
	 * Add the chosen exercises into the table rep_robj_xtov_e2o
	 *  
	 * @global type $tpl
	 * @global type $lng
	 * @global type $ilCtrl
	 * @global type $ilAccess
	 *
	 */
	public function performAddExcercise() {

		global $tpl, $lng, $ilCtrl, $ilAccess;

		include_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . "/classes/mapper/class.ilExerciseImport.php";
		$mapper = new ExerciseImport ();
		$overviewId = $this->object->getId();

		if (!isset($_POST['nodes']) || !is_array($_POST['nodes']) || !$_POST['nodes']) {
			ilUtil::sendFailure($lng->txt('select_one'));
			$this->selectExercises();
			return;
		}
		$num_nodes = 0;
		if ($_POST['nodes'] != null) {
			foreach ($_POST['nodes'] as $ref_id) {
				$mapper->createEntry($overviewId, $ref_id);
				++$num_nodes;
			}
		}
		if (!$num_nodes) {
			ilUtil::sendFailure($lng->txt('select_one'));
			$this->selectExercises();
			return;
		}
		ilUtil::sendSuccess($this->txt('exercises_updated_success'), true);
		$ilCtrl->redirect($this, 'subTabEO2');
	}
	
	/**
	 * 
	 * Add the chosen tests into the table rep_robj_xtov_t2o
	 * 
	 * @global type $lng
	 * @global type $ilCtrl
	 * @global type $ilAccess
	 * 
	 */
	public function performAddTests() {
		/**
		 * @var $lng      ilLanguage
		 * @var $ilCtrl   ilCtrl
		 * @var $ilAccess ilAccessHandler
		 */
		global $lng, $ilCtrl, $ilAccess;

		if (!isset($_POST['nodes']) || !is_array($_POST['nodes']) || !$_POST['nodes']) {
			ilUtil::sendFailure($lng->txt('select_one'));
			$this->selectTests();
			return;
		}
		$num_nodes = 0;
		foreach ($_POST['nodes'] as $ref_id) {
			if ($ilAccess->checkAccess('tst_statistics', '', $ref_id) || $ilAccess->checkAccess('write', '', $ref_id)) {
				$this->object->addTest($ref_id);
				++$num_nodes;
			}
		}
		if (!$num_nodes) {
			ilUtil::sendFailure($lng->txt('select_one'));
			$this->selectTests();
			return;
		}
		ilUtil::sendSuccess($this->txt('tests_updated_success'), true);

		$ilCtrl->redirect($this, 'subTabTO2');
	}
	
	/**
	 * 
	 * Removes the chosen test-entries in the rep_robj_xtov_t2o table 
	 * 
	 * @global type $tpl
	 * @global type $lng
	 * @global type $ilCtrl
	 */
	public function removeTests() {
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
				$this->object->rmTest($testId);
			}
			ilUtil::sendSuccess($lng->txt('rep_robj_xtov_tests_updated_success'), true);
			$ilCtrl->redirect($this, 'subTabTO2');
		}
		ilUtil::sendFailure($lng->txt('rep_robj_xtov_min_one_check_test'), true);
		//$tpl->setContent($this->renderSettings());
		$ilCtrl->redirect($this, 'subTabTO2');
	}
	
	/**
	 * 
	 * Removes the chosen exercise-entries in the rep_robj_xtov_e2o table 
	 * 
	 * @global type $tpl
	 * @global type $lng
	 * @global type $ilCtrl
	 */
	public function removeExercises() {
		/**
		 * @var $tpl    ilTemplate
		 * @var $lng    ilLanguage
		 * @var $ilCtrl ilCtrl
		 */
		global $tpl, $lng, $ilCtrl;
		if (isset($_POST['exercise_ids'])) {
			foreach ($_POST['exercise_ids'] as $exerciseID) {
				$this->object->rmExercise($exerciseID);
			}
			ilUtil::sendSuccess($lng->txt('rep_robj_xtov_tests_updated_success'), true);
			$ilCtrl->redirect($this, 'subTabEO2');
		}
		ilUtil::sendFailure($lng->txt('rep_robj_xtov_min_one_check_exercise'), true);
		//$tpl->setContent($this->renderSettings());
		$ilCtrl->redirect($this, 'subTabEO2');
	}

	/**
	 * 	Command for updating the participants groups added to the overview.
	 *
	 * 	This command is executed when the ilMembershipListTableGUI
	 * 	is submitted.
	 */
	protected function updateMemberships() {
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
		if (isset($_POST['membership_ids']) || !empty($overviewGroups)) {
			if (!isset($_POST['membership_ids']))
				$_POST['membership_ids'] = array();
			/* Executing the registered test retrieval again with the same filters
			  allows to determine which tests are really removed. */
			include_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
							->getDirectory() . "/classes/mapper/class.ilMembershipMapper.php";
			$mapper = new ilMembershipMapper;
			$displayedIds = array();
			$displayedGroups = $mapper->getList(array(), $this->getMembershipList()->filter);
			foreach ($displayedGroups['items'] as $grp) {
				$displayedIds[] = $grp->obj_id;
			}
			$displayedIds = array_intersect($displayedIds, array_keys($overviewGroups));
			/* Check for deleted/added IDs and execute corresponding routine. */
			$deletedIds = array_diff($displayedIds, $_POST['membership_ids']);
			$addedIds = array_diff($_POST['membership_ids'], array_keys($overviewGroups));
			foreach ($deletedIds as $groupId) {
				$this->object
						->rmGroup($groupId);
			}
			foreach ($addedIds as $groupId) {
				$this->object
						->addGroup($groupId);
			}
			ilUtil::sendSuccess($lng->txt('rep_robj_xtov_memberships_updated_success'), true);
			/* redirect umgeleitet für neues to */
			$ilCtrl->redirect($this, 'subTabTO2');
		}
		ilUtil::sendFailure($lng->txt('rep_robj_xtov_min_one_check_membership'), true);
		$ilCtrl->redirect($this, 'subTabTO2');
	}
	/**
	 * Adds the chosen group_id's into the rep_robj_xtov_p2o
	 * 
	 * @global type $tpl
	 * @global type $lng
	 * @global type $ilCtrl
	 */
	public function addMemberships() {
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
						->addGroup($groupId);
			}
			ilUtil::sendSuccess($lng->txt('rep_robj_xtov_memberships_updated_success'), true);
			$ilCtrl->redirect($this, 'subTabTO2');
		}
		ilUtil::sendFailure($lng->txt('rep_robj_xtov_min_one_check_membership'), true);
		$ilCtrl->redirect($this, 'subTabTO2');
	}
	
	/**
	 * Removes the chosen group_id's from rep_robj_xtov_p2o
	 * 
	 * @global type $tpl
	 * @global type $lng
	 * @global type $ilCtrl
	 */
	public function removeMemberships() {
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
			foreach ($_POST['membership_ids'] as $containerId) {
				$this->object->rmGroup($containerId);
			}
			ilUtil::sendSuccess($lng->txt('rep_robj_xtov_memberships_updated_success'), true);
			$ilCtrl->redirect($this, 'subTabTO2');
		}

		ilUtil::sendFailure($lng->txt('rep_robj_xtov_min_one_check_membership'));
		//$tpl->setContent( $this->renderSettings() );
		$ilCtrl->redirect($this, 'subTabTO2');
	}

	/**
	 * 	Retrieve the plugin's creations forms.
	 *
	 * 	This method is called internally by ilias
	 * 	to retrieve the creation-, cloning- [and
	 * 	optionally the import-] forms.
	 *
	 * 	@param	string	$a_new_type	Key name of the plugin
	 * 	@return	array
	 */
	protected function initCreationForms($a_new_type) {
		$forms = array(
			self::CFORM_NEW => $this->initCreateForm($a_new_type),
			self::CFORM_CLONE => $this->fillCloneTemplate(null, $a_new_type)
		);
		return $forms;
	}

	/**
	 * 	Retrieve the creation form.
	 *
	 * 	This method is only overloaded to provide the
	 * 	extension possibility.
	 *
	 * 	@param	string	$a_new_type	Key name of the plugin
	 * 	@return ilFormGUI
	 */
	public function initCreateForm($a_new_type) {
		$form = parent::initCreateForm($a_new_type);
		return $form;
	}

	/**
	 * 	Configure the displayed form for Settings edition.
	 *
	 * 	This method is called internally by @see
	 */
	protected function initSettingsForm() {
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
		$this->form->addItem($ti);
		$this->form->addItem($ta);
		$this->form->addCommandButton('updateSettings', $this->txt('save'));
	}

	/**
	 * 	Populate the Test Overview settings.
	 *
	 * 	This method is called internally by
	 * 	@see self::editSettings() to fill the form
	 * 	by the current settings' values.
	 */
	protected function populateSettings() {
		$values['title'] = $this->object->getTitle();
		$values['desc'] = $this->object->getDescription();
		$this->form->setValuesByArray($values);
	}

	/**
	 * 	Apply a filter to the overview table.
	 *
	 * 	The applyOverviewFilter() method is used as a command
	 * 	to apply (re-populate) and save the filters.
	 */
	public function applyOverviewFilter() {
		$this->includePluginClasses(array(
			"ilTestOverviewTableGUI"));
		$table = new ilTestOverviewTableGUI($this, 'showContent');
		$table->resetOffset();
		$table->writeFilterToSession();
		$this->showContent();
	}

	/**
	 * 	Apply a filter to the tests list table.
	 *
	 * 	The applyTestsFilter() method is used as a command
	 * 	to apply (re-populate) and save the filters.
	 */
	public function applyTestsFilter() {
		$this->includePluginClasses(array(
			"ilTestListTableGUI"));
		$table = new ilTestListTableGUI($this, 'subTabTO2');
		$table->resetOffset();
		$table->writeFilterToSession();
		$this->subTabTO2();
	}

	/**
	 * 	Apply a filter to the groups list table.
	 *
	 * 	The applyGroupFilter() method is used as a command
	 * 	to apply (re-populate) and save the filters.
	 */
	public function applyGroupsFilter() {
		$this->includePluginClasses(array(
			"ilMembershipListTableGUI"));
		$table = new ilMembershipListTableGUI($this, 'subTabTO2');
		$table->resetOffset();
		$table->writeFilterToSession();
		$this->subTabTO2();
	}

	/**
	 * 	Reset the overview filters
	 *
	 * 	This method is used as a command (form submit handler)
	 * 	to reset the filters set on the overview table.
	 */
	public function resetOverviewFilter() {
		$this->includePluginClasses(array(
			"ilTestOverviewTableGUI"));
		$table = new ilTestOverviewTableGUI($this, 'editSettings');
		$table->resetOffset();
		$table->resetFilter();
		$this->showContent();
	}

	/**
	 * 	Reset the tests list filters
	 *
	 * 	This method is used as a command (form submit handler)
	 * 	to reset the filters set on the tests list table.
	 */
	public function resetTestsFilter() {
		$this->includePluginClasses(array(
			"ilTestListTableGUI"));
		$table = new ilTestListTableGUI($this, 'subTabTO2');
		$table->resetOffset();
		$table->resetFilter();
		$this->subTabTO2();
	}

	/**
	 * 	Reset the tests list filters
	 *
	 * 	This method is used as a command (form submit handler)
	 * 	to reset the filters set on the tests list table.
	 */
	public function resetExerciseFilterRanking() {
		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/GUI/class.ilMappedTableGUI.php';
		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/GUI/class.ilExerciseOverviewTableGUI.php';

		$table = new ilExerciseOverviewTableGUI($this, 'subTabTORanking');
		$table->resetOffset();
		$table->resetFilter();
		$this->subTabEORanking();
	}

	/**
	 * 	Apply a filter to the exercise list table.
	 *
	 * 	The applyExerciseFilter() method is used as a command
	 * 	to apply (re-populate) and save the filters.
	 */
	public function applyExerciseFilterRanking() {
		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/GUI/class.ilMappedTableGUI.php';
		require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
						->getDirectory() . '/classes/GUI/class.ilExerciseOverviewTableGUI.php';

		$table = new ilExerciseOverviewTableGUI($this, 'subTabTORanking');
		$table->resetOffset();
		$table->writeFilterToSession();
		$this->subTabEORanking();
	}

	/**
	 * 	Apply a filter to the exercise list table.
	 *
	 * 	The applyExerciseFilter() method is used as a command
	 * 	to apply (re-populate) and save the filters.
	 */
	public function applyExerciseFilter() {
		$this->includePluginClasses(
				array("ilExerciseListTableGUI"));
		$table = new ilExerciseListTableGUI($this, "subTabEO2");
		$table->resetOffset();
		$table->writeFilterToSession();
		$this->subTabEO2();
	}

	/**
	 * 	Reset the exercise list filters
	 *
	 * 	This method is used as a command (form submit handler)
	 * 	to reset the filters set on the exercise list table.
	 */
	public function resetExerciseFilter() {
		$this->includePluginClasses(
				array("ilExerciseListTableGUI"));
		$table = new ilExerciseListTableGUI($this, "subTabEO2");
		$table->resetOffset();
		$table->resetFilter();
		$this->subTabEO2();
	}

	/**
	 * 	Reset the groups list filters
	 *
	 * 	This method is used as a command (form submit handler)
	 * 	to reset the filters set on the groups list table.
	 */
	public function resetGroupsFilter() {
		$this->includePluginClasses(array(
			"ilMembershipListTableGUI"));
		$table = new ilMembershipListTableGUI($this, 'subTabTO2');
		$table->resetOffset();
		$table->resetFilter();
		$this->subTabTO2();
	}

	/**
	 * 	Retrieve the tests list table.
	 *
	 * 	The getTestList() method should be used to
	 * 	retrieve the GUI object responsible for listing
	 * 	the tests which can be added to the overview.
	 *
	 * 	@return ilTestListTableGUI
	 */
	protected function getTestList() {
		$this->includePluginClasses(array(
			"ilTestListTableGUI",
			"ilTestMapper"));
		$testList = new ilTestListTableGUI($this, 'subTabTO2');
		$testList->setMapper(new ilTestMapper)
				->populate();
		return $testList;
	}

	/**
	 * 	Retrieve the exercise list table.
	 *
	 * 	The getExerciseList() method should be used to
	 * 	retrieve the GUI object responsible for listing
	 * 	the exercises which can be added to the overview.
	 *
	 * 	@return ilTestListTableGUI
	 */
	protected function getExerciseList() {
		$this->includePluginClasses(array(
			"ilExerciseListTableGUI",
			"ilExerciseSettingsMapper"));
		$Obj = new ilExerciseListTableGUI($this, 'subTabEO2');
		$Obj->setMapper(new ilExerciseSettingsMapper())
				->populate();
		return $Obj;
	}

	/**
	 * 	Retrieve the memberships list table.
	 *
	 * 	The getMembershipList() method should be used to
	 * 	retrieve the GUI object responsible for listing
	 * 	the participants groups which can be added to
	 * 	the overview.
	 *
	 * 	@return ilMembershipListTableGUI
	 */
	protected function getMembershipList() {
		$this->includePluginClasses(array(
			"ilMembershipListTableGUI",
			"ilMembershipMapper"));
		$testList = new ilMembershipListTableGUI($this, 'subTabTO2');
		$testList->setMapper(new ilMembershipMapper)
				->populate();
		return $testList;
	}

	protected function getMembershipListEx() {
		$this->includePluginClasses(array(
			"ilMembershipListTableGUI",
			"ilMembershipMapper"));
		$testList = new ilMembershipListTableGUI($this, 'subTabEO2');
		$testList->setMapper(new ilMembershipMapper)
				->populate();
		return $testList;
	}

	/**
	 * 	Apply a filter to the groups list table.
	 *
	 * 	The applyGroupFilter() method is used as a command
	 * 	to apply (re-populate) and save the filters.
	 */
	public function applyGroupsFilterEx() {
		$this->includePluginClasses(array(
			"ilMembershipListTableGUI"));
		$table = new ilMembershipListTableGUI($this, 'subTabEO2');
		$table->resetOffset();
		$table->writeFilterToSession();
		$this->subTabEO2();
	}

	/**
	 * 	Reset the groups list filters
	 *
	 * 	This method is used as a command (form submit handler)
	 * 	to reset the filters set on the groups list table.
	 */
	public function resetGroupsFilterEx() {
		$this->includePluginClasses(array(
			"ilMembershipListTableGUI"));
		$table = new ilMembershipListTableGUI($this, 'subTabEO2');
		$table->resetOffset();
		$table->resetFilter();
		$this->subTabEO2();
	}

	public function addMembershipsEx() {
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
						->addGroup($groupId);
			}
			ilUtil::sendSuccess($lng->txt('rep_robj_xtov_memberships_updated_success'), true);
			$ilCtrl->redirect($this, 'subTabEO2');
		}
		ilUtil::sendFailure($lng->txt('rep_robj_xtov_min_one_check_membership'), true);
		$ilCtrl->redirect($this, 'subTabEO2');
		//$tpl->setContent( $this->renderSettings() );
	}

	public function removeMembershipsEx() {
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
			foreach ($_POST['membership_ids'] as $containerId) {
				$this->object->rmGroup($containerId);
			}
			ilUtil::sendSuccess($lng->txt('rep_robj_xtov_memberships_updated_success'), true);
			$ilCtrl->redirect($this, 'subTabEO2');
		}

		ilUtil::sendFailure($lng->txt('rep_robj_xtov_min_one_check_membership'));
		//$tpl->setContent( $this->renderSettings() );
		$ilCtrl->redirect($this, 'subTabEO2');
	}

	/**
	 * 	Include a class implemented by this plugin.
	 *
	 * 	The includePluginClasses() method can be used to
	 * 	include classes which are located in the plugin's
	 * 	directory.
	 *
	 * 	@params	array	$classes	List of classes to be included
	 *
	 * 	@throws InvalidArgumentException on invalid class name. (File not found)
	 */
	private function includePluginClasses(array $classes) {
		$plugin = ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview');
		$pluginDirectory = $plugin->getDirectory();
		foreach ($classes as $class) {
			if (class_exists($class))
				continue;
			$additionalFolder = "";
			if (strrpos($class, "Mapper") !== false) {
				/* Custom mapper classes */
				$additionalFolder = "mapper/";
			} elseif (strpos($class, "ilObj") === false && strrpos($class, "GUI") !== false) {
				/* Custom GUI classes (Not a plugin GUI controller) */
				$additionalFolder = "GUI/";
			}
			$classFile = $pluginDirectory . "/classes/{$additionalFolder}class.$class.php";
			if (!file_exists($classFile))
				throw new InvalidArgumentException;
			require_once $classFile;
		}
	}

	/**
	 * @param string $a_sub_type
	 * @param int    $a_sub_id
	 * @return ilObjectListGUI|ilObjTestOverviewListGUI
	 */
	protected function initHeaderAction($a_sub_type = null, $a_sub_id = null) {
		/**
		 * @var $ilUser ilObjUser
		 */
		global $ilUser;
		$lg = parent::initHeaderAction();
		if ($lg instanceof ilObjTestOverviewListGUI) {
			if ($ilUser->getId() != ANONYMOUS_USER_ID) {
				// Maybe handle notifications in future ...
			}
		}
		return $lg;
	}

	/**
	 * @see ilDesktopItemHandling::addToDesk()
	 */
	public function addToDeskObject() {
		/**
		 * @var $ilSetting ilSetting
		 * @var $lng       ilLanguage
		 */
		global $ilSetting, $lng;
		if ((int) $ilSetting->get('disable_my_offers')) {
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
	public function removeFromDeskObject() {
		global $ilSetting, $lng;
		if ((int) $ilSetting->get('disable_my_offers')) {
			$this->showContent();
			return;
		}
		include_once './Services/PersonalDesktop/classes/class.ilDesktopItemGUI.php';
		ilDesktopItemGUI::removeFromDesktop();
		ilUtil::sendSuccess($lng->txt('removed_from_desktop'));
		$this->showContent();
	}

}
