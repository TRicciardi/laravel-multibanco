<?php

return [
  'type'=> env('MB_TYPE',null),
  'easypay' => [
    'provider' => tricciardi\LaravelMultibanco\Providers\Easypay::class,
    'ep_user'=> env('EP_USER',''),
    'ep_mode'=> env('EP_MODE','prod'),
    'ep_partner'=> env('EP_PARTNER',false),
    'ep_cin'=> env('EP_CIN',''),
    'ep_code'=> env('EP_CODE',''),
    'ep_entity'=> env('EP_ENTITY',''),
    'ep_url'=> env('EP_URL','https://www.easypay.pt/'),
    'max_date'=> env('EP_MAX_DATE',null),
  ],
  'ifthen' => [
    'provider' => tricciardi\LaravelMultibanco\Providers\Ifthen::class,
    'key' => env('IFTHEN_KEY',null),
    'entity' => env('IFTHEN_ENTITY',null),
    'subentity' => env('IFTHEN_SUBENTITY',null),
  ],
  'eupago' => [
    'provider' => tricciardi\LaravelMultibanco\Providers\Eupago::class,
    'key'=>env('EUPAGO_KEY',null),
    'url' => env('EUPAGO_URL', 'https://replica.eupago.pt/clientes/rest_api/'),
  ],

];
