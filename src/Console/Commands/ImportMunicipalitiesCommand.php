<?php

declare(strict_types=1);

namespace Wttks\JlgCode\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Wttks\JlgCode\MunicipalityImporter;

class ImportMunicipalitiesCommand extends Command
{
    protected $signature = 'jlg:import
                            {--path=     : CSVファイルのパス（省略時はデフォルトパス）}
                            {--deprecate : CSV に存在しないコードを廃止扱いにする（全件CSVを使う場合のみ推奨）}
                            {--districts-path= : 郡名等のCSVのパス（省略時はデフォルトパス）}';

    protected $description = '全国地方公共団体コード（市区町村）データをCSVからインポート';

    public function handle(MunicipalityImporter $importer): int
    {
        $path = $this->option('path') ?? MunicipalityImporter::defaultCsvPath();

        $this->info('市区町村データのインポートを開始します...');
        $this->line("  CSVパス: {$path}");

        try {
            $count = $importer->import($path, markDeprecated: (bool) $this->option('deprecate'));
            $this->info("完了: {$count} 件のデータをインポートしました");

            $districtsPath = $this->option('districts-path') ?? MunicipalityImporter::defaultDistrictCsvPath();
            $this->line("  郡名等のCSVパス: {$districtsPath}");
            $updated = $importer->importDistricts($districtsPath);
            $this->info("完了: 郡名等を {$updated} 件に反映しました");

            return self::SUCCESS;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
