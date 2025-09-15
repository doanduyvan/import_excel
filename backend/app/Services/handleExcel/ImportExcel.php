<?php

namespace App\Services\handleExcel;

use Illuminate\Support\Facades\Log;
use Exception;
use ZipArchive;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Carbon\Carbon;
use App\Services\handleExcel\ReadExcel;


class ImportExcel
{

    public $errs = []; // mảng lưu các lỗi trong quá trình xử lý
    private $isTruncatedTender = false;

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

    public function FakeEmails()
    {

        return [
            "status" => 200,
            "is_next" => true,
            "is_err" => false,
            "message" => "Thành công",
            "data" => [
                [
                    "email_number" => 572,
                    "subject" => "Fw: 5001580 R001195VN  DailyNetSalesData_NW",
                    "from" => "Quang Doan <ds.duyquang@hotmail.com>",
                    "date" => "Wed, 6 Aug 2025 10:04:32 +0000",
                    "type" => "sales",
                    "zips" => [
                        "filename" => "5001580 R001195VN  DailyNetSalesData_NW.zip",
                        "path" => "C:\duyvan\projects\import_excel\storage\app/tmp_zips/5001580 R001195VN  DailyNetSalesData_NW.zip"
                    ],
                    "pathExcel" => "C:\duyvan\projects\import_excel\storage\app/tmp_excels/5001580 R001195VN  DailyNetSalesData_NW/R001195VN_5001580_2025-01-17-04-30-41_DailyNetSalesData_NW.xlsx"
                ],
                [
                    "email_number" => 573,
                    "subject" => "Fw: 5001580 R000772VN TenderQuotaStatus",
                    "from" => "Quang Doan <ds.duyquang@hotmail.com>",
                    "date" => "Wed, 6 Aug 2025 10:04:50 +0000",
                    "type" => "tender",
                    "zips" => [
                        "filename" => "5001580 R000772VN TenderQuotaStatus.zip",
                        "path" => "C:\duyvan\projects\import_excel\storage\app/tmp_zips/5001580 R000772VN TenderQuotaStatus.zip"
                    ],
                    "pathExcel" => "C:\duyvan\projects\import_excel\storage\app/tmp_excels/5001580 R000772VN TenderQuotaStatus/R000772VN_0005001580_2025-07-29-03-33-14_TenderQuotaStatus.XLS"
                ],

            ],
        ];
    }


    public function handleAll()
    {
        ini_set('memory_limit', '512M'); // tăng giới hạn bộ nhớ
        ini_set('max_execution_time', 0); // không giới hạn thời gian

        // Lấy email trong 5 ngày gần đây, trả về các email có file Zip đúng cấu trúc
        $this->cleanFolder(); // xóa thư mục tạm trước khi bắt đầu
        $emails = $this->receiveMail(5);
        if (!$emails['is_next']) {
            return;
        }
        // giải nén và lấy path excel
        foreach ($emails['data'] as $key => $email) {
            $handlExtractZip = $this->handleFileZip($email['zips']);
            if (!$handlExtractZip['is_next']) {
                // lỗi giải nén của từng mail sẽ sử lí ở đây
                // echo $handlExtractZip['message'];
                continue;
            }
            $emails['data'][$key]['pathExcel'] = $handlExtractZip['data'];
        }
        // sử lí đọc file excel và lưu vào db
        // $emails = $this->FakeEmails(); // tạm thời dùng dữ liệu giả để test

        foreach ($emails['data'] as $key => $email) {
            $type = $email['type'];

            // đọc file excel và lưu vào db
            $pathExcel = $email['pathExcel'];
            if (!file_exists($pathExcel)) {
                $this->errs[] = "File Excel không tồn tại: $pathExcel";
                continue;
            }

            // import dữ liệu từ file excel
            $result = $this->handleExcel($pathExcel, $type);
            if (isset($result['is_err']) && $result['is_err']) {
                // nếu có lỗi trong quá trình import thì lưu lại
                $this->errs[] = "Lỗi khi import file Excel: " . $result['message'];
            }
        }

        Log::info("Xử lý hoàn tất. Tổng số lỗi: " . count($this->errs));
        Log::info("Danh sách lỗi: " . json_encode($this->errs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        // print_r($emails);
    }

    public function handleExcel($path, $type)
    {

        $fmt = $this->detectExcelFormat($path);

        return match ($fmt) {
            'xlsx_zip' => $this->importLargeExcel_xlsx($path, $type),
            'xls_ole'  => $this->importLargeExcel_xls($path, $type),
            'csv'      => $this->importLargeExcel_csv($path, $type),
            default    => $this->returnResult(415, false, true, "Định dạng không hỗ trợ hoặc file hỏng: $fmt"),
        };
    }

    /**
     * Đọc .xlsx theo batch bằng Spout, chỉ xử lý 1 sheet duy nhất.
     *
     * @param string $path       Đường dẫn file .xlsx
     * @param string $type       Loại dữ liệu (để truyền cho handleBatch)
     * @param int    $batchSize  Số dòng mỗi batch (mặc định 1000)
     * @param int    $startRow   Dòng bắt đầu đọc (1-based). Nếu có header, truyền 2
     * @param int    $sheetIndex Index sheet cần đọc (0-based)
     */
    public function importLargeExcel_xlsx(
        string $path,
        string $type,
        int $batchSize = 1000,
        int $startRow = 7,
        int $sheetIndex = 0
    ): array {

        // $readerexcel = new ReadExcel();
        // $result = $readerexcel->getCellValueXlsx($path, 'B', 4);
        // Log::info($result);
        // return [];
        // Lấy mapping theo type (key là cột chữ: "A" => "field")
        $mapping = $this->excel_mapping_db($type);

        try {
            if (!file_exists($path)) {
                throw new \Exception("File not found: $path");
            }
            // $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            // if ($ext !== 'xlsx') {
            //     throw new \Exception("importLargeExcel_xlsx() chỉ hỗ trợ .xlsx, đã nhận .$ext");
            // }

            // Chuẩn hoá mapping "A"=>"field" -> [0=>"field", 1=>"field", ...]
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
                            $this->handleBatch($type, $dataBatch);
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
                $this->handleBatch($type, $dataBatch);
            }

            $reader->close();
            return $this->returnResult(200, true, false, "Thành Công");
        } catch (\Throwable $e) {
            $this->errs[] = 'Lỗi import Excel (xlsx-stream): ' . $e->getMessage();
            return $this->returnResult(500, false, true, '');
        }
    }


    /**
     * Đọc file .xls theo từng khúc (chunk) để tiết kiệm RAM (giả lập streaming).
     * - Chỉ hỗ trợ .xls (BIFF)
     * - PhpSpreadsheet + ReadFilter, mỗi lần chỉ nạp batchSize dòng
     * - API giống hệt hàm xử lý .xlsx của bạn
     *
     * @param string $path       Đường dẫn file .xls
     * @param string $type       Loại dữ liệu (để truyền cho handleBatch)
     * @param int    $batchSize  Số dòng mỗi khúc (nên 500–2000 cho host 1GB RAM)
     * @param int    $startRow   Dòng bắt đầu đọc (1-based). Nếu có header, truyền 2.
     * @param int    $sheetIndex Sheet index (0-based), chỉ xử lý 1 sheet
     */
    public function importLargeExcel_xls(
        string $path,
        string $type,
        int $batchSize = 1000,
        int $startRow = 1,
        int $sheetIndex = 0
    ): array {
        $mapping = $this->excel_mapping_db($type);

        try {
            if (!file_exists($path)) {
                throw new \Exception("File not found: $path");
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext !== 'xls') {
                throw new \Exception("importLargeExcel_xls() chỉ hỗ trợ .xls, đã nhận .$ext");
            }

            // Chuẩn bị: tính sẵn index cột từ mapping để không cần getHighestColumn()
            // ["A" => "field"] -> [1 => "field"] (1-based theo PhpSpreadsheet)
            $colIndexToField = [];
            $maxColIndex = 0;
            foreach ($mapping as $colLetter => $field) {
                $idx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($colLetter);
                $colIndexToField[$idx] = $field;
                if ($idx > $maxColIndex) $maxColIndex = $idx;
            }

            // Filter nội bộ cho từng khúc
            $filter = new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
                private int $startRow = 1;
                private int $endRow = 1;
                public function setRows(int $startRow, int $chunkSize): void
                {
                    $this->startRow = $startRow;
                    $this->endRow   = $startRow + $chunkSize - 1;
                }
                public function readCell($column, $row, $worksheetName = ''): bool
                {
                    // Chỉ đọc các ô nằm trong dải dòng cần thiết; cột nào cần đã do ta lấy trực tiếp theo mapping.
                    return $row >= $this->startRow && $row <= $this->endRow;
                }
            };

            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
            $reader->setReadDataOnly(true);        // bỏ style/format để nhẹ RAM
            $reader->setReadFilter($filter);

            $currentRow = $startRow;
            $emptyChunkStreak = 0;
            $maxEmptyChunkStreak = 2; // gặp 2 khúc rỗng liên tiếp thì dừng (tránh quét dài ở phần đuôi trống)

            while (true) {
                $filter->setRows($currentRow, $batchSize);

                // Mỗi khúc load lại workbook nhưng chỉ parse phần dòng đã filter
                $spreadsheet = $reader->load($path);
                /** @var \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet */
                $sheet = $spreadsheet->getSheet($sheetIndex);

                $rowsData = [];
                $nonEmptyRows = 0;

                // Đọc đúng dải dòng của khúc hiện tại
                $endRow = $currentRow + $batchSize - 1;
                for ($r = $currentRow; $r <= $endRow; $r++) {
                    $record = [];
                    $rowAllEmpty = true;

                    // Chỉ đọc các cột có trong mapping để giảm overhead
                    foreach ($colIndexToField as $colIdx => $fieldName) {
                        $cell = $sheet->getCellByColumnAndRow($colIdx, $r, false);
                        // PhpSpreadsheet có thể tạo cell null nếu vượt phạm vi thực; an toàn kiểm tra isNull
                        if ($cell === null) {
                            $record[$fieldName] = null;
                            continue;
                        }

                        $val = $cell->getValue();

                        // Convert ngày trong .xls:
                        // - BIFF lưu ngày dạng số seri; nếu cell là date-format thì isDateTime = true
                        // - Một số file để format chưa chuẩn → thử thêm khi là số
                        if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                            try {
                                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val);
                                $val = $dt instanceof \DateTimeInterface ? $dt->format('Y-m-d') : $val;
                            } catch (\Throwable $e) {
                                // Giữ nguyên nếu convert lỗi
                            }
                        } elseif (is_numeric($val)) {
                            // Optional: thử convert nếu là số seri và nằm trong khoảng hợp lý (1900–2100)
                            // Tránh convert nhầm số lượng/giá
                            try {
                                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val);
                                if ($dt instanceof \DateTimeInterface) {
                                    $year = (int)$dt->format('Y');
                                    if ($year >= 1900 && $year <= 2100) {
                                        $val = $dt->format('Y-m-d');
                                    }
                                }
                            } catch (\Throwable $e) {
                            }
                        }

                        if (is_string($val)) $val = trim($val);
                        if ($val !== null && $val !== '') $rowAllEmpty = false;

                        $record[$fieldName] = $val;
                    }

                    if (!$rowAllEmpty) {
                        $rowsData[] = $record;
                        $nonEmptyRows++;
                    }
                }

                if (!empty($rowsData)) {
                    // Có dữ liệu → xử lý và chuyển sang khúc kế tiếp
                    $this->handleBatch($type, $rowsData);
                    $currentRow += $batchSize;
                    $emptyChunkStreak = 0;
                } else {
                    // Khúc rỗng
                    $emptyChunkStreak++;
                    if ($emptyChunkStreak >= $maxEmptyChunkStreak) {
                        // Gặp nhiều khúc rỗng liên tiếp → coi như hết file
                        $spreadsheet->disconnectWorksheets();
                        unset($spreadsheet);
                        gc_collect_cycles();
                        break;
                    }
                    // Không tăng currentRow để thử lại khúc kế tiếp (đề phòng có dải dữ liệu cách quãng rất xa),
                    // hoặc bạn có thể currentRow += $batchSize; nếu chắc chắn dữ liệu liên tục
                    $currentRow += $batchSize;
                }

                // Giải phóng RAM sau mỗi khúc
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                gc_collect_cycles();
            }

            return $this->returnResult(200, true, false, "Thành Công");
        } catch (\Throwable $e) {
            $this->errs[] = 'Lỗi importLargeExcel_xls (stream-like): ' . $e->getMessage();
            return $this->returnResult(500, false, true, '');
        }
    }

    public function importLargeExcel_csv(
        string $path,
        string $type,
        int $startRow = 7,
        int $batchSize = 1000
    ): array {
        $mapping = $this->excel_mapping_db($type);
        if (!$mapping) {
            return $this->returnResult(400, false, true, "Không tìm thấy mapping cho type: $type");
        }
        try {
            if (!file_exists($path)) {
                throw new \Exception("File not found: $path");
            }

            // Chuẩn hoá mapping "A"=>"field" -> [0=>"field", ...]
            $mapIdxToField = [];
            foreach ($mapping as $colLetter => $field) {
                $idx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($colLetter) - 1;
                $mapIdxToField[$idx] = $field;
            }

            // Tạo CSV reader của Spout (streaming)
            $reader = \Box\Spout\Reader\Common\Creator\ReaderEntityFactory::createCSVReader();

            // Thiết lập delimiter/encoding
            if (method_exists($reader, 'setFieldDelimiter')) {
                $reader->setFieldDelimiter($this->detectCsvDelimiter($path));
            }
            if (method_exists($reader, 'setEncoding')) {
                // Nếu phía đối tác là UTF-8 thì để 'UTF-8'; nếu có dấu hiệu khác hãy đổi sang 'ISO-8859-1' / 'WINDOWS-1252'
                $reader->setEncoding('UTF-8');
            }
            if (method_exists($reader, 'setShouldPreserveEmptyRows')) {
                $reader->setShouldPreserveEmptyRows(true);
            }

            $reader->open($path);

            $dataBatch = [];
            $rowNumber = 0;

            foreach ($reader->getSheetIterator() as $sheet) { // CSV chỉ có 1 "sheet"
                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    if ($rowNumber < $startRow) continue;

                    $cells = $row->getCells(); // array of CellInterface
                    $rowData = [];

                    foreach ($mapIdxToField as $colIdx => $fieldName) {
                        $value = isset($cells[$colIdx]) ? $cells[$colIdx]->getValue() : null;
                        // if (is_string($value)) $value = trim($value);
                        if (is_string($value)) {
                            // làm sạch null-byte (thường gặp khi nguồn UTF-16)
                            if (strpos($value, "\0") !== false) {
                                $value = str_replace("\0", '', $value);
                            }
                            // chuyển an toàn về UTF-8 (tự dò encoding phổ biến)
                            $value = $this->toUtf8Safe($value);
                            $value = trim($value);
                        }
                        $rowData[$fieldName] = $value;
                    }

                    if (!array_filter($rowData, fn($v) => $v !== null && $v !== '')) continue;

                    $dataBatch[] = $rowData;
                    if (count($dataBatch) === $batchSize) {
                        $this->handleBatch($type, $dataBatch);
                        $dataBatch = [];
                    }
                }
                break; // chỉ 1 CSV
            }

            if (!empty($dataBatch)) {
                $this->handleBatch($type, $dataBatch);
            }

            $reader->close();
            return $this->returnResult(200, true, false, "Thành Công");
        } catch (\Throwable $e) {
            $this->errs[] = 'Lỗi import CSV (stream): ' . $e->getMessage();
            return $this->returnResult(500, false, true, '');
        }
    }


    private function detectCsvDelimiter(string $path): string
    {
        $fh = fopen($path, 'rb');
        if (!$fh) return ',';
        // Bỏ BOM UTF-8 nếu có
        $start = fread($fh, 3);
        if ($start !== "\xEF\xBB\xBF") fseek($fh, 0);
        $line = fgets($fh) ?: '';
        fclose($fh);

        $candidates = [",", ";", "\t"];
        $best = ",";
        $bestCount = -1;
        foreach ($candidates as $d) {
            $cnt = substr_count($line, $d);
            if ($cnt > $bestCount) {
                $best = $d;
                $bestCount = $cnt;
            }
        }
        return $best;
    }

    private function toUtf8Safe(?string $s): ?string
    {
        if ($s === null) return null;

        // Loại null-byte (hay gặp khi nguồn UTF-16)
        if (strpos($s, "\0") !== false) {
            $s = str_replace("\0", '', $s);
        }

        // Danh sách encoding ứng viên (đủ dùng cho CSV/Excel VN)
        $candidates = ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'Windows-1258'];

        // Lọc theo môi trường: chỉ giữ encoding mbstring hỗ trợ
        $supported = array_map('strtolower', mb_list_encodings());
        $encList = array_values(array_filter($candidates, function ($e) use ($supported) {
            // so sánh không phân biệt hoa thường
            return in_array(strtolower($e), $supported, true);
        }));

        // Thử detect
        $enc = @mb_detect_encoding($s, $encList, true);
        if ($enc === false) {
            // Nếu detect thất bại, cứ thử convert từ từng encoding
            foreach ($encList as $try) {
                $out = @mb_convert_encoding($s, 'UTF-8', $try);
                if ($out !== false) return $out;
                $out = @iconv($try, 'UTF-8//IGNORE', $s);
                if ($out !== false) return $out;
            }
            // Bó tay thì trả nguyên (đỡ crash)
            return $s;
        }

        if ($enc === 'UTF-8') return $s;

        $out = @mb_convert_encoding($s, 'UTF-8', $enc);
        if ($out !== false) return $out;

        $out = @iconv($enc, 'UTF-8//IGNORE', $s);
        return $out !== false ? $out : $s;
    }



    public function handleBatch($type, $dataBatch)
    {
        // dữ liệu data Batch đã được chunk, chỉ nhận vào tối đa 1k dòng, và dữ liệu đã định dạng theo maping, 'tên cột' => giá trị

        $this->insertCustomer($dataBatch);
        // $this->insertProduct($dataBatch);

        if ($type === 'tender') {

            $this->insertTender($dataBatch);
        }

        if ($type === 'sales') {

            $this->insertSales($dataBatch);
        }
    }

    public function insertCustomer($dataBatch)
    {
        $now = now('Asia/Ho_Chi_Minh');

        $customers = [];

        foreach ($dataBatch as $item) {
            if (!isset($item['customer_code']) || $item['customer_code'] === '') {
                $this->errs[] = "Customer code không được để trống trong dữ liệu: " . json_encode($item, JSON_UNESCAPED_UNICODE);
                continue;
            }
            $customers[] = [
                'customer_code' => $item['customer_code'] ?? '',
                'customer_name' => $item['customer_name'] ?? '',
                'area' => $item['area'] ?? '',
                'created_at' => $now,
                'customer_account_id' => null,
            ];
        }

        if (!empty($customers)) {
            try {
                DB::beginTransaction();
                foreach (array_chunk($customers, 1000) as $chunk) {
                    DB::table('customers')->insertOrIgnore($chunk);
                }
                $accountService = new \App\Services\ImportAccounts();
                $accountService->handle();
                DB::commit();
            } catch (\Exception $e) {
                $this->errs[] = "Lỗi khi insert customers: " . $e->getMessage();
                DB::rollBack();
            }
        }

        return;
    }


    public function insertProduct($dataBatch)
    {
        $now = now('Asia/Ho_Chi_Minh');
        $products = [];
        foreach ($dataBatch as $item) {
            if (!isset($item['sap_item_code']) || $item['sap_item_code'] === '') {
                $this->errs[] = "sap_item_code không được để trống trong dữ liệu: " . json_encode($item, JSON_UNESCAPED_UNICODE);
                continue;
            }
            $products[] = [
                'sap_item_code' => $item['sap_item_code'] ?? '',
                'item_short_description' => $item['item_short_description'] ?? '',
                'created_at' => $now,
            ];
        }

        if (!empty($products)) {
            try {
                DB::beginTransaction();
                foreach (array_chunk($products, 1000) as $chunk) {
                    DB::table('products')->insertOrIgnore($chunk);
                }
                DB::commit();
            } catch (\Exception $e) {
                $this->errs[] = "Lỗi khi insert products: " . $e->getMessage();
                DB::rollBack();
            }
        }

        return;
    }

    public function insertSales($dataBatch)
    {
        $now = now('Asia/Ho_Chi_Minh');

        // lấy id của customer_code
        $customerCodes = [];
        foreach ($dataBatch as $item) {
            if (!isset($item['customer_code']) || $item['customer_code'] === '') continue;
            $customerCodes[$item['customer_code']] = $item['customer_code'];
        }

        $customerIds = DB::table('customers')
            ->whereIn('customer_code', array_keys($customerCodes))
            ->pluck('id', 'customer_code')
            ->toArray();

        $sales = [];
        foreach ($dataBatch as $item) {
            if (!isset($item['order_number']) || $item['order_number'] === '') {
                $this->errs[] = "order_number không được để trống trong dữ liệu: " . json_encode($item, JSON_UNESCAPED_UNICODE);
                continue;
            }

            if (!isset($item['customer_code']) || $item['customer_code'] === '') {
                $this->errs[] = "customer_code không được để trống trong dữ liệu: " . json_encode($item, JSON_UNESCAPED_UNICODE);
                continue;
            }
            $sales[] = [
                'order_number' => $item['order_number'] ?? '',
                'invoice_number' => $item['invoice_number'] ?? '',
                'contract_number' => $item['contract_number'] ?? '',
                'expiry_date' => $this->parseDateDMYToYMD($item['expiry_date']),
                'selling_price' => $item['selling_price'] ?? 0,
                'commercial_quantity' => $item['commercial_quantity'] ?? 0,
                'invoice_confirmed_date' => $this->parseDateDMYToYMD($item['invoice_confirmed_date']),
                'net_sales_value' => $item['net_sales_value'] ?? 0,
                'accounts_receivable_date' => $this->parseDateDMYToYMD($item['accounts_receivable_date']),
                'customer_id' =>  $customerIds[$item['customer_code']] ?? '',
                'created_at' => $now,
            ];
        }

        if (!empty($sales)) {
            try {
                DB::beginTransaction();
                foreach (array_chunk($sales, 1000) as $chunk) {
                    DB::table('sales')->insertOrIgnore($chunk);
                }
                DB::commit();
            } catch (\Exception $e) {
                $this->errs[] = "Lỗi khi insert sales: " . $e->getMessage();
                DB::rollBack();
            }
        }

        // sau khi insert salse thì cần insert vào bảng variants_sales
        // B1: Lấy danh sách order_number và sap_item_code từ batch
        $orderNumbers = [];
        $sapItemCodes = [];
        foreach ($dataBatch as $item) {
            if (!empty($item['order_number'])) {
                $orderNumbers[] = $item['order_number'];
            }
            if (!empty($item['sap_item_code'])) {
                $sapItemCodes[] = $item['sap_item_code'];
            }
        }
        $orderNumbers = array_values(array_unique($orderNumbers));
        $sapItemCodes = array_values(array_unique($sapItemCodes));
        // B2: Lấy ID từ DB
        $saleIds = DB::table('sales')->whereIn('order_number', $orderNumbers)->pluck('id', 'order_number')->toArray();
        $productIds = DB::table('variants')->whereIn('sap_item_code', $sapItemCodes)->pluck('id', 'sap_item_code')->toArray();

        // B3: Ghép bảng trung gian
        $productSales = [];
        foreach ($dataBatch as $item) {
            $orderNumber = $item['order_number'] ?? null;
            $sapItemCode = $item['sap_item_code'] ?? null;

            if (!$orderNumber || !$sapItemCode) {
                $this->errs[] = "Thiếu order_number hoặc sap_item_code trong dòng: " . json_encode($item, JSON_UNESCAPED_UNICODE);
                continue;
            }

            $saleId = $saleIds[$orderNumber] ?? null;
            $productId = $productIds[$sapItemCode] ?? null;

            if ($saleId && $productId) {
                $productSales[] = [
                    'sale_id' => $saleId,
                    'variant_id' => $productId,
                ];
            }
        }

        // B4: Insert bảng trung gian
        if (!empty($productSales)) {
            try {
                DB::beginTransaction();
                foreach (array_chunk($productSales, 1000) as $chunk) {
                    DB::table('variants_sales')->insertOrIgnore($chunk);
                }
                DB::commit();
            } catch (\Exception $e) {
                $this->errs[] = "Lỗi khi insert vào products_sales: " . $e->getMessage();
                DB::rollBack();
            }
        }
        return;
    }

    public function insertTender($dataBatch)
    {
        // Log::info($dataBatch);
        // return; // tạm thời không làm gì, chỉ để test
        $now = now('Asia/Ho_Chi_Minh');

        // lấy id của customer_code
        $customerCodes = [];
        foreach ($dataBatch as $item) {
            if (!isset($item['customer_code']) || $item['customer_code'] === '') continue;
            $customerCodes[$item['customer_code']] = $item['customer_code'];
        }

        $customerIds = DB::table('customers')
            ->whereIn('customer_code', array_keys($customerCodes))
            ->pluck('id', 'customer_code')
            ->toArray();

        // lưu ý quan trọng: không có mã tender, chỉ cần insert vào bảng tender
        // nên không cần kiểm tra trùng lặp, chỉ cần insert tất cả các bản ghi
        // kiểm tra $this->isTruncatedTender để biết có cần xóa dữ liệu cũ hay không
        $tenders = [];
        foreach ($dataBatch as $item) {
            if (!isset($item['customer_code']) || $item['customer_code'] === '') {
                $this->errs[] = "customer_code không được để trống trong dữ liệu: " . json_encode($item, JSON_UNESCAPED_UNICODE);
                continue;
            }
            // $hash = md5(json_encode([
            //     $item['customer_code'],
            //     $item['cust_quota_start_date'],
            //     $item['cust_quota_end_date'],
            //     $item['customer_quota_description']
            // ]));
            $tenders[] = [
                'customer_quota_description' => $item['customer_quota_description'] ?? '',
                'cust_quota_start_date' =>  $this->parseDateDMYToYMD($item['cust_quota_start_date']),
                'cust_quota_end_date' => $this->parseDateDMYToYMD($item['cust_quota_end_date']),
                'cust_quota_quantity' => $item['cust_quota_quantity'] ?? 0,
                'invoice_quantity' => $item['invoice_quantity'] ?? 0,
                'return_quantity' => $item['return_quantity'] ?? 0,
                'allocated_quantity' => $item['allocated_quantity'] ?? 0,
                'used_quota' => $item['used_quota'] ?? 0,
                'remaining_quota' => $item['remaining_quota'] ?? 0,
                'report_run_date' => $this->parseDateDMYToYMD($item['report_run_date']),
                'tender_price' => $item['tender_price'] ?? 0,
                'sap_item_code' => $item['sap_item_code'] ?? '',
                'item_short_description' => $item['item_short_description'] ?? '',
                'customer_id' => $customerIds[$item['customer_code']] ?? '',
                'created_at' => $now,
            ];
        }

        if (!$this->isTruncatedTender) {
            // nếu có truncate thì xóa hết dữ liệu cũ
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::table('variants_tender')->truncate();
            DB::table('tender')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->isTruncatedTender = true; // đánh dấu đã xóa dữ liệu cũ
        }

        // insert hàng loạt các bản ghi

        if (!empty($tenders)) {
            try {
                DB::beginTransaction();
                foreach (array_chunk($tenders, 1000) as $chunk) {
                    DB::table('tender')->insertOrIgnore($chunk);
                }
                DB::commit();
            } catch (\Exception $e) {
                $this->errs[] = "Lỗi khi insert tender: " . $e->getMessage();
                DB::rollBack();
            }
        }

        // bỏ bảng trung gian.
        return;

        // sau khi insert tender thì cần insert vào bảng variants_tender
        // B1: Lấy danh sách hash_key và sap_item_code từ batch
        $hash_key_tender = [];
        $sapItemCodes = [];
        foreach ($dataBatch as $item) {
            $hash = md5(json_encode([
                $item['customer_code'],
                $item['cust_quota_start_date'],
                $item['cust_quota_end_date'],
                $item['customer_quota_description']
            ]));
            $hash_key_tender[] = $hash;
            if (!empty($item['sap_item_code'])) {
                $sapItemCodes[] = $item['sap_item_code'];
            }
        }
        $hash_key_tender = array_values(array_unique($hash_key_tender));
        $sapItemCodes = array_values(array_unique($sapItemCodes));

        // B2: Lấy ID từ DB
        $tenderIds = DB::table('tender')->whereIn('hash_key', $hash_key_tender)->pluck('id', 'hash_key')->toArray();
        $productIds = DB::table('variants')->whereIn('sap_item_code', $sapItemCodes)->pluck('id', 'sap_item_code')->toArray();

        // B3: Ghép bảng trung gian
        $productTenders = [];
        foreach ($dataBatch as $item) {
            $hash_key = md5(json_encode([
                $item['customer_code'],
                $item['cust_quota_start_date'],
                $item['cust_quota_end_date'],
                $item['customer_quota_description']
            ]));
            $sapItemCode = $item['sap_item_code'] ?? null;

            if (!$hash_key || !$sapItemCode) {
                $this->errs[] = "Thiếu hash_key_tender hoặc sap_item_code trong dòng: " . json_encode($item, JSON_UNESCAPED_UNICODE);
                continue;
            }

            $tender_id = $tenderIds[$hash_key] ?? null;
            $productId = $productIds[$sapItemCode] ?? null;

            if ($tender_id && $productId) {
                $productTenders[] = [
                    'tender_id' => $tender_id,
                    'variant_id' => $productId,
                ];
            }
        }
        // B4: Insert bảng trung gian
        if (!empty($productTenders)) {
            try {
                DB::beginTransaction();
                foreach (array_chunk($productTenders, 1000) as $chunk) {
                    DB::table('variants_tender')->insertOrIgnore($chunk);
                }
                DB::commit();
            } catch (\Exception $e) {
                $this->errs[] = "Lỗi khi insert vào products_tender: " . $e->getMessage();
                DB::rollBack();
            }
        }
        return;
    }

    // app/services/handleExcel/ImportExcel.php
    public function receiveMail($days = 10)
    {
        $WordCheckMail = [
            'sales' => 'DailyNetSalesData_NW',
            'tender' => 'TenderQuotaStatus',
        ];
        // $hostname = '{imap.gmail.com:993/imap/ssl}INBOX';
        // $username = env("MAIL_USERNAME");
        // $password = env('MAIL_PASSWORD');
        $hostname = '{mail.odinn.site:143/imap/notls}INBOX';
        $username = "mail@odinn.site";
        $password = "o^yer9]KsD61V@oB";

        $pathZip = 'app/tmp_zips';

        $hasFolderZip = storage_path($pathZip);
        if (is_dir($hasFolderZip)) {
            $this->deleteFolder($hasFolderZip);
        }

        try {
            $inbox = imap_open($hostname, $username, $password);
            if (!$inbox) {
                // tất cả phương thức để trả về định dạng như thế này để dễ dàng xử lý
                return [
                    'status' => '500', // trạng thái thành công
                    'is_next' => false,  // có tiếp tục chạy tiếp hay không
                    'is_err' => true, // có phải lỗi hay không
                    'message' => "Không thể kết nối đến hộp thư đến. Vui lòng kiểm tra thông tin đăng nhập.",
                    'data' => null
                ];
            }

            // Tính ngày bắt đầu
            $sinceDate = now()->subDays($days)->format('d-M-Y'); // IMAP yêu cầu định dạng: 06-Aug-2025
            $searchCriteria = 'UNSEEN SINCE "' . $sinceDate . '"';

            // Lấy email theo ngày
            $emails = imap_search($inbox, $searchCriteria);


            if (!$emails) {
                imap_close($inbox);
                return [
                    'status' => '200', // trạng thái thành công
                    'is_next' => false,  // có tiếp tục chạy tiếp hay không
                    'is_err' => false, // có phải lỗi hay không
                    'message' => "Không có email trong $days ngày gần đây.",
                    'data' => null
                ];
            }

            $emailsResult = []; // mảng lưu các mail phù hợp với điều kiện, trong đó mỗi phần tử là một mảng với các thông tin như tiêu đề, người gửi, ngày gửi, và một mảng file .zip)
            $messageEmptyEmail = '';
            foreach ($emails as $email_number) {
                $overview = imap_fetch_overview($inbox, $email_number, 0)[0];
                $subject = $overview->subject ?? '';
                $subjectLower = strtolower($subject);
                // $body = imap_fetchbody($inbox, $email_number, 1, FT_PEEK);
                // cách chuyển đổi thành utf-8 an toàn
                $subjectLower = $this->decodeMimeStr($subjectLower);
                // kiểm tra tiêu đề email có chứa từ khóa cần tìm
                $type = null;
                foreach ($WordCheckMail as $key => $keyword) {
                    if (str_contains($subjectLower, strtolower($keyword))) {
                        $type = $key;
                        break;
                    }
                }

                if (!$type) continue; // Nếu tiêu đề không chứa từ khóa nào, bỏ qua

                // kiểm tra xem email có chứa file đính kèm là ZIP không
                $structure = imap_fetchstructure($inbox, $email_number);
                if (!isset($structure->parts)) {
                    $messageEmptyEmail .= " - [Mail: $subject => Không tìm thấy file Zip] ";
                    continue;
                }
                $zipFiles = [];
                foreach ($structure->parts as $i => $part) {
                    if (
                        isset($part->disposition) &&
                        strtolower($part->disposition) === 'attachment'
                    ) {
                        $filename = null;

                        if (isset($part->dparameters)) {
                            foreach ($part->dparameters as $dparam) {
                                if (in_array(strtoupper($dparam->attribute), ['FILENAME', 'NAME'])) {
                                    $filename = $dparam->value;
                                    break;
                                }
                            }
                        }

                        if (!$filename && isset($part->parameters)) {
                            foreach ($part->parameters as $param) {
                                if (strtoupper($param->attribute) === 'NAME') {
                                    $filename = $param->value;
                                    break;
                                }
                            }
                        }

                        if ($filename && str_ends_with(strtolower($filename), '.zip')) {
                            $content = imap_fetchbody($inbox, $email_number, $i + 1, FT_PEEK);
                            $decoded = match ($part->encoding) {
                                3 => base64_decode($content),
                                4 => quoted_printable_decode($content),
                                default => $content,
                            };

                            $dir = storage_path($pathZip);
                            if (!is_dir($dir)) {
                                mkdir($dir, 0777, true); // tạo thư mục nếu chưa tồn tại
                            }
                            $storedPath = storage_path($pathZip . '/' . $filename); // lấy đường dẫn lưu file zip
                            if (file_exists($storedPath)) {
                                // unlink($storedPath); // xóa file cũ nếu đã tồn tại
                            }
                            file_put_contents($storedPath, $decoded); // lưu nội dung file zip vào đường dẫn đã chỉ định

                            $zipFiles[] = [
                                'filename' => $filename,
                                'path' => $storedPath,   // chỉ lưu đường dẫn
                            ];
                        }
                    }
                }
                if (!empty($zipFiles)) {
                    if (count($zipFiles) > 1) {
                        $messageEmptyEmail .= " - [Email: $subject => Chỉ nhận mail có 1 file Zip]";
                        continue;
                    }
                    $emailsResult[] = [
                        'email_number' => $email_number,
                        'subject' => $subject,
                        'from' => $overview->from ?? '',
                        'date' => $overview->date ?? '',
                        'type' => $type,
                        'zips' => $zipFiles[0]
                    ];
                }
            }

            // đánh dấu đã đọc các email có trong emailsResult
            foreach ($emailsResult as $email) {
                imap_setflag_full($inbox, $email['email_number'], '\\Seen');
            }

            // Nếu không có email nào phù hợp, trả về thông báo
            imap_close($inbox);
            if (empty($emailsResult)) {
                $er = 'Không có email nào phù hợp. ' . $messageEmptyEmail;
                $this->errs[] = $er;
                return $this->returnResult(404, false, $messageEmptyEmail === '' ? false : true, $er);
            }

            return $this->returnResult(200, true, false, 'Thành công', $emailsResult);
        } catch (Exception $e) {
            $this->errs[] = 'Lỗi khi nhận email: ' . $e->getMessage();
            return $this->returnResult(500, false, true, $e->getMessage());
        }
    }



    public function handleFileZip(array $zipFiles): array
    {

        $filenameWithoutExt = pathinfo($zipFiles['filename'], PATHINFO_FILENAME);
        $pathFolderExcel = storage_path('app/tmp_excels/' . $filenameWithoutExt);
        // đã tồn tại thì xóa
        if (is_dir($pathFolderExcel)) {
            $this->deleteFolder($pathFolderExcel);
        }

        // tạo mới thư mục 

        if (!mkdir($pathFolderExcel, 0777, true) && !is_dir($pathFolderExcel)) {
            throw new Exception("Không thể tạo thư mục: " . $pathFolderExcel);
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($zipFiles['path']) === TRUE) {
                $zip->extractTo($pathFolderExcel);
                $zip->close();
                // 3. Duyệt thư mục vừa giải nén để tìm file Excel
                $files = scandir($pathFolderExcel);
                if ($files === false) {
                    throw new Exception("Không thể đọc nội dung thư mục: $pathFolderExcel");
                }
                $excelFiles = [];
                foreach ($files as $f) {
                    $extension = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                    if (in_array($extension, ['xls', 'xlsx'])) {
                        $excelFiles[] = $pathFolderExcel . '/' . $f;
                    }
                }
                if (empty($excelFiles)) {
                    throw new Exception("Không tìm thấy file Excel trong file Zip [" . $zipFiles['filename'] . "]");
                }
                if (count($excelFiles) > 1) {
                    throw new Exception("Chỉ hỗ trợ 1 file Excel trong file Zip [" . $zipFiles['filename'] . "]");
                }

                return $this->returnResult(200, true, false, 'Thành công', $excelFiles[0]);
            } else {
                throw new Exception('Không giải nén file Zip');
            }
        } catch (Exception $err) {
            $this->errs[] = 'Lỗi khi giải nén file Zip: ' . $err->getMessage();
            return $this->returnResult(500, false, true, $err->getMessage());
        } finally {
            if (file_exists($zipFiles['path'])) {
                // unlink($zipFiles['path']);
            }
        }
    }

    public function detectExcelFormat(string $path): string
    {
        if (!is_file($path) || !is_readable($path) || filesize($path) === 0) {
            return 'unreadable';
        }

        // Thử nhận dạng bằng PhpSpreadsheet trước
        try {
            $type = \PhpOffice\PhpSpreadsheet\IOFactory::identify($path);
            // Mapping về nhóm mình dùng
            return match ($type) {
                'Xlsx' => 'xlsx_zip',
                'Xls'  => 'xls_ole',
                'Ods'  => 'ods',
                'Csv'  => 'csv',
                'Html' => 'html',
                'Slk'  => 'sylk',
                'Gnumeric' => 'gnumeric',
                'Xml'  => 'xml',
                default => 'unknown',
            };
        } catch (\Throwable $e) {
            // Fallback: magic bytes 8 byte đầu
            $fh = @fopen($path, 'rb');
            if (!$fh) return 'unreadable';
            $sig8 = fread($fh, 8) ?: '';
            fclose($fh);

            if (strncmp($sig8, "PK\x03\x04", 4) === 0) return 'xlsx_zip';
            if ($sig8 === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") return 'xls_ole';

            $low = strtolower($sig8);
            if (str_contains($low, '<html') || str_contains($low, '<!doctyp')) return 'html';
            if (str_contains($low, '<?xml') || str_contains($low, '<workboo')) return 'xml';
            if (strncmp($sig8, "ID;P", 4) === 0) return 'sylk';

            return 'unknown';
        }
    }

    function deleteFolder($folderPath)
    {
        if (!is_dir($folderPath)) return false;

        foreach (scandir($folderPath) as $item) {
            if ($item === '.' || $item === '..') continue;

            $path = $folderPath . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteFolder($path); // Xóa thư mục con
            } else {
                unlink($path); // Xóa file
            }
        }

        return rmdir($folderPath); // Xóa thư mục rỗng sau khi dọn hết
    }

    public function returnResult($status, $is_next, $is_errr, $message, $data = null)
    {
        return [
            'status' => $status, // trạng thái thành công
            'is_next' => $is_next,  // có tiếp tục chạy tiếp hay không
            'is_err' => $is_errr, // có phải lỗi hay không
            'message' => $message,
            'data' => $data
        ];
    }

    public function cleanFolder()
    {
        $folders = [
            storage_path('app/tmp_zips'),
            storage_path('app/tmp_excels'),
        ];

        foreach ($folders as $folder) {
            $this->deleteFolder($folder);
        }
    }

    public function readSmallExcelXlsx(
        string $path,
        array $mapping,
        int $startRow = 1,
        int $sheetIndex = 0
    ): array {
        try {
            if (!file_exists($path)) {
                throw new \Exception("File not found: $path");
            }

            // Chuyển mapping từ cột chữ sang index số (1-based để dùng getCell)
            $mapIdxToField = [];
            foreach ($mapping as $colLetter => $field) {
                $colIndex = Coordinate::columnIndexFromString($colLetter); // 1-based
                $mapIdxToField[$colIndex] = $field;
            }

            // Load toàn bộ file
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getSheet($sheetIndex);

            $highestRow = $sheet->getHighestRow();
            $data = [];

            for ($row = $startRow; $row <= $highestRow; $row++) {
                $rowData = [];

                foreach ($mapIdxToField as $colIndex => $fieldName) {
                    $cellValue = $sheet->getCell(Coordinate::stringFromColumnIndex($colIndex) . $row)->getValue();

                    // Format ngày nếu là DateTime
                    if ($cellValue instanceof \DateTimeInterface) {
                        $cellValue = $cellValue->format('Y-m-d');
                    }

                    if (is_string($cellValue)) {
                        $cellValue = trim($cellValue);
                    }

                    $rowData[$fieldName] = $cellValue;
                }

                // Bỏ qua dòng rỗng
                if (!array_filter($rowData, fn($v) => $v !== null && $v !== '')) {
                    continue;
                }

                $data[] = $rowData;
            }

            return $data;
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    protected function parseDateDMYToYMD($value)
    {
        if (!$value || !is_string($value)) return null;

        $formats = ['d/m/Y', 'Ymd', 'Y-m-d'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Exception $e) {
                continue;
            }
        }

        $this->errs[] = "Lỗi định dạng ngày: $value";
        return null;
    }

    // function decodeMimeStr($string, $charset = 'UTF-8')
    // {
    //     $elements = imap_mime_header_decode($string);
    //     $result = '';
    //     foreach ($elements as $element) {
    //         $fromCharset = strtoupper($element->charset);
    //         if ($fromCharset != 'DEFAULT') {
    //             $result .= iconv($fromCharset, $charset, $element->text);
    //         } else {
    //             $result .= $element->text;
    //         }
    //     }
    //     return $result;
    // }

    function decodeMimeStr($string, $charset = 'UTF-8')
    {
        $elements = imap_mime_header_decode($string);
        $result = '';
        foreach ($elements as $element) {
            $fromCharset = strtoupper($element->charset);
            if ($fromCharset != 'DEFAULT') {
                // Dùng //IGNORE để tránh lỗi và giữ lại text hợp lệ
                $text = @iconv($fromCharset, $charset . '//IGNORE', $element->text);
                if ($text === false) {
                    $result .= $element->text; // Nếu vẫn lỗi thì giữ nguyên
                } else {
                    $result .= $text;
                }
            } else {
                $result .= $element->text;
            }
        }
        return $result;
    }
}
