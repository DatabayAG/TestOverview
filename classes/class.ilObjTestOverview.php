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
 *	@category	Core
 *	@author		Greg Saive <gsaive@databay.de>
 */
class ilObjTestOverview extends ilObjectPlugin
{
    public const PRESENTATION_PERCENTAGE = 'percentage';
    public const PRESENTATION_POINTS     = 'points';

    private $test_objects = array();
    private $test_obj_id_by_ref_id;
    private $test_ref_ids_by_obj_id;
    private $result_presentation = 'percentage';
    private $result_column = true;
    private $points_column = false;
    private $average_column = false;
    private $enable_excel = false;
    private $header_points = false;

    private array $groups = [];
    private ilOverviewMapper $mapper;

    public function __construct(int $a_ref_id = 0)
    {
        parent::__construct($a_ref_id);
        $this->mapper = new ilOverviewMapper();
    }

    /**
     *	Set the object type.
     */
    protected function initType(): void
    {
        $this->setType('xtov');
    }

    public function getMapper(): \ilOverviewMapper
    {
        return $this->mapper;
    }

    protected function doCreate(bool $clone_mode = false): void
    {
        $this->getMapper()
             ->insert(
                 "rep_robj_xtov_overview",
                 array(
                    "obj_id" => $this->getId(),
                    'result_presentation' => $this->result_presentation,
                    'result_column' => (int) $this->result_column,
                    'points_column' => (int) $this->points_column,
                    'average_column' => (int) $this->average_column,
                    'enable_excel' => (int) $this->enable_excel,
                    'header_points' => (int) $this->header_points,
                    )
             );
        $this->createMetaData();
    }

    protected function doUpdate(): void
    {
        $this->db->update(
            'rep_robj_xtov_overview',
            array(
                        'result_presentation' => array('text', $this->result_presentation),
                        'result_column'	      => array('int', (int) $this->result_column),
                        'points_column'       => array('int', (int) $this->points_column),
                        'average_column'      => array('int', (int) $this->average_column),
                        'enable_excel'        => array('int', (int) $this->enable_excel),
                        'header_points'        => array('int', (int) $this->header_points),
                      ),
            array(
                        'obj_id' => array('int', $this->getId()))
        );

        $this->updateMetaData();
    }

    protected function doRead(): void
    {
        $res = $this->db->query('
			SELECT
				*
			FROM
				rep_robj_xtov_overview
			WHERE
				obj_id = ' . $this->db->quote($this->getId(), 'integer'));

        while ($row = $this->db->fetchObject($res)) {
            $this->setId((int) $row->obj_id);
            $this->result_presentation = $row->result_presentation;
            $this->result_column = (bool) $row->result_column;
            $this->points_column = (bool) $row->points_column;
            $this->average_column = (bool) $row->average_column;
            $this->enable_excel = (bool) $row->enable_excel;
            $this->header_points = (bool) $row->header_points;
        }
    }

    protected function doDelete(): void
    {
        $this->db->manipulate('
			DELETE FROM rep_robj_xtov_overview
			WHERE obj_id = ' . $this->db->quote($this->getId(), 'integer'));
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
    protected function doCloneObject(ilObject2 $new_obj, int $a_target_id, ?int $a_copy_id = null): void
    {
        global $ilDB;
        $new_obj->setResultPresentation($this->result_presentation);

        $tests  = $this->getUniqueTests(true);
        $groups = $this->getParticipantGroups(true);

        $tests  = array_keys($tests);
        $groups = array_keys($groups);

        $tvals = "";
        foreach ($tests as $tid) {
            $tvals .= (empty($tvals) ? "" : ",") . "({$new_obj->getId()}, $tid)";
        }

        $gvals = "";
        foreach ($groups as $gid) {
            $gvals .= (empty($gvals) ? "" : ",") . "({$new_obj->getId()}, $gid)";
        }

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
        $ilDB->manipulate(sprintf(
            $baseSQLTst,
            "rep_robj_xtov_t2o",
            "test",
            $tvals
        ));

        /* Insert groups */
        $ilDB->manipulate(sprintf(
            $baseSQLFilter,
            "rep_robj_xtov_p2o",
            "grpcrs",
            $gvals
        ));

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
    public function addTest($test): void
    {
        /**
         * @var $ilDB ilDBInterface
         */
        global $ilDB;

        $testRefId = $test;
        if ($test instanceof ilObjTest) {
            $testRefId = $test->getRefId();
        }

        /* Insert t2o entry (test 2 overview) */
        $ilDB->replace(
            'rep_robj_xtov_t2o',
            array(
                'obj_id_overview' => array('integer', $this->getId()),
                'ref_id_test' => array('integer', $testRefId)
            ),
            array()
        );

        if (! $test instanceof ilObjTest) {
            /* XXX fetch++ ... */
            $test = ilObjectFactory::getInstanceByRefId((int) $testRefId);
        }

        $this->test_objects[$test->getId()] = $test;
    }

    /**
     *	Remove a test from the overview.
     *
     *	The rmTest() method should be called to unregister
     *	a test from an overview.
     *
     *	@params	int|ilObjTest	$test
     */
    public function rmTest($test): void
    {
        /**
         * @var $ilDB ilDBInterface
         */
        global $ilDB;

        $testRefId = $test;
        if ($test instanceof ilObjTest) {
            $testRefId = $test->getRefId();
        }

        /* Remove t2o entry (test 2 overview) */
        $ilDB->manipulateF(
            "
			DELETE FROM rep_robj_xtov_t2o
			WHERE
				obj_id_overview = %s
				AND ref_id_test = %s",
            array('integer', 'integer'),
            array($this->getId(), $testRefId)
        );

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
    public function addGroup($group): void
    {
        /**
         * @var $ilDB ilDBInterface
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
    public function rmGroup($group): void
    {
        /**
         * @var $ilDB ilDBInterface
         */
        global $ilDB;

        $groupId = $group;
        if ($group instanceof ilObjCourse
            || $group instanceof ilObjGroup) {

            $groupId = $group->getId();
        }

        /* Remove p2o entry (test 2 overview) */
        $ilDB->manipulateF(
            "
			DELETE FROM rep_robj_xtov_p2o
			WHERE
				obj_id_overview = %s
				AND obj_id_grpcrs = %s",
            array('integer', 'integer'),
            array($this->getId(), $groupId)
        );

        /* XXX update $this->tests */
    }

    private function loadTestData(): void
    {
        $res = $this->db->queryF(
            "
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
				AND ref.deleted IS NULL
			ORDER BY ordering, object.title ASC
			",
            array('text', 'integer'),
            array('tst', $this->getId())
        );

        /* Fetch objects into $this->tests. */

        $this->test_obj_id_by_ref_id = array();
        $this->test_ref_ids_by_obj_id = array();

        while ($row = $this->db->fetchAssoc($res)) {
            if (!isset($this->test_ref_ids_by_obj_id[ $row['obj_id'] ])) {
                $this->test_ref_ids_by_obj_id[ $row['obj_id'] ] = array();
            }

            $this->test_obj_id_by_ref_id[$row['ref_id']] = $row['obj_id'];
            $this->test_ref_ids_by_obj_id[$row['obj_id']][] = $row['ref_id'];
        }
    }

    private function isTestDataLoaded(): bool
    {
        return !is_null($this->test_obj_id_by_ref_id) && !is_null($this->test_ref_ids_by_obj_id);
    }

    public function getUniqueTests(bool $fromDB = false): array
    {
        if ($fromDB || !$this->isTestDataLoaded()) {
            $this->loadTestData();
        }

        return $this->test_ref_ids_by_obj_id;
    }

    public function getTestReferences(bool $fromDB = false): array
    {
        if ($fromDB || !$this->isTestDataLoaded()) {
            $this->loadTestData();
        }

        return $this->test_obj_id_by_ref_id;
    }

    public function getTest($obj_id)
    {
        if (!isset($this->test_objects[$obj_id])) {
            $this->test_objects[$obj_id] = ilObjectFactory::getInstanceByObjId($obj_id);
        }

        return $this->test_objects[$obj_id];
    }

    public function getParticipantGroups(bool $fromDB = false): array
    {
        if ($fromDB) {
            $res = $this->db->queryF(
                "
				SELECT
					p2o.obj_id_grpcrs obj_id
				FROM
					rep_robj_xtov_p2o p2o
					JOIN object_data object
						ON (object.obj_id = p2o.obj_id_grpcrs)
				WHERE
					p2o.obj_id_overview = %s",
                array('integer'),
                array($this->getId())
            );

            /* Fetch objects into $this->tests. */
            $this->groups = array();
            while ($row = $this->db->fetchAssoc($res)) {

                $object = ilObjectFactory::getInstanceByObjId($row['obj_id'], false);
                if ($object) {
                    $this->groups[ $row['obj_id'] ] = $object;
                }
            }
        }

        return $this->groups;
    }

    public function gatherTestData(\ilObjTest $test, array &$data): void
    {
        global $ilDB;

        $res = $ilDB->query("
			SELECT
				tst_active.active_id,
				tst_active.tries,
				tst_active.last_finished_pass,
				COUNT(tst_sequence.active_fi) sequences,
				CASE WHEN
					(tst_tests.nr_of_tries - 1) = tst_active.last_finished_pass
				THEN '1'
				ELSE '0'
				END is_last_pass
			FROM tst_active
			LEFT JOIN tst_tests
				ON tst_tests.test_id = tst_active.test_fi
			LEFT JOIN tst_sequence
				ON tst_sequence.active_fi = tst_active.active_id
			WHERE tst_active.test_fi = {$ilDB->quote($test->getTestId(), 'integer')} 
			GROUP BY
				tst_active.active_id,
				tst_active.tries,
				tst_active.last_finished_pass,
				tst_tests.nr_of_tries
		");

        while ($row = $ilDB->fetchAssoc($res)) {
            if ($row['sequences'] == '0') {
                continue;
            }

            if ($test->getPassScoring() == SCORE_LAST_PASS) {
                $is_finished = false;
                if ($row['last_finished_pass'] != null && $row['sequences'] - 1 == $row['last_finished_pass']) {
                    $is_finished = true;
                }
                $row['is_finished'] = $is_finished;
            }

            $data[$test->getId()][$row['active_id']] = $row;
        }
    }

    public function getResultPresentation(): string
    {
        return $this->result_presentation;
    }

    public function setResultPresentation(string $result_presentation): void
    {
        $this->result_presentation = $result_presentation;
    }

    public function getResultColumn(): bool
    {
        return $this->result_column;
    }

    public function setResultColumn(bool $result_column): void
    {
        $this->result_column = $result_column;
    }

    public function getPointsColumn(): bool
    {
        return $this->points_column;
    }

    public function setPointsColumn(bool $points_column): void
    {
        $this->points_column = $points_column;
    }

    public function getAverageColumn(): bool
    {
        return $this->average_column;
    }

    public function setAverageColumn(bool $average_column): void
    {
        $this->average_column = $average_column;
    }

    public function getEnableExcel(): bool
    {
        return $this->enable_excel;
    }

    public function setEnableExcel(bool $enable_excel): void
    {
        $this->enable_excel = $enable_excel;
    }

    public function getHeaderPoints(): bool
    {
        return $this->header_points;
    }

    public function setHeaderPoints(bool $header_points): void
    {
        $this->header_points = $header_points;
    }

    public function setTestOrderValueForRef($ref_id, $order_value): void
    {
        $this->db->update(
            'rep_robj_xtov_t2o',
            array(
                'ordering' => array('int', $order_value)
            ),
            array(
                'obj_id_overview' 	=> array('int', $this->id),
                'ref_id_test'		=> array('int', $ref_id)
            )
        );
    }
}
