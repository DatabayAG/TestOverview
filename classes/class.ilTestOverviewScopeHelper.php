<?php

declare(strict_types=1);

/**
 * Resolves course/group obj_ids in scope of a TestOverview.
 * Scope root is the nearest course or group above the overview, or the direct parent.
 */
class ilTestOverviewScopeHelper
{
    /**
     * @return int[]
     */
    public static function resolveScopeObjIds(ilTree $tree, int $overview_ref_id): array
    {
        $pnode = self::scopeRootNode($tree, $overview_ref_id);
        if (!is_array($pnode) || !isset($pnode['ref_id'])) {
            return [];
        }

        $obj_ids = [];
        if (in_array($pnode['type'] ?? '', ['crs', 'grp'], true)) {
            $obj_ids[] = (int) $pnode['obj_id'];
        }

        $parent_node = $tree->getNodeData((int) $pnode['ref_id']);
        foreach ($tree->getSubTree($parent_node, true, ['crs', 'grp']) as $node) {
            $obj_ids[] = (int) $node['obj_id'];
        }

        return array_values(array_unique(array_filter($obj_ids)));
    }

    /**
     * Nearest course or group above the overview. Falls back to the direct parent
     * when the overview is not placed inside a course or group.
     */
    private static function scopeRootNode(ilTree $tree, int $overview_ref_id): ?array
    {
        if ($overview_ref_id < 1 || !$tree->isInTree($overview_ref_id)) {
            return null;
        }

        foreach (array_reverse($tree->getPathId($overview_ref_id)) as $ref_id) {
            $ref_id = (int) $ref_id;
            if ($ref_id === $overview_ref_id) {
                continue;
            }
            $node = $tree->getNodeData($ref_id);
            if (is_array($node) && in_array($node['type'] ?? '', ['crs', 'grp'], true)) {
                return $node;
            }
        }

        $parent = $tree->getParentNodeData($overview_ref_id);

        return is_array($parent) ? $parent : null;
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
