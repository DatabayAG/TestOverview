<?php

declare(strict_types=1);

/**
 * Resolves course/group obj_ids in scope of a TestOverview's parent container.
 */
class ilTestOverviewScopeHelper
{
    /**
     * @return int[]
     */
    public static function resolveScopeObjIds(ilTree $tree, int $overview_ref_id): array
    {
        $pnode = $tree->getParentNodeData($overview_ref_id);
        if (!is_array($pnode) || !isset($pnode['ref_id'])) {
            return [];
        }

        $obj_ids = [];
        if (in_array($pnode['type'] ?? '', ['crs', 'grp'], true)) {
            $obj_ids[] = (int) $pnode['obj_id'];
        }

        foreach ($tree->getFilteredSubTree((int) $pnode['ref_id'], ['crs', 'grp']) as $node) {
            $obj_ids[] = (int) $node['obj_id'];
        }

        return array_values(array_unique(array_filter($obj_ids)));
    }

    /**
     * @param int[] $scope_obj_ids
     * @return int[]
     */
    public static function filterObjIdsToScope(array $obj_ids, array $scope_obj_ids): array
    {
        if ($scope_obj_ids === []) {
            return [];
        }

        $scope = array_flip($scope_obj_ids);

        return array_values(array_filter(
            $obj_ids,
            static fn(int $obj_id): bool => isset($scope[$obj_id])
        ));
    }
}
