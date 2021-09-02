<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateServiceCenterStockTransferHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('service_center_stock_transfer_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('from_service_center_id');
            $table->integer('transfer_product_id');
            $table->integer('receive_service_center_id');
            $table->integer('receive_product_id');
            $table->decimal('quantity', 20, 4);
            $table->integer('created_by')->nullable();
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
        Schema::dropIfExists('service_center_stock_transfer_histories');
    }
}
