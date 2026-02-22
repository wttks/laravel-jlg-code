<?php

declare(strict_types=1);

namespace Wttks\JlgCode\Database\Seeders;

use Illuminate\Database\Seeder;
use Wttks\JlgCode\Models\Municipality;

class MunicipalitySeeder extends Seeder
{
    /**
     * CSVファイルから市区町村データを投入
     *
     * 検索順: 1) storage/app/data/municipalities.csv（公開済み）
     *         2) パッケージ内 resources/data/municipalities.csv
     */
    public function run(): void
    {
        $csvPath = $this->resolveCsvPath();

        if ($csvPath === null) {
            $this->command->error('CSVファイルが見つかりません。vendor:publish --tag=jlg-code-migrations を実行してください。');

            return;
        }

        $handle = fopen($csvPath, 'r');
        fgetcsv($handle); // ヘッダー行をスキップ

        $records = [];
        $now = now();

        while (($row = fgetcsv($handle)) !== false) {
            $records[] = [
                'code' => $row[0],
                'prefecture_code' => $row[1],
                'name' => $row[2],
                'name_kana' => $row[3],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        fclose($handle);

        // 500件ずつ upsert
        foreach (array_chunk($records, 500) as $chunk) {
            Municipality::query()->upsert($chunk, ['code'], ['prefecture_code', 'name', 'name_kana', 'updated_at']);
        }

        $this->command->info('市区町村データを '.count($records).' 件投入しました');
    }

    /**
     * CSVファイルパスを解決する
     */
    private function resolveCsvPath(): ?string
    {
        // アプリ側に公開されたCSVを優先
        if (function_exists('storage_path')) {
            $appPath = storage_path('app/data/municipalities.csv');
            if (file_exists($appPath)) {
                return $appPath;
            }
        }

        // パッケージ内のCSVにフォールバック
        $packagePath = __DIR__.'/../../resources/data/municipalities.csv';
        if (file_exists($packagePath)) {
            return $packagePath;
        }

        return null;
    }
}
