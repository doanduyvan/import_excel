<?php

namespace App\Services;

use App\Services\handleExcel\ReadExcel;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ImportProduct
{
    public function handle()
    {
        $this->import();
    }

    public function import()
    {
        $path = storage_path('app/seeder/products.xlsx');
        $mapping = [
            'A' => 'sap_item_code',
            'B' => 'item_short_description',
        ];
        $readexcel = new ReadExcel();
        $res = $readexcel->readxlsx($path, $mapping, 4, function ($dataBatch) {
            $format = [];
            foreach ($dataBatch as $row) {
                $key = trim(Str::slug($row['sap_item_code'], ''));
                $format[$key] = [
                    'sap_item_code' => $key,
                    'item_short_description' => trim($row['item_short_description']),
                    'group_name' => strtolower(trim(substr(trim($row['item_short_description']), 0, 6)))
                ];
            }

            // lưu vào bảng products
            $dataInsert = [];
            foreach ($format as $row) {
                $key = $row['group_name'];
                $dataInsert[$key] = [
                    'name' => $key,
                ];
            }
            DB::table('products')->insertOrIgnore(array_values($dataInsert));
            // lấy lại ID của các bản ghi đã chèn
            $insertedIds = DB::table('products')->whereIn('name', array_keys($dataInsert))
                ->pluck('id', 'name')
                ->toArray();
            // lưu vào bảng variants
            $datainsert = [];
            foreach ($format as $row) {
                $key = $row['sap_item_code'];
                $foreignKey = $row['group_name'];
                $datainsert[$key] = [
                    'sap_item_code' => $key,
                    'item_short_description' => $row['item_short_description'],
                    'product_id' => $insertedIds[$foreignKey] ?? null,
                ];
            }
            // Chèn vào DB
            DB::table('variants')->insertOrIgnore(array_values($datainsert));
            \Log::info($format);
            // Insert into DB
            // DB::table('products')->insertOrIgnore(array_values($datainsert));
        }, 5);
    }
}
