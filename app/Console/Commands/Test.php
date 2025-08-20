<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Services\ImportAccounts;
use App\Services\ImportProduct;


class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        $test = new ImportAccounts();
        // $test = new ImportProduct();

        $test->handle();
    }
}
