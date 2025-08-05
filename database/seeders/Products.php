<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Services\handleExcel\ImportExcel;
use App\Models\Products as ProductModel;

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
        $data = $importExcel->import($mapping, $pathExcel, $startRow);
        foreach ($data as $row) {
            $exists = ProductModel::where('sap_item_code', $row['sap_item_code'])->exists();

            if (!$exists) {
                ProductModel::create([
                    'sap_item_code' => $row['sap_item_code'],
                    'item_short_description' => $row['item_short_description'],
                ]);
            }
        }
        printf("Products seeder completed successfully.\n");
    }
}
