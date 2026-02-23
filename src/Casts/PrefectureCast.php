<?php

declare(strict_types=1);

namespace Wttks\JlgCode\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Wttks\JlgCode\Enums\Prefecture;

/**
 * Prefecture Enum 用 Eloquent Cast
 *
 * DB には JLGコード互換の2桁文字列（'01'〜'47'）で保存し、
 * PHP 側では int backed の Prefecture Enum として扱う。
 *
 * @implements CastsAttributes<Prefecture, string>
 */
class PrefectureCast implements CastsAttributes
{
    /**
     * DB値（'01'〜'47'）→ Prefecture Enum
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Prefecture
    {
        if ($value === null) {
            return null;
        }

        return Prefecture::from((int) $value);
    }

    /**
     * Prefecture Enum → DB値（'01'〜'47'）
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Prefecture) {
            return $value->code();
        }

        if (is_int($value)) {
            return Prefecture::from($value)->code();
        }

        if (is_string($value)) {
            return Prefecture::from((int) $value)->code();
        }

        throw new InvalidArgumentException("PrefectureCast: '{$key}' には Prefecture Enum または都道府県コードを渡してください");
    }
}
