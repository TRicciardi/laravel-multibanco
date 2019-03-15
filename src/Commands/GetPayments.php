<?php

namespace tricciardi\LaravelMultibanco\Commands;

use Illuminate\Console\Command;

use tricciardi\LaravelMultibanco\Multibanco;


class GetPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mb:getpayments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get easypay payments';



    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();


    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
      $mb = new Multibanco;
      $mb->processNotifications();
    }
}
