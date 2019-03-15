<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMbNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mb_notifications', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ref_identifier')->unique();
            $table->integer('state')->index();
            $table->string('reference')->nullable();
            $table->decimal('value',20,2)->nullable();
            $table->longText('payload');
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
        Schema::dropIfExists('mb_notifications');
    }
}
