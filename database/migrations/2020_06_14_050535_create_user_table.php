<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

class CreateUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->integer('identification', 13)->unsigned();
            $table->string('name', 51);
            $table->string('last_Name', 50);
            $table->string('email', 100);
            $table->string('phone', 20);
            $table->string('Direction',100);
            //$table->string('type_document', 4);
            $table->string('user_name', 50);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 100);
            $table->string('confirm_password', 100);
            $table->rememberToken();
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
        Schema::dropIfExists('users');
    }
}
