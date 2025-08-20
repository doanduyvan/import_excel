<?php

namespace App\Services\HandleExcel;

class ReadExcel
{
    public $errs = []; // mảng lưu các lỗi trong quá trình xử lý

    public function readxlsx(
        string $path,
        array $mapping = [],
        int $startRow = 1,
        ?callable $handleBatch = null,
        int $batchSize = 1000,
        int $sheetIndex = 0
    ) {
        try {
            if (!file_exists($path)) {
                throw new \Exception("File not found: $path");
            }
            $mapIdxToField = [];
            foreach ($mapping as $colLetter => $field) {
                $idx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($colLetter) - 1; // 0-based
                $mapIdxToField[$idx] = $field;
            }

            // Tạo Spout reader (streaming, ít RAM)
            $reader = \Box\Spout\Reader\Common\Creator\ReaderEntityFactory::createXLSXReader();

            if (method_exists($reader, 'setShouldFormatDates')) {
                $reader->setShouldFormatDates(true); // Trả DateTime cho ô ngày
            }
            if (method_exists($reader, 'setShouldPreserveEmptyRows')) {
                $reader->setShouldPreserveEmptyRows(true); // Giữ dòng trống nếu có
            }

            $reader->open($path);

            $dataBatch = [];
            $rowNumber = 0;
            $currentSheetIndex = 0;

            foreach ($reader->getSheetIterator() as $sheet) {
                if ($currentSheetIndex === $sheetIndex) {
                    // Đúng sheet cần đọc → xử lý
                    foreach ($sheet->getRowIterator() as $row) {
                        $rowNumber++;
                        if ($rowNumber < $startRow) {
                            continue; // Bỏ qua header
                        }

                        $cells = $row->getCells(); // mảng Cell 0..N
                        $rowData = [];

                        foreach ($mapIdxToField as $colIdx => $fieldName) {
                            $value = isset($cells[$colIdx]) ? $cells[$colIdx]->getValue() : null;

                            if ($value instanceof \DateTimeInterface) {
                                $value = $value->format('Y-m-d');
                            }
                            if (is_string($value)) {
                                $value = trim($value);
                            }

                            $rowData[$fieldName] = $value;
                        }

                        // Bỏ dòng rỗng
                        if (!array_filter($rowData, fn($v) => $v !== null && $v !== '')) {
                            continue;
                        }

                        $dataBatch[] = $rowData;

                        if (count($dataBatch) === $batchSize) {
                            if (is_callable($handleBatch)) {
                                $handleBatch($dataBatch);
                            }
                            $dataBatch = [];
                            // gc_collect_cycles(); // có thể bật nếu muốn đảm bảo RAM ổn định
                        }
                    }

                    // Sau khi xử lý xong sheet được chọn → thoát luôn
                    break;
                }

                $currentSheetIndex++;
            }

            // Batch cuối
            if (!empty($dataBatch)) {
                if (is_callable($handleBatch)) {
                    $handleBatch($dataBatch);
                }
            }

            $reader->close();
            return self::returnResult(200, true, false, "Thành Công");
        } catch (\Throwable $e) {
            $this->errs[] = 'Lỗi import Excel (xlsx-stream): ' . $e->getMessage();
            return self::returnResult(500, false, true, '');
        }
    }

    public static function returnResult($status, $is_next, $is_errr, $message, $data = null)
    {
        return [
            'status' => $status, // trạng thái thành công
            'is_next' => $is_next,  // có tiếp tục chạy tiếp hay không
            'is_err' => $is_errr, // có phải lỗi hay không
            'message' => $message,
            'data' => $data
        ];
    }
}
