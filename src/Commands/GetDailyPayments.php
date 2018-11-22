<?php

namespace tricciardi\LaravelMultibanco\Commands;

use Illuminate\Console\Command;
use tricciardi\LaravelMultibanco\Helpers\PaymentHelpers;

class GetDailyPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mb:getdaily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get easypay daily payments';



    /**
     * Create a new command instance.
     *
     * @param  DripEmailer  $drip
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
      if( config('multibanco.type') == 'ifthen' ) {
        //ifthen does not allow to get payments received. exit here
        return;
      }

      $client = new Client([
          'base_uri' =>  config('multibanco.easypay.ep_url'),
      ]);

      // get last 3 months of payments
      $response = $client->request('GET','_s/api_easypay_040BG1.php',['query'=>[
                                                                            'ep_user'=>config('multibanco.EP_USER'),
                                                                            'ep_entity'=>config('multibanco.EP_ENTITY'),
                                                                            'ep_cin'=>config('multibanco.EP_CIN'),
                                                                            's_code'=>config('multibanco.EP_CODE'),
                                                                            'o_list_type'=>'date',
                                                                            'o_ini'=>date("Y-m-d",strtotime("now - 3 months")),
                                                                            'o_last'=>date("Y-m-d",strtotime("now - 1 day")),

                                                                              ] ]);

      $xml = $response->getBody() ;
      $payments =  xml_string_to_array($xml);

      //extract all references
      $notifications = $payments['ref_detail']['ref'];

      //for each reference, check if its notification is on the notifications table.
      //if not, add to table
      foreach($notifications as $payment) {
        $not = EasypayNotification::where('ep_doc',$payment['ep_doc'])->first();
        if(!$not) {
          $not = new EasypayNotification;
          $not->ep_doc = $payment['ep_doc'];
          $not->ep_cin = $payment['ep_cin'];
          $not->ep_user = $payment['ep_user'];

          $not->ep_status = 'ok0';
          $not->save();
        }

        //if notification not processed, process it
        if( $not->ep_status == 'ok0') {
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
          $not->ep_date = $payment['ep_payment_date'];
          $not->save();
          //notification processed.
          //check if there is a reference for this notification
          $ref = Reference::where('ep_reference',$not->ep_reference)->first();
          if($ref) {
            //reference exists, set state 1 if needed
            if($ref->state != 0) {
              $ref->paid_value = $not->ep_value;
              $ref->paid_date = $not->ep_date;
              $ref->state=1;
              $ref->save();
              event(new PaymentReceived($ref->foreign_id,$not->ep_value));
            }
          } else {
            //unknown reference, set notification status to no-reference
            $not->ep_status = 'no-reference';
            $not->save();
          }
        }
      }
    }
}
