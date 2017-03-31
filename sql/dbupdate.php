<#1>
<?php
/**
 * Script to initialize the db 
 */
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
if($ilDB->tableColumnExists('rep_robj_xtov_t2o', 'obj_id_test'))
{
	$ilDB->renameTableColumn('rep_robj_xtov_t2o', 'obj_id_test', 'ref_id_test');
	$ilDB->addPrimaryKey('rep_robj_xtov_t2o', array('obj_id_overview', 'ref_id_test'));
}
?>
<#4>
<?php
$res = $ilDB->query('SELECT * FROM rep_robj_xtov_t2o');
$tst_obj_ids = array();
while($row = $ilDB->fetchAssoc($res))
{
	$tst_obj_ids[$row['obj_id_overview']][] = $row['ref_id_test'];
}

$ilDB->manipulate('DELETE FROM rep_robj_xtov_t2o');

$stmt = $ilDB->prepare('SELECT ref_id FROM object_reference WHERE obj_id = ?', array('integer'));

foreach($tst_obj_ids as $tov_obj_id => $tst_obj_ids)
{
	foreach($tst_obj_ids as $tst_obj_id)
	{
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
/*ExerciseOverview Table with a list of all Objects (ID's)*/
$fields_ExOverview = array(
	'obj_id' => array(
		'type'    => 'integer',
		'length'  => 4,
		'notnull' => true,
		'default' => 0
	),
);

/* Exercise to Overview relationship table */
$fields_exercise = array(
	'obj_id_overview' => array(
		'type'    => 'integer',
		'length'  => 4,
		'notnull' => true,
		'default' => 0
	),
	'obj_id_exercise' => array(
		'type'    => 'integer',
		'length'  => 4,
		'notnull' => true,
		'default' => 0
	),
);
if(!$ilDB->tableExists('rep_robj_xtov_e2o'))
{
    $ilDB->createTable('rep_robj_xtov_e2o', $fields_exercise);
    $ilDB->addPrimaryKey('rep_robj_xtov_e2o', array('obj_id_overview', 'obj_id_exercise'));
}
else
{
    if (!$ilDB->tableColumnExists('rep_robj_xtov_e2o','obj_id_overview') && !$ilDB->tableColumnExists('rep_robj_xtov_e2o','obj_id_exercise'))
    {
        $ilDB->dropTable('rep_robj_xtov_e2o');
        $ilDB->createTable('rep_robj_xtov_e2o', $fields_exercise);
        $ilDB->addPrimaryKey('rep_robj_xtov_e2o', array('obj_id_overview', 'obj_id_exercise'));
    }
}
/* Participants to ExOverview relationship table,
   contains groups or courses IDs. */
$fields_participants = array(
	'obj_id_exview' => array(
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
if(!$ilDB->tableExists('rep_robj_xtov_e2o'))
{
    $ilDB->createTable('rep_robj_xtov_p2e', $fields_participants);
    $ilDB->addPrimaryKey('rep_robj_xtov_p2e', array('obj_id_exview', 'obj_id_grpcrs'));
}
 ?>
<#6>

<?php

/**
 * Update of varchar length
 */

?>
<#7>

<?php
$fields_rankto = array(
        'stud_id' => array(
		'type'    => 'integer',
		'length'  => 4,
		'notnull' => true,
		'default' => 0    
	),
        'rank' => array(
		'type'    => 'integer',
		'length'  => 4,
		'notnull' => true,
		'default' => 0    
	),
        'to_id' => array(
		'type'    => 'integer',
		'length'  => 4,
		'notnull' => true,
		'default' => 0    
	),
);

$ilDB->createTable('rep_robj_xtov_torank', $fields_rankto);
$ilDB->addPrimaryKey('rep_robj_xtov_torank', array('stud_id','to_id'));
 ?>

<#8>
<?php
$fields_rankeo = array(
        'stud_id' => array(
		'type'    => 'integer',
		'length'  => 4,
		'notnull' => true,
		'default' => 0    
	),
        'rank' => array(
		'type'    => 'integer',
		'length'  => 4,
		'notnull' => true,
		'default' => 0    
	),
        'eo_id' => array(
		'type'    => 'integer',
		'length'  => 4,
		'notnull' => true,
		'default' => 0    
	),
);

$ilDB->createTable('rep_robj_xtov_eorank', $fields_rankeo);
$ilDB->addPrimaryKey('rep_robj_xtov_eorank', array('stud_id','eo_id'));


 ?>
<#9>
<?php
$fields_rankdate = array(
        'rankdate' => array(
			'type' => 'date',			
			'notnull' => false
		),
        'otype' => array(
		'type'    => 'text',
		'length'  => 2,
		'notnull' => true,
		'default' => "-"    
	),
        
        'o_id' => array(
		'type'    => 'integer',
		'length'  => 4,
		'notnull' => true,
		'default' => 0    
	),
);

$ilDB->createTable('rep_robj_xtov_rankdate', $fields_rankdate);
$ilDB->addPrimaryKey('rep_robj_xtov_rankdate', array('otype','o_id'));


 ?>