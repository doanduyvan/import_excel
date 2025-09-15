<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\handleExcel\ImportExcel;
use Illuminate\Support\Str;

class Accounts extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now('Asia/Ho_Chi_Minh');
        $pathExcel = storage_path('app/seeder/accounts.xlsx');
        $importExcel = new ImportExcel();
        $mapping = [
            'B' => 'account_name',
            'F' => 'customer_code',
        ];
        $startRow = 2;
        $data = $importExcel->readSmallExcelXlsx($pathExcel, $mapping, $startRow);

        // Bước 1: Chuẩn hóa & gom nhóm dữ liệu
        $map = []; // key = account_name => array of customer_code

        foreach ($data as $row) {
            if (!$row['account_name'] || !$row['customer_code']) continue;
            $accountName = Str::slug($row['account_name'], '');
            $map[trim($accountName)][] = trim($row['customer_code']);
        }
        $accountNames = array_keys($map);

        // Bước 2: Upsert các account_name vào bảng account
        $accountInsert = array_map(fn($name) => ['account_name' => $name, 'created_at' => $now], $accountNames);
        //    DB::table('users')->upsert($accountInsert, ['account_name'], []);
        try {
            DB::table('users')->insertOrIgnore($accountInsert);
        } catch (\Exception $e) {
            Log::error('Error inserting users: ' . $e->getMessage());
        }

        // Bước 3: Lấy danh sách account_name ↔ id

        $accounts = DB::table('users')
            ->whereIn('account_name', $accountNames)
            ->pluck('id', 'account_name') // [account_name => id]
            ->toArray();
        // Bước 4: Build subquery JOIN để update hàng loạt
        $updateRows = [];
        foreach ($map as $accountName => $customerCodes) {
            $accountId = $accounts[$accountName];
            foreach ($customerCodes as $code) {
                $updateRows[] = ['customer_code' => $code, 'user_id' => $accountId];
            }
        }

        // Bước 5: Chunk và UPDATE ... JOIN hàng loạt

        $chunks = array_chunk($updateRows, 1000); // tùy theo server

        foreach ($chunks as $chunk) {
            $first = true;
            $sub = null;

            foreach ($chunk as $row) {
                $q = DB::query()->selectRaw('? AS customer_code, ? AS user_id', [
                    $row['customer_code'],
                    $row['user_id']
                ]);
                if ($first) {
                    $sub = $q;
                    $first = false;
                } else {
                    $sub = $sub->unionAll($q);
                }
            }

            DB::table('customers as c')
                ->joinSub($sub, 'tmp', 'tmp.customer_code', '=', 'c.customer_code')
                ->update(['c.user_id' => DB::raw('tmp.user_id')]);
        }
    }
}
