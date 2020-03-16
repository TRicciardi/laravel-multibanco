<?php namespace tricciardi\LaravelMultibanco\Providers;

use tricciardi\LaravelMultibanco\Contracts\Multibanco;

//models
use tricciardi\LaravelMultibanco\Reference;
use tricciardi\LaravelMultibanco\MBNotification;

//exceptions
use tricciardi\LaravelMultibanco\Exceptions\EasypayException;

//events
use \tricciardi\LaravelMultibanco\Events\PaymentReceived;

//libs
use GuzzleHttp\Client;
use Illuminate\Http\Request;


class Easypay2 implements Multibanco {

  private function getClient() {
    $client = new Client([
        'base_uri' => config('multibanco.easypay2.url'),
        'headers' => [
          'AccountId' => config('multibanco.easypay2.accountid'),
          'ApiKey' => config('multibanco.easypay2.key'),
          'Content-Type' => 'Application/Json',
        ]
    ]);
    return $client;

  }

  /**
   * Get Easypay reference.
   *
   *
   * @return Reference
   */
  public function getReference(Reference $reference, $name='' ) {

    $client = $this->getClient();

    $body = [
      'type'=> 'sale',
      'key' => 'laravel-multibanco',
      'currency' => 'EUR',
      "capture" => [
        "transaction_key" => (string) $reference->id,
        "descriptive" => config('app.name'),
        "capture_date" => date("Y-m-d"),
      ],
      'value' => $reference->value,
      'method' => 'mb',
    ];

    //if ref should expire
    if($reference->expiration_date !== null) {
      $body['expiration_time'] = $reference->expiration_date .' 23:59';
    }



    //request reference from easypay
    $response = $client->request('POST','single', [
                                                    'json'=>$body ,
                                                  ]
                                  );


    $reply = $response->getBody() ;

    //log the response from easypay for analys
    $reference->log = (string)$reply;
    $reference->log .= "\r\nQuery:\r\n";
    $reference->log .= json_encode($body);
    $reference->save();

    $response = json_decode((string) $reply);

    //if response not ok, delete and abort
    if($response->status !== 'ok') {
      $reference->delete();
      throw new EasypayException($response['ep_message']);
    }

    //set entity, reference and value
    $reference->provider_id = $response->id;
    $reference->entity = $response->method->entity;
    $reference->reference = $response->method->reference;
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

        $client = $this->getClient();

        $body = [
          'type'=> 'sale',
          'key' => 'laravel-multibanco',
          'currency' => 'EUR',
          "capture" => [
            "transaction_key" => (string) $reference->id,
            "descriptive" => config('app.name'),
            "capture_date" => date("Y-m-d"),
          ],
          'customer'=> [
            'phone'=>$phone_number
          ],
          'value' => $reference->value,
          'method' => 'mbw',
        ];

        //request reference from easypay
        $response = $client->request('POST','single', [
                                                        'json'=>$body ,
                                                      ]
                                      );


        $reply = $response->getBody() ;

        //log the response from easypay for analys
        $reference->log = (string)$reply;
        $reference->log .= "\r\nQuery:\r\n";
        $reference->log .= json_encode($body);
        $reference->save();

        $response = json_decode((string) $reply);

        $reference->provider_id = $response->id;
        $reference->save();
        //return reference
        return $reference;
  }

  public function notificationReceived(Request $request) {
    $ep = new \stdClass;
    $ep->id = $request->input('id');
    $ep->key = $request->input('key');
    $ep->type = $request->input('type');
    $ep->status = $request->input('status');
    $ep->messages = $request->input('messages');
    $ep->date = $request->input('date');
    $key = $ep->id;

    $notification = MBNotification::where('ref_identifier',$key)->first();


    if(!$notification) {
      $notification = new MBNotification;
      $notification->ref_identifier = $key;
      $notification->state = 0;
      $notification->payload = json_encode($ep);
      $notification->save();
    }
    return view('multibanco::notification', compact('notification') );
  }

  /**
  * Process received payment notifications
  **/
  public function processNotification() {
    $client = $this->getClient();
    $notifications = MBNotification::where('state',0)->get();
    foreach($notifications as $not) {
      $response = $client->request('GET','single/'.$not->ref_identifier);
      $response = json_decode((string) $response->getBody());
      if($response->id == $not->ref_identifier) {
        $ref = Reference::where('provider_id', $response->id)->first();
        if($ref) {
          if($response->payment_status == 'paid') {
            $ref->state = 1;
            $ref->paid_value = $response->value;
            $ref->paid_date = $response->paid_at;
            $ref->save();
            event(new PaymentReceived($ref->foreign_type, $ref->foreign_id, $response->value));
            $not->state = 1;
            $not->save();
          }
        } else {
          $not->state = -1;
          $not->save();
        }
      }
    }
  }

  public function getPayments($date_start, $date_end, $page=1) {
    $client = $this->getClient();
    $params = [
      'created_at'=> 'interval('.$date_start.' 00:00, '.$date_end.' 23:59)',
      'records_per_page'=>100,
      'page'=>$page,
    ];
    $response = $client->request('GET','single', ['query'=>$params]);
    $response = json_decode((string) $response->getBody());
    $meta = $response->meta;
    $pages = $meta->page->total;
    $references = $response->data;
    foreach($references as $ref) {
      $notification = MBNotification::where('ref_identifier',$ref->id)->first();

      if(!$notification) {
        $notification = new MBNotification;
        $notification->ref_identifier = $ref->id;
        $notification->state = 0;
        $notification->payload = json_encode($ref);
        $notification->save();
      }

      $mine = Reference::where('provider_id', $ref->id)->first();
      if($mine && $mine->state != 1) {
        if($ref->payment_status == 'paid') {
          $mine->state = 1;
          $mine->paid_value = $ref->value;
          $mine->paid_date = $ref->paid_at;
          $mine->save();
          $notification->state = 1;
          $notification->save();
          event(new PaymentReceived($mine->foreign_type, $mine->foreign_id, $mine->paid_value));
        }
      } elseif(!$mine) {
        $notification->state = -1;
        $notification->save();
      }
    }
    if($pages > $page) {
      $this->getPayments($date_start, $date_end, $page+1);
    }
  }
}
