<?php

namespace App\Funnel;

class Configuration
{
    public static function supported(array $config): bool
    {
        return ($config['schema_version'] ?? null) === 3;
    }

    public static function screen(array $config, string $id): ?array
    {
        return collect($config['steps'])->firstWhere('id', $id);
    }

    public static function entry(array $config): string
    {
        return $config['entry'];
    }

    public static function next(array $config, array $screen, array $answer): string
    {
        return $screen['type'] === 'upsell' && $answer['decision'] === 'decline'
            ? $screen['decline_next'] : $screen['next'];
    }

    public static function validate(array $config): void
    {
        V3Configuration::validate($config);
    }
}
