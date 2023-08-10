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
 * @author Greg Saive <gsaive@databay.de>
 */
class ilTestMapper extends ilDataMapper
{
    protected string $tableName = 'rep_robj_xtov_t2o';

    protected function getSelectPart(): string
    {
        $fields = array(
            'obj_fi',
            'test_id',
            'ref.ref_id',
            'od.title',
            'od.description',
            'ordering'
        );

        return implode(', ', $fields);
    }

    protected function getFromPart(): string
    {
        $joins = array(
            'INNER JOIN object_reference ref ON (ref.ref_id = rep_robj_xtov_t2o.ref_id_test AND deleted IS NULL)',
            "INNER JOIN object_data od ON (od.obj_id = ref.obj_id) AND od.type = 'tst'",
            'INNER JOIN tst_tests test ON (test.obj_fi = od.obj_id)'
        );

        return $this->tableName . ' ' . implode(' ', $joins);
    }

    protected function getWherePart(array $filters): string
    {
        $conditions = array('rep_robj_xtov_t2o.obj_id_overview = ' . $this->db->quote($filters['overview_id'], 'integer'));

        if(!empty($filters['flt_tst_name'])) {
            $conditions[] = $this->db->like('od.title', 'text', '%' . $filters['flt_tst_name'] . '%', false);
        }

        return implode(' AND ', $conditions);
    }
}
