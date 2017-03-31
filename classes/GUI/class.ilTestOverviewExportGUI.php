<?php

/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * 	@package	TestOverview repository plugin
 * 	@category	Core
 * 	@author		Benedict Steuerlein <st111340@stud.uni-stuttgart.de>
 *  
 * The class creates a CSV file from the User Data that can be downloaded 
 */

/**
 * @ilCtrl_isCalledBy ilTestOverviewExportGUI: ilObjTestOverviewGUI
 */
class ilTestOverviewExportGUI extends ilObjTestOverviewGUI {

	/**
	 * 	@var ilPropertyFormGUI
	 */
	protected $form;
	protected $parent;
	protected $id;


	/**
	 * Constructor
	 */
	public function __construct($a_parent_gui, $id, $a_main_object = null) {
		parent::__construct($a_parent_gui, $a_main_object);
		$this->parent = $a_parent_gui;
		$this->id = $id;
	}

	function &executeCommand() {
		global $ilCtrl;
		$next_class = $ilCtrl->getNextClass($this);
		$cmd = $ilCtrl->getCmd();
		switch ($next_class) {
			default :
				switch ($cmd) {

					default: $this->$cmd();
						break;
				}
		}
	}

	/**
	 * initialize the export form and render the template
	 */
	public function export() {


		/* initialize Export form */
		$this->initExportForm();

		/* Populate template */
		$this->tpl->setContent($this->form->getHTML());
	}

	/**
	 * 
	 * @global type $tpl
	 * @global type $lng
	 * @global type $ilCtrl
	 * 
	 * Command that is triggered when the 'Export'-button is pressed
	 */
	protected function triggerExport() {
		$this->initExportForm();
		if ($this->form->checkInput()) {

			$export_type = $this->form->getInput("export_type");

			require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/TestOverview/classes/class.ilTestOverviewExport.php';
			$to_exp = new ilTestOverviewExport($this->parent, $this->id, $export_type);
			$to_exp->buildExportFile();
		}
	}

	/**
	 * 
	 * @global type $ilCtrl
	 * @global type $tpl
	 * @global type $lng
	 * 
	 * inintialize the export form
	 */
	public function initExportForm() {

		global $ilCtrl, $tpl, $lng;
		include_once("./Services/Form/classes/class.ilPropertyFormGUI.php");

		$this->form = new ilPropertyFormGUI();
		$this->form->setTitle("Export " . $this->txt("properties"));
		$this->form->setFormAction($ilCtrl->getFormAction($this));

		//radio group: Export type
		$checkbox_overview = new ilRadioGroupInputGUI("Type", "export_type");
		$overview_op = new ilCheckboxOption($this->txt("reduced"), "reduced", $this->txt("reduced_exp"));
		$overview_op2 = new ilCheckboxOption($this->txt("extended"), "extended", $this->txt("extended_exp"));

		$checkbox_overview->addOption($overview_op);
		$checkbox_overview->addOption($overview_op2);
		$checkbox_overview->setRequired(true);


		$this->form->addItem($checkbox_overview);

		$this->form->addCommandButton("triggerExport", "Export");
	}

}

?>