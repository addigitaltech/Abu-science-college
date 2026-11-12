<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    public function test_the_public_homepage_renders_from_seeded_school_records(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Abu Science College')
            ->assertSee('READERS ARE LEADERS')
            ->assertSee('Building future leaders through knowledge, science and technology.');
    }
}
