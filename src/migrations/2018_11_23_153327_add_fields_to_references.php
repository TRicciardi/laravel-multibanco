<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFieldsToReferences extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('mb_references', function (Blueprint $table) {
          $table->decimal('paid_value',20,2)->default(0)->after('expiration_date');
          $table->datetime('paid_date')->nullable()->after('paid_value');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('mb_references', function (Blueprint $table) {
            $table->dropColumn('paid_value');
            $table->dropColumn('paid_date');
        });
    }
}
