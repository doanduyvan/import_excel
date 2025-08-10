<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Services\handleExcel\ImportExcel;
use App\Models\Products as ProductModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;



class Products extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pathExcel = storage_path('app/seeder/products.xlsx');
        $importExcel = new ImportExcel();
        $mapping = [
            'A' => 'sap_item_code',
            'B' => 'item_short_description',
        ];
        $startRow = 4;
        $data = $importExcel->readSmallExcelXlsx($pathExcel, $mapping, $startRow);
        try {
            DB::table('products')->insertOrIgnore($data);
        } catch (\Exception $e) {
            Log::error('Error inserting products: ' . $e->getMessage());
        }
        printf("Products seeder completed successfully.\n");
    }
}
