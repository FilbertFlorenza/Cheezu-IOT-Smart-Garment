<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A domain-level API failure with a stable machine-readable error code.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function duplicateInspection(string $inspectionId): self
    {
        return new self(
            409,
            'DUPLICATE_INSPECTION',
            'An inspection with this inspection_id has already been recorded.',
            ['inspection_id' => $inspectionId],
        );
    }

    public static function duplicateForecast(string $source, string $externalId): self
    {
        return new self(
            409,
            'DUPLICATE_FORECAST',
            'A forecast with this forecast_id has already been recorded for this source.',
            ['source' => $source, 'forecast_id' => $externalId],
        );
    }

    public static function unknownDevice(string $deviceCode): self
    {
        return new self(
            422,
            'UNKNOWN_DEVICE',
            'The device is not registered. Register the device before submitting inspections.',
            ['device_code' => $deviceCode],
        );
    }

    public static function contractMismatch(string $message, array $details = []): self
    {
        return new self(422, 'CONTRACT_MISMATCH', $message, $details);
    }
}
