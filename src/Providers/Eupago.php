<?php namespace tricciardi\LaravelMultibanco\Providers;

use tricciardi\LaravelMultibanco\Contracts\Multibanco;

//models
use tricciardi\LaravelMultibanco\Reference;
use tricciardi\LaravelMultibanco\MBNotification;

//events
use \tricciardi\LaravelMultibanco\Events\PaymentReceived;

//exceptions
use tricciardi\LaravelMultibanco\Exceptions\EupagoException;

//libs
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class Eupago implements Multibanco {

  /**
   * Get Easypay reference.
   *
   *
   * @return Reference
   */
  public function getReference(Reference $reference, $name='' ) {

    $client = new Client([
        'base_uri' => config('multibanco.eupago.url'),
    ]);
    // $customer  = $this->customer;

    $query = [
      'chave' => config('multibanco.eupago.key'),
      'valor'=>$reference->value,
      'id'=>$reference->id,
    ];

    //if ref should expire
    if($reference->expiration_date !== false) {
      $query['data_fim'] = $reference->expiration_date;
    }

    //request reference from easypay
    $body = $client->request('POST','multibanco/create',
                                  ['form_params'=>$query ]
                                  )->getBody();


    $response = json_decode((string) $body);
    if($response->sucesso) {
      $reference->entity = $response->entidade;
      $reference->reference = $response->referencia;
      $reference->value = $response->valor;
      $reference->save();
    } else {
      throw new EupagoException('Erro ao gerar referencia');
    }
    //set entity, reference and value


    //return reference
    return $reference;
  }

  public function purchaseMBWay(Reference $reference, $payment_title, $phone_number) {
    return false;
  }

  public function notificationReceived(Request $request) {
    //valor=100&canal=nome_canal&referencia=10357XXXXXXXX&transacao=99&identificador=205
    $eupago = new \stdClass;
    $eupago->valor = request('valor');
    $eupago->canal = request('canal');
    $eupago->transacao = request('transacao','');
    $eupago->identificador = request('identificador','');
    $eupago->referencia = request('referencia','');
    if($eupago->referencia == '' || $eupago->transacao == '') {
      abort(422, 'Invalid input');
    }
    $key = $eupago->referencia.'|'.$eupago->transacao;

    $notification = MBNotification::where('ref_identifier',$key)->first();

    if(!$notification) {
      $notification = new MBNotification;
      $notification->ref_identifier = $key;
      $notification->reference = $eupago->referencia;
      $notification->value = $eupago->valor;
      $notification->state = 0;
      $notification->payload = json_encode($eupago);
      $notification->save();
    }

  }

  /**
  * Process received payment notifications
  **/
  public function processNotification() {

    //get unprocessed notifications
    $notifications = MBNotification::where('state', 0)->get();

    //process notifications
    foreach($notifications as $not) {
      $reference = Reference::where('reference',$not->reference)->first();
      if($reference) {
        $notification_payload = json_decode($not->payload);
        $response = $this->getReferenceInfo($reference);
        //verify payment, process notification
        foreach( $response->pagamentos as $payment ) {

          if($payment->trid == $notification_payload->transacao) {
            if($reference->state != 1) {
              $reference->paid_value = $payment->valor;
              $reference->paid_date = $payment->data_pagamento.' '.$payment->hora_pagamento;
              $reference->log = json_encode($response);
              $reference->state=1;
              $reference->save();
              event(new PaymentReceived($reference));
            }
          }
        }
        $not->state = 1;

        $not->save();
      } else {
        $not->ep_status = -1;
        $not->save();
      }
    }
  }

  private function getReferenceInfo($reference) {
    $client = new Client([
        'base_uri' =>  config('multibanco.eupago.url'),
    ]);

    $query = [
      'chave' => config('multibanco.eupago.key'),
      'referencia'=>$reference->reference,
      'entidade'=>$reference->entity,
    ];

    //request reference info from eupago
    $body = $client->request('POST','multibanco/info',
    ['form_params'=>$query ]
    )->getBody();


    $response = json_decode((string) $body);

    return $response;
  }

  public function getPayments($date_start, $date_end) {
    $references = Reference::where('state',0)->get();
    foreach($references as $reference) {
      $response = $this->getReferenceInfo($reference);
      //check if transaction was notified
      if(isset($response->pagamentos)) {
        foreach( $response->pagamentos as $payment ) {
          $key = $reference->reference.'|'. $payment->trid;
          $payment->transacao = $payment->trid;
          $notification = MBNotification::where('ref_identifier',$key)->first();
          if(!$notification) {
            $notification = new MBNotification;
            $notification->ref_identifier = $key;
            $notification->reference = $reference->reference;
            $notification->value = $payment->valor;
            $notification->state = 0;
            $notification->payload = json_encode($payment);
            $notification->save();
          }
        }
      }
    }
    $this->processNotification();
  }
}
