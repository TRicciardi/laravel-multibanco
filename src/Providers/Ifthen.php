<?php namespace tricciardi\LaravelMultibanco\Providers;

use tricciardi\LaravelMultibanco\Contracts\Multibanco;


//models
use tricciardi\LaravelMultibanco\Reference;

//exceptions
use tricciardi\LaravelMultibanco\Exceptions\IFThenException;

//events
use \tricciardi\LaravelMultibanco\Events\PaymentReceived;

//libs
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class Ifthen implements Multibanco {

  private function getClient() {
    $client = new Client([
        'base_uri' => config('multibanco.ifthen.url'),
        // 'headers' => [
        //   'Content-Type' => 'Application/Json',
        // ]
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
    return $this->mbway_purchase($reference, $payment_title, $phone_number);
  }

  public function notificationReceived(Request $request) {
    $our_key = config('multibanco.ifthen.key');
    $key = request('chave');
    if($key != $our_key)
      abort(403,'Not allowed');
    if(request('type', 'mb') === 'mbway' ) {
      return $this->mbwayNotification($request);
    }
    $entidade = request('entidade');
    $referencia = request('referencia');
    $valor = request('valor');
    $datahorapag = request('datahorapag');
    $terminal = request('terminal');

    $notification = new \stdClass;
    $notification->entidade = $entidade;
    $notification->referencia = $referencia;
    $notification->valor = $valor;
    $notification->datahorapag = $datahorapag;
    $notification->terminal = $terminal;

    $ref = Reference::where('reference',$referencia)->where('entity',$entidade)->first();
    if($ref && $ref->state != 1 ) {
      $ref->state = 1;
      $ref->paid_value = $notification->valor;
      $ref->paid_date = date("Y-m-d H:i:s", strtotime($notification->datahorapag));
      $ref->log .= json_encode($notification);
      $ref->save();
      event(new PaymentReceived($ref));
    }
  }

  //chave=[CHAVE_ANTI_PHISHING]&referencia=[REFERENCIA]&idpedido=[ID_TRANSACAO]&valor=[VALOR]&datahorapag=[DATA_HORA_PAGAMENTO]&estado=[ESTADO]
  private function mbwayNotification(Request $request) {
    $referencia = request('referencia');
    $idpedido = request('idpedido');
    $valor = request('valor');
    $datahorapag = request('datahorapag');
    $estado = request('estado');

    $notification = new \stdClass;
    $notification->estado = $estado;
    $notification->referencia = $referencia;
    $notification->valor = $valor;
    $notification->datahorapag = $datahorapag;
    $notification->idpedido = $idpedido;

    if($estado == 'PAGO') {
      $ref = Reference::where('provider_id', $idpedido)->first();
      if($ref && $ref->state != 1 ) {
        $ref->state = 1;
        $ref->paid_value = $notification->valor;
        $ref->paid_date = date("Y-m-d H:i:s", strtotime($notification->datahorapag));
        $ref->log .= json_encode($notification);
        $ref->save();
        event(new PaymentReceived($ref));
      }
    }
  }

  public function processNotification() {
    //nothing to do
  }

  public function getPayments($date_start, $date_end) {
    //nothing to do
    // https://www.ifthenpay.com

    $body = [];
    $body['chavebackoffice'] = config('multibanco.ifthen.backoffice_key');
    $body['entidade'] = config('multibanco.ifthen.entity');
    $body['subentidade'] = config('multibanco.ifthen.subentity');
    $body['dtHrInicio'] = date('d-m-Y 00:00:00', strtotime($date_start));
    $body['dtHrFim'] = date('d-m-Y 23:59:59', strtotime($date_end));
    $body['referencia'] = '';
    $body['valor'] = '';
    $body['sandbox'] = 0;
    // $body['nrtlm'] = $phone_number;
    // $body['email'] = '';
    // $body['descricao'] = $payment_title;
    $client = $this->getClient();

    //request reference from easypay
    $response = $client->request('POST','/IfmbWS/WsIfmb.asmx/GetPaymentsJsonV2', [
                                                    'form_params'=>$body ,
                                                  ]
                                  );

    $references = json_decode((string) $response->getBody());

    foreach($references as $ref) {
      $mine = Reference::where('reference', $ref->Referencia)->first();
      if($mine && $mine->state != 1) {
        $mine->state = 1;
        $mine->paid_value = $ref->Valor;
        $mine->paid_date = date("Y-m-d H:i:s", strtotime($ref->DtHrPagamento));
        $mine->log .= json_encode($ref);
        $mine->save();
        event(new PaymentReceived($mine));
      }
    }
  }

  /*
  *
  * MbWayKey - (Obrigatório) fornecido pela IFTHENPAY aquando da celebração do
    contrato.
    • canal - (Obrigatório) no caso desta API terá de ter sempre o valor constante “03”.
    • referencia – (Obrigatório) Identificador do pagamento a definir pelo cliente (ex.
    número da fatura, encomenda, etc…); Máximo 15 caracteres.
    • valor - (Obrigatório) valor a cobrar.
    • nrtlm - (Obrigatório) Número do telemóvel do cliente.
    • email - (Opcional) email do cliente.
    • descricao - (Obrigatório) descrição do pagamento (pode utilizar a tag [REFERENCIA]
    caso pretenda que a descrição seja igual à referência); Máximo 50 caracteres.
  *
  */

  public function mbway_purchase($reference, $payment_title, $phone_number) {
    $body = [];
    $body['MbWayKey'] = config('multibanco.ifthen.mbwaykey');
    $body['canal'] = '03';
    $body['referencia'] = $reference->id;
    $body['valor'] = $reference->value;
    $body['nrtlm'] = $phone_number;
    $body['email'] = '';
    $body['descricao'] = $payment_title;
    $client = $this->getClient();

    //request reference from easypay
    $response = $client->request('POST','/mbwayws/IfthenPayMBW.asmx/SetPedidoJSON', [
                                                    'form_params'=>$body ,
                                                  ]
                                  );


    $reply = json_decode((string) $response->getBody()) ;
    $reference->provider_id = $reply->IdPedido;
    if($reply->Estado !== '000') {
      throw new IFThenException('Erro MBWAY');
    }
    $reference->log = json_encode($reply);
    $reference->log .= "\r\nQuery:\r\n";
    $reference->log .= json_encode($body);
    $reference->save();
    //return reference
    return $reference;

    // /
    // MbWayKey=string&canal=string&referencia=string&valor=string&nrtlm=string&email=string&descricao=string
  }
}
