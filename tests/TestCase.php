<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function setUp():void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../migrations');
        \DB::enableQueryLog();
    }

    public function tearDown():void
    {
        parent::tearDown();
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Setup config.
    }

    /**
     * Add package service providers.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            \Amondar\Sextant\SextantServiceProvider::class,
        ];
    }

    /**
     * Set new data to request.
     *
     * @param array $arr
     * @return array|\Illuminate\Http\Request|string
     */
    public function setRequest(array $arr)
    {
        $request = new \Illuminate\Http\Request;
        $request->replace($arr);
        return $request;
    }
}

class User extends \Illuminate\Database\Eloquent\Model
{
    use \Amondar\Sextant\Models\HasSextantOperations;

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function viewed()
    {
        return $this->belongsToMany(Post::class, 'post_viewers');
    }

    public function scopeTestRelationScopes($query, $name)
    {
        $query->where('full_name', 'like', "%$name%");
    }

    public function extraFields()
    {
        return ['posts','viewed'];
    }

    public function extraScopes()
    {
        return ["testRelationScopes"];
    }
}

class Post extends \Illuminate\Database\Eloquent\Model
{
    use \Amondar\Sextant\Models\HasSextantOperations;

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function viewers()
    {
        return $this->belongsToMany(User::class, 'post_viewers');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'post_id', 'id');
    }

    public function scopeTestTitleSimpleSearch($query)
    {
        $query->where('title', 'like', '%First%');
    }

    public function extraFields()
    {
        return ['owner','viewers', 'owner.posts', 'comments'];
    }

    public function extraScopes()
    {
        return ['testTitleSimpleSearch'];
    }
}

class Comment extends \Illuminate\Database\Eloquent\Model
{
    use \Amondar\Sextant\Models\HasSextantOperations;

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function extraFields()
    {
        return ['owner','post', 'post.owner'];
    }
}