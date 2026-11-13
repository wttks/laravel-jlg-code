<?php

declare(strict_types=1);

namespace Wttks\JlgCode;

use RuntimeException;

/**
 * 日本郵便の KEN_ALL（UTF-8 版）から、市区町村ごとの郡名・政令市の親コード・住所での表記を作る
 *
 * KEN_ALL の列: 0=団体コード(5桁・チェックディジットなし) / 6=都道府県 / 7=市区町村（例: 遠賀郡芦屋町・札幌市中央区）
 * 市区町村マスタ（municipalities.csv）の各行について、KEN_ALL の市区町村欄を次のように読む。
 *
 *   - 「○○郡」＋名前                → district_name=○○郡
 *   - 「○○市」＋名前（区）           → parent_code=同じ県の「○○市」のコード
 *   - 名前と一致                    → どちらもなし
 *   - それ以外（三宅島三宅村・檮原町等）→ 郡名だけ取れれば入れ、address_name に KEN_ALL の表記を残す
 *
 * KEN_ALL に無い行（政令市そのもの・郵便番号の無い北方領土の村）は address_name=名前。
 * 北方領土の村の郡名は KEN_ALL から取れないため SUPPLEMENTAL_DISTRICTS で補う。
 *
 * @see https://www.post.japanpost.jp/service/search/zipcode/download/utf-zip.html
 */
final class MunicipalityDistrictBuilder
{
    /**
     * KEN_ALL に無い町村の郡名（団体コード => 郡名）
     */
    private const SUPPLEMENTAL_DISTRICTS = [
        '016951' => '色丹郡', // 色丹村
        '016969' => '国後郡', // 泊村
        '016977' => '国後郡', // 留夜別村
        '016985' => '択捉郡', // 留別村
        '016993' => '紗那郡', // 紗那村
        '017001' => '蘂取郡', // 蘂取村
    ];

    /**
     * @return list<array{code: string, district_name: ?string, parent_code: ?string, address_name: string}>
     *
     * @throws RuntimeException ファイルが読めない場合
     */
    public function build(string $kenAllCsvPath, string $municipalitiesCsvPath): array
    {
        $kenAllNames = $this->readKenAllNames($kenAllCsvPath);
        $municipalities = $this->readMunicipalities($municipalitiesCsvPath);

        // 同じ県の「○○市」→ コード（政令市の区の親を引く）
        $codeByPrefAndName = [];
        foreach ($municipalities as $row) {
            $codeByPrefAndName[$row['prefecture_code']][$row['name']] = $row['code'];
        }

        $result = [];
        foreach ($municipalities as $row) {
            $result[] = $this->buildRow($row, $kenAllNames[substr($row['code'], 0, 5)] ?? null, $codeByPrefAndName);
        }

        return $result;
    }

    /**
     * 結果を CSV に書き出す
     *
     * @param  list<array{code: string, district_name: ?string, parent_code: ?string, address_name: string}>  $rows
     */
    public function write(array $rows, string $outputPath): void
    {
        $dir = dirname($outputPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $handle = fopen($outputPath, 'w');
        if ($handle === false) {
            throw new RuntimeException("CSVファイルを書き込めません: {$outputPath}");
        }
        fputcsv($handle, ['code', 'district_name', 'parent_code', 'address_name'], escape: '');
        foreach ($rows as $row) {
            fputcsv($handle, [$row['code'], $row['district_name'] ?? '', $row['parent_code'] ?? '', $row['address_name']], escape: '');
        }
        fclose($handle);
    }

    /**
     * @param  array{code: string, prefecture_code: string, name: string}  $row
     * @param  array<string, array<string, string>>  $codeByPrefAndName
     * @return array{code: string, district_name: ?string, parent_code: ?string, address_name: string}
     */
    private function buildRow(array $row, ?string $kenAllName, array $codeByPrefAndName): array
    {
        $districtName = self::SUPPLEMENTAL_DISTRICTS[$row['code']] ?? null;
        $parentCode = null;
        $addressName = $kenAllName ?? ($districtName !== null ? $districtName.$row['name'] : $row['name']);

        if ($kenAllName !== null && $kenAllName !== $row['name']) {
            if (preg_match('/^(.+?郡)(.+)$/u', $kenAllName, $m)) {
                // 「高岡郡檮原町」のように町村名の字体が違っても郡名は取る
                $districtName = $m[1];
            } elseif (preg_match('/^(.+?市)(.+区)$/u', $kenAllName, $m) && $m[2] === $row['name']) {
                $parentCode = $codeByPrefAndName[$row['prefecture_code']][$m[1]] ?? null;
            }
        }

        return [
            'code' => $row['code'],
            'district_name' => $districtName,
            'parent_code' => $parentCode,
            'address_name' => $addressName,
        ];
    }

    /**
     * KEN_ALL から 団体コード(5桁) => 市区町村欄 を作る（同じコードは同じ表記）
     *
     * @return array<string, string>
     */
    private function readKenAllNames(string $path): array
    {
        $handle = $this->open($path);
        $names = [];
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            if (count($row) < 8) {
                continue;
            }
            $names[$row[0]] ??= $row[7];
        }
        fclose($handle);

        if ($names === []) {
            throw new RuntimeException("KEN_ALL の行がありません（UTF-8 版か確認してください）: {$path}");
        }

        return $names;
    }

    /**
     * @return list<array{code: string, prefecture_code: string, name: string}>
     */
    private function readMunicipalities(string $path): array
    {
        $handle = $this->open($path);
        fgetcsv($handle, escape: ''); // ヘッダー行をスキップ
        $rows = [];
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            if (count($row) < 3) {
                continue;
            }
            $rows[] = ['code' => $row[0], 'prefecture_code' => $row[1], 'name' => $row[2]];
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return resource
     */
    private function open(string $path)
    {
        if (! file_exists($path)) {
            throw new RuntimeException("CSVファイルが見つかりません: {$path}");
        }
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("CSVファイルを開けません: {$path}");
        }

        return $handle;
    }
}
