<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class AdminPaginationTest extends TestCase
{
    public function test_admin_pagination_partial_renders_laravel_elements(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 20), 120, 20, 3, ['path' => '/admin/jobs']);
        $html = (string) $paginator->links('admin.partials.pagination');
        $this->assertStringContainsString('<span class="active"><span>3</span></span>', $html);
        $this->assertStringContainsString('/admin/jobs?page=2', $html);
        $this->assertStringContainsString('/admin/jobs?page=4', $html);
    }
}
