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
 *	@category	GUI
 *	@author		Greg Saive <gsaive@databay.de>
 */
abstract class ilMappedTableGUI extends ilTable2GUI
{
    private ?ilDataMapper $mapper = null;
    private ilObjectDataCache $objectDataCache;

    public function __construct(?object $a_parent_obj, string $a_parent_cmd = "", string $a_template_context = "")
    {
        global $DIC;
        $this->objectDataCache = $DIC['ilObjDataCache'];
        parent::__construct($a_parent_obj, $a_parent_cmd, $a_template_context);
    }

    /**
     *    Set the mapper to be used for data retrieval.
     *
     * @params    ilDataMapper $mapper
     * @param ilDataMapper $mapper
     * @return ilMappedTableGUI
     */
    public function setMapper(ilDataMapper $mapper): \ilMappedTableGUI
    {
        $this->mapper = $mapper;
        return $this;
    }

    public function getMapper(): ?\ilDataMapper
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
     */
    protected function formatData(array $data): array
    {
        return $data;
    }

    /**
     * overwrite this method for ungregging the object data structures
     * since ilias tables support arrays only
     */
    protected function buildTableRowsArray($data): array
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
     */
    public function populate(): \ilMappedTableGUI
    {
        if($this->getExternalSegmentation() && $this->getExternalSorting()) {
            $this->determineOffsetAndOrder();
        } elseif(!$this->getExternalSegmentation() && $this->getExternalSorting()) {
            $this->determineOffsetAndOrder(true);
        } else {
            throw new ilException('invalid table configuration: extSort=false / extSegm=true');
        }

        /* Configure query execution */
        $params = array();
        if($this->getExternalSegmentation()) {
            $params['limit'] = $this->getLimit();
            $params['offset'] = $this->getOffset();
        }
        if($this->getExternalSorting()) {
            $params['order_field'] = $this->getOrderField();
            $params['order_direction'] = $this->getOrderDirection();
        }

        $overview = $this->getParentObject()->getObject();
        $filters  = array("overview_id" => $overview->getId()) + $this->filter;

        /* Execute query. */
        $data = $this->getMapper()->getList($params, $filters);

        if(!count($data['items']) && $this->getOffset() > 0) {
            /* Query again, offset was incorrect. */
            $this->resetOffset();
            $data = $this->getMapper()
                         ->getList($params, $filters);
        }

        /* Post-query logic. Implement custom sorting or display
           in formatData overload. */
        $data = $this->formatData($data);

        $this->setData($this->buildTableRowsArray($data['items']));

        if($this->getExternalSegmentation()) {
            $this->setMaxCount((int)$data['cnt']);
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
     * @throws ilException
     * @return ilParticipants|ilGroupParticipants|ilCourseParticipants
     */
    protected function getMembersObject(stdClass $container)
    {
        $type = $container->type;
        if($type == '') {
            $type = $this->objectDataCache->lookupType((int)$container->obj_id);
        }

        switch ($type) {
            case "grp":
                return new ilGroupParticipants((int)$container->obj_id);
            case "crs":
                return new ilCourseParticipants((int)$container->obj_id);
            default:
                throw new ilException("Type not supported");
        }
    }
}
