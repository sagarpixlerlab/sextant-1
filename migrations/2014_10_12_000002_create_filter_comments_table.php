<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFilterCommentsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->index();
            $table->integer('post_id')->unsigned()->index();
            $table->string('text');
            $table->nullableTimestamps();
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
        Schema::dropIfExists('comments');
    }

    /**
     * Seed start user.
     */
    protected function seed()
    {
        \DB::table('comments')->insert([
            [
                'user_id' => 1,
                'post_id' => 2,
                'text' => 'Good post test'
            ],
            [
                'user_id' => 2,
                'post_id' => 3,
                'text' => 'Bad post tost'
            ],
        ]);
    }
}
