<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_front_page_sends_visitors_to_the_sign_in_screen(): void
    {
        // There is no public marketing page: the application starts at login.
        $this->get('/')->assertRedirect('/login');
    }
}
