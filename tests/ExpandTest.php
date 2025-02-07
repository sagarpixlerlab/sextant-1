<?php

class ExpandTest extends TestCase
{

    /** @test * */
    function expand_work_for_eloquent_model()
    {
        $request = $this->setRequest([
            'filter'     => '{"id":1}',
            'expand'     => 'posts,viewed',
            'sortExpand' => 'posts.id',
        ]);

        /** @var User $user */
        $user = User::withSextant($request)->first();

        $this->assertNotEquals(
            null,
            $user
        );

        $this->assertTrue(
            $user->relationLoaded('posts')
        );

        $this->assertEquals(
            'First post',
            $user->posts->first()->title
        );

        $this->assertTrue($user->relationLoaded('viewed'));
    }

    /** @test * */
    function expand_restrictions_works_as_expected()
    {
        $request = $this->setRequest([
            'filter'     => '{"id":1,"posts.id":1}',
            'expand'     => 'posts,viewed',
            'sortExpand' => 'posts.id',
        ]);

        $user = User::withSextant($request, [], [ 'except' => [ 'expand' => ['posts'] ] ])->first();

        $this->assertTrue($user->relationLoaded('viewed'));

        $this->assertFalse($user->relationLoaded('posts'));

        $user = User::withSextant($request, [], [ 'only' => [ 'expand' => ['viewed'] ] ])->first();

        $this->assertTrue($user->relationLoaded('viewed'));

        $this->assertFalse($user->relationLoaded('posts'));
    }
}
