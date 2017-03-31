<?php

/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * 	@package	TestOverview repository plugin
 * 	@category	Core
 * 	@author		Greg Saive <gsaive@databay.de>
 */
abstract class ilDataMapper {

	/**
	 * @var ilDB
	 */
	protected $db;

	/**
	 * 	@var string
	 */
	protected $tableName;

	/**
	 * 	Constructor logic.
	 *
	 * 	Inject database object.
	 */
	public function __construct() {
		/**
		 * @var $ilDB ilDB
		 */
		global $ilDB;

		$this->db = $ilDB;
	}

	/**
	 * 	Retrieve the fields list for the SELECT clause.
	 *
	 * 	The getSelectPart() method is called internally
	 * 	to build the SELECT fields list.
	 *
	 * 	@return string
	 */
	abstract protected function getSelectPart();

	/**
	 * 	Retrieve the FROM clause rows.
	 *
	 * 	The getFromPart() method is called internally
	 * 	to build the FROM rows list, including the JOIN(s).
	 *
	 * 	@return string
	 */
	abstract protected function getFromPart();

	/**
	 *    Retrieve the WHERE clause conditions.
	 *
	 *    The getWherePart() method is called internally
	 *    to build the WHERE clause. All conditions should
	 *    be concatenated and returned by this method.
	 *
	 * @param array $filters
	 * @return string
	 */
	abstract protected function getWherePart(array $filters);

	public function dbZurückgeben() {
		return $this->db;
	}

	/**
	 * 	Retrieve the relation name
	 *
	 * 	@return string
	 */
	public function getTableName() {
		return $this->tableName;
	}

	/**
	 *    Fetch a single field value from the database.
	 *
	 *    The getValue() method can be used to fetch a single
	 *    field's value from the database.
	 *
	 * @params    string    $table        relation name
	 * @params    string    $field        name of the field to retrieve.
	 * @params    array    $conditions    WHERE clause conditions
	 *
	 * @param       $table
	 * @param       $field
	 * @param array $conditions
	 * @return mixed
	 */
	public function getValue($table, $field, array $conditions = array()) {
		$where = !empty($conditions) ? implode(' AND ', $conditions) : "TRUE";
		$query = "
			SELECT
				$field
			FROM
				$table
			WHERE
				$where
		";
		$res = $this->db->query($query);
		$row = $this->db->fetchObject($res);

		return $row->{strtolower($field)} || $row->{strtoupper($field)};
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
	 * @param array $params
	 * @param array $filters
	 * @throws InvalidArgumentException
	 * @return array with indexes 'items' and 'cnt'.
	 */
	public function getList(array $params = array(), array $filters = array()) {
		$data = array(
			'items' => array(),
			'cnt' => 0
		);

		$select = $this->getSelectPart();
		$where = $this->getWherePart($filters);
		$from = $this->getFromPart();
		$order = "";
		$group = "";
		$limit = "";

		/* Build ORDER BY */
		if (isset($params['order_field'])) {
			if (!is_string($params['order_field']))
				throw new InvalidArgumentException("Please provide a valid order field.");

			if (!isset($params['order_direction']))
			/* Defaulting to ASC(ending) order. */
				$params['order_direction'] = "ASC";
			elseif (!in_array(strtolower($params['order_direction']), array("asc", "desc")))
				throw new InvalidArgumentException("Please provide a valid order direction.");

			$order = $params['order_field'] . ' ' . $params['order_direction'];
		}

		/* Build GROUP BY */
		if (isset($params['group'])) {
			if (!is_string($params['group']))
				throw new InvalidArgumentException("Please provide a valid group field parameter.");

			$group = $params['group'];
		}

		/* Build LIMIT */
		if (isset($params['limit'])) {
			if (!is_numeric($params['limit']))
				throw new InvalidArgumentException("Please provide a valid numerical limit.");

			if (!isset($params['offset']))
				$params['offset'] = 0;
			elseif (!is_numeric($params['offset']))
				throw new InvalidArgumentException("Please provide a valid numerical offset.");

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
		if (!empty($group))
			$query .= " GROUP BY $group";
		if (!empty($order))
			$query .= " ORDER BY $order";

		/* Execute query and fetch items. */
		$result = $this->db->query($query);
		while ($row = $this->db->fetchObject($result))
			$data['items'][] = $row;
		if (isset($params['limit'])) {
			/* Fill 'cnt' with total count of items */
			$cntSQL = "SELECT COUNT(*) cnt FROM ($query) subquery";
			$rowCnt = $this->db->fetchAssoc($this->db->query($cntSQL));
			$data['cnt'] = $rowCnt['cnt'];
		}
		return $data;
	}

	/**
	 * 	Insert data into a relation.
	 *
	 * 	This method can be used to execute an INSERT INTO
	 * 	query on the given table name.
	 *
	 * 	@params	string	$table	relation name
	 * 	@params	array	$values	fields/values pairs
	 */
	public function insert($table, array $values) {
		global $ilDB;

		/* Quote values. */
		$fields = array();
		$quoted = array();
		foreach ($values as $field => $value) {
			$fields[] = $field;

			if (is_numeric($value))
				$type = "integer";
			else
				$type = "text";

			$quoted[] = $ilDB->quote($value, $type);
		}

		/* Build SQL query. */
		$sql = sprintf("
			INSERT INTO %s
				(%s)
			VALUES
				(%s)
			", $table, implode(", ", $fields), implode(", ", $quoted));

		/* Execute SQL */
		$ilDB->manipulate($sql);
	}

}
