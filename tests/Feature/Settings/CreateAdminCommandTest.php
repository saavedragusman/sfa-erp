<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;

it('BR-01 app:create-admin creates an admin with a hashed password and an audit entry', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->artisan('app:create-admin')
        ->expectsQuestion('Nombre', 'Ana Admin')
        ->expectsQuestion('Correo', 'ana@example.com')
        ->expectsQuestion('Contraseña', 'correct-horse-battery')
        ->expectsQuestion('Confirmar contraseña', 'correct-horse-battery')
        ->assertSuccessful();

    $admin = User::where('email', 'ana@example.com')->firstOrFail();

    expect($admin->hasPermissionTo('staff.manage_admins'))->toBeTrue()
        ->and($admin->roles->pluck('name')->all())->toBe([RoleName::Admin->value])
        ->and(Hash::check('correct-horse-battery', $admin->password))->toBeTrue();

    $log = AuditLog::firstOrFail();

    expect($log->action)->toBe('staff.created')
        ->and($log->actor_id)->toBeNull()
        ->and($log->entity_id)->toBe($admin->id)
        ->and(json_encode($log->after))->not->toContain('correct-horse-battery');
});

it('BR-01 app:create-admin refuses to run before roles are seeded', function () {
    $this->artisan('app:create-admin')->assertFailed();

    expect(User::count())->toBe(0);
});

it('BR-01 app:create-admin rejects an email that already exists', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    User::factory()->create(['email' => 'dup@example.com']);

    $this->artisan('app:create-admin')
        ->expectsQuestion('Nombre', 'Dup')
        ->expectsQuestion('Correo', 'dup@example.com')
        ->expectsQuestion('Contraseña', 'correct-horse-battery')
        ->expectsQuestion('Confirmar contraseña', 'correct-horse-battery')
        ->assertFailed();

    expect(User::count())->toBe(1)->and(AuditLog::count())->toBe(0);
});

it('BR-01 app:create-admin rejects a password confirmation mismatch', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->artisan('app:create-admin')
        ->expectsQuestion('Nombre', 'Ana')
        ->expectsQuestion('Correo', 'ana@example.com')
        ->expectsQuestion('Contraseña', 'correct-horse-battery')
        ->expectsQuestion('Confirmar contraseña', 'different-password')
        ->assertFailed();

    expect(User::count())->toBe(0);
});
