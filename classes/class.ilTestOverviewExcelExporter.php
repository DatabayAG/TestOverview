<?php
require_once './Services/Excel/classes/class.ilExcel.php';

class ilTestOverviewExcelExporter
{
	/** @var ilLanguage $lng */
	protected $lng;

	public function __construct()
	{
		global $DIC;
		$this->lng = $DIC['lng'];
	}

	public function export($title, $columns, $row_data, $filename, $send = true)
	{
		$excel = new ilExcel();
		$excel->addSheet($title ? $title : $this->lng->txt("export")
		);
		$row = 1;

		ob_start();

		$pre = $row;

		$this->fillHeaderExcel($excel, $columns, $row); // row should NOT be incremented in fillHeaderExcel()! (required method)
		if ($pre == $row)
		{
			$row++;
		}

		foreach ($row_data as $set)
		{
			$this->fillRowExcel($excel, $row, $set);
			$row++;
		}

		ob_end_clean();

		if ($send)
		{
			$excel->sendToClient($filename);
		}
		else
		{
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
	protected function fillHeaderExcel(ilExcel $a_excel, $columns, $row)
	{
		$col = 0;
		foreach ($columns as $column)
		{
			$title = strip_tags($column);
			if ($title)
			{
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
	protected function fillRowExcel(ilExcel $a_excel, $row, $a_set)
	{
		$col = 0;
		foreach ($a_set as $value)
		{
			if (is_array($value))
			{
				$value = implode(', ', $value);
			}
			$a_excel->setCell($row, $col++, $value);
		}
	}
}