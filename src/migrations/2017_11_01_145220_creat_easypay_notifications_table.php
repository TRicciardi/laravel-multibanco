<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatEasypayNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('easypay_notifications', function (Blueprint $table) {
          $table->increments('id')->nullable();
          $table->string('ep_doc')->unique()->nullable();
          $table->string('ep_cin')->nullable();
          $table->string('ep_user')->nullable();
          $table->string('ep_status')->nullable();
          $table->string('ep_entity')->nullable();
          $table->string('ep_reference')->nullable();
          $table->double('ep_value')->nullable();
          $table->datetime('ep_date')->nullable();
          $table->string('ep_payment_type')->nullable();
          $table->double('ep_value_fixed')->nullable();
          $table->double('ep_value_var')->nullable();
          $table->double('ep_value_tax')->nullable();
          $table->double('ep_value_transf')->nullable();
          $table->date('ep_date_transf')->nullable();
          $table->string('t_key')->nullable();
          $table->timestamp('notification_date')->nullable();
          $table->string('ep_type')->nullable();
          $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('easypay_notifications');
    }
}
