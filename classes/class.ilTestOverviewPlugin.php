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
class ilTestOverviewPlugin extends ilRepositoryObjectPlugin
{
    /**
     * @return string
     */
    public function getPluginName(): string
    {
        return 'TestOverview';
    }

    public static function _getIcon(string $a_type): string
    {
        return ilUtil::getImagePath("icon_$a_type.svg", 'Customizing/global/plugins/Services/Repository/RepositoryObject/TestOverview');
    }

    protected function uninstallCustom(): void
    {
        /**
         * @var $ilDB ilDBInterface
         */
        global $ilDB;

        if($ilDB->tableExists('rep_robj_xtov_overview')) {
            $ilDB->dropTable('rep_robj_xtov_overview');
        }

        if($ilDB->tableExists('rep_robj_xtov_t2o')) {
            $ilDB->dropTable('rep_robj_xtov_t2o');
        }

        if($ilDB->tableExists('rep_robj_xtov_p2o')) {
            $ilDB->dropTable('rep_robj_xtov_p2o');
        }
    }

}
