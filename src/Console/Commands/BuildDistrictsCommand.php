<?php

declare(strict_types=1);

namespace Wttks\JlgCode\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Wttks\JlgCode\MunicipalityDistrictBuilder;
use Wttks\JlgCode\MunicipalityImporter;

/**
 * 日本郵便の KEN_ALL（UTF-8 版）から郡名・政令市の親コード・住所での表記の CSV を作る
 *
 * KEN_ALL は自動では取得しない（配布 URL が変わることがあるため）。
 * https://www.post.japanpost.jp/service/search/zipcode/download/utf-zip.html の utf_ken_all.zip を展開して渡す。
 */
class BuildDistrictsCommand extends Command
{
    protected $signature = 'jlg:build-districts
                            {ken-all : KEN_ALL（UTF-8 版 utf_ken_all.csv）のパス}
                            {--municipalities= : 市区町村CSVのパス（省略時はデフォルトパス）}
                            {--output=         : 出力CSVのパス（省略時は storage/app/data/municipality_districts.csv）}';

    protected $description = 'KEN_ALL から郡名・政令市の親コード・住所での表記のCSVを作る';

    public function handle(MunicipalityDistrictBuilder $builder): int
    {
        $municipalitiesPath = $this->option('municipalities') ?? MunicipalityImporter::defaultCsvPath();
        $outputPath = $this->option('output') ?? storage_path('app/data/municipality_districts.csv');

        try {
            $rows = $builder->build((string) $this->argument('ken-all'), $municipalitiesPath);
            $builder->write($rows, $outputPath);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $districts = count(array_filter($rows, fn (array $row) => $row['district_name'] !== null));
        $wards = count(array_filter($rows, fn (array $row) => $row['parent_code'] !== null));
        $this->info(sprintf('完了: %d 件（郡に属する町村 %d／政令市の区 %d）→ %s', count($rows), $districts, $wards, $outputPath));
        $this->line('  DBへ反映するには以下を実行してください:');
        $this->line('  php artisan jlg:import');

        return self::SUCCESS;
    }
}
