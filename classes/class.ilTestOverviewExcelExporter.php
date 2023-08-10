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


class ilTestOverviewExcelExporter
{
    /** @var ilLanguage $lng */
    protected $lng;

    public function __construct()
    {
        global $DIC;
        $this->lng = $DIC['lng'];
    }

    public function export($title, $columns, $row_data, $filename, $send = true): void
    {
        $excel = new ilExcel();
        $excel->addSheet(
            $title ?: $this->lng->txt("export")
        );
        $row = 1;

        ob_start();

        $pre = $row;

        $this->fillHeaderExcel($excel, $columns, $row); // row should NOT be incremented in fillHeaderExcel()! (required method)
        if ($pre === $row) {
            $row++;
        }

        if(is_array($row_data)) {
            foreach ($row_data as $set) {
                $this->fillRowExcel($excel, $row, $set);
                $row++;
            }
        }
        ob_end_clean();

        if ($send) {
            $excel->sendToClient($filename);
        } else {
            $excel->writeToFile($filename);
        }
    }

    /**
     * Excel Version of Fill Header. Likely to
     * be overwritten by derived class.
     *
     * @param ilExcel $a_excel Excel wrapper
     * @param array   $columns Header row array
     * @param integer $row     Row counter
     */
    protected function fillHeaderExcel(ilExcel $a_excel, $columns, $row): void
    {
        $col = 0;
        foreach ($columns as $column) {
            $title = strip_tags($column);
            if ($title) {
                $a_excel->setCell($row, $col++, $title);
            }
        }
        $a_excel->setBold("A" . $row . ":" . $a_excel->getColumnCoord($col - 1) . $row);
    }

    /**
     * Fills a row
     *
     * @param ilExcel $a_excel excel wrapper
     * @param integer $row     row counter
     * @param array   $a_set   data array
     */
    protected function fillRowExcel(ilExcel $a_excel, $row, $a_set): void
    {
        $col = 0;
        foreach ($a_set as $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $col++;


            $color = "FFFFFF";
            if(str_replace("##no-result##", '', $value) !== $value) {
                $color = "999999";
            } elseif (str_replace('##red-result##', '', $value) !== $value) {
                $color = "FF0000";
            } elseif (str_replace('##green_result##', '', $value) !== $value) {
                $color = "00CC00";
            } elseif (str_replace('##yellow-result##', '', $value) !== $value) {
                $color = "FFC100";
            } elseif (str_replace('##orange-result##', '', $value) !== $value) {
                $color = "FFC100";
            }
            $value = str_replace(['##no-result##', '##red-result##', '##green-result##', '##yellow-result##', '##orange-result##'], '', $value);

            $a_excel->setCell($row, $col-1, $value);
            if($color !== "FFFFFF") {
                $a_excel->setColors($a_excel->getCoordByColumnAndRow($col - 1, $row), $color);
            }
        }
    }
}
