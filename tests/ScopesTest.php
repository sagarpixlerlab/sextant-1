<?php

class ScopesTest extends TestCase
{

    /** @test **/
    function is_scopes_works_as_expected()
    {
        $request = $this->setRequest([
            'scopes' => '[{"name":"testTitleSimpleSearch","parameters":[]}]'
        ]);

        $posts = Post::withSextant($request)->get();

        $this->assertEquals(
            1,
            $posts->count()
        );

        $request = $this->setRequest([
            'scopes' => '[{"name":"testTitleSimpleSearch"}]'
        ]);

        $posts = Post::withSextant($request)->get();

        $this->assertEquals(
            3,
            $posts->count()
        );
    }
}
