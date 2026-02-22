<?php

declare(strict_types=1);

namespace Wttks\JlgCode\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Wttks\JlgCode\ValueObjects\MunicipalityCode;

/**
 * MunicipalityCode ValueObject 用 Eloquent Cast
 *
 * @implements CastsAttributes<MunicipalityCode, string>
 */
class MunicipalityCodeCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?MunicipalityCode
    {
        if ($value === null) {
            return null;
        }

        return new MunicipalityCode($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof MunicipalityCode) {
            return $value->value;
        }

        if (is_string($value)) {
            return (new MunicipalityCode($value))->value;
        }

        throw new InvalidArgumentException("MunicipalityCodeCast: '{$key}' には MunicipalityCode または文字列を渡してください");
    }
}
