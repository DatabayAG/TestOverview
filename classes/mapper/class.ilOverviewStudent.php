<?php

/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * 	@package	TestOverview repository plugin
 * 	@category	Core
 * 
 * 	@author		Jan Ruthardt <janruthardt@web.de>
 *  @author		Benedict Steuerlein <st111340@stud.uni-stuttgart.de>
 *  @author		Martin Dinkel <hmdinkel@web.de>
 *
 *
 * DB Mapper for the Student View (User with read-only permissons)
 */
class studentMapper {
    /**
     * 
     * @global type $ilDB
     * @param type $studId
     * @param type $testRefId
     * @return type student result of the last run of a test
     * 
     */
    public function getTestData($studId,$testRefId){
        global $ilDB;
        $query = "SELECT DISTINCT
					title,
					points,
					ref_id,
					maxpoints,
					tst_pass_result.tstamp,
					tst_tests.ending_time,
					ending_time_enabled AS timeded
				FROM
					object_reference
				JOIN
					tst_tests
				JOIN
					tst_active
				JOIN
					tst_pass_result
				JOIN
					object_data ON (object_reference.obj_id = tst_tests.obj_fi
				AND tst_active.test_fi = tst_tests.test_id
				AND tst_active.active_id = tst_pass_result.active_fi
				AND object_reference.obj_id = object_data.obj_id)
				AND tst_active.user_fi = %s
				AND ref_id = %s
				ORDER BY tst_pass_result.tstamp DESC
				LIMIT 1
                ";
        $result = $ilDB->queryF($query,array('integer','integer'), array($studId,$testRefId));
       return  $ilDB->fetchObject($result);
        
    }

   
    
    
    /**
     * Gives back the Results for the given Student and TestOverview ID 
     * @global type $ilDB
     * @param type $studId
     * @param type $overviewId
     * @return string
     */
    public function getResults($studId, $overviewId) {
        global $ilDB, $lng;
        $average;
        $maxPoints;

        $data = array();
        
        $query = "SELECT ref_id_test FROM rep_robj_xtov_t2o WHERE obj_id_overview = %s";
        $result = $ilDB->queryF($query, array('integer'), array($overviewId));
        $tpl = new ilTemplate("tpl.stud_view.html", true, true, "Customizing/global/plugins/Services/Repository/RepositoryObject/TestOverview");
        //Internationalization
        $lng->loadLanguageModule("assessment");
        $lng->loadLanguageModule("certificate");
        $lng->loadLanguageModule("rating");
        $lng->loadLanguageModule("common");
        $lng->loadLanguageModule("trac");
        $tpl->setCurrentBlock("head_row");
        $tpl->setVariable("test", $lng->txt("rep_robj_xtov_testOverview"));
        $tpl->setVariable("exercise", $lng->txt("rep_robj_xtov_ex_overview"));
        $tpl->setVariable("testTitle", $lng->txt("certificate_ph_testtitle"));
        $tpl->setVariable("score", $lng->txt("toplist_col_score"));
        $tpl->parseCurrentBlock();
        $tpl->setVariable("average", $lng->txt('rep_robj_xtov_avg_points'));
        $tpl->setVariable("averagePercent", $lng->txt("trac_average"));
        $tpl->setVariable("exerciseTitle", $lng->txt("certificate_ph_exercisetitle"));
        $tpl->setVariable("mark", $lng->txt("tst_mark"));
        $tpl->setVariable("studentRanking", $lng->txt("toplist_your_result"));
        //Baut aus den Einzelnen Zeilen Objekte
        while ($testObj = $ilDB->fetchObject($result)) {
            array_push($data, $testObj);
        }


        foreach ($data as $set) {
            $result = $this-> getTestData($studId,$set->ref_id_test);
            $timestamp = time();
            //$datum = (float) date("YmdHis", $timestamp);

            $testTime = (float) $result->ending_time;
            
          

            /* Checks if the test has been finished or if no end time is given */
           if ((($testTime - $timestamp) < 0 || $result->timeded == 0) && $this->isTestDeleted($set->ref_id_test) == null && $result != null  ) {
                $tpl->setCurrentBlock("test_results");
                $tpl->setVariable("Name", $result->title);
                $average += $result->points;
                $maxPoints += $result->maxpoints;
                if ($result->points > ($result->maxpoints / 2)) {
                    $pointsHtml = "<td class='green-result'>" . $result->points . "</td>";
                } else {
                    $pointsHtml = "<td class='red-result'>" . $result->points . "</td>";
                }
                $tpl->setVariable("Point", $pointsHtml);

                $tpl->parseCurrentBlock();
            }
        }
        ////Exercise Part ////
        require_once ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'TestOverview')
                        ->getDirectory() . '/classes/mapper/class.ilExerciseMapper.php';
        $excMapper = new ilExerciseMapper();
        $grades = $this->getExerciseMarks($studId, $overviewId);
        $totalGrade = 0;

        foreach ($grades as $grade) {
            if ($this->isExerciseDeleted($grade->obj_id) == null) {
                $gradeName = $excMapper->getExerciseName($grade->obj_id);
                $totalGrade += $grade->mark;
                $tpl->setCurrentBlock("exercise_results");
                $tpl->setVariable("Exercise", $gradeName);
                $tpl->setVariable("Mark", $grade->mark);
                $tpl->parseCurrentBlock();
            }
        }

        //// general Part /////
        if ($this->getNumTests($overviewId) == 0) {
            $averageNum = 0;
        } else {
            $averageNum = round($average / $this->getNumTests($overviewId), 2);
        }
        $tpl->setVariable("AveragePoints", $averageNum);
        if ($maxPoints == 0) {
            $Prozentnum = 0;
        } else {
            $Prozentnum = (float) ($average / $maxPoints) * 100;
        }
        $lng->loadLanguageModule("crs");
        $tpl->setVariable("averageMark", $lng->txt('rep_robj_xtov_average_mark'));

        if (count($grades) > 0) {

            $tpl->setVariable("AverageMark", $totalGrade / count($grades));
        }
        $tpl->setVariable("totalMark", $lng->txt('rep_robj_xtov_total_mark'));
        $tpl->setVariable("TotalMark", $totalGrade);

        $tpl->setVariable("Average", round($Prozentnum, 2));
        /// ranking part /////
        $ilOverviewMapper = new ilOverviewMapper();
        $rank = $ilOverviewMapper->getRankedStudent($overviewId, $studId);
        $count = $ilOverviewMapper->getCount($overviewId);
        $date = $ilOverviewMapper->getDate($overviewId);
        if (!$rank == '0') {
            $tpl->setVariable("toRanking", $rank . " " . $lng->txt('rep_robj_xtov_out_of') . " " . $count . "<br> ".$lng->txt('rep_robj_xtov_lastupdate').": " . $date);
        } else {
            $tpl->setVariable("toRanking", $lng->txt('links_not_available'));
        }
        $ilExerciseMapper = new ilExerciseMapper();
        $rank = $ilExerciseMapper->getRankedStudent($overviewId, $studId);
        $count = $ilExerciseMapper->getCount($overviewId);
        $date = $ilExerciseMapper->getDate($overviewId);
        if (!$rank == '0') {
            $tpl->setVariable("eoRanking", $rank . " " . $lng->txt('rep_robj_xtov_out_of') . "  " . $count . "<br> ".$lng->txt('rep_robj_xtov_lastupdate').": " . $date);
        } else {
            $tpl->setVariable("eoRanking", $lng->txt('links_not_available'));
        }

        return $tpl->get();
    }
    /**
	 * 
	 * @global type $ilDB
	 * @param type $refId
	 * @return deleted, NULL if object is not deleted, date if object is deleted
	 */
    public function isTestDeleted($refId) {
        global $ilDB;
        $query = "SELECT deleted FROM object_reference WHERE ref_id = %s";
        $result = $ilDB->queryF($query, array('integer'), array($refId));
        $deleteObj = $ilDB->fetchObject($result);
        return $deleteObj->deleted;
    }
	/**
	 * 
	 * @global type $ilDB
	 * @param type $ObjId
	 * @return deleted, NULL if object is not deleted, date if object is deleted
	 */
    public function isExerciseDeleted($ObjId) {
        global $ilDB;
        $query = "SELECT deleted FROM object_reference WHERE obj_id = %s";
        $result = $ilDB->queryF($query, array('integer'), array($objId));
        $deleteObj = $ilDB->fetchObject($result);
        return $deleteObj->deleted;
    }


    
	/**
	 * 
	 * @global type $ilDB
	 * @param type $overviewId
	 * @return int Number of tests associated with the Overview object
	 */
    private function getNumTests($overviewId) {
        global $ilDB;
        $count = 0;
        $data = array();
        $query = "SELECT 
					ref_id_test, tst_tests.ending_time
				FROM
					rep_robj_xtov_t2o
				JOIN
					object_reference
				JOIN
					tst_tests ON (ref_id_test = ref_id AND obj_id = obj_fi)
				WHERE
					obj_id_overview = %s AND object_reference.deleted IS NULL";
        $result = $ilDB->queryF($query, array('integer'), array($overviewId));
        while ($testObj = $ilDB->fetchObject($result)) {
            array_push($data, $testObj);
        }
        $timestamp = time();
        
        foreach ($data as $set) {
            $testTime = (float) $set->ending_time;
            if (($testTime - $timestamp) < 0) {
                $count ++;
            }
        }
        return $count;
    }

    /////////////////////////////////////////// Exercise VIEW ////////////////////////////////////////
    /**
     * Gets all exercises and marks for the given student 
     * @param type $studId
     */
    private function getExerciseMarks($studId, $overviewId) {
        global $ilDB;
        $grades = array();
        $query = "SELECT 
					ut_lp_marks.usr_id AS user_id, obj_id, mark
				FROM
					rep_robj_xtov_e2o
				JOIN
					ut_lp_marks
				JOIN
					usr_data ON (rep_robj_xtov_e2o.obj_id_exercise = ut_lp_marks.obj_id
				AND 
					ut_lp_marks.usr_id = usr_data.usr_id)
				WHERE
					obj_id_overview = %s
				AND 
					ut_lp_marks.usr_id = %s";
        $result = $ilDB->queryF($query,array('integer','integer'),array($overviewId,$studId));
        while ($exercise = $ilDB->fetchObject($result)) {
            array_push($grades, $exercise);
        }
        return $grades;
    }

}

?>
