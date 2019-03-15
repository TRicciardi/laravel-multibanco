<?php

namespace tricciardi\LaravelMultibanco;

use App\Http\Controllers\Controller;
use tricciardi\LaravelMultibanco\EasypayNotification;
use tricciardi\LaravelMultibanco\MBNotification;
use tricciardi\LaravelMultibanco\Reference;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use tricciardi\LaravelMultibanco\Events\PaymentSuccess;
use tricciardi\LaravelMultibanco\Helpers\PaymentHelpers;

class MultibancoController extends Controller
{
    public function notify(Request $request) {
      if( env('MB_TYPE') == 'ifthen' ) {
        return $this->notifyIfThen($request);
      } else {
        return $this->notifyEasypay($request);
      }
    }

    public function notifyIfThen(Request $request) {
      $our_key = config('multibanco.ifthen.key');
      $key = request('chave');
      if($key != $our_key)
        abort(403,'Not allowed');
      $entidade = request('entidade');
      $referencia = request('referencia');
      $valor = request('valor');
      $datahorapag = request('datahorapag');
      $terminal = request('terminal');

      $ref = Reference::where('ep_reference',$referencia)->where('ep_entity',$entidade)->first();
      if($ref && $ref->state != 1 ) {
        if($ref->registration && $ref->registration->state >= 0) {
          $ref->state = 1;
          $ref->save();
          event(new \tricciardi\LaravelMultibanco\Events\PaymentReceived($ref->registration_id,$ref->payment_id,$valor));
        } else {
          $ref->state = 1;
          $ref->save();
        }
      }
    }


    public function notifyEupago(Request $request) {
      //valor=100&canal=nome_canal&referencia=10357XXXXXXXX&transacao=99&identificador=205
      $eupago = new \stdClass;
      $eupago->valor = request('valor');
      $eupago->canal = request('canal');
      $eupago->transacao = request('transacao');
      $eupago->identificador = request('identificador');
      $eupago->referencia = request('referencia');

      $key = $eupago->referencia.'|'.$eupago->transacao;

      $notification = MBNotification::where('ref_identifier',$key)->first();

      if(!$notification) {
        $notification = new MBNotification;
        $notification->ref_identifier = $key;
        $notification->referencia = $eupago->referencia;
        $notification->value = $eupago->valor;
        $notification->state = 0;
        $notification->payload = json_encode($eupago);
        $notification->save();
      }
    }


    public function notifyEasypay(Request $request) {
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

    public function getPayment() {
      PaymentService::getPayments();
    }
}
