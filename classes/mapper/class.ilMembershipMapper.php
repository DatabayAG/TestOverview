<?php

declare(strict_types=1);

/**
 *	@package	TestOverview repository plugin
 *	@category	Core
 *	@author		Greg Saive <gsaive@databay.de>
 */
class ilMembershipMapper extends ilDataMapper
{
    protected string $tableName = "object_data obd";

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
        return $this->tableName . "
            INNER JOIN object_reference obr ON obr.obj_id = obd.obj_id";
    }

    /**
     *	@see ilDataMapper::getWherePart()
     */
    protected function getWherePart(array $filters): string
    {
        $conditions = array(
            "obr.deleted IS NULL",
            $this->db->in('obd.type', array('crs', 'grp'), false, 'text'),
        );

        if (! empty($filters['flt_grp_name'])) {
            $conditions[] = sprintf(
                "obd.title LIKE %s",
                $this->db->quote("%" . $filters['flt_grp_name'] . "%", 'text')
            );
        }

        if (array_key_exists('scope_obj_ids', $filters)) {
            if ($filters['scope_obj_ids'] === []) {
                $conditions[] = '1 = 2';
            } else {
                $conditions[] = $this->db->in('obd.obj_id', $filters['scope_obj_ids'], false, 'integer');
            }
        }

        return implode(' AND ', $conditions);
    }
}
