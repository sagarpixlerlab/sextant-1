<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFilterPostsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->index();
            $table->string('title');
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
        Schema::dropIfExists('posts');
    }

    /**
     * Seed start user.
     */
    protected function seed()
    {
        \DB::table('posts')->insert([
            [
                'user_id'    => 1,
                'title'      => 'First post',
                'created_at' => \Carbon\Carbon::createFromDate(2017, 1, 10)->setTime(10, 55),
                'updated_at' => \Carbon\Carbon::createFromDate(2017, 1, 10)->setTime(10, 55),
            ],
            [
                'user_id'    => 1,
                'title'      => 'Second post',
                'created_at' => \Carbon\Carbon::createFromDate(2017, 1, 10)->setTime(23, 55),
                'updated_at' => \Carbon\Carbon::createFromDate(2017, 1, 10)->setTime(23, 55),
            ],
            [
                'user_id'    => 3,
                'title'      => 'Third post',
                'created_at' => \Carbon\Carbon::createFromDate(2017, 1, 30)->setTime(00, 55),
                'updated_at' => \Carbon\Carbon::createFromDate(2017, 1, 30)->setTime(00, 55),
            ],
        ]);
    }
}
