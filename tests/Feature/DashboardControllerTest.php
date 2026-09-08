<?php

namespace Tests\Feature;

use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    /**
     * Test the dashboard page can be rendered with default title.
     */
    public function test_dashboard_can_be_rendered_with_default_title(): void
    {
        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('valor default');
    }

    /**
     * Test the dashboard page can be rendered with custom title.
     */
    public function test_dashboard_can_be_rendered_with_custom_title(): void
    {
        $response = $this->get('/dashboard?title=AdminPanel');

        $response->assertStatus(200);
        $response->assertSee('AdminPanel');
    }
}
