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
abstract class ilDataMapper
{
    protected ilDBInterface $db;

    protected string $tableName;

    public function __construct()
    {
        global $ilDB;
        $this->db = $ilDB;
    }

    /**
     *	Retrieve the fields list for the SELECT clause.
     *
     *	The getSelectPart() method is called internally
     *	to build the SELECT fields list.
     */
    abstract protected function getSelectPart(): string;

    /**
     *	Retrieve the FROM clause rows.
     *
     *	The getFromPart() method is called internally
     *	to build the FROM rows list, including the JOIN(s).
     */
    abstract protected function getFromPart(): string;

    /**
     *    Retrieve the WHERE clause conditions.
     *
     *    The getWherePart() method is called internally
     *    to build the WHERE clause. All conditions should
     *    be concatenated and returned by this method.
     */
    abstract protected function getWherePart(array $filters): string;

    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     *    Fetch a single field value from the database.
     *
     *    The getValue() method can be used to fetch a single
     *    field's value from the database.
     * @return mixed
     */
    public function getValue(string $table, string $field, array $conditions = array())
    {
        $where = !empty($conditions) ? implode(' AND ', $conditions) : "TRUE";
        $query = "
			SELECT
				$field
			FROM
				$table
			WHERE
				$where
		";
        $res  = $this->db->query($query);
        $row  = $this->db->fetchObject($res);

        return $row !== null ? $row->{strtolower($field)} || $row->{strtoupper($field)} : null;
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

        /* Build ORDER BY */
        if (isset($params['order_field'])) {
            if (! is_string($params['order_field'])) {
                throw new InvalidArgumentException("Please provide a valid order field.");
            }

            if (! isset($params['order_direction'])) {
                /* Defaulting to ASC(ending) order. */
                $params['order_direction'] = "ASC";
            } elseif (! in_array(strtolower($params['order_direction']), array("asc", "desc"))) {
                throw new InvalidArgumentException("Please provide a valid order direction.");
            }

            $order = $params['order_field'] . ' ' . $params['order_direction'];
        }

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

    /**
     *	Insert data into a relation.
     *
     *	This method can be used to execute an INSERT INTO
     *	query on the given table name.
     *	@params	array	$values	fields/values pairs
     */
    public function insert($table, array $values): void
    {
        /* Quote values. */
        $fields = array();
        $quoted = array();
        foreach ($values as $field => $value) {
            $fields[] = $field;

            if (is_numeric($value)) {
                $type = "integer";
            } else {
                $type = "text";
            }

            $quoted[] = $this->db->quote($value, $type);
        }

        /* Build SQL query. */
        $sql = sprintf(
            "
			INSERT INTO %s
				(%s)
			VALUES
				(%s)
			",
            $table,
            implode(", ", $fields),
            implode(", ", $quoted)
        );

        /* Execute SQL */
        $this->db->manipulate($sql);
    }

}
