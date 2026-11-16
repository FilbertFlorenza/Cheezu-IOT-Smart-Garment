<?php

use Carbon\CarbonInterface;

if (! function_exists('display_timezone')) {
    function display_timezone(): string
    {
        return config('inspection.display_timezone', 'Asia/Manila');
    }
}

if (! function_exists('display_datetime')) {
    /**
     * Render a UTC timestamp in the configured display timezone.
     */
    function display_datetime(?CarbonInterface $value, string $format = 'M j, Y H:i'): string
    {
        if ($value === null) {
            return '—';
        }

        return $value->copy()->timezone(display_timezone())->format($format);
    }
}

if (! function_exists('display_date')) {
    function display_date(?CarbonInterface $value, string $format = 'M j, Y'): string
    {
        return display_datetime($value, $format);
    }
}

if (! function_exists('metric_label')) {
    function metric_label(string $metricName, ?string $source = null): string
    {
        $configured = $source
            ? config("forecast.metrics.{$source}.{$metricName}")
            : null;

        return $configured['label'] ?? str($metricName)->replace('_', ' ')->title()->toString();
    }
}

if (! function_exists('metric_color')) {
    function metric_color(string $metricName, ?string $source = null, string $fallback = '#64748b'): string
    {
        return config("forecast.metrics.{$source}.{$metricName}.color", $fallback);
    }
}
