<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MbreferencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mb_references', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('state')->default(0)->index();
            $table->integer('foreign_id')->nullable()->index();
            $table->integer('reference_number')->nullable()->index();
            $table->string('entity')->nullable();
            $table->string('reference')->nullable();
            $table->decimal('value',20,2)->nullable();
            $table->decimal('paid_value',20,2)->nullable();
            $table->datetime('paid_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->longText('log')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mb_references');
    }
}
