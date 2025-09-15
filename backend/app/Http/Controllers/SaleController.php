<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Customer_account;
use App\Models\Customers;
use App\Models\Sales;
use App\Models\Variants;
use Illuminate\Support\Facades\DB;


class SaleController extends Controller
{

    public function DetailbyinvoiceView()
    {
        //
        return view('detailbyinvoice');
    }

    public function Detailbyinvoice(Request $request)
    {
        //

        \DB::listen(function ($query) {
            \Log::info($query->sql, $query->bindings);
        });

        // $all = User::query();
        // $all = $all->with(['customer_account' => function ($q) {
        //     $q->with(['customers' => function ($sq) {
        //         $sq->with(['sales' => function ($ssq) {
        //             $ssq->with(['variants' => function ($vsq) {
        //                 $vsq->select('variants.id', 'item_short_description', 'sap_item_code');
        //             }])->select('id', 'order_number', 'commercial_quantity', 'invoice_confirmed_date', 'expiry_date', 'customer_id');
        //         }])->select('id', 'customer_code', 'customer_name', 'area', 'customer_account_id');
        //     }])->select('id', 'brick_codewo', 'user_id');
        // }])->select('id', 'fullname');

        // $all = $all->get();

        $sql = "SELECT
        u.fullname,
        cu.customer_code,
        cu.customer_name,
        sa.order_number,
        sa.commercial_quantity,
        sa.invoice_confirmed_date,
        sa.expiry_date,
        va.item_short_description
        FROM `users` as u 
        INNER JOIN customer_account as ca on ca.user_id = u.id
        INNER JOIN customers as cu on cu.customer_account_id = ca.id
        INNER JOIN sales as sa on sa.customer_id = cu.id
        INNER JOIN variants_sales as vs on vs.sale_id = sa.id 
        INNER JOIN variants as va on va.id = vs.variant_id
        ORDER BY sa.invoice_confirmed_date DESC";
        
        $all = $results = DB::select($sql);
        return response()->json($all, 200);
    }


    /**
     * Display a listing of the resource. 
     */
    public function index()
    {
        //
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
