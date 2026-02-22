<?php

declare(strict_types=1);

namespace Wttks\JlgCode;

use Wttks\JlgCode\Enums\Prefecture;

/**
 * 日本語住所から都道府県・市区町村名を抽出するユーティリティ
 */
final class AddressParser
{
    /**
     * 都道府県名 → Prefecture enum のマッピング（長い名前順にソート済み）
     *
     * @var array<string, Prefecture>|null
     */
    private static ?array $prefectureMap = null;

    /**
     * 市区町村を抽出する正規表現パターン
     *
     * マッチ優先順:
     *   1. 政令指定都市の区: XX市XX区
     *   2. 郡+町村: XX郡XX(?:町|村)
     *   3. 一般市: XX市
     *   4. 東京23区等: XX区
     *   5. 町: XX町
     *   6. 村: XX村
     */
    private const MUNICIPALITY_PATTERN = '/^(.+?市.+?区|.+?郡.+?(?:町|村)|.+?市|.+?区|.+?町|.+?村)/u';

    /**
     * 住所文字列をパースして都道府県・市区町村名・残りを返す
     *
     * @return array{prefecture: ?Prefecture, municipality: ?string, rest: string}
     */
    public static function parse(string $address): array
    {
        $address = mb_trim($address);

        $prefecture = self::extractPrefecture($address);
        $remaining = $prefecture
            ? mb_substr($address, mb_strlen($prefecture->label()))
            : $address;

        $municipality = self::extractMunicipalityName($remaining);
        $rest = $municipality
            ? mb_substr($remaining, mb_strlen($municipality))
            : $remaining;

        return [
            'prefecture' => $prefecture,
            'municipality' => $municipality,
            'rest' => $rest,
        ];
    }

    /**
     * 住所文字列から都道府県を抽出
     */
    public static function extractPrefecture(string $address): ?Prefecture
    {
        $address = mb_trim($address);

        foreach (self::getPrefectureMap() as $name => $prefecture) {
            if (str_starts_with($address, $name)) {
                return $prefecture;
            }
        }

        return null;
    }

    /**
     * 都道府県を除いた住所文字列から市区町村名を抽出
     *
     * 都道府県が含まれている場合は先に除去してから渡すこと
     */
    public static function extractMunicipalityName(string $addressWithoutPrefecture): ?string
    {
        $text = mb_trim($addressWithoutPrefecture);

        if ($text === '') {
            return null;
        }

        if (preg_match(self::MUNICIPALITY_PATTERN, $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * 都道府県名 → Prefecture enum のマッピングを取得（長い名前順）
     *
     * 「京都府」が「京都」で早期マッチしないよう、長い名前を先にマッチさせる
     *
     * @return array<string, Prefecture>
     */
    private static function getPrefectureMap(): array
    {
        if (self::$prefectureMap !== null) {
            return self::$prefectureMap;
        }

        $map = [];
        foreach (Prefecture::cases() as $prefecture) {
            $map[$prefecture->label()] = $prefecture;
        }

        // 長い都道府県名を先にマッチさせるためソート（例: 鹿児島県 > 島根県）
        uksort($map, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        self::$prefectureMap = $map;

        return self::$prefectureMap;
    }
}
