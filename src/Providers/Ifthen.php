<?php namespace tricciardi\LaravelMultibanco\Providers;

use tricciardi\LaravelMultibanco\Contracts\Multibanco;


//models
use tricciardi\LaravelMultibanco\Reference;

//exceptions
use tricciardi\LaravelMultibanco\Exceptions\IFThenException;

//libs
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class Ifthen implements Multibanco {

  /**
   * Get Easypay reference.
   *
   *
   * @return Reference
   */
  public function getReference(Reference $reference, $name='' ) {
    $chk_val = 0;
    $entity = config('multibanco.ifthen.entity',null);
    $subentity = config('multibanco.ifthen.subentity',null);

    //if not configured, throw exception
    if(!$entity || !$subentity) {
      throw new IFThenException('IFTHEN invalid or subentity');
    }

    $order_id = "0000". $reference->id;
    $order_value = ifthen_format( $reference->value );

    if(strlen($entity)<5)
    {
      $reference->delete();
      throw new IFThenException( 'IFTHEN invalid entity');
    }else if(strlen($entity)>5){
      $reference->delete();
      throw new IFThenException( 'IFTHEN invalid entity');
    }if(strlen($subentity)==0){
      $reference->delete();
      throw new IFThenException( 'IFTHEN invalid entity');
    }



    if(strlen($subentity)==1){
      //Apenas sao considerados os 6 caracteres mais a direita do order_id
      $order_id = substr($order_id, (strlen($order_id) - 6), strlen($order_id));
      $chk_str = sprintf('%05u%01u%06u%08u', $entity, $subentity, $order_id, round($order_value*100));
    }else if(strlen($subentity)==2){
      //Apenas sao considerados os 5 caracteres mais a direita do order_id
      $order_id = substr($order_id, (strlen($order_id) - 5), strlen($order_id));
      $chk_str = sprintf('%05u%02u%05u%08u', $entity, $subentity, $order_id, round($order_value*100));
    }else {
      //Apenas sao considerados os 4 caracteres mais a direita do order_id
      $order_id = substr($order_id, (strlen($order_id) - 4), strlen($order_id));
      $chk_str = sprintf('%05u%03u%04u%08u', $entity, $subentity, $order_id, round($order_value*100));
    }

    //c�lculo dos check digits

    $chk_array = array(3, 30, 9, 90, 27, 76, 81, 34, 49, 5, 50, 15, 53, 45, 62, 38, 89, 17, 73, 51);

    for ($i = 0; $i < 20; $i++)
    {
      $chk_int = substr($chk_str, 19-$i, 1);
      $chk_val += ($chk_int%10)*$chk_array[$i];
    }

    $chk_val %= 97;

    $chk_digits = sprintf('%02u', 98-$chk_val);

    $reference->entity = $entity;
    $reference->reference = substr($chk_str, 5, 3).substr($chk_str, 8, 3).substr($chk_str, 11, 1).$chk_digits;

    $reference->save();
    return $reference;
  }

  public function purchaseMBWay(Reference $reference, $payment_title, $phone_number) {
    throw new \Exception('MBWay not supported');
  }

  public function notificationReceived(Request $request) {
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

  public function processNotification() {
    //nothing to do
  }

  public function getPayments($date_start, $date_end) {
    //nothing to do
  }


}
