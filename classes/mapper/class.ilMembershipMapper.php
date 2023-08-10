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
class ilMembershipMapper extends ilDataMapper
{
    protected string $tableName = "rbac_ua ua";

    /**
     *	@see ilDataMapper::getSelectPart()
     */
    protected function getSelectPart(): string
    {
        $fields = array(
            "DISTINCT obd.obj_id",
            "obd.type",
            "obd.title",
            "obr.ref_id",);

        return implode(', ', $fields);
    }

    /**
     *	@see ilDataMapper::getFromPart()
     */
    protected function getFromPart(): string
    {
        if(version_compare(ILIAS_VERSION_NUMERIC, '4.5.0') >= 0) {
            $joins = array(
                "JOIN rbac_fa fa ON ua.rol_id = fa.rol_id",
                "JOIN tree t1 ON t1.child = fa.parent",
                "JOIN object_reference obr ON t1.child = obr.ref_id",
                "JOIN object_data obd ON obr.obj_id = obd.obj_id"
            );
        } else {
            $joins = array(
                "JOIN rbac_fa fa ON ua.rol_id = fa.rol_id",
                "JOIN tree t1 ON t1.child = fa.parent",
                "JOIN object_reference obr ON t1.parent = obr.ref_id",
                "JOIN object_data obd ON obr.obj_id = obd.obj_id"
            );
        }

        return $this->tableName . " " . implode(' ', $joins);
    }

    /**
     *	@see ilDataMapper::getWherePart()
     */
    protected function getWherePart(array $filters): string
    {
        global $ilUser;

        $conditions = array(
            "obr.deleted IS NULL",
            "fa.assign = 'y'",
            $this->db->in('obd.type', array('crs', 'grp'), false, 'text'),
            "ua.usr_id = " . $this->db->quote($ilUser->getId(), 'integer'),);

        if (! empty($filters['flt_grp_name'])) {
            $conditions[] = sprintf(
                "obd.title LIKE %s",
                $this->db->quote("%" . $filters['flt_grp_name'] . "%", 'text')
            );
        }

        return implode(' AND ', $conditions);
    }
}
