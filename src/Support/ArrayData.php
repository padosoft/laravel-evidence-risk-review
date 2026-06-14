<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Support;

use InvalidArgumentException;

final class ArrayData
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("Expected non-empty string at [{$key}].");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function nullableString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException("Expected string or null at [{$key}].");
        }

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function map(array $payload, string $key): array
    {
        $value = array_key_exists($key, $payload) ? $payload[$key] : [];

        if (! is_array($value)) {
            throw new InvalidArgumentException("Expected object map at [{$key}].");
        }

        return self::stringKeyedMap($value, $key);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public static function stringList(array $payload, string $key): array
    {
        $value = array_key_exists($key, $payload) ? $payload[$key] : [];

        if (! is_array($value)) {
            throw new InvalidArgumentException("Expected list of strings at [{$key}].");
        }

        if (! array_is_list($value)) {
            throw new InvalidArgumentException("Expected list of strings at [{$key}], associative array given.");
        }

        $strings = [];

        foreach ($value as $item) {
            if (! is_string($item) || $item === '') {
                throw new InvalidArgumentException("Expected list of non-empty strings at [{$key}].");
            }

            $strings[] = $item;
        }

        return $strings;
    }

    /**
     * @return array<string, mixed>
     */
    public static function requireMap(mixed $value, string $context): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException("Expected object map at [{$context}].");
        }

        return self::stringKeyedMap($value, $context);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<string, mixed>
     */
    private static function stringKeyedMap(array $value, string $context): array
    {
        foreach (array_keys($value) as $key) {
            if (! is_string($key)) {
                throw new InvalidArgumentException("Expected string-keyed object map at [{$context}].");
            }
        }

        /** @var array<string, mixed> $value */
        return $value;
    }
}
