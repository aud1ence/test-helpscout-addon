<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRockposSupportTicketTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rockpos_support_ticket', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_addon_thread');
            $table->bigInteger('id_helpscout_convo');
            $table->date('create_time_at');
            $table->unique(['id_addon_thread','id_helpscout_convo']);
            $table->timestamps();
            $table->unique(['id_helpscout_convo','id_addon_thread']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rockpos_support_ticket');
    }
}
