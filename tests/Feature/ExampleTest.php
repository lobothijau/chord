<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_song_list(): void
    {
        $this->get('/')->assertRedirect(route('songs.index'));

        $this->get(route('songs.index'))->assertOk();
    }
}
