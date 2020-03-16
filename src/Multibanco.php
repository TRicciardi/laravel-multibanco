<?php namespace tricciardi\LaravelMultibanco;

//models
use tricciardi\LaravelMultibanco\Reference;

//libs
use Illuminate\Http\Request;


class Multibanco
{

  protected $reference;
  protected $provider = null;

  public function __construct($value=null, $foreign_key=null, $max_date=null,$name=null, $foreign_type=null) {
    $type = config('multibanco.type');
    $provider = config('multibanco.'.$type.'.provider');
    $this->provider = new $provider;
    // if($value) {
    //   $this->getReference($value, $foreign_key, $max_date,$name);
    // }
  }

  /**
   * Get reference.
   *
   *
   * @return Reference
   */
  public function getReference($value, $foreign_key=null, $max_date=null,$name=null,  $foreign_type=null) {
    $this->reference = new Reference;
    if($foreign_key) {
      $this->reference->foreign_id = $foreign_key;
    }
    if($foreign_type) {
      $this->reference->foreign_type = $foreign_type;
    }
    $this->reference->value = $value;
    if($max_date != null) {
      $this->reference->expiration_date = $max_date;
    }
    $this->reference->save();

    return $this->provider->getReference($this->reference, $name);
  }


  /**
   * Makes MBWay payment
   *
   *
   * @return Reference
   */
  public function purchaseMBWay($phone_number, $value, $payment_title, $foreign_key=null, $max_date=null,$name=null,  $foreign_type=null) {
    $this->reference = new Reference;
    if($foreign_key) {
      $this->reference->foreign_id = $foreign_key;
    }
    if($foreign_type) {
      $this->reference->foreign_type = $foreign_type;
    }
    $this->reference->value = $value;
    if($max_date != null) {
      $this->reference->expiration_date = $max_date;
    }
    $this->reference->save();

    return $this->provider->purchaseMBWay($this->reference, $payment_title, $phone_number);
  }

  /**
   * @deprecated
   * Makes MBWay payment
   *
   *
   * @return Reference
   */
  public function mbway_purchase($payment_title, $phone_number) {
    return $this->provider->purchaseMBWay($this->reference, $payment_title, $phone_number);
  }

  public function notificationReceived(Request $request) {
    return $this->provider->notificationReceived($request);
  }

  public function processNotifications() {
    return $this->provider->processNotification();
  }

  public function getPayments($mindate, $maxdate) {
    return $this->provider->getPayments($mindate, $maxdate);
  }

}
