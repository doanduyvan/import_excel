<?php

namespace App\Services\handleExcel;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;
use Exception;

class ImportExcel
{

    public function excel_mapping_db(): array
    {
        return
            [
                "A" => "name",
                "B" => "phone",
                "C" => "email"
            ];
    }

    public function import(): array|bool
    {

        $pathtest = storage_path('app/excel/temp.xlsx'); // temp check
        $path = $pathtest;
        $sheetIndex = 0; // chọn sheet cần lấy theo index

        try {
            if (!file_exists($path)) {
                Log::error("File Excel không tồn tại: $path");
                return false;
            }

            $mapping = $this->excel_mapping_db();
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getSheet($sheetIndex);
            $rows = $sheet->toArray(null, false, true, true); // A, B, C...

            $data = [];

            foreach ($rows as $index => $row) {
                if ($index === 1) continue; // Bỏ dòng tiêu đề && index trong $rows bắt đầu từ 1

                $record = [];

                foreach ($mapping as $column => $fieldName) {
                    $record[$fieldName] = trim($row[$column] ?? '');
                }

                // Bỏ dòng rỗng hoàn toàn
                if (!array_filter($record)) continue;

                $data[] = $record;
            }

            return $data;
        } catch (Exception $e) {
            Log::error('Lỗi import Excel: ' . $e->getMessage());
            return false;
        }
    }
}
