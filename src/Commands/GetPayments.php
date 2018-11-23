<?php

namespace tricciardi\LaravelMultibanco\Commands;

use Illuminate\Console\Command;

use tricciardi\LaravelMultibanco\Events\PaymentSuccess;
use tricciardi\LaravelMultibanco\EasypayNotification;
use \tricciardi\LaravelMultibanco\Events\PaymentReceived;
use tricciardi\LaravelMultibanco\Reference;
use GuzzleHttp\Client;
use Parser;


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

      if( config('multibanco.type') == 'ifthen' ) {
        //ifthen does not require to verify notifications. exit here
        return;
      }
      $client = new Client([
          'base_uri' =>  config('multibanco.easypay.ep_url'),
      ]);
      //get unprocessed notifications
      $notifications = EasypayNotification::where('ep_status','ok0')->get();

      //process notifications
      foreach($notifications as $not) {

        //get confirmation and details from easypay
        $response = $client->request('GET','_s/api_easypay_03AG.php',['query'=>[
                                                                              'ep_user'=>$not->ep_user,
                                                                              'ep_doc'=>$not->ep_doc,
                                                                              'ep_cin'=>config('multibanco.easypay.ep_cin'),
                                                                              's_code'=>config('multibanco.easypay.ep_code'),
                                                                              'ep_key'=>$not->id,
                                                                        ] ]);

        $xml = $response->getBody() ;
        $payment =  xml_string_to_array($xml);

        //verify payment, process notification
        if( $payment['ep_status'] == 'ok0') {
          $not->ep_status = 'processed';

          $not->ep_entity = $payment['ep_entity'];
          $not->ep_reference = $payment['ep_reference'];
          $not->ep_value = $payment['ep_value'];
          $not->t_key = $payment['t_key'];
          $not->ep_value = $payment['ep_value'];
          $not->ep_payment_type = $payment['ep_payment_type'];
          $not->ep_value_fixed = $payment['ep_value_fixed'];
          $not->ep_value_var = $payment['ep_value_var'];
          $not->ep_value_tax = $payment['ep_value_tax'];
          $not->ep_value_transf = $payment['ep_value_transf'];
          $not->ep_date_transf = $payment['ep_date_transf'];
          $not->ep_date = $payment['ep_date'];
          $not->save();
          $ref = Reference::where('reference',$not->ep_reference)->first();
          //if reference not paid, mark as paid
          if($ref->state == 0) {
            $ref->paid_value = $not->ep_value;
            $ref->paid_date = $not->ep_date;
            $ref->state=1;
            $ref->save();
            event(new PaymentReceived($ref->foreign_id,$not->ep_value));
          } else {
            $not->ep_status = 'no-reference';
            $not->save();
          }
        }
      }
    }
}
