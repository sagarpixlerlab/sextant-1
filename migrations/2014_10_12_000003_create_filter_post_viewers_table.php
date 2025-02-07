<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFilterPostViewersTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('post_viewers', function (Blueprint $table) {
            $table->integer('user_id')->undigned()->index();
            $table->integer('post_id')->unsigned()->index();
        });

        $this->seed();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('post_viewers');
    }

    /**
     * Seed start user.
     */
    protected function seed()
    {
        \DB::table('post_viewers')->insert([
            [
                'user_id' => 1,
                'post_id' => 1
            ],
            [
                'user_id' => 1,
                'post_id' => 2
            ],
            [
                'user_id' => 1,
                'post_id' => 3
            ],
            [
                'user_id' => 2,
                'post_id' => 1
            ],
            [
                'user_id' => 2,
                'post_id' => 3
            ],
            [
                'user_id' => 3,
                'post_id' => 2
            ],
        ]);
    }
}
