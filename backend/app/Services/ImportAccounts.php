<?php

namespace App\Services;

use App\Services\handleExcel\ReadExcel;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;


class ImportAccounts
{


    public function handle()
    {
        $this->import();
    }

    public function import()
    {
        //
        $path = storage_path('app/seeder/accounts.xlsx');
        $mapping = [
            'B' => 'account_name',
            'D' => 'brick_codewo',
            'E' => 'customer_account_name',
            'F' => 'customer_code',
        ];
        $readexcel = new ReadExcel();
        $res = $readexcel->readxlsx($path, $mapping, 2, function ($dataBatch) {
            // \Log::info($dataBatch);
            // \Log::info('end batch');
            $datainsert = [];
            foreach ($dataBatch as $row) {
                $key = trim(Str::slug($row['account_name'], ''));
                $datainsert[$key] = [
                    'account_name' => $key,
                    'fullname' => $row['account_name']
                ];
            }
            // Chèn vào DB
            DB::table('users')->insertOrIgnore(array_values($datainsert));
            // lấy lại ID của các bản ghi đã chèn
            $insertedIds = DB::table('users')->whereIn('account_name', array_keys($datainsert))
                ->pluck('id', 'account_name')
                ->toArray();

            // chèn DB bảng customer_account
            $datainsert = [];
            foreach ($dataBatch as $row) {
                $key = trim($row['brick_codewo']);
                $foreignKey = trim(Str::slug($row['account_name'], ''));
                $datainsert[$key] = [
                    'brick_codewo' => $key,
                    'customer_account_name' => $row['customer_account_name'],
                    'user_id' => $insertedIds[$foreignKey] ?? null,
                ];
            }

            DB::table('customer_account')->insertOrIgnore(array_values($datainsert));
            $insertedIds = DB::table('customer_account')->whereIn('brick_codewo', array_keys($datainsert))
                ->pluck('id', 'brick_codewo')
                ->toArray();

            // tìm kiếm bản ghi trong bảng customer và cập nhật id khóa ngoại
            // $updateRows[] = ['customer_code' => $code, 'customer_account_id' => $accountId];
            $updateRows = [];
            foreach ($dataBatch as $row) {
                $key = trim($row['customer_code']);
                $foreignKey = trim($row['brick_codewo']);
                $updateRows[$key] = [
                    'customer_code' => $key,
                    'customer_account_id' => $insertedIds[$foreignKey] ?? null,
                ];
                // $updateRows[$key] = $insertedIds[$foreignKey] ?? null;
            }
            $this->batchUpdate($updateRows);
            \Log::info($updateRows);
        }, 5);
        \Log::info($readexcel->errs);
    }

    function batchUpdate(array $data)
    {
        if (empty($data)) return;

        $cases = '';
        $codes = [];
        foreach ($data as $row) {
            $customer_code = $row['customer_code'];
            $customer_account_id = $row['customer_account_id'];
            $cases .= "WHEN '$customer_code' THEN $customer_account_id ";
            $codes[] = "'$customer_code'";
        }
        $codesList = implode(',', $codes);

        $sql = "
        UPDATE customers
        SET customer_account_id = CASE customer_code
            $cases
        END
        WHERE customer_code IN ($codesList)
    ";
        DB::statement($sql);
    }
}
