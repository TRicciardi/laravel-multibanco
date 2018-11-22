<?php namespace tricciardi\LaravelMultibanco;

//events
use \tricciardi\LaravelMultibanco\Events\PaymentReceived;

//models
use tricciardi\LaravelMultibanco\Reference;

//exceptions
use tricciardi\LaravelMultibanco\Exceptions\EasypayException;
use tricciardi\LaravelMultibanco\Exceptions\IFThenException;

//libs
use GuzzleHttp\Client;


class Multibanco
{

  protected $reference;

  public function __construct($value=null, $foreign_key=null, $max_date=null,$name=null) {
    if($value) {
      $this->getReference($value, $foreign_key, $max_date,$name);
    }
  }
  /**
   * Get reference.
   *
   *
   * @return Reference
   */
  public function getReference($value, $foreign_key=null, $max_date=null,$name=null) {
    $this->reference = new Reference;
    if($foreign_key) {
      $this->reference->foreign_id = $foreign_key;
    }
    $this->reference->value = $value;
    $this->reference->expiration_date = $max_date;
    $this->reference->save();

    if( config('multibanco.type') == 'ifthen' ) {
      return $this->getIfThen();
    } else {
      return $this->getEasyPay($name);
    }
  }

  /**
   * Get Easypay reference.
   *
   *
   * @return Reference
   */
  private function getEasyPay($name='') {

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
      't_value'=>$this->reference->value,
      't_key'=>$this->reference->id,
      'o_name'=>$name,
      'ep_country'=>'PT',
      'ep_language'=>'PT',
    ];

    //if ref should expire
    if($this->reference->expiration_date !== false) {
      $query['o_max_date'] = $this->reference->expiration_date;
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
    $this->reference->log = $xml;
    $this->reference->log .= "\r\nQuery:\r\n";
    $this->reference->log .= json_encode($query);
    $this->reference->save();

    //parse reference from xml
    $response =  xml_string_to_array($xml);


    //if response not ok, delete and abort
    if(   $response['ep_status'] !== 'ok0' ) {
      $this->reference->delete();
      throw new EasypayException($response['ep_message']);
    }

    //set entity, reference and value
    $this->reference->entity = $response['ep_entity'];
    $this->reference->reference = $response['ep_reference'];
    $this->reference->value = $response['ep_value'];
    $this->reference->save();

    //return reference
    return $this->reference;
  }

  /**
   * Get Ifthen reference.
   *
   *
   * @return Reference
   */
  private function getIfThen() {
    $chk_val = 0;
    $entity = config('ifthenpay.entity',null);
    $subentity = config('ifthenpay.subentity',null);

    //if not configured, throw exception
    if(!$entity || !$subentity) {
      throw new IFThenException();
    }

    $order_id = "0000". $this->reference->id;
    $order_value = ifthen_format( $this->reference->value );

    if(strlen($entity)<5)
    {
      $this->reference->delete();
      throw new IFThenException( 'IFTHEN invalid entity');
    }else if(strlen($entity)>5){
      $this->reference->delete();
      throw new IFThenException( 'IFTHEN invalid entity');
    }if(strlen($subentity)==0){
      $this->reference->delete();
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

    $this->reference->entity = $entity;
    $this->reference->reference = substr($chk_str, 5, 3).substr($chk_str, 8, 3).substr($chk_str, 11, 1).$chk_digits;

    $this->reference->save();
    return $this->reference;
  }


  public function mbway_authorize($payment_title, $phone_number) {
    $client = new Client([
        'base_uri' => config('multibanco.easypay.ep_url'),
    ]);
    $query = [
      's_code'=>config('multibanco.easypay.ep_code'),
      'e'=>$this->reference->entity,
      'r'=>$this->reference->reference,
      'v'=>(float)$this->reference->value,
      'mbway'=>'yes',
      'mbway_title'=>$payment_title,
      'mbway_type'=>'authorization',
      'mbway_phone_indicative'=>'351',
      'mbway_phone'=>$phone_number,
      't_key'=>$this->reference->id,
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


  public function mbway_capture($epk1, $payment_title, $phone_number) {
    $client = new Client([
        'base_uri' => config('multibanco.easypay.ep_url'),
    ]);
    $query = [
      's_code'=>config('multibanco.easypay.ep_code'),
      'e'=>$this->reference->entity,
      'r'=>$this->reference->reference,
      'v'=>(float)$this->reference->value,
      'mbway'=>'yes',
      'mbway_title'=>$payment_title,
      'mbway_type'=>'capture',
      'mbway_phone_indicative'=>'351',
      'mbway_phone'=>$phone_number,
      't_key'=>$this->reference->id,
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


  public function mbway_purchase($payment_title, $phone_number) {
    $client = new Client([
        'base_uri' => config('multibanco.easypay.ep_url'),
    ]);
    $query = [
      's_code'=>config('multibanco.easypay.ep_code'),
      'e'=>$this->reference->entity,
      'r'=>$this->reference->reference,
      'v'=>(float)$this->reference->value,
      'mbway'=>'yes',
      'mbway_title'=>$payment_title,
      'mbway_type'=>'purchase',
      'mbway_phone_indicative'=>'351',
      'mbway_phone'=>$phone_number,
      't_key'=>$this->reference->id,
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

}
