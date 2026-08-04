<?php


class ilTestOverviewData
{
    private ilLanguage $lng;
    private ilObjTestOverview $TestOverviewObject;
    private float $full_max = 0; // Necessary?
    private $evalDataByTestId = [];
    private array $export_row_data = [];
    private array $export_header_data = [];

    public \ilTestOverviewDataPermissionsIndex $permissionsReadIndex;
    public \ilTestOverviewDataPermissionsIndex $permissionsAccessIndex;

    public function __construct(ilObjTestOverview $object)
    {
        $this->TestOverviewObject = $object;

        global $DIC;
        $this->lng = $DIC->language();

        $this->permissionsAccessIndex = new \ilTestOverviewDataPermissionsIndex();
        $this->permissionsReadIndex = new \ilTestOverviewDataPermissionsIndex();
    }

    /**
     * @param int      $obj_id
     * @param int[]    $refs
     * @param ilAccess $ilAccess
     * @return void
     */
    public function determineTestAccessPermissions(int $obj_id, array $refs, ilAccess $ilAccess)
    {
        // Set default permissions based on statistics or write access
        $this->permissionsAccessIndex[$obj_id] = false;
        $this->permissionsReadIndex[$obj_id] = false;
        foreach($refs as $ref_id) {
            switch(true) {
                case $ilAccess->checkAccess("tst_statistics", "", $ref_id):
                case $ilAccess->checkAccess("write", "", $ref_id):
                    $this->permissionsAccessIndex[$obj_id] = $ref_id;
                    break 2;
                case $ilAccess->checkAccess("read", "", $ref_id):
                    $this->permissionsReadIndex[$obj_id] = $ref_id;
                    break 2;
            }
        }
    }

    public function getValidRefIdForAccess(int $obj_id): ?int
    {
        return $this->permissionsAccessIndex[$obj_id] ?: $this->permissionsReadIndex[$obj_id] ?: null;
    }

    public function getExportHeaderData(ilAccess $ilAccess): array
    {
        $this->export_header_data[] = $this->lng->txt('rep_robj_xtov_test_overview_hdr_firstname');
        $this->export_header_data[] = $this->lng->txt('rep_robj_xtov_test_overview_hdr_lastname');
        $this->export_header_data[] = $this->lng->txt('rep_robj_xtov_test_overview_hdr_login');

        foreach($this->TestOverviewObject->getUniqueTests() as $obj_id => $refs) {
            $this->determineTestAccessPermissions($obj_id, $refs, $ilAccess);

            $title_text = $this->TestOverviewObject->getTest($obj_id)->getTitle();
            /** @var ilObjTest $test_object */
            $test_object = $this->TestOverviewObject->getTest($obj_id);
            $evaluation = $test_object->getCompleteEvaluationData();
            $participants = $evaluation->getParticipants();
            if(count($participants)) {
                /** @var ilTestEvaluationUserData $participant */
                $participant = current($participants);
                $this->full_max = $this->full_max + $participant->getMaxpoints();
            }

            if($this->TestOverviewObject->getPointsColumn() && $this->TestOverviewObject->getHeaderPoints()) {
                if(count($participants)) {
                    /** @var ilTestEvaluationUserData $participant */
                    $participant = current($participants);
                    $title_text .= ' (' . $participant->getMaxpoints() . ' ' . $this->lng->txt('points') . ')';
                } else {
                    $title_text .= ' (? ' . $this->lng->txt('points') . ')';
                }
            }

            //$ilCtrl->setParameterByClass("ilobjtestgui", 'ref_id', $this->to_data->getValidRefIdForAccess($obj_id));
            //$this->addTestColumn($title_text, $ilCtrl->getLinkTargetByClass('ilobjtestgui', 'infoScreen'));
            $this->export_header_data[] = $title_text;
            //$ilCtrl->setParameterByClass("ilobjtestgui", 'ref_id', '');
            $this->TestOverviewObject->gatherTestData($this->TestOverviewObject->getTest($obj_id), $this->evalDataByTestId);
        }

        if($this->TestOverviewObject->getResultColumn()) {
            if ($this->TestOverviewObject->getResultPresentation() == ilObjTestOverview::PRESENTATION_PERCENTAGE) {
                $this->export_header_data[] = $this->lng->txt('rep_robj_xtov_test_overview_hdr_avg');
            } else {
                $this->export_header_data[] = $this->lng->txt('rep_robj_xtov_test_overview_hdr_sum');
            }
        }

        if($this->TestOverviewObject->getPointsColumn()) {
            $points = "";
            if($this->full_max > 0) {
                $points = " (" . $this->full_max . " " . $this->lng->txt('points') . ")";
            }
            $this->export_header_data[] = $this->lng->txt('rep_robj_xtov_test_overview_hdr_points') . $points;
        }

        if($this->TestOverviewObject->getAverageColumn()) {
            $this->export_header_data[] = $this->lng->txt('rep_robj_xtov_test_overview_hdr_avg');
        }

        return $this->export_header_data;
    }

    public function getExportRowData(ilTOMappedTableGUI $table): array
    {
        $mapper = new ilOverviewMapper();
        $data = $mapper->getList(
            $table->getParams(),
            $table->getFilters()
        );

        $rows = $table->formatData($data);
        foreach($rows['items'] as $row) {
            $user = $row;
            $member_id = $row->getId();
            $results = array();

            $max_points = 0;
            $reached_points = 0;
            $row_data = [];
            foreach ($this->TestOverviewObject->getUniqueTests() as $obj_id => $refs) {
                $test = $this->TestOverviewObject->getTest($obj_id);
                $activeId = $test->getActiveIdOfUser($member_id);
                if ($activeId === null) {
                    $row_data[] = "##no-result##";
                } else {
                    $testResult = null;
                    global $ilUser;
                    $testResult = $test->getTestResult($activeId);
                    if ($this->permissionsAccessIndex[$obj_id] || ($this->permissionsReadIndex[$obj_id] && $ilUser->getId() == $member_id)) {
                        if ($testResult !== [] && strlen($testResult['pass']['percent'])) {
                            $max_points = $max_points + $testResult['pass']['total_max_points'];
                            $reached_points = $reached_points + $testResult['pass']['total_reached_points'];
                            if ($this->TestOverviewObject->getResultPresentation() == ilObjTestOverview::PRESENTATION_PERCENTAGE) {
                                $result = sprintf("%.2f %%", (float) $testResult['pass']['percent'] * 100);
                            } else {
                                if ($this->TestOverviewObject->getPointsColumn() && $this->TestOverviewObject->getHeaderPoints()) {
                                    $result = $testResult['pass']['total_reached_points'];
                                } else {
                                    $result = $testResult['pass']['total_reached_points'] . ' / ' . $testResult['pass']['total_max_points'];
                                }
                            }
                            $row_data[] = $result;
                            $results[] = $result;
                        } else {
                            $row_data[] = " ";
                            $results[] = 0;
                        }
                    } else {
                        $row_data[] = " ";
                    }
                }
            }

            $this->populateEvaluationColumns($row_data, $reached_points, $max_points);

            $row_data2 = array();
            $row_data2[] = $user->getFirstname();
            $row_data2[] = $user->getLastname();
            $row_data2[] = $user->getLogin();
            foreach($row_data as $item) {
                $row_data2[] = $item;
            }
            foreach ($this->temp_results as $item) {
                $row_data2[] = $item;
            }
            $this->export_row_data[] = $row_data2;
            $this->temp_results = array();
        }
        return $this->export_row_data;
    }

    public function populate(ilMappedTableGUI $table): array
    {
        if($table->getExternalSegmentation() && $table->getExternalSorting()) {
            $table->determineOffsetAndOrder();
        } elseif(!$table->getExternalSegmentation() && $table->getExternalSorting()) {
            $table->determineOffsetAndOrder(true);
        } else {
            throw new ilException('invalid table configuration: extSort=false / extSegm=true');
        }

        /* Configure query execution */
        $params = array();
        if($table->getExternalSegmentation()) {
            $params['limit'] = $table->getLimit();
            $params['offset'] = $table->getOffset();
        }
        if($table->getExternalSorting()) {
            $params['order_field'] = $table->getOrderField();
            $params['order_direction'] = $table->getOrderDirection();
        }

        $overview = $table->getParentObject()->getObject();
        $filters  = array("overview_id" => $overview->getId()) + $table->filter;

        /* Execute query. */
        $data = $table->getMapper()->getList($params, $filters);

        if(!count($data['items']) && $table->getOffset() > 0) {
            /* Query again, offset was incorrect. */
            $table->resetOffset();
            $data = $table->getMapper()->getList($params, $filters);
        }

        /* Post-query logic. Implement custom sorting or display
           in formatData overload. */
        $data = $table->formatData($data);

        return $table->buildTableRowsArray($data['items']);
    }

    protected function populateEvaluationColumns($results, $reached_points, $max_points)
    {
        if($this->TestOverviewObject->getResultColumn()) {
            if       (count($results)) {
                if($this->TestOverviewObject->getResultPresentation() == ilObjTestOverview::PRESENTATION_PERCENTAGE) {
                    if($max_points === 0) {
                        $points = '0.00 %';
                    } else {
                        $points = sprintf("%.2f %%", ($reached_points / $max_points) * 100);
                    }
                } else {
                    $points = sprintf("%.2f", $reached_points) . ' / ' . sprintf("%.2f", $max_points);
                }
            } else {
                $points = "0.00 %";
            }
            $this->temp_results[] = $points;
        }

        $t_results = [];
        foreach ($results as $result) {
            $t_results[] = (int)$result;
        }

        if($this->TestOverviewObject->getPointsColumn()) {
            if (count($results)) {
                $points = sprintf("%.2f", array_sum($t_results));
            } else {
                $points = "";
            }
            $this->temp_results[] = $points;
        }

        if($this->TestOverviewObject->getAverageColumn()) {
            if (count($results)) {
                if($this->TestOverviewObject->getResultPresentation() == ilObjTestOverview::PRESENTATION_PERCENTAGE) {
                    if(array_sum($t_results) == 0) {
                        $points = '0.00 %';
                    } else {
                        $points = sprintf("%.2f %%", (array_sum($t_results) / count($results)));
                    }
                } else {
                    if($this->full_max === 0 || array_sum($results) === 0) {
                        $points = '0.00 %';
                    } else {
                        $points = sprintf("%.2f %%", (array_sum($t_results) / $this->full_max) * 100);
                    }
                }
            } else {
                $points = "0.00 %";
            }

            $this->temp_results[] = $points;
        }
    }
}
