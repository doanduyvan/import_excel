<?php

namespace App\Services\handleExcel;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Str;
use ZipArchive;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

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


    public function handleAll()
    {
        // ini_set('memory_limit', '1024M'); // tăng giới hạn bộ nhớ
        ini_set('max_execution_time', 60); // 60s

        // Lấy email trong 5 ngày gần đây, trả về các email có file Zip đúng cấu trúc
        $emails = $this->receiveMail(5);
        if (!$emails['is_next']) {
            echo ($emails['message']);
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
        // sử lí đọc file excel
        print_r($emails);
    }

    public function import($mapping, $path, $startRow = 1, $sheetIndex = 0): array|bool
    {

        try {
            if (!file_exists($path)) {
                throw new Exception("File not found: " . $path);
            }

            $reader = new Xlsx();
            $reader->setReadDataOnly(true); // chỉ đọc dữ liệu, bỏ định dạng
            $spreadsheet = $reader->load($path);
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

    // app/services/handleExcel/ImportExcel.php
    public function receiveMail($days = 10)
    {
        $WordCheckMail = [
            'sales' => 'DailyNetSalesData_NW',
            'tender' => 'TenderQuotaStatus',
        ];
        $hostname = '{imap.gmail.com:993/imap/ssl}INBOX';
        $username = env("MAIL_USERNAME");
        $password = env('MAIL_PASSWORD');
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
            $searchCriteria = 'SINCE "' . $sinceDate . '"';

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
                    $messageEmptyEmail += " - [Mail: $subject => Không tìm thấy file Zip] ";
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
                                unlink($storedPath); // xóa file cũ nếu đã tồn tại
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
                        $messageEmptyEmail += " - [Email: $subject => Chỉ nhận mail có 1 file Zip]";
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

            // Nếu không có email nào phù hợp, trả về thông báo
            imap_close($inbox);
            if (empty($emailsResult)) {
                return $this->returnResult(404, false, $messageEmptyEmail === '' ? false : true, 'Không có email nào phù hợp. ' . $messageEmptyEmail);
            }

            return $this->returnResult(200, true, false, 'Thành công', $emailsResult);
        } catch (Exception $e) {
            return $this->returnResult(500, false, true, $e->getMessage());
        }
    }



    public function handleFileZip(array $zipFiles): array
    {

        // cấu trúc dữ liệu đầu vào
        //        "zips" => array:2 [
        //     "filename" => "5001580 R000772VN TenderQuotaStatus.zip"
        //     "path" => "C:\xampp\htdocs\temp2\temp2\storage\app/tmp_zips/5001580 R000772VN TenderQuotaStatus.zip"
        //   ]

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
            return $this->returnResult(500, false, true, $err->getMessage());
        } finally {
            if (file_exists($zipFiles['path'])) {
                unlink($zipFiles['path']);
            }
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
}
