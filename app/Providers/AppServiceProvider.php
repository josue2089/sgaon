<?php

namespace App\Providers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Evita fechas con el año mal escrito (p. ej. 0026 en vez de 2026). Año mínimo por defecto 2020; sane_date:1900 para nacimientos.
        Validator::extend('sane_date', function (string $attribute, mixed $value, array $parameters): bool {
            if (blank($value)) {
                return true;
            }
            if (! preg_match('/^(\d{1,4})-\d{1,2}-\d{1,2}/', trim((string) $value), $matches)) {
                return true;
            }
            $year = (int) $matches[1];

            return $year >= (int) ($parameters[0] ?? 2020) && $year <= 2100;
        });
        Validator::replacer('sane_date', fn (string $message, string $attribute, string $rule, array $parameters) => 'Revisa el año de la fecha: debe estar entre '.($parameters[0] ?? 2020).' y 2100.');
    }
}
