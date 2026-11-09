<?php

declare(strict_types=1);

it('only asks for permissions, never for roles or a Gate::before bypass', function () {
    $offenders = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root = dirname(__DIR__, 2).'/app', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $code = (string) file_get_contents($file->getPathname());

        if (preg_match('/->(hasRole|hasAnyRole|hasAllRoles|hasExactRoles)\s*\(|Gate::before\s*\(/', $code) === 1) {
            $offenders[] = str_replace(dirname($root).'/', '', $file->getPathname());
        }
    }

    expect($offenders)->toBe([]);
});
