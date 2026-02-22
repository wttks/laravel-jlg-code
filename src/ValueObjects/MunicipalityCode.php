<?php

declare(strict_types=1);

namespace Wttks\JlgCode\ValueObjects;

use InvalidArgumentException;
use Stringable;
use Wttks\JlgCode\Enums\Prefecture;

/**
 * 全国地方公共団体コード（6桁）
 *
 * 構成: 都道府県コード(2桁) + 市区町村コード(3桁) + チェックディジット(1桁)
 */
final readonly class MunicipalityCode implements Stringable
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = str_pad($value, 6, '0', STR_PAD_LEFT);

        if (! preg_match('/\A\d{6}\z/', $normalized)) {
            throw new InvalidArgumentException("団体コードは6桁の数字である必要があります: {$value}");
        }

        if (! self::isValidCheckDigit($normalized)) {
            throw new InvalidArgumentException("団体コードのチェックディジットが不正です: {$value}");
        }

        $prefCode = substr($normalized, 0, 2);
        if (Prefecture::tryFrom($prefCode) === null) {
            throw new InvalidArgumentException("都道府県コードが不正です: {$prefCode}");
        }

        $this->value = $normalized;
    }

    /**
     * チェックディジット検証（モジュラス11）
     *
     * 上5桁に対して重み 6,5,4,3,2 で加重和を取り、
     * 11で割った余りから検査数字を算出する
     */
    private static function isValidCheckDigit(string $code): bool
    {
        $weights = [6, 5, 4, 3, 2];
        $sum = 0;

        for ($i = 0; $i < 5; $i++) {
            $sum += (int) $code[$i] * $weights[$i];
        }

        $remainder = $sum % 11;
        $expected = match (true) {
            $remainder === 0 => 1,
            $remainder === 1 => 0,
            default => 11 - $remainder,
        };

        return (int) $code[5] === $expected;
    }

    /**
     * 都道府県コード部分（上2桁）
     */
    public function prefectureCode(): string
    {
        return substr($this->value, 0, 2);
    }

    /**
     * 都道府県 Enum
     */
    public function prefecture(): Prefecture
    {
        return Prefecture::from($this->prefectureCode());
    }

    /**
     * 市区町村コード部分（3〜5桁目）
     */
    public function localCode(): string
    {
        return substr($this->value, 2, 3);
    }

    /**
     * チェックディジット（6桁目）
     */
    public function checkDigit(): string
    {
        return $this->value[5];
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
