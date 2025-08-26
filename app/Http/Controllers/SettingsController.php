<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\handleExcel\ImportExcel;


class SettingsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return view('settings');
    }

    public function checkmail()
    {
        $importExcel = new ImportExcel();
        $importExcel->handleAll();
        $result = $importExcel->errs;
        return response()->json($result, 200);
    }

    public function importAccount(Request $request)
    {
        $file = $request->file('file');
        if (!$file) {
            return response()->json(['message' => 'No file uploaded'], 400);
        }
        // nếu có file thì lưu vào storage/app/seeder/accounts.xlsx
        // kiểm tra rằng nó tồn tại chưa, nếu có rồi thì xóa đi rồi lưu file mới vào
        $dir = storage_path('app/seeder');
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir . '/accounts.xlsx';
        if (file_exists($path)) {
            unlink($path);
        }
        $file->move($dir, 'accounts.xlsx');

        $service = new \App\Services\ImportAccounts();
        $service->import();

        return response()->json(['message' => 'import thành công'], 200);
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
