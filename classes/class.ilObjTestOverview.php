<?php
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 *	@package	TestOverview repository plugin
 *	@category	Core
 *	@author		Greg Saive <gsaive@databay.de>
 */

require_once 'Services/Repository/classes/class.ilObjectPlugin.php';
require_once 'Modules/Test/classes/class.ilObjTest.php';
require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
				->getDirectory() . '/classes/mapper/class.ilOverviewMapper.php';

class ilObjTestOverview extends ilObjectPlugin
{
	private $test_objects = array();
	private $test_obj_id_by_ref_id = null;
	private $test_ref_ids_by_obj_id = null;

	/**
	 *	@var array
	 */
	private $groups;

	/**
	 *	@var ilOverviewMapper
	 */
	private $mapper;


	/**
	 * @param int $a_ref_id
	 */
	public function __construct($a_ref_id = 0)
	{
		parent::__construct($a_ref_id);

		$this->groups = array();
		$this->mapper = new ilOverviewMapper;
	}

	/**
 	 *	Set the object type.
	 */
	protected function initType()
	{
		$this->setType('xtov');
	}

	/**
	 *	Retrieve the mapper instance
	 *
	 *	@return ilOverviewMapper
	 */
	public function getMapper()
	{
		return $this->mapper;
	}

	/**
 	 *	Create entry in database
	 */
	public function doCreate()
	{
		$this->getMapper()
			 ->insert(
				"rep_robj_xtov_overview",
				array("obj_id" => $this->getId()));
		$this->createMetaData();
	}

	/**
	 * 
	 */
	public function doUpdate()
	{
		$this->updateMetaData();
	}

	/**
	 *	Read data from db
	 */
	public function doRead()
	{
		/**
		 * @var $ilDB ilDB
		 */
		global $ilDB;

		$res = $ilDB->query('
			SELECT
				*
			FROM
				rep_robj_xtov_overview
			WHERE
				obj_id = ' . $ilDB->quote($this->getId(), 'integer'));

		while($row = $ilDB->fetchObject($res))
			$this->obj_id = $row->obj_id;
	}

	/**
	 *	Delete entry from database
	 */
	public function doDelete()
	{
		/**
 		 * @var $ilDB ilDB
		 */
		global $ilDB;

		$ilDB->manipulate('
			DELETE FROM rep_robj_xtov_overview
			WHERE obj_id = ' . $ilDB->quote($this->getId(), 'integer'));
	}

	/**
	 *	Clone an ilObjTestOverview object.
	 *
	 *	This method effectively clones all objects belonging
	 *	to the overview. First the tests & groups are retrieved,
	 *	then the overview_id=>obj_id pair is inserted in the
	 *	correct relation table (either t2o or p2o, respectively as in
	 *	test_2_overview & participants_2_overview)
	 *
	 *	@params ilObjTestOverview	$new_obj
	 *	@params	int					$a_target_id
	 *	@params int					$a_copy_id
	 */
	public function doCloneObject($new_obj, $a_target_id, $a_copy_id)
	{
		global $ilDB;

		$tests  = $this->getTests(true);
		$groups = $this->getParticipantGroups(true);

		$tests  = array_keys($tests);
		$groups = array_keys($groups);

		$tvals = "";
		foreach ($tests as $tid)
			$tvals .= (empty($tvals) ? "" : ",") . "({$new_obj->getId()}, $tid)";

		$gvals = "";
		foreach ($groups as $gid)
			$gvals .= (empty($gvals) ? "" : ",") . "({$new_obj->getId()}, $gid)";

		$baseSQLTst = "
			INSERT INTO %s
				(obj_id_overview, ref_id_%s)
			VALUES
				%s";

		$baseSQLFilter = "
			INSERT INTO %s
				(obj_id_overview, obj_id_%s)
			VALUES
				%s";

		/* Insert tests */
		$ilDB->manipulate(sprintf($baseSQLTst,
			"rep_robj_xtov_t2o",
			"test",
			$tvals));

		/* Insert groups */
		$ilDB->manipulate(sprintf($baseSQLFilter,
			"rep_robj_xtov_p2o",
			"grpcrs",
			$gvals));

		$this->cloneMetaData($new_obj);
	}

	/**
	 *	Add a test to the overview.
	 *
	 *	The addTest() method should be called
	 *	to register a test in the currently
	 *	edited overview.
	 *
	 *	@params	int|ilObjTest	$test
	 */
	public function	addTest( $test )
	{
		/**
		 *	@var ilDB $ilDB
		 */
		global $ilDB;

		$tesRefId = $test;
		if ( $test instanceof ilObjTest ) {
			$tesRefId = $test->getRefId();
		}
		
		/* Insert t2o entry (test 2 overview) */
		$ilDB->replace(
			'rep_robj_xtov_t2o',
			array(
				'obj_id_overview' => array('integer', $this->getId()),
				'ref_id_test' => array('integer', $tesRefId)
			),
			array()
		);

		if ( ! $test instanceof ilObjTest ) {
			/* XXX fetch++ ... */
			$test = ilObjectFactory::getInstanceByRefId($tesRefId);
		}

		$this->tests[$test->getRefId()] = $test;
	}

	/**
	 *	Remove a test from the overview.
	 *
	 *	The rmTest() method should be called to unregister
	 *	a test from an overview.
	 *
	 *	@params	int|ilObjTest	$test
	 */
	public function rmTest( $test )
	{
		/**
		 *	@var ilDB $ilDB
		 */
		global $ilDB;

		$testRefId = $test;
		if ( $test instanceof ilObjTest ) {
			$testRefId = $test->getRefId();
		}

		/* Remove t2o entry (test 2 overview) */
		$ilDB->manipulateF("
			DELETE FROM rep_robj_xtov_t2o
			WHERE
				obj_id_overview = %s
				AND ref_id_test = %s",
			array('integer', 'integer'),
			array($this->getId(), $testRefId));

		/* XXX update $this->tests */
	}

	/**
	 *	Add a participant group to the overview.
	 *
	 *	The addGroup() method should be called to register
	 *	a participant group in the overview.
	 *
	 *	@params	ilObjCourse|ilObjGroup	$group
	 */
	public function	addGroup( $group )
	{
		/**
		 *	@var ilDB $ilDB
		 */
		global $ilDB;

		$groupId = $group;
		if ($groupId instanceof ilObjCourse
			|| $groupId instanceof ilObjGroup) {

			$groupId = $group->getId();
		}

		/* Insert p2o entry (test 2 overview) */
		$ilDB->replace(
			'rep_robj_xtov_p2o',
			array(
				'obj_id_overview' => array('integer', $this->getId()),
				'obj_id_grpcrs' => array('integer', $groupId)
			),
			array()
		);

		$this->groups[$groupId] = $group;
	}

	/**
	 *	Remove a group from the overview.
	 *
	 *	The rmGroup() method should be called to unregister
	 *	a group from an overview.
	 *
	 *	@params	ilObjCourse|ilObjGroup	$group
	 */
	public function rmGroup( $group )
	{
		/**
		 *	@var ilDB $ilDB
		 */
		global $ilDB;

		$groupId = $group;
		if ($group instanceof ilObjCourse
			|| $group instanceof ilObjGroup) {

			$groupId = $group->getId();
		}

		/* Remove p2o entry (test 2 overview) */
		$ilDB->manipulateF("
			DELETE FROM rep_robj_xtov_p2o
			WHERE
				obj_id_overview = %s
				AND obj_id_grpcrs = %s",
			array('integer', 'integer'),
			array($this->getId(), $groupId));

		/* XXX update $this->tests */
	}

	private function loadTestData()
	{
		global $ilDB;

		$res = $ilDB->queryF("
			SELECT
				t2o.ref_id_test ref_id,
				ref.obj_id obj_id
			FROM
				rep_robj_xtov_t2o t2o
				JOIN object_reference ref
					ON (ref.ref_id = t2o.ref_id_test)
				JOIN object_data object
					ON (object.obj_id = ref.obj_id) AND type = %s
				JOIN tst_tests test
					ON (test.obj_fi = object.obj_id)
			WHERE
				t2o.obj_id_overview = %s
				AND ref.deleted IS NULL",
			array('text', 'integer'),
			array('tst', $this->getId()));

		/* Fetch objects into $this->tests. */

		$this->test_obj_id_by_ref_id = array();
		$this->test_ref_ids_by_obj_id = array();

		while ($row = $ilDB->fetchAssoc( $res ))
		{
			if( !isset($this->test_ref_ids_by_obj_id[ $row['obj_id'] ]) )
			{
				$this->test_ref_ids_by_obj_id[ $row['obj_id'] ] = array();
			}

			$this->test_obj_id_by_ref_id[$row['ref_id']] = $row['obj_id'];
			$this->test_ref_ids_by_obj_id[$row['obj_id']][] = $row['ref_id'];
		}
	}
	
	private function isTestDataLoaded()
	{
		return !is_null($this->test_obj_id_by_ref_id) && !is_null($this->test_ref_ids_by_obj_id);
	}

	/**
	 *    Retrieve the list of tests.
	 *
	 *    The getTests() method is used to retrieve
	 *    the list of tests registered with the overview.
	 *
	 * @params    boolean    $fromDB        Wether to fetch from database.
	 *
	 * @param bool $fromDB
	 * @return array
	 */
	public function getUniqueTests( $fromDB = false )
	{
		if ( $fromDB || !$this->isTestDataLoaded() )
		{
			$this->loadTestData();
		}

		return $this->test_ref_ids_by_obj_id;
	}


	/**
	 *    Retrieve the list of tests.
	 *
	 *    The getTests() method is used to retrieve
	 *    the list of tests registered with the overview.
	 *
	 * @params    boolean    $fromDB        Wether to fetch from database.
	 *
	 * @param bool $fromDB
	 * @return array
	 */
	public function getTestReferences( $fromDB = false )
	{
		if ( $fromDB || !$this->isTestDataLoaded() )
		{
			$this->loadTestData();
		}

		return $this->test_obj_id_by_ref_id;
	}
	
	public function getTest($obj_id)
	{
		if( !isset($this->test_objects[$obj_id]) )
		{
			$this->test_objects[$obj_id] = ilObjectFactory::getInstanceByObjId($obj_id);
		}
		
		return $this->test_objects[$obj_id];
	}

	/**
	 *    Retrieve the list of participants groups.
	 *
	 *    The getParticipantGroups() method is used to retrieve
	 *    the list of participants groups registered
	 *    with the overview.
	 *
	 * @params    boolean $fromDB        Wether to fetch from database.
	 *
	 * @param bool $fromDB
	 * @return array
	 */
	public function getParticipantGroups( $fromDB = false )
	{
		if ($fromDB) {

			global $ilDB;

			$res = $ilDB->queryF("
				SELECT
					p2o.obj_id_grpcrs obj_id
				FROM
					rep_robj_xtov_p2o p2o
					JOIN object_data object
						ON (object.obj_id = p2o.obj_id_grpcrs)
				WHERE
					p2o.obj_id_overview = %s",
				array('integer'),
				array($this->getId()));

			/* Fetch objects into $this->tests. */
			$this->groups = array();
			while ($row = $ilDB->fetchAssoc( $res )) {

				$object = ilObjectFactory::getInstanceByObjId( $row['obj_id'], false );
				if( $object )
				{
					$this->groups[ $row['obj_id'] ] = $object;
				}
			}
		}

		return $this->groups;
	}

}
