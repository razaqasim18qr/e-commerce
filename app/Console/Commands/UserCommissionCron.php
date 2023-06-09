<?php

namespace App\Console\Commands;

use App\Helpers\CustomHelper;
use Illuminate\Console\Command;

class UserCommissionCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usercommission:cron';

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
        info("Users Commission Job running at " . now());
        CustomHelper::directChildCommission();

        // $userCommsionJob = new userCommsionJob();
        // Queue::push($userCommsionJob);
    }
}
