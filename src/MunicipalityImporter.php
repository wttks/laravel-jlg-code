<?php

declare(strict_types=1);

namespace Wttks\JlgCode;

use RuntimeException;
use Wttks\JlgCode\Models\Municipality;

class MunicipalityImporter
{
    /**
     * CSVファイルから市区町村データをインポートする
     *
     * @param  bool  $markDeprecated  true の場合、CSV に存在しないコードを廃止扱いにする
     *                                （全件データでの更新時に使用。部分CSVには使用しないこと）
     * @return int インポート件数
     *
     * @throws RuntimeException ファイルが見つからない場合
     */
    public function import(string $csvPath, bool $markDeprecated = false): int
    {
        if (! file_exists($csvPath)) {
            throw new RuntimeException("CSVファイルが見つかりません: {$csvPath}");
        }

        $handle = fopen($csvPath, 'r');
        fgetcsv($handle); // ヘッダー行をスキップ

        $records = [];
        $now = now();

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 4) {
                continue;
            }

            $records[] = [
                'code' => $row[0],
                'prefecture_code' => $row[1],
                'name' => $row[2],
                'name_kana' => $row[3],
                'deprecated_at' => null, // 現行データはフラグをリセット
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        fclose($handle);

        foreach (array_chunk($records, 500) as $chunk) {
            Municipality::query()->upsert(
                $chunk,
                ['code'],
                ['prefecture_code', 'name', 'name_kana', 'deprecated_at', 'updated_at']
            );
        }

        // CSV に存在しないコードを廃止扱いに設定
        if ($markDeprecated && count($records) > 0) {
            $activeCodes = array_column($records, 'code');

            Municipality::query()
                ->whereNotIn('code', $activeCodes)
                ->whereNull('deprecated_at') // まだ廃止マークされていないものだけ
                ->update(['deprecated_at' => $now]);
        }

        return count($records);
    }

    /**
     * デフォルトのCSVパスを解決する
     *
     * 優先順:
     *   1) storage/app/data/municipalities.csv（アプリ側で差し替え可能）
     *   2) パッケージ同梱の resources/data/municipalities.csv
     */
    public static function defaultCsvPath(): string
    {
        if (function_exists('storage_path')) {
            $appPath = storage_path('app/data/municipalities.csv');
            if (file_exists($appPath)) {
                return $appPath;
            }
        }

        return __DIR__.'/../resources/data/municipalities.csv';
    }
}
