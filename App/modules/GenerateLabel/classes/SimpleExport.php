<?php

namespace label;

/**
 * Class SimpleExport
 * Class used to process simple tables exports into xls or csv
 */
class SimpleExport
{
    const OUTPUT_CSV = 'csv';
    const OUTPUT_XLS = 'xls';

    const COLUMN_TYPE_STRING = 'string';
    const COLUMN_TYPE_INT = 'int';

    private $outputFormat;
    private $filename;
    private $types = [];
    private $head;
    private $body;

    /**
     * SimpleExport constructor.
     * @param string $filename without filetype
     * @param string $outputFormat can be one of self::OUTPUT_*
     * @throws \Exception if wrong format passed
     */
    public function __construct($filename, $outputFormat)
    {
        if (
            ($outputFormat !== self::OUTPUT_XLS)
            && ($outputFormat !== self::OUTPUT_CSV)
        ) {
            throw new \Exception('Unknown output format');
        }
        $this->outputFormat = $outputFormat;
        $this->filename = $filename;
    }

    /**
     * Take a list of types in table, one value - one row.
     * Filtering wrong types
     * @param string[] $types
     */
    public function setColumnTypes($types)
    {
        $this->types = array_map(function($type){if ($type === self::COLUMN_TYPE_INT) return self::COLUMN_TYPE_INT; return self::COLUMN_TYPE_STRING;}, $types);
    }

    /**
     * Making files to export and returning it to standart output
     * @param string[] $head head row
     * @param string[][] $body rows for columns
     */
    public function export($head, $body)
    {
        $this->head = $head;
        $this->body = $body;
        switch ($this->outputFormat) {
            case self::OUTPUT_XLS:
                $this->printXLS();
                break;
            case self::OUTPUT_CSV:
                $this->printCSV();
                break;
        }
        exit;
    }

    /**
     * Printing CSV
     */
    private function printCSV()
    {
        $tmpFile = TMP_DIR . '/export_' . $this->filename . '.csv';
        $handler = fopen($tmpFile, 'w');

        fputcsv($handler, $this->head, ';');
        foreach ($this->body as $row) {
            fputcsv($handler, $row, ';');
        }
        fclose($handler);

        header('Content-type: application/excel; name=' . $this->filename . '.csv');
        header('Content-disposition: attachment; filename=' . $this->filename . '.csv');

        echo file_get_contents($tmpFile);
        unset($tmpFile);
    }

    /**
     * Printing XLS
     */
    private function printXLS()
    {
        error_reporting(0);
        ini_set('display_errors', 'off');
        require_once ROOT_DIR.'/Spreadsheet/Excel/Writer.php';

        $workbook = new \Spreadsheet_Excel_Writer();
        $workbook->setVersion(8);

        $sheet = $workbook->addWorksheet('lalala');
        $sheet->setInputEncoding('UTF-8');

        $this->addXLSHead($workbook, $sheet);
        $this->addXLSBody($sheet);

        $workbook->send($this->filename . '.xls');
        $workbook->close();
    }

    /**
     * Adding head to XLS
     * @param \Spreadsheet_Excel_Writer $workbook
     * @param \Spreadsheet_Excel_Writer_Worksheet $sheet
     */
    private function addXLSHead(\Spreadsheet_Excel_Writer $workbook, \Spreadsheet_Excel_Writer_Worksheet $sheet)
    {
        $headFormat = $workbook->addFormat();
        $headFormat->setBold();
        $headFormat->setFgColor(22);
        $headFormat->setAlign('center');

        for ($y = 0; $y < count($this->head); $y++) {
            $sheet->setColumn($y, $y, 12);
            $sheet->writeString(0, $y, $this->head[$y], $headFormat);
        }
    }

    /**
     * Adding body to xls
     * @param \Spreadsheet_Excel_Writer_Worksheet $sheet
     */
    private function addXLSBody(\Spreadsheet_Excel_Writer_Worksheet $sheet)
    {
        $rowNumber = 1;
        foreach ($this->body as $row) {
            for ($y = 0; $y < count($row); $y++) {
                if (isset($row[$y])) {
                    if ($this->getColumnType($y) === self::COLUMN_TYPE_INT) {
                        $sheet->writeNumber($rowNumber, $y, $row[$y]);
                    } else {
                        $sheet->writeString($rowNumber, $y, $row[$y]);
                    }
                }
            }
            $rowNumber++;
        }
    }

    /**
     * Returns type of column
     * @param int $colNumber column number
     * @return string one of self::COLUMN_TYPE_*
     */
    private function getColumnType($colNumber)
    {
        if (isset($this->types[$colNumber])) {
            return $this->types[$colNumber];
        }
        return self::COLUMN_TYPE_STRING;
    }
}