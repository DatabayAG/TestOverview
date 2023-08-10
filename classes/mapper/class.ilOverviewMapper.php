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
class ilOverviewMapper extends ilDataMapper
{
    protected string $tableName = "rep_robj_xtov_overview overview";

    /**
     *	@see ilDataMapper::getSelectPart()
     */
    protected function getSelectPart(): string
    {
        $fields = array(
            "participants.obj_id_grpcrs obj_id",);

        return implode(', ', $fields);
    }

    /**
     *	@see ilDataMapper::getFromPart()
     */
    protected function getFromPart(): string
    {
        $joins = array(
            "JOIN rep_robj_xtov_p2o participants
				ON (overview.obj_id = participants.obj_id_overview)",);

        return $this->tableName . " " . implode(' ', $joins);
    }

    /**
     *	@see ilDataMapper::getWherePart()
     */
    protected function getWherePart(array $filters): string
    {
        $conditions = array("1 = 1");

        if (! empty($filters['overview_id'])) {
            $conditions[] = "overview.obj_id = " . $this->db->quote($filters['overview_id'], 'integer');
        }

        return implode(' AND ', $conditions);
    }

    /**
     *	Get pairs of Participants groups.
     *
     *	This method can be used to list groups in a
     *	HTML <select>. The index in the returned array
     *	corresponds to the groups' obj_id and the value
     *	is the groups' title.
     *
     *	@param	integer	$overviewId
     *	@return array	Where index = obj_id and value = group title
     */
    public function getGroupPairs($overviewId): array
    {
        $pairs   = array();
        $rawData = $this->getList(array(), array("overview_id" => $overviewId));
        foreach ($rawData['items'] as $item) {
            $object = ilObjectFactory::getInstanceByObjId((int)$item->obj_id, false);
            if($object !== null) {
                $pairs[$item->obj_id] = $object->getTitle();
            }
        }

        return $pairs;
    }

    public function getUniqueTestParticipants(array $obj_ids): array
    {
        $in_tst_std   = $this->db->in('tst_std.obj_fi', $obj_ids, false, 'integer');
        $in_tst_fixed = $this->db->in('tst_fixed.obj_fi', $obj_ids, false, 'integer');

        $query   = "
			(SELECT act.user_fi
			FROM tst_tests tst_std
			INNER JOIN object_data ON object_data.obj_id = tst_std.obj_fi AND object_data.type = 'tst'
			INNER JOIN tst_active act
				ON act.test_fi = tst_std.test_id
			INNER JOIN usr_data ud_std
				ON ud_std.usr_id = act.user_fi
			WHERE $in_tst_std)
			UNION 
			(SELECT inv.user_fi
			FROM tst_tests tst_fixed
			INNER JOIN object_data ON object_data.obj_id = tst_fixed.obj_fi AND object_data.type = 'tst'
			INNER JOIN tst_invited_user inv
				ON inv.test_fi = tst_fixed.test_id
			INNER JOIN usr_data ud_fixed
				ON ud_fixed.usr_id = inv.user_fi
			WHERE $in_tst_fixed)
			";
        $res     = $this->db->query($query);
        $usr_ids = array();
        while($row = $this->db->fetchAssoc($res)) {
            $usr_ids[] = (int)$row['user_fi'];
        }

        $data        = array('items' => array_unique($usr_ids));
        $data['cnt'] = 0;

        return $data;
    }

    /**
     *    Fetch a list of entries from the database.
     *
     *    The getList() method can be used to retrieve a collection
     *    of entries saved in the database in a given table.
     *
     * @params    array    $params Possible parameters indexes include:
     *                             - limit    [~numeric]
     *                             - offset    [~numeric]
     *                             - order_field        [~string]
     *                             - order_direction    [=ASC|DESC]
     *                             - group    [~string]
     *
     * @throws InvalidArgumentException
     * @return array with indexes 'items' and 'cnt'.
     */
    public function getList(array $params = array(), array $filters = array()): array
    {
        $data = array(
            'items' => array(),
            'cnt'   => 0
        );

        $select = $this->getSelectPart();
        $where  = $this->getWherePart($filters);
        $from   = $this->getFromPart();
        $order  = "";
        $group  = "";
        $limit  = "";

        /* Build ORDER BY
        if (isset($params['order_field'])) {
            if (! is_string($params['order_field']))
                throw new InvalidArgumentException("Please provide a valid order field.");

            if (! isset($params['order_direction']))
                /* Defaulting to ASC(ending) order.*/ /*
                $params['order_direction'] = "ASC";
            elseif (! in_array(strtolower($params['order_direction']),array("asc", "desc")) )
                throw new InvalidArgumentException("Please provide a valid order direction.");

            $order = $params['order_field'] . ' ' . $params['order_direction'];
        }
        */

        /* Build GROUP BY */
        if (isset($params['group'])) {
            if (! is_string($params['group'])) {
                throw new InvalidArgumentException("Please provide a valid group field parameter.");
            }

            $group = $params['group'];
        }

        /* Build LIMIT */
        if (isset($params['limit'])) {
            if (! is_numeric($params['limit'])) {
                throw new InvalidArgumentException("Please provide a valid numerical limit.");
            }

            if (! isset($params['offset'])) {
                $params['offset'] = 0;
            } elseif (! is_numeric($params['offset'])) {
                throw new InvalidArgumentException("Please provide a valid numerical offset.");
            }

            $this->db->setLimit($params['limit'], $params['offset']);
        }

        /* Build SQL query */
        $query = "
			SELECT
				$select
			FROM
				$from
			WHERE
				$where
		";

        if (! empty($group)) {
            $query .= " GROUP BY $group";
        }
        if (! empty($order)) {
            $query .= " ORDER BY $order";
        }

        /* Execute query and fetch items. */
        $result = $this->db->query($query);
        while ($row = $this->db->fetchObject($result)) {
            $data['items'][] = $row;
        }

        if(isset($params['limit'])) {
            /* Fill 'cnt' with total count of items */
            $cntSQL = "SELECT COUNT(*) cnt FROM ($query) subquery";
            $rowCnt = $this->db->fetchAssoc($this->db->query($cntSQL));
            $data['cnt'] = $rowCnt['cnt'];
        }
        return $data;
    }

}
