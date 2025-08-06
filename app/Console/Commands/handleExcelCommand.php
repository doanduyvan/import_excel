<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class handleExcelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:handle-excel-command';

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
        $this->info('Bắt đầu import dữ liệu...');

        $this->info('Import hoàn tất!');
    }
}
