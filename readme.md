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

      $mbway = $multibanco->mbway_authorize('teste','9xxxxxxxxx');

    }
}
```
