<?php

Route::get('payments/notify','tricciardi\LaravelMultibanco\MultibancoController@notify');
Route::post('payments/notify','tricciardi\LaravelMultibanco\MultibancoController@notify');
