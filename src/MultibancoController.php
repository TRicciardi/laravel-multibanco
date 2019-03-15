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
      $mb = new Multibanco;
      return $mb->notificationReceived($request);
    }

}
