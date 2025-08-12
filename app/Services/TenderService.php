<?php

namespace App\Services;

class TenderService
{
    public function getTenderData()
    {
        // Giả lập dữ liệu tender
        return [
            ['id' => 1, 'name' => 'Tender A', 'status' => 'open'],
            ['id' => 2, 'name' => 'Tender B', 'status' => 'closed'],
            ['id' => 3, 'name' => 'Tender C', 'status' => 'open'],
        ];
    }

    public function filterTendersByStatus($tenders, $status)
    {
        return array_filter($tenders, fn($tender) => $tender['status'] === $status);
    }
}
