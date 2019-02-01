<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeForeignNameOnReferences extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('mb_references', function (Blueprint $table) {
          $table->renameColumn('foreign_type','foreign_type');
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
          $table->renameColumn('foreign_type','foreign_type');
      });
    }
}
