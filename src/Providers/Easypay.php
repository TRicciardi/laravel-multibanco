<?php namespace tricciardi\LaravelMultibanco\Providers;

use tricciardi\LaravelMultibanco\Contracts\Multibanco;

//models
use tricciardi\LaravelMultibanco\Reference;
use tricciardi\LaravelMultibanco\EasypayNotification;

//exceptions
use tricciardi\LaravelMultibanco\Exceptions\EasypayException;

//events
use \tricciardi\LaravelMultibanco\Events\PaymentReceived;

//libs
use GuzzleHttp\Client;
use Illuminate\Http\Request;


class Easypay implements Multibanco {

  /**
   * Get Easypay reference.
   *
   *
   * @return Reference
   */
  public function getReference(Reference $reference, $name='' ) {

    $client = new Client([
        'base_uri' => config('multibanco.easypay.ep_url'),
    ]);
    // $customer  = $this->customer;

    $query = [
      's_code'=>config('multibanco.easypay.ep_code'),
      'ep_cin'=>config('multibanco.easypay.ep_cin'),
      'ep_user'=>config('multibanco.easypay.ep_user'),
      'ep_entity'=>config('multibanco.easypay.ep_entity'),
      'ep_ref_type'=>'auto',
      't_value'=>$reference->value,
      't_key'=>$reference->id,
      'o_name'=>$name,
      'ep_country'=>'PT',
      'ep_language'=>'PT',
    ];

    //if ref should expire
    if($reference->expiration_date !== false) {
      $query['o_max_date'] = $reference->expiration_date;
    }

    //if EP_PARTNER is configured add to query
    if(config('multibanco.easypay.ep_partner') !== false) {
      $query['ep_partner'] = config('multibanco.easypay.ep_partner');
    }


    //request reference from easypay
    $response = $client->request('GET','/_s/api_easypay_01BG.php',
                                  ['query'=>$query ]
                                  );



    $xml = $response->getBody() ;


    //log the response from easypay for analys
    $reference->log = (string)$xml;
    $reference->log .= "\r\nQuery:\r\n";
    $reference->log .= json_encode($query);
    $reference->save();

    //parse reference from xml
    $response =  xml_string_to_array($xml);



    //if response not ok, delete and abort
    if(   $response['ep_status'] !== 'ok0' ) {
      $reference->delete();
      throw new EasypayException($response['ep_message']);
    }

    //set entity, reference and value
    $reference->entity = $response['ep_entity'];
    $reference->reference = $response['ep_reference'];
    $reference->value = $response['ep_value'];
    $reference->save();

    //return reference
    return $reference;
  }

  public function purchaseMBWay(Reference $reference, $payment_title, $phone_number) {
    return $this->mbway_purchase($reference, $payment_title, $phone_number);
  }

  public function mbway_authorize($reference, $payment_title, $phone_number) {
    $client = new Client([
        'base_uri' => config('multibanco.easypay.ep_url'),
    ]);
    $query = [
      's_code'=>config('multibanco.easypay.ep_code'),
      'e'=>$reference->entity,
      'r'=>$reference->reference,
      'v'=>(float)$reference->value,
      'mbway'=>'yes',
      'mbway_title'=>$payment_title,
      'mbway_type'=>'authorization',
      'mbway_phone_indicative'=>'351',
      'mbway_phone'=>$phone_number,
      't_key'=>$reference->id,
      'mbway_currency'=>'EUR',
    ];
    //request reference from easypay
    $response = $client->request('GET','/_s/api_easypay_05AG.php',
                                  ['query'=>$query ]
                                  );



    $xml = $response->getBody();
    $response =  xml_string_to_array($xml);
    if($response['ep_status'] !== 'ok0') {
      throw new EasypayException('MBWAY error');
    }
  }

  public function mbway_capture($reference, $epk1, $payment_title, $phone_number) {
    $client = new Client([
        'base_uri' => config('multibanco.easypay.ep_url'),
    ]);
    $query = [
      's_code'=>config('multibanco.easypay.ep_code'),
      'e'=>$reference->entity,
      'r'=>$reference->reference,
      'v'=>(float)$reference->value,
      'mbway'=>'yes',
      'mbway_title'=>$payment_title,
      'mbway_type'=>'capture',
      'mbway_phone_indicative'=>'351',
      'mbway_phone'=>$phone_number,
      't_key'=>$reference->id,
      'ep_k1'=>$epk1,
      'mbway_currency'=>'EUR',
    ];
    //request reference from easypay
    $response = $client->request('GET','/_s/api_easypay_05AG.php',
                                  ['query'=>$query ]
                                  );



    $xml = $response->getBody();
    $response =  xml_string_to_array($xml);
    if($response['ep_status'] !== 'ok0') {
      throw new EasypayException('MBWAY error');
    }
  }

  public function mbway_purchase($reference, $payment_title, $phone_number) {
    $client = new Client([
        'base_uri' => config('multibanco.easypay.ep_url'),
    ]);
    $query = [
      's_code'=>config('multibanco.easypay.ep_code'),
      'e'=>$reference->entity,
      'r'=>$reference->reference,
      'v'=>(float)$reference->value,
      'mbway'=>'yes',
      'mbway_title'=>$payment_title,
      'mbway_type'=>'purchase',
      'mbway_phone_indicative'=>'351',
      'mbway_phone'=>$phone_number,
      't_key'=>$reference->id,
      'mbway_currency'=>'EUR',
    ];
    //request reference from easypay
    $response = $client->request('GET','/_s/api_easypay_05AG.php',
                                  ['query'=>$query ]
                                  );



    $xml = $response->getBody();

    $response =  xml_string_to_array($xml);
    switch($response['ep_status']) {
      case 'ok0':
        break;
      case 'refused':
        return false;
        break;
      default:
        throw new EasypayException('MBWAY error');
        break;
    }
    return true;
  }

  public function notificationReceived(Request $request) {
    if(!$request->input('ep_doc', null )) {
      abort(422,'Invalid input');
    }
    $notification = EasypayNotification::where('ep_doc',$request->input('ep_doc' ))->first();
    if(!$notification) {
      $notification = new EasypayNotification;
      $notification->ep_cin = $request->input('ep_cin', config('multibanco.easypay.ep_cin') );
      $notification->ep_user = $request->input('ep_user', config('multibanco.easypay.ep_user') );
      $notification->ep_doc = $request->input('ep_doc' );
      $notification->ep_type = $request->input('ep_type' ,'');
      $notification->ep_status = 'ok0';
      $notification->save();
    }

    return view('multibanco::notification', compact('notification') );
  }

  /**
  * Process received payment notifications
  **/
  public function processNotification() {

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
        $not->t_key = ($payment['t_key'])?$payment['t_key']:'';
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

        if($ref) {
          //if reference not paid, mark as paid
          if($ref->state != 1) {
            $ref->paid_value = $not->ep_value;
            $ref->paid_date = $not->ep_date;
            $ref->state=1;
            $ref->save();
            event(new PaymentReceived($ref));
          }
        } else {
          $not->ep_status = 'no-reference';
          $not->save();
        }
      } else {
        $not->ep_status = 'error';
        $not->save();
      }
    }
  }

  public function getPayments($date_start, $date_end) {
    $client = new Client([
        'base_uri' =>  config('multibanco.easypay.ep_url'),
    ]);

    // get last 3 months of payments
    $response = $client->request('GET','_s/api_easypay_040BG1.php',['query'=>[
                                                                          'ep_user'=>config('multibanco.easypay.ep_user'),
                                                                          'ep_entity'=>config('multibanco.easypay.ep_entity'),
                                                                          'ep_cin'=>config('multibanco.easypay.ep_cin'),
                                                                          's_code'=>config('multibanco.easypay.ep_code'),
                                                                          'o_list_type'=>'date',
                                                                          'o_ini'=>$date_start,
                                                                          'o_last'=>$date_end,

                                                                            ] ]);

    $xml = $response->getBody() ;
    $payments =  xml_string_to_array($xml);
    //extract all references
    if(isset($payments['ref_detail'])) {

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
          $not->t_key = ($payment['t_key'])?$payment['t_key']:'';
          $not->ep_value = $payment['ep_value'];
          $not->ep_payment_type = $payment['ep_payment_type'];
          $not->ep_value_fixed = $payment['ep_value_fixed'];
          $not->ep_value_var = $payment['ep_value_var'];
          $not->ep_value_tax = $payment['ep_value_tax'];
          $not->ep_value_transf = $payment['ep_value_transf'];
          $not->ep_date_transf = $payment['ep_date_transf'];
          $not->ep_date = $payment['ep_payment_date'];
          $not->save();
          $ref = Reference::where('reference',$not->ep_reference)->first();
          if($ref) {
            //reference exists, set state 1 if needed
            if($ref->state != 1) {
              $ref->paid_value = $not->ep_value;
              $ref->paid_date = $not->ep_date;
              $ref->state=1;
              $ref->save();
              event(new PaymentReceived($ref));
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
}
