<?php

namespace App\Services\handleExcel;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;
use Exception;

class ImportExcel
{

    public function excel_mapping_db($type): array | null
    {
        if ($type === 'tender') {
            return
                [
                    "A" => "customer_code",
                    "B" => "customer_name",
                    "E" => "area",
                    "L" => "sap_item_code",
                    "N" => "item_short_description",
                    "P" => "customer_quota_description",
                    "T" => "cust_quota_start_date",
                    "U" => "cust_quota_end_date",
                    "X" => "cust_quota_quantity",
                    "Y" => "invoice_quantity",
                    "Z" => "return_quantity",
                    "AA" => "allocated_quantity",
                    "AB" => "used_quota",
                    "AC" => "remaining_quota",
                    "AD" => "report_run_date",
                    "W" => "tender_price",
                ];
        }

        if ($type === 'sales') {
            return
                [
                    "A" => "customer_code",
                    "C" => "customer_name",
                    "F" => "area",
                    "M" => "sap_item_code",
                    "O" => "item_short_description",
                    "H" => "order_number",
                    "I" => "invoice_number",
                    "J" => "contract_number",
                    "T" => "expiry_date",
                    "X" => "selling_price",
                    "Y" => "commercial_quantity",
                    "AB" => "invoice_confirmed_date",
                    "AA" => "net_sales_value",
                    "AC" => "accounts_receivable_date",
                ];
        }
        return null;
    }

    public function import($mapping, $path, $startRow = 1, $sheetIndex = 0): array|bool
    {

        try {
            if (!file_exists($path)) {
                throw new Exception("File not found: " . $path);
            }

            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getSheet($sheetIndex);
            $rows = $sheet->toArray(null, false, true, true); // A, B, C...

            $data = [];

            foreach ($rows as $index => $row) {
                if ($index < $startRow) continue; // Bỏ dòng tiêu đề && index trong $rows bắt đầu từ 1

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
