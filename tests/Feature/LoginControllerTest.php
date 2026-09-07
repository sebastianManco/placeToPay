<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    /**
     * Test the login page can be rendered successfully.
     */
    public function test_login_page_can_be_rendered(): void
    {
        $response = $this->get('/home/login');

        $response->assertStatus(200);
        $response->assertSee('Login', false);
    }
}
