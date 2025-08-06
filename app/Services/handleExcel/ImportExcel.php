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

    // app/services/handleExcel/ImportExcel.php
    public function receiveMail($days = 10)
    {
        $hostname = '{imap.gmail.com:993/imap/ssl}INBOX';
        $username = env("MAIL_USERNAME");
        $password = env('MAIL_PASSWORD');

        try {
            $inbox = imap_open($hostname, $username, $password);
            if (!$inbox) {
                throw new Exception('Không thể kết nối đến Gmail: ' . imap_last_error());
            }

            // Tính ngày bắt đầu
            $sinceDate = now()->subDays($days)->format('d-M-Y'); // IMAP yêu cầu định dạng: 06-Aug-2025
            $searchCriteria = 'SINCE "' . $sinceDate . '"';

            // Lấy email theo ngày
            $emails = imap_search($inbox, $searchCriteria);

            if (!$emails) {
                echo "📭 Không có email trong $days ngày gần đây.\n";
                imap_close($inbox);
                return;
            }

            foreach ($emails as $email_number) {
                $structure = imap_fetchstructure($inbox, $email_number);
                $hasZip = false;

                if (isset($structure->parts)) {
                    for ($i = 0; $i < count($structure->parts); $i++) {
                        $part = $structure->parts[$i];

                        // Kiểm tra nếu là file đính kèm có tên và là file zip
                        if (
                            isset($part->disposition) &&
                            strtolower($part->disposition) === 'attachment' &&
                            isset($part->dparameters[0]->value)
                        ) {
                            $filename = $part->dparameters[0]->value;
                            if (str_ends_with(strtolower($filename), '.zip')) {
                                $hasZip = true;
                                break;
                            }
                        }
                    }
                }
                // Nếu không có file zip, bỏ qua email này
                if (!$hasZip) {
                    continue;
                }

                $overview = imap_fetch_overview($inbox, $email_number, 0);
                $body = imap_fetchbody($inbox, $email_number, 1, FT_PEEK);

                echo "---------------------------\n";
                echo "📧 Tiêu đề: " . ($overview[0]->subject ?? '[Không tiêu đề]') . "\n";
                echo "👤 Từ: " . ($overview[0]->from ?? '[Không xác định]') . "\n";
                echo "🕒 Ngày: " . ($overview[0]->date ?? '[Không có ngày]') . "\n";
                echo "📝 Nội dung:\n" . substr($body, 0, 500) . "\n";
                echo "---------------------------\n\n";
            }

            imap_close($inbox);
        } catch (Exception $e) {
            echo "❌ Lỗi: " . $e->getMessage();
        }
    }
}
