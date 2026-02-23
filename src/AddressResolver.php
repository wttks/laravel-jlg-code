<?php

declare(strict_types=1);

namespace Wttks\JlgCode;

use Wttks\JlgCode\Enums\Prefecture;
use Wttks\JlgCode\Models\Municipality;

/**
 * 住所文字列から市区町村コード・都道府県を解決するユーティリティ
 *
 * 5段フォールバック戦略:
 *   1. 直接検索（例: "旭川市"）
 *   2. 政令指定都市の区: "XX市YY区" → "YY区"
 *   3. 郡部の町村: "XX郡YY町" → "YY町"
 *   4. 市名再抽出: "大和郡山市下三橋町" → "大和郡山市"
 *   5. 末尾 "市" 付加: "四日市" → "四日市市"
 */
final class AddressResolver
{
    /**
     * 住所 → 市区町村コード（6桁文字列）
     *
     * 解決できない場合は null を返す
     */
    public static function resolveCode(string $address): ?string
    {
        return self::resolve($address)['code'];
    }

    /**
     * 住所 → Prefecture enum
     *
     * 解決できない場合は null を返す
     */
    public static function resolvePrefecture(string $address): ?Prefecture
    {
        return self::resolve($address)['prefecture'];
    }

    /**
     * 住所 → ['prefecture' => Prefecture|null, 'code' => string|null]
     *
     * @return array{prefecture: Prefecture|null, code: string|null}
     */
    public static function resolve(string $address): array
    {
        $parsed = AddressParser::parse($address);

        if ($parsed['prefecture'] === null || $parsed['municipality'] === null) {
            return ['prefecture' => $parsed['prefecture'], 'code' => null];
        }

        $prefecture = $parsed['prefecture'];
        $prefCode = $prefecture->code();
        $municipalityName = $parsed['municipality'];

        $code = self::findCode($prefCode, $municipalityName);

        return ['prefecture' => $prefecture, 'code' => $code];
    }

    /**
     * 都道府県コードと市区町村名からコードを5段フォールバックで検索する
     */
    private static function findCode(string $prefCode, string $municipalityName): ?string
    {
        // 1. 直接検索（例: "旭川市", "越谷市"）
        $municipality = Municipality::query()
            ->where('prefecture_code', $prefCode)
            ->where('name', $municipalityName)
            ->first();

        if ($municipality !== null) {
            return $municipality->getRawOriginal('code');
        }

        // 2. 政令指定都市の区: "XX市YY区" → "YY区" でフォールバック検索
        //    例: "札幌市東区" → "東区"
        if (preg_match('/^.+市(.+区)$/u', $municipalityName, $matches)) {
            $municipality = Municipality::query()
                ->where('prefecture_code', $prefCode)
                ->where('name', $matches[1])
                ->first();

            if ($municipality !== null) {
                return $municipality->getRawOriginal('code');
            }
        }

        // 3. 郡部の町村: "XX郡YY町/YY村" → "YY町/YY村" でフォールバック検索
        //    例: "宮城郡利府町" → "利府町"
        if (preg_match('/^.+郡(.+(?:町|村))$/u', $municipalityName, $matches)) {
            $municipality = Municipality::query()
                ->where('prefecture_code', $prefCode)
                ->where('name', $matches[1])
                ->first();

            if ($municipality !== null) {
                return $municipality->getRawOriginal('code');
            }
        }

        // 4. 市名の再抽出: パーサーが "XX郡YY市下町" のように誤抽出した場合
        //    例: "大和郡山市下三橋町" → "大和郡山市"
        if (preg_match('/^(.+市)/u', $municipalityName, $matches) && $matches[1] !== $municipalityName) {
            $municipality = Municipality::query()
                ->where('prefecture_code', $prefCode)
                ->where('name', $matches[1])
                ->first();

            if ($municipality !== null) {
                return $municipality->getRawOriginal('code');
            }
        }

        // 5. 市名末尾に "市" を付加: "四日市" → "四日市市" のようなケース
        $municipality = Municipality::query()
            ->where('prefecture_code', $prefCode)
            ->where('name', $municipalityName.'市')
            ->first();

        if ($municipality !== null) {
            return $municipality->getRawOriginal('code');
        }

        return null;
    }
}
