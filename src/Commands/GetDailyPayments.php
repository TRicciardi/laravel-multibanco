<?php

namespace tricciardi\LaravelMultibanco\Commands;

use Illuminate\Console\Command;
use tricciardi\LaravelMultibanco\Multibanco;

class GetDailyPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mb:getdaily {until=1} {from=30}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get easypay daily payments';



    /**
     * Create a new command instance.
     *
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();


    }

    /**
     * Execute the console command.
     * Command to get last 3 months of payments to make sure no payment was missed
     *
     * @return mixed
     */
    public function handle()
    {
      $until = (int) $this->argument('until');
      $from = (int) $this->argument('from');

      $mindate = date("Y-m-d",strtotime("now - ".$from." days"));
      $maxdate = date("Y-m-d",strtotime("now - ".$until." days"));
      $this->info( "fecthing from ".$mindate.' ('.$from.') to '.$maxdate." (".$until.")");
      $mb = new Multibanco;
      $mb->getPayments($mindate, $maxdate);
    }
}
