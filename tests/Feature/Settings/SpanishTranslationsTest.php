<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

beforeEach(function () {
    app()->setLocale('es');
});

it('BR-59 shows the short password error in Spanish instead of a translation key', function () {
    $errors = Validator::make(['password' => 'abc'], ['password' => ['required', Password::default()]])->errors();

    expect($errors->first('password'))->toBe('El campo contraseña debe tener al menos 8 caracteres.');
});

it('BR-59 shows the required and confirmation errors in Spanish', function () {
    $required = Validator::make(['email' => ''], ['email' => ['required']])->errors();
    $confirmed = Validator::make(['password' => 'abcdefgh', 'password_confirmation' => 'other'], ['password' => ['confirmed']])->errors();

    expect($required->first('email'))->toBe('El campo correo es obligatorio.')
        ->and($confirmed->first('password'))->toBe('La confirmación del campo contraseña no coincide.');
});

it('BR-59 translates the password broker messages to Spanish', function () {
    expect(__('passwords.token'))->toBe('El enlace para restablecer la contraseña no es válido o expiró.')
        ->and(__('passwords.reset'))->toBe('Tu contraseña fue restablecida.');
});

it('BR-59 keeps every Laravel translation line available in Spanish', function (string $file) {
    $english = require base_path("vendor/laravel/framework/src/Illuminate/Translation/lang/en/{$file}.php");
    $spanish = require lang_path("es/{$file}.php");

    $englishLines = Arr::dot($english);
    $spanishLines = Arr::dot($spanish);

    // custom.* and attributes.* are project-specific placeholders, not framework lines.
    $required = array_filter(
        array_keys($englishLines),
        fn (string $key): bool => ! in_array(explode('.', $key)[0], ['custom', 'attributes'], true),
    );

    foreach ($required as $key) {
        expect($spanishLines)->toHaveKey($key)
            ->and($spanishLines[$key])->not->toBe($englishLines[$key]);
    }
})->with(['validation', 'auth', 'passwords']);
