<#1>
<?php
$fields_overview = array(
    'obj_id' => array(
        'type'    => 'integer',
        'length'  => 4,
        'notnull' => true,
        'default' => 0
    ),
);

$ilDB->createTable('rep_robj_xtov_overview', $fields_overview);
$ilDB->addPrimaryKey('rep_robj_xtov_overview', array('obj_id'));

/* Test to overview relationship table */
$fields_tests = array(
    'obj_id_overview' => array(
        'type'    => 'integer',
        'length'  => 4,
        'notnull' => true,
        'default' => 0
    ),
    'obj_id_test' => array(
        'type'    => 'integer',
        'length'  => 4,
        'notnull' => true,
        'default' => 0
    ),
);

$ilDB->createTable('rep_robj_xtov_t2o', $fields_tests);
$ilDB->addPrimaryKey('rep_robj_xtov_t2o', array('obj_id_overview', 'obj_id_test'));

/* Participants to overview relationship table,
   contains groups or courses IDs. */
$fields_participants = array(
    'obj_id_overview' => array(
        'type'    => 'integer',
        'length'  => 4,
        'notnull' => true,
        'default' => 0
    ),
    'obj_id_grpcrs' => array(
        'type'    => 'integer',
        'length'  => 4,
        'notnull' => true,
        'default' => 0
    ),
);

$ilDB->createTable('rep_robj_xtov_p2o', $fields_participants);
$ilDB->addPrimaryKey('rep_robj_xtov_p2o', array('obj_id_overview', 'obj_id_grpcrs'));
?>
<#2>
<?php
$ilDB->dropPrimaryKey('rep_robj_xtov_t2o');
?>
<#3>
<?php
if($ilDB->tableColumnExists('rep_robj_xtov_t2o', 'obj_id_test')) {
    $ilDB->renameTableColumn('rep_robj_xtov_t2o', 'obj_id_test', 'ref_id_test');
    $ilDB->addPrimaryKey('rep_robj_xtov_t2o', array('obj_id_overview', 'ref_id_test'));
}
?>
<#4>
<?php
$res = $ilDB->query('SELECT * FROM rep_robj_xtov_t2o');
$tst_obj_ids = array();
while($row = $ilDB->fetchAssoc($res)) {
    $tst_obj_ids[$row['obj_id_overview']][] = $row['ref_id_test'];
}

$ilDB->manipulate('DELETE FROM rep_robj_xtov_t2o');

$stmt = $ilDB->prepare('SELECT ref_id FROM object_reference WHERE obj_id = ?', array('integer'));

foreach($tst_obj_ids as $tov_obj_id => $tst_obj_ids) {
    foreach($tst_obj_ids as $tst_obj_id) {
        $res = $ilDB->execute($stmt, array($tst_obj_id));
        $obj_ref = $ilDB->fetchAssoc($res);

        $ilDB->insert('rep_robj_xtov_t2o', array(
            'obj_id_overview' => array('integer', $tov_obj_id),
            'ref_id_test' => array('integer', $obj_ref['ref_id'])
        ));
    }
}

$ilDB->free($stmt);
?>
<#5>
<?php
if(!$ilDB->tableColumnExists('rep_robj_xtov_overview', 'result_presentation')) {
    $ilDB->addTableColumn(
        'rep_robj_xtov_overview',
        'result_presentation',
        array(
                  'type'    => 'text',
                  'length'  => 255,
                  'notnull' => false,
                  'default' => 'percentage'
              )
    );
}
?>
<#6>
<?php
if(!$ilDB->tableColumnExists('rep_robj_xtov_overview', 'result_column')) {
    $ilDB->addTableColumn(
        'rep_robj_xtov_overview',
        'result_column',
        array(
            'type'    => 'integer',
            'length'  => 4,
            'notnull' => false,
            'default' => 1
        )
    );
}

if(!$ilDB->tableColumnExists('rep_robj_xtov_overview', 'points_column')) {
    $ilDB->addTableColumn(
        'rep_robj_xtov_overview',
        'points_column',
        array(
            'type'    => 'integer',
            'length'  => 4,
            'notnull' => false,
            'default' => 0
        )
    );
}

if(!$ilDB->tableColumnExists('rep_robj_xtov_overview', 'average_column')) {
    $ilDB->addTableColumn(
        'rep_robj_xtov_overview',
        'average_column',
        array(
            'type'    => 'integer',
            'length'  => 4,
            'notnull' => false,
            'default' => 0
        )
    );
}

if(!$ilDB->tableColumnExists('rep_robj_xtov_overview', 'enable_excel')) {
    $ilDB->addTableColumn(
        'rep_robj_xtov_overview',
        'enable_excel',
        array(
            'type'    => 'integer',
            'length'  => 4,
            'notnull' => false,
            'default' => 0
        )
    );
}

if(!$ilDB->tableColumnExists('rep_robj_xtov_overview', 'header_points')) {
    $ilDB->addTableColumn(
        'rep_robj_xtov_overview',
        'header_points',
        array(
            'type'    => 'integer',
            'length'  => 4,
            'notnull' => false,
            'default' => 0
        )
    );
}
?>
<#7>
<?php
if(!$ilDB->tableColumnExists('rep_robj_xtov_t2o', 'ordering')) {
    $ilDB->addTableColumn(
        'rep_robj_xtov_t2o',
        'ordering',
        array(
            'type'    => 'integer',
            'length'  => 4,
            'notnull' => false,
            'default' => 0
        )
    );
}
?>