<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Utility;

/**
 * Narrows mixed values of settings, request arguments, database rows and
 * global configuration to scalar types. Values, which can not be converted
 * (arrays, objects, resources), result in the empty value of the type.
 */
final class TypeCaster
{
    public static function toString(mixed $value): string
    {
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string)$value;
        }

        return '';
    }

    public static function toInt(mixed $value): int
    {
        return is_scalar($value) ? (int)$value : 0;
    }

    /**
     * @return array<mixed>
     */
    public static function toArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function toStringKeyedArray(mixed $value): array
    {
        $result = [];
        foreach (self::toArray($value) as $key => $item) {
            $result[(string)$key] = $item;
        }

        return $result;
    }
}
