<?php

use Workbench\Database\Factories\UserFactory;

it('signs in the workbench user on any panel page', function (): void {
    $user = UserFactory::new()->create(['email' => 'test@example.com']);

    $this->withoutVite()->get('/admin/taxonomies')->assertOk();

    $this->assertAuthenticatedAs($user);
});

it('keeps the login form reachable for guests', function (): void {
    UserFactory::new()->create(['email' => 'test@example.com']);

    $this->withoutVite()->get('/admin/login')->assertOk();

    $this->assertGuest();
});

it('falls back to the login form when the workbench user does not exist', function (): void {
    $this->withoutVite()->get('/admin')->assertRedirect('/admin/login');

    $this->assertGuest();
});
