<?php

namespace tricciardi\LaravelMultibanco;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use GuzzleHttp\Client;
use Parser;
class Reference extends Model
{
  use SoftDeletes;
  protected $table = 'mb_references';

}
