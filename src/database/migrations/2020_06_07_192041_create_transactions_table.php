<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string("access_code")->unique();
            $table->string("reference")->unique();
            $table->bigInteger("user_id");
            $table->bigInteger("paystack_id")->unique()->nullable();
            $table->string("plan_code")->nullable();
            $table->decimal("amount", 8, 2)->nullable();
            $table->string("status")->default("pending");
            $table->string("gateway_response")->nullable();
            $table->string("currency")->nullable();
            $table->integer("fees")->nullable();
            $table->timestamp("paid_at")->nullable();
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
        Schema::dropIfExists('transactions');
    }
}
