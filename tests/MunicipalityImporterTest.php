<?php

declare(strict_types=1);

namespace Wttks\JlgCode\Tests;

use PHPUnit\Framework\Attributes\Test;
use Wttks\JlgCode\Models\Municipality;
use Wttks\JlgCode\MunicipalityImporter;

class MunicipalityImporterTest extends TestCase
{
    private MunicipalityImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new MunicipalityImporter;
    }

    // =========================================================================
    // import()
    // =========================================================================

    #[Test]
    public function デフォルトCSVからインポートできる(): void
    {
        $path = MunicipalityImporter::defaultCsvPath();
        $count = $this->importer->import($path);

        $this->assertGreaterThan(1000, $count);
        $this->assertSame($count, Municipality::query()->count());
    }

    #[Test]
    public function インポート後に団体コードで検索できる(): void
    {
        $this->importer->import(MunicipalityImporter::defaultCsvPath());

        $municipality = Municipality::query()->where('code', '131041')->first();
        $this->assertNotNull($municipality);
        $this->assertSame('新宿区', $municipality->name);
    }

    #[Test]
    public function upsertにより二重インポートしても重複しない(): void
    {
        $path = MunicipalityImporter::defaultCsvPath();

        $countFirst = $this->importer->import($path);
        $countSecond = $this->importer->import($path);

        $this->assertSame($countFirst, $countSecond);
        $this->assertSame($countFirst, Municipality::query()->count());
    }

    #[Test]
    public function 存在しないCSVパスはRuntimeExceptionをスローする(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/CSVファイルが見つかりません/');

        $this->importer->import('/nonexistent/path.csv');
    }

    #[Test]
    public function カスタムCSVからインポートできる(): void
    {
        $tmpPath = $this->makeCsv([
            ['131041', '13', '新宿区', 'シンジュクク'],
            ['011002', '01', '札幌市', 'サッポロシ'],
        ]);

        try {
            $count = $this->importer->import($tmpPath);
            $this->assertSame(2, $count);
            $this->assertSame(2, Municipality::query()->count());
        } finally {
            unlink($tmpPath);
        }
    }

    // =========================================================================
    // markDeprecated
    // =========================================================================

    #[Test]
    public function markDeprecated_trueで新データにないコードに廃止フラグが立つ(): void
    {
        // まず2件だけ入れる
        $tmpPath = $this->makeCsv([
            ['131041', '13', '新宿区', 'シンジュクク'],
            ['131059', '13', '文京区', 'ブンキョウク'],
        ]);

        try {
            $this->importer->import($tmpPath);

            // 1件だけの新CSVで更新 → 文京区が廃止扱いになる
            $smallPath = $this->makeCsv([
                ['131041', '13', '新宿区', 'シンジュクク'],
            ]);
            try {
                $this->importer->import($smallPath, markDeprecated: true);
            } finally {
                unlink($smallPath);
            }

            $shinjuku = Municipality::query()->where('code', '131041')->first();
            $bunkyo = Municipality::query()->where('code', '131059')->first();

            $this->assertNull($shinjuku->deprecated_at);
            $this->assertFalse($shinjuku->is_deprecated);

            $this->assertNotNull($bunkyo->deprecated_at);
            $this->assertTrue($bunkyo->is_deprecated);
        } finally {
            unlink($tmpPath);
        }
    }

    #[Test]
    public function markDeprecated_trueで廃止後に復活したコードのフラグがリセットされる(): void
    {
        // 新宿区のみ → 文京区を廃止フラグ付きで登録
        $onlyShinjuku = $this->makeCsv([
            ['131041', '13', '新宿区', 'シンジュクク'],
        ]);
        Municipality::insert(['code' => '131059', 'prefecture_code' => '13', 'name' => '文京区', 'name_kana' => 'ブンキョウク', 'created_at' => now(), 'updated_at' => now()]);

        try {
            $this->importer->import($onlyShinjuku, markDeprecated: true);

            // 文京区が廃止になっているはず
            $this->assertTrue(Municipality::query()->where('code', '131059')->first()->is_deprecated);

            // 文京区も含む全件CSVで再インポート → 廃止フラグがリセットされる
            $bothPath = $this->makeCsv([
                ['131041', '13', '新宿区', 'シンジュクク'],
                ['131059', '13', '文京区', 'ブンキョウク'],
            ]);
            try {
                $this->importer->import($bothPath, markDeprecated: true);
            } finally {
                unlink($bothPath);
            }

            $bunkyo = Municipality::query()->where('code', '131059')->first();
            $this->assertNull($bunkyo->deprecated_at);
            $this->assertFalse($bunkyo->is_deprecated);
        } finally {
            unlink($onlyShinjuku);
        }
    }

    #[Test]
    public function markDeprecated_falseではフラグが立たない(): void
    {
        $tmpPath = $this->makeCsv([
            ['131041', '13', '新宿区', 'シンジュクク'],
            ['131059', '13', '文京区', 'ブンキョウク'],
        ]);

        try {
            $this->importer->import($tmpPath);

            // 1件だけのCSVでフラグなしインポート → 文京区は廃止にならない
            $smallPath = $this->makeCsv([
                ['131041', '13', '新宿区', 'シンジュクク'],
            ]);
            try {
                $this->importer->import($smallPath, markDeprecated: false);
            } finally {
                unlink($smallPath);
            }

            $bunkyo = Municipality::query()->where('code', '131059')->first();
            $this->assertNull($bunkyo->deprecated_at);
        } finally {
            unlink($tmpPath);
        }
    }

    // =========================================================================
    // defaultCsvPath()
    // =========================================================================

    #[Test]
    public function defaultCsvPathは存在するパスを返す(): void
    {
        $path = MunicipalityImporter::defaultCsvPath();

        $this->assertFileExists($path);
    }

    // =========================================================================
    // jlg:import コマンド
    // =========================================================================

    // =========================================================================
    // Artisan コマンド
    // =========================================================================

    #[Test]
    public function artisanコマンドjlg_importでインポートできる(): void
    {
        $this->artisan('jlg:import')
            ->assertSuccessful();

        $this->assertGreaterThan(1000, Municipality::query()->count());
    }

    #[Test]
    public function artisanコマンドに存在しないパスを渡すと失敗する(): void
    {
        $this->artisan('jlg:import', ['--path' => '/nonexistent/path.csv'])
            ->assertFailed();
    }

    #[Test]
    public function artisanコマンドのdeprecateオプションで廃止フラグが立つ(): void
    {
        $tmpPath = $this->makeCsv([
            ['131041', '13', '新宿区', 'シンジュクク'],
            ['131059', '13', '文京区', 'ブンキョウク'],
        ]);

        try {
            $this->artisan('jlg:import', ['--path' => $tmpPath]);

            $smallPath = $this->makeCsv([
                ['131041', '13', '新宿区', 'シンジュクク'],
            ]);
            try {
                $this->artisan('jlg:import', ['--path' => $smallPath, '--deprecate' => true])
                    ->assertSuccessful();
            } finally {
                unlink($smallPath);
            }

            $this->assertTrue(Municipality::query()->where('code', '131059')->first()->is_deprecated);
        } finally {
            unlink($tmpPath);
        }
    }

    // =========================================================================
    // ヘルパー
    // =========================================================================

    /**
     * @param  array<array<string>>  $rows
     */
    private function makeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'jlg_test_');
        $handle = fopen($path, 'w');
        fputcsv($handle, ['code', 'prefecture_code', 'name', 'name_kana']);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }
}
