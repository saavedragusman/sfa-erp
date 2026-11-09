<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('clinic.name', 'Servicios Veterinarios San Francisco de Asís');
        $this->migrator->add('clinic.rif', null);
        $this->migrator->add('clinic.address', null);
        $this->migrator->add('clinic.phone', null);
        $this->migrator->add('clinic.logo_path', null);
        $this->migrator->add('clinic.primary_color', '#f59e0b');
        $this->migrator->add('clinic.timezone', 'America/Caracas');
    }
};
