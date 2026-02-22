<?php

declare(strict_types=1);

namespace Wttks\JlgCode\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Wttks\JlgCode\MunicipalityImporter;

/**
 * nojimage/local-gov-code-jp から最新の市区町村コードデータを取得してCSVを更新する
 *
 * @see https://github.com/nojimage/local-gov-code-jp
 */
class UpdateMunicipalitiesCommand extends Command
{
    protected const SOURCE_URL = 'https://raw.githubusercontent.com/nojimage/local-gov-code-jp/master/index.json';

    protected $signature = 'jlg:update
                            {--output= : 出力CSVのパス（省略時は storage/app/data/municipalities.csv）}
                            {--import  : CSV更新後にDBへのインポートも実行}';

    protected $description = 'nojimage/local-gov-code-jp から最新の市区町村データを取得してCSVを更新';

    public function handle(MunicipalityImporter $importer): int
    {
        $outputPath = $this->option('output') ?? storage_path('app/data/municipalities.csv');

        $this->info('市区町村コードデータを取得中...');
        $this->line('  ソース: '.self::SOURCE_URL);

        // JSONをダウンロード
        try {
            $json = $this->download(self::SOURCE_URL);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $data = json_decode($json, true);
        if ($data === null) {
            $this->error('JSONのパースに失敗しました');

            return self::FAILURE;
        }

        $this->info('CSVに変換中...');

        // city（市・町・村）と ward（政令指定都市の区）のみ抽出
        $rows = [];
        foreach ($data as $item) {
            if (! in_array($item['type'] ?? '', ['city', 'ward'], true)) {
                continue;
            }

            $code = $item['code'] ?? '';
            $prefCode = substr($item['pref_code'] ?? $code, 0, 2);

            if ($item['type'] === 'city') {
                $name = $item['city_name'] ?? '';
                $kana = $this->hiraganaToKatakana($item['city_kana'] ?? '');
            } else {
                $name = $item['ward_name'] ?? '';
                $kana = $this->hiraganaToKatakana($item['ward_kana'] ?? '');
            }

            if ($code === '' || $name === '') {
                continue;
            }

            $rows[] = [$code, $prefCode, $name, $kana];
        }

        // コード順にソート
        usort($rows, fn ($a, $b) => $a[0] <=> $b[0]);

        // CSV出力ディレクトリを作成
        $dir = dirname($outputPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // CSV書き出し
        $handle = fopen($outputPath, 'w');
        fputcsv($handle, ['code', 'prefecture_code', 'name', 'name_kana']);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        $count = count($rows);
        $this->info("CSV更新完了: {$count} 件 → {$outputPath}");

        // --import オプションが指定された場合はDBへのインポートも実行
        // 全件データのため、CSVに存在しないコードを廃止扱いにする
        if ($this->option('import')) {
            $this->info('DBへのインポートを実行中...');
            try {
                $imported = $importer->import($outputPath, markDeprecated: true);
                $this->info("インポート完了: {$imported} 件");
            } catch (RuntimeException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        } else {
            $this->line('  DBへ反映するには以下を実行してください:');
            $this->line('  php artisan jlg:import');
        }

        return self::SUCCESS;
    }

    /**
     * URLからコンテンツを取得する
     *
     * @throws RuntimeException ダウンロード失敗時
     */
    private function download(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'wttks/laravel-jlg-code',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);

        if ($content === false) {
            throw new RuntimeException("データの取得に失敗しました: {$url}");
        }

        return $content;
    }

    /**
     * ひらがなをカタカナに変換する
     */
    private function hiraganaToKatakana(string $text): string
    {
        $result = '';
        $len = mb_strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($text, $i, 1);
            $cp = mb_ord($ch);
            // ひらがな U+3041〜U+3096 → カタカナ U+30A1〜U+30F6
            if ($cp >= 0x3041 && $cp <= 0x3096) {
                $result .= mb_chr($cp + 0x60);
            } else {
                $result .= $ch;
            }
        }

        return $result;
    }
}
