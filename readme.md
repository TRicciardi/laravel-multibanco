# Multibanco - Easypay e Ifthen

### Instalation
```
composer require tricciardi/laravel-multibanco
```
### Usage
```
<?php

namespace App\Http\Controllers;

use tricciardi\LaravelMultibanco\Multibanco;

class TestController extends Controller
{
    public function index() {

      $multibanco = new Multibanco;
      $reference = $multibanco->getReference(1,1, '2018-11-30');


      //make mbway purchase
      $mbway = $multibanco->mbway_authorize('teste','9xxxxxxxxx');

    }
}
```

### Config
```
php artisan vendor:publish

define options on config/multibanco.php


```

### Console Commands
To process received notifications run
```
php artisan mb:getpayments
```

To get all paid references from Easypay and process them:
```
php artisan mb:getdaily

```
