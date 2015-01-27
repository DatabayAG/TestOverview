<?php
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 *	@package	TestOverview repository plugin
 *	@category	GUI
 *	@author		Greg Saive <gsaive@databay.de>
 */

require_once 'Services/Table/classes/class.ilTable2GUI.php';

abstract class ilMappedTableGUI extends ilTable2GUI
{
	/**
	 * @var ilDataMapper
	 */
	private $mapper;

	/**
	 *    Set the mapper to be used for data retrieval.
	 *
	 * @params    ilDataMapper $mapper
	 * @param ilDataMapper $mapper
	 * @return ilMappedTableGUI
	 */
	public function setMapper( ilDataMapper $mapper )
	{
		$this->mapper = $mapper;
		return $this;
	}

	/**
	 *	Get the registered mapper instance
	 *
	 *	@return ilDataMapper
	 */
	public function getMapper()
	{
		return $this->mapper;
	}

	/**
	 *    Post-query data formatter.
	 *
	 *    The formatData() method should be used to retrieve
	 *    the correct data format after execution of a query.
	 *    The @see ilMappedTableGUI::populate() method should
	 *    call formatData() before calling setData().
	 *
	 * @params    array    $data
	 * @param array $data
	 * @return array
	 */
	protected function formatData( array $data )
	{
		return $data;
	}
	
	/**
	 * overwrite this method for ungregging the object data structures
	 * since ilias tables support arrays only
	 * 
	 * @param mixed $data
	 * @return array
	 */
	protected function buildTableRowsArray($data)
	{
		return $data;
	}

	/**
	 *    Populate the TableGUI using the Mapper.
	 *
	 *    The populate() method should be called
	 *    to fill the overview table with data.
	 *    The getList() method is called on the
	 *    registered mapper instance. The formatData()
	 *    method should be overloaded to handle specific
	 *    cases of displaying or ordering rows.
	 *
	 * @throws ilException
	 * @return ilMappedTableGUI
	 */
	public function populate()
    {
		if( $this->getExternalSegmentation() && $this->getExternalSorting() )
		{
			$this->determineOffsetAndOrder();
		}
		elseif( !$this->getExternalSegmentation() && $this->getExternalSorting() )
		{
			$this->determineOffsetAndOrder(true);
		}
		else
		{
			throw new ilException('invalid table configuration: extSort=false / extSegm=true');
		}
		
		/* Configure query execution */
		$params = array();
		if( $this->getExternalSegmentation() )
		{
			$params['limit'] = $this->getLimit();
			$params['offset'] = $this->getOffset();
		}
		if( $this->getExternalSorting() )
		{
			$params['order_field'] = $this->getOrderField();
			$params['order_direction'] = $this->getOrderDirection();
		}

		$overview = $this->getParentObject()->object;
		$filters  = array("overview_id" => $overview->getId()) + $this->filter;

		/* Execute query. */
        $data = $this->getMapper()
				     ->getList($params, $filters);

        if( !count($data['items']) && $this->getOffset() > 0) {
			/* Query again, offset was incorrect. */
            $this->resetOffset();
	        $data = $this->getMapper()
					     ->getList($params, $filters);
        }

		/* Post-query logic. Implement custom sorting or display
		   in formatData overload. */
		$data = $this->formatData($data);

		$this->setData( $this->buildTableRowsArray($data['items']) );
		
 		if( $this->getExternalSegmentation() )
		{
			$this->setMaxCount($data['cnt']);
		}
		
        return $this;
    }

	/**
	 *    Retrieve a group object.
	 *
	 *    Load a il(Course|Group)Participants object
	 *    from a ilObj(Course|Group) object. Unfortunately
	 *    the getMembersObject method is implemented only
	 *    in ilObjCourse.
	 *
	 * @params    stdClass    $container    The container object
	 * @param stdClass $container
	 * @throws ilException
	 * @return ilParticipants|ilGroupParticipants|ilCourseParticipants
	 */
	protected function getMembersObject( stdClass $container )
	{
		global $ilObjDataCache;
		
		$type = $container->type;
		if(!strlen($type))
		{
			$type = $ilObjDataCache->lookupType($container->obj_id);
		}

		switch ($type) {

			case "grp":
				include_once 'Modules/Group/classes/class.ilGroupParticipants.php';
				return new ilGroupParticipants( $container->obj_id );

			case "crs":
				include_once 'Modules/Course/classes/class.ilCourseParticipants.php';
				return new ilCourseParticipants( $container->obj_id );

			default :
				throw new ilException("Type not supported");
		}
	}
}

