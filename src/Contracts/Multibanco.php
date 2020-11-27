<?php

namespace tricciardi\LaravelMultibanco\Contracts;

use Illuminate\Http\Request;
use tricciardi\LaravelMultibanco\Reference;

interface Multibanco
{
  /**
   * Get reference.
   *
   *
   * @return Reference
   */
    public function getReference(Reference $reference, $name='');

  /**
   * make mbway purchase.
   *
   *
   * @return boolean
   */
   public function purchaseMBWay(Reference $reference, $payment_title, $phone_number);

   /**
    * check payments
    *
    *
    * @return boolean
    */
   public function notificationReceived(Request $request);

  /**
   * check payments
   *
   *
   * @return boolean
   */
   public function processNotification();

  /**
   * check payments between dates
   *
   *
   * @return boolean
   */
   public function getPayments($date_start, $date_end);

}
