<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Generic Excel export for any report produced by ReportService. */
class ReportExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(private array $report)
    {
    }

    public function array(): array
    {
        // Send numbers as numbers so totals can be calculated in Excel.
        return array_map(function ($row) {
            foreach ($this->report['numeric'] as $i) {
                if (isset($row[$i]) && is_string($row[$i])) {
                    $row[$i] = (float) str_replace([',', '+'], '', $row[$i]);
                }
            }

            return $row;
        }, $this->report['rows']);
    }

    public function headings(): array
    {
        return $this->report['columns'];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return mb_substr($this->report['title'], 0, 31);
    }
}
