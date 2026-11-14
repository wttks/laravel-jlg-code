<?php

declare(strict_types=1);

namespace Wttks\JlgCode;

use Wttks\JlgCode\Enums\AddressResolveStatus;
use Wttks\JlgCode\Enums\Prefecture;
use Wttks\JlgCode\Models\Municipality;
use Wttks\JlgCode\ValueObjects\ResolvedAddress;

/**
 * 住所文字列から市区町村コード・都道府県を解決するユーティリティ
 *
 * 解決の手順:
 *   1. 都道府県名を取る（無くてもよい）
 *   2. 市区町村の索引で「住所の先頭と一番長く一致する表記」を探す
 *      表記＝市区町村名／郡＋町村名（遠賀郡芦屋町）／政令市＋区名（札幌市中央区）／住所での表記（三宅島三宅村・檮原町）
 *      県名ありはその県の中だけ、県名なしは全国から探す。一致した表記の候補が1つなら解決、複数なら Ambiguous
 *   3. 索引で見つからず県名があるときは、従来の5段フォールバック（正規表現で切り出して検索）を試す
 *
 * 正規表現で先に切り出さないので「新宿区市谷…」「杵島郡大町町…」も正しく読める。
 * 索引はプロセス内に持ち、呼び出しごとに「件数・最終更新日時」を1回問い合わせて、変わっていたら作り直す
 * （テストでデータを入れ替えても古い索引で引かないため）。updated_at を変えずに行を書き換えた場合は flushIndex() を呼ぶ。
 */
final class AddressResolver
{
    /**
     * 索引 [現行/廃止 => [都道府県コード|'*' => [先頭1文字 => [表記 => list<団体コード>]]]]
     * 廃止済みは、現行で見つからないときだけ使う
     *
     * @var array{active: array<string, array<string, array<string, list<string>>>>, deprecated: array<string, array<string, array<string, list<string>>>>}|null
     */
    private static ?array $index = null;

    /**
     * 索引を作ったときのデータの目印（件数・最終更新日時）
     */
    private static ?string $signature = null;

    /**
     * 団体コード → 都道府県コード
     *
     * @var array<string, string>
     */
    private static array $prefectureOfCode = [];

    /**
     * 住所 → 市区町村コード（6桁文字列）
     *
     * 解決できない場合（候補が複数の場合を含む）は null を返す
     */
    public static function resolveCode(string $address): ?string
    {
        return self::resolveDetailed($address)->code;
    }

    /**
     * 住所 → Prefecture enum
     *
     * 解決できない場合は null を返す
     */
    public static function resolvePrefecture(string $address): ?Prefecture
    {
        return self::resolveDetailed($address)->prefecture;
    }

    /**
     * 住所 → ['prefecture' => Prefecture|null, 'code' => string|null]
     *
     * @return array{prefecture: Prefecture|null, code: string|null}
     */
    public static function resolve(string $address): array
    {
        $result = self::resolveDetailed($address);

        return ['prefecture' => $result->prefecture, 'code' => $result->code];
    }

    /**
     * 住所 → 解決結果（段階・候補・市区町村として読んだ部分を含む）
     */
    public static function resolveDetailed(string $address): ResolvedAddress
    {
        $text = self::normalize($address);
        $prefecture = AddressParser::extractPrefecture($text);
        $remaining = $prefecture !== null ? mb_substr($text, mb_strlen($prefecture->label())) : $text;

        $index = self::index();
        $scope = $prefecture !== null ? $prefecture->code() : '*';

        $match = self::longestMatch($index['active'][$scope] ?? [], $remaining)
            ?? self::longestMatch($index['deprecated'][$scope] ?? [], $remaining);

        if ($match !== null) {
            return self::fromMatch($prefecture, $match['key'], $match['codes'], mb_substr($remaining, mb_strlen($match['key'])));
        }

        if ($prefecture !== null) {
            return self::resolveLegacy($prefecture, $text)
                ?? new ResolvedAddress(AddressResolveStatus::PrefectureOnly, $prefecture, null, [], null, $remaining);
        }

        return new ResolvedAddress(AddressResolveStatus::Unresolved, null, null, [], null, $remaining);
    }

    /**
     * 索引を捨てる（市区町村データを入れ替えた後・テストで使う）
     */
    public static function flushIndex(): void
    {
        self::$index = null;
        self::$signature = null;
        self::$prefectureOfCode = [];
    }

    /**
     * @param  list<string>  $codes
     */
    private static function fromMatch(?Prefecture $prefecture, string $key, array $codes, string $rest): ResolvedAddress
    {
        if ($prefecture === null) {
            // 県名なし：候補がすべて同じ県なら県は分かる
            $prefectureCodes = array_values(array_unique(array_map(fn (string $code) => self::$prefectureOfCode[$code], $codes)));
            $prefecture = count($prefectureCodes) === 1 ? Prefecture::from((int) $prefectureCodes[0]) : null;
        }

        if (count($codes) === 1) {
            return new ResolvedAddress(AddressResolveStatus::Resolved, $prefecture, $codes[0], [], $key, $rest);
        }

        return new ResolvedAddress(AddressResolveStatus::Ambiguous, $prefecture, null, $codes, $key, $rest);
    }

    /**
     * 先頭1文字で絞った表記の中から、テキストの先頭と一番長く一致するものを返す
     *
     * @param  array<string, array<string, list<string>>>  $buckets
     * @return array{key: string, codes: list<string>}|null
     */
    private static function longestMatch(array $buckets, string $text): ?array
    {
        if ($text === '') {
            return null;
        }

        $best = null;
        foreach ($buckets[mb_substr($text, 0, 1)] ?? [] as $key => $codes) {
            $key = (string) $key;
            if (str_starts_with($text, $key) && ($best === null || strlen($key) > strlen($best['key']))) {
                $best = ['key' => $key, 'codes' => $codes];
            }
        }

        return $best;
    }

    /**
     * 照合用に住所を整える（空白を除き、先頭の郵便番号を外し、ヶ をケにそろえる）
     *
     * ヶ→ケ は1文字→1文字なので、残りの位置はずれない
     */
    private static function normalize(string $text): string
    {
        $text = preg_replace('/[\s　]+/u', '', $text) ?? $text;

        // 先頭の郵便番号（〒・区切りは任意・全角可）。7桁の後にさらに数字が続くものは郵便番号とみなさない
        $text = preg_replace('/^〒?[0-9０-９]{3}[-－ー‐−]?[0-9０-９]{4}(?![0-9０-９])/u', '', $text) ?? $text;

        return str_replace('ヶ', 'ケ', $text);
    }

    /**
     * @return array{active: array<string, array<string, array<string, list<string>>>>, deprecated: array<string, array<string, array<string, list<string>>>>}
     */
    private static function index(): array
    {
        $stat = Municipality::query()->toBase()->selectRaw('COUNT(*) AS cnt, MAX(updated_at) AS last_updated')->first();
        $signature = ($stat->cnt ?? 0).'|'.($stat->last_updated ?? '');
        if (self::$index !== null && self::$signature === $signature) {
            return self::$index;
        }
        self::$prefectureOfCode = [];

        $rows = Municipality::query()
            ->toBase()
            ->get(['code', 'prefecture_code', 'name', 'district_name', 'parent_code', 'address_name', 'deprecated_at']);

        $nameOfCode = [];
        foreach ($rows as $row) {
            $nameOfCode[(string) $row->code] = (string) $row->name;
            self::$prefectureOfCode[(string) $row->code] = (string) $row->prefecture_code;
        }

        $index = ['active' => [], 'deprecated' => []];
        foreach ($rows as $row) {
            $group = $row->deprecated_at === null ? 'active' : 'deprecated';
            $code = (string) $row->code;
            foreach (self::keysOf($row, $nameOfCode) as $key) {
                $first = mb_substr($key, 0, 1);
                foreach ([(string) $row->prefecture_code, '*'] as $scope) {
                    $codes = $index[$group][$scope][$first][$key] ?? [];
                    if (! in_array($code, $codes, true)) {
                        $codes[] = $code;
                    }
                    $index[$group][$scope][$first][$key] = $codes;
                }
            }
        }

        self::$signature = $signature;

        return self::$index = $index;
    }

    /**
     * 1つの市区町村を住所の中で表しうる表記の一覧
     *
     * @param  array<string, string>  $nameOfCode
     * @return list<string>
     */
    private static function keysOf(object $row, array $nameOfCode): array
    {
        $name = (string) $row->name;
        $district = (string) ($row->district_name ?? '');
        $addressName = (string) ($row->address_name ?? '');
        $keys = [$name];

        if ($district !== '') {
            $keys[] = $district.$name;
        }
        if ($row->parent_code !== null && isset($nameOfCode[(string) $row->parent_code])) {
            $keys[] = $nameOfCode[(string) $row->parent_code].$name;
        }
        if ($addressName !== '') {
            $keys[] = $addressName;
            // 「高岡郡檮原町」→「檮原町」（郡を省いた書き方）
            if ($district !== '' && str_starts_with($addressName, $district)) {
                $keys[] = substr($addressName, strlen($district));
            }
        }

        $keys = array_map(self::normalize(...), $keys);

        return array_values(array_unique(array_filter($keys, fn (string $key) => $key !== '')));
    }

    /**
     * 従来の5段フォールバック（索引で見つからなかったときの最後の手段）
     */
    private static function resolveLegacy(Prefecture $prefecture, string $text): ?ResolvedAddress
    {
        $parsed = AddressParser::parse($text);
        if ($parsed['municipality'] === null) {
            return null;
        }

        $code = self::findCode($prefecture->code(), $parsed['municipality']);
        if ($code === null) {
            return null;
        }

        return new ResolvedAddress(AddressResolveStatus::Resolved, $prefecture, $code, [], $parsed['municipality'], $parsed['rest']);
    }

    /**
     * 都道府県コードと市区町村名からコードを5段フォールバックで検索する
     */
    private static function findCode(string $prefCode, string $municipalityName): ?string
    {
        // 1. 直接検索（例: "旭川市"）
        $candidates = [$municipalityName];

        // 2. 政令指定都市の区: "XX市YY区" → "YY区"（例: "札幌市東区" → "東区"）
        if (preg_match('/^.+市(.+区)$/u', $municipalityName, $matches)) {
            $candidates[] = $matches[1];
        }

        // 3. 郡部の町村: "XX郡YY町/YY村" → "YY町/YY村"（例: "宮城郡利府町" → "利府町"）
        if (preg_match('/^.+郡(.+(?:町|村))$/u', $municipalityName, $matches)) {
            $candidates[] = $matches[1];
        }

        // 4. 市名の再抽出: パーサーが "XX郡YY市下町" のように誤抽出した場合（例: "大和郡山市下三橋町" → "大和郡山市"）
        if (preg_match('/^(.+市)/u', $municipalityName, $matches) && $matches[1] !== $municipalityName) {
            $candidates[] = $matches[1];
        }

        // 5. 市名末尾に "市" を付加: "四日市" → "四日市市"
        $candidates[] = $municipalityName.'市';

        foreach ($candidates as $candidate) {
            $municipality = Municipality::query()
                ->where('prefecture_code', $prefCode)
                ->where('name', $candidate)
                ->first();

            if ($municipality !== null) {
                return $municipality->getRawOriginal('code');
            }
        }

        return null;
    }
}
