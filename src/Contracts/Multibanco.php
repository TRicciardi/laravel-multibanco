<?php

namespace tricciardi\LaravelMultibanco\Contracts;

interface Multibanco
{
  /**
   * Get reference.
   *
   *
   * @return Reference
   */
    public function getReference($reference, $name);

  /**
   * make mbway purchase.
   *
   *
   * @return boolean
   */
   public function purchaseMBWay($reference, $payment_title, $phone_number);


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
