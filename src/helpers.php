<?php

if(! function_exists('format_reference') ) {
  function format_reference($ref) {
    return str_pad(number_format($ref,0,' ',' '), 11, '0', STR_PAD_LEFT);
  }
}

if(! function_exists('ifthen_format') ) {
  function ifthen_format($number) {
    $verifySepDecimal = number_format(99,2);

    $valorTmp = $number;

    $sepDecimal = substr($verifySepDecimal, 2, 1);

    $hasSepDecimal = True;

    $i=(strlen($valorTmp)-1);

    for($i;$i!=0;$i-=1)
    {
      if(substr($valorTmp,$i,1)=="." || substr($valorTmp,$i,1)==","){
        $hasSepDecimal = True;
        $valorTmp = trim(substr($valorTmp,0,$i))."@".trim(substr($valorTmp,1+$i));
        break;
      }
    }

    if($hasSepDecimal!=True){
      $valorTmp=number_format($valorTmp,2);

      $i=(strlen($valorTmp)-1);

      for($i;$i!=1;$i--)
      {
        if(substr($valorTmp,$i,1)=="." || substr($valorTmp,$i,1)==","){
          $hasSepDecimal = True;
          $valorTmp = trim(substr($valorTmp,0,$i))."@".trim(substr($valorTmp,1+$i));
          break;
        }
      }
    }

    for($i=1;$i!=(strlen($valorTmp)-1);$i++)
    {
      if(substr($valorTmp,$i,1)=="." || substr($valorTmp,$i,1)=="," || substr($valorTmp,$i,1)==" "){
        $valorTmp = trim(substr($valorTmp,0,$i)).trim(substr($valorTmp,1+$i));
        break;
      }
    }

    if (strlen(strstr($valorTmp,'@'))>0){
      $valorTmp = trim(substr($valorTmp,0,strpos($valorTmp,'@'))).trim($sepDecimal).trim(substr($valorTmp,strpos($valorTmp,'@')+1));
    }

    return $valorTmp;
  }
}

if(! function_exists('datept') ) {
  function datept($date) {
    return date("d/m/Y", strtotime($date));
  }
}

if(! function_exists('getParam') ) {
  function getParam($params, $key_string, $default=null) {

    $keys = explode('.',$key_string);

    $value = $default;

    if(count($keys) == 1) {
      return isset($params->{$keys[0]})?$params->{$keys[0]}:$default;
    }
    $key = array_shift($keys);
    if($key && isset($params->{$key})) {
      $value = getParam($params->{$key}, implode('.',$keys),$value);
    } else {
      return $value;
    }

    return $value;
  }
}

if(! function_exists('nf') ) {
  function nf($number,$decimals=2) {
    return number_format($number,$decimals,',','.');
  }
}

if(! function_exists('s3_get_signed') ) {
  function s3_get_signed($file, $minutes=10) {
    $s3 = Storage::disk('s3');
    $client = $s3->getDriver()->getAdapter()->getClient();
    $expiry = "+".$minutes." minutes";

    $command = $client->getCommand('GetObject', [
        'Bucket' => \Config::get('filesystems.disks.s3.bucket'),
        'Key'    => $file
    ]);

    $request = $client->createPresignedRequest($command, $expiry);

    return (string) $request->getUri();
  }
}
if(! function_exists('age') ) {
  function age($birth_date, $date) {
      //date in mm/dd/yyyy format; or it can be in other formats as well

    //explode the date to get month, day and year
    $birthDate = strtotime($birth_date." 00:00:00");
    $calcDate = strtotime($date." 00:00:00");
    //get age from date or birthdate
    $age = date("md", $birthDate ) > date("md", $calcDate)
      ? date("Y",$calcDate) - date("Y",$birthDate) - 1
      : date("Y",$calcDate) - date("Y",$birthDate);
    return $age;
  }
}
if(! function_exists('randomCode') ) {
  function randomCode($size=8) {
        $numeric = '1234567890';
        $alpha='abcdefghijklmnopqrstuvwxyz';
        $pass = array(); //remember to declare $pass as an array

        for ($i = 0; $i < $size; $i++) {
            if($i%2!=0) {
              $n = rand(0, strlen($numeric)-1);
              $pass[] = $numeric[$n];
            } else {
              $n = rand(0, strlen($alpha)-1);
              $pass[] = $alpha[$n];
            }
        }
        return implode($pass); //turn the array into a string
    }
}
if(! function_exists('strim') ) {
  function strim($str) {
    $str = preg_replace('/^[\s\x00]+|[\s\x00]+$/u', '', $str);
    return $str;
  }
}
