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

class ilTestOverviewTestSelectionExplorer extends ilPasteIntoMultipleItemsExplorer
{
    protected $hidden_nodes = array();

    /**
     * @param string $session_key
     */
    public function __construct($session_key)
    {
        parent::__construct(ilPasteIntoMultipleItemsExplorer::SEL_TYPE_CHECK, 'ilias.php?baseClass=ilRepositoryGUI&cmd=goto', $session_key);
        $this->removeFormItemForType('root');
        $this->removeFormItemForType('crs');
        $this->removeFormItemForType('grp');
        $this->removeFormItemForType('cat');
        $this->removeFormItemForType('fold');
        $this->removeFormItemForType('lso');
        $this->addFormItemForType('tst');
        $this->addFilter('tst');
    }

    /**
     * @param int    $a_ref_id
     * @param string $a_type
     * @return bool
     */
    public function isVisible($a_ref_id, $a_type): bool
    {
        /**
         * @var $ilAccess ilAccessHandler
         */
        global $ilAccess;

        if(isset($this->hidden_nodes[$a_ref_id]) && $this->hidden_nodes[$a_ref_id]) {
            return false;
        }

        $visible = parent::isVisible($a_ref_id, $a_type);

        if('tst' == $a_type) {
            $test = ilObjectFactory::getInstanceByRefId($a_ref_id);
            if((!$ilAccess->checkAccess('tst_statistics', '', (int)$a_ref_id) && !$ilAccess->checkAccess('write', '', $a_ref_id)) || $test->getAnonymity()) {
                return false;
            }
        }

        return $visible;
    }

    /**
     * @param $objects
     */
    public function setDefaultHiddenObjects(array $objects)
    {
        foreach($objects as $data) {
            $this->hidden_nodes[$data[0]] = true;
        }
    }
}
