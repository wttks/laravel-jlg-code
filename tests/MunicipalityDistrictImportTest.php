<?php

declare(strict_types=1);

namespace Wttks\JlgCode\Tests;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Wttks\JlgCode\Models\Municipality;
use Wttks\JlgCode\MunicipalityImporter;

/**
 * 郡名・政令市の親コード・住所での表記の取込
 */
class MunicipalityDistrictImportTest extends TestCase
{
    #[Test]
    public function 同梱データで郡名と親コードと表記が入る(): void
    {
        $importer = new MunicipalityImporter;
        $importer->import(MunicipalityImporter::defaultCsvPath());
        $updated = $importer->importDistricts(MunicipalityImporter::defaultDistrictCsvPath());

        $this->assertSame(Municipality::query()->count(), $updated);
        $this->assertSame(['遠賀郡', null, '遠賀郡芦屋町'], $this->columnsOf('403814'));
        $this->assertSame([null, '011002', '札幌市中央区'], $this->columnsOf('011011'));
        $this->assertSame([null, null, '太宰府市'], $this->columnsOf('402214'));
        $this->assertSame(171, Municipality::query()->whereNotNull('parent_code')->count());
    }

    #[Test]
    public function 空欄はヌルで入る(): void
    {
        $this->insertMunicipality('402214', '40', '太宰府市', districtName: 'ダミー郡', parentCode: '999999');

        (new MunicipalityImporter)->importDistricts($this->makeCsv([['402214', '', '', '太宰府市']]));

        $this->assertSame([null, null, '太宰府市'], $this->columnsOf('402214'));
    }

    #[Test]
    public function 取込データに無いコードは触らない(): void
    {
        $this->insertMunicipality('403814', '40', '芦屋町', districtName: '遠賀郡');
        $this->insertMunicipality('402214', '40', '太宰府市');

        $updated = (new MunicipalityImporter)->importDistricts($this->makeCsv([['402214', '', '', '太宰府市']]));

        $this->assertSame(1, $updated);
        $this->assertSame('遠賀郡', Municipality::query()->where('code', '403814')->value('district_name'));
    }

    #[Test]
    public function テーブルに無いコードは追加しない(): void
    {
        $updated = (new MunicipalityImporter)->importDistricts($this->makeCsv([['403814', '遠賀郡', '', '遠賀郡芦屋町']]));

        $this->assertSame(0, $updated);
        $this->assertSame(0, Municipality::query()->count());
    }

    #[Test]
    public function 取込ファイルが無ければ例外(): void
    {
        $this->expectException(RuntimeException::class);

        (new MunicipalityImporter)->importDistricts('/nonexistent/municipality_districts.csv');
    }

    #[Test]
    public function jlg_importで郡名等も入る(): void
    {
        $this->artisan('jlg:import')->assertSuccessful();

        $this->assertSame(['遠賀郡', null, '遠賀郡芦屋町'], $this->columnsOf('403814'));
    }

    #[Test]
    public function jlg_importの郡名等のパスを指定できる(): void
    {
        $this->artisan('jlg:import', ['--districts-path' => $this->makeCsv([['403814', '指定郡', '', '指定郡芦屋町']])])
            ->assertSuccessful();

        $this->assertSame(['指定郡', null, '指定郡芦屋町'], $this->columnsOf('403814'));
    }

    #[Test]
    public function jlg_build_districtsでファイルを作れる(): void
    {
        $kenAll = $this->writeTemp('40381,"807  ","8070000","ﾌｸｵｶｹﾝ","ｵﾝｶﾞｸﾞﾝｱｼﾔﾏﾁ","ｲｶﾆ","福岡県","遠賀郡芦屋町","以下に掲載がない場合",0,0,0,0,0,0'."\n");
        $municipalities = $this->writeTemp("code,prefecture_code,name,name_kana\n403814,40,芦屋町,アシヤマチ\n");
        $output = sys_get_temp_dir().'/jlg_build_'.uniqid().'.csv';

        try {
            $this->artisan('jlg:build-districts', ['ken-all' => $kenAll, '--municipalities' => $municipalities, '--output' => $output])
                ->assertSuccessful();

            $this->assertSame("code,district_name,parent_code,address_name\n403814,遠賀郡,,遠賀郡芦屋町\n", file_get_contents($output));
        } finally {
            @unlink($kenAll);
            @unlink($municipalities);
            @unlink($output);
        }
    }

    #[Test]
    public function jlg_build_districtsは郵便番号データが無ければ失敗(): void
    {
        $this->artisan('jlg:build-districts', ['ken-all' => '/nonexistent/utf_ken_all.csv'])->assertFailed();
    }

    /**
     * @return array{?string, ?string, ?string}
     */
    private function columnsOf(string $code): array
    {
        $row = Municipality::query()->where('code', $code)->firstOrFail();

        return [$row->district_name, $row->parent_code, $row->address_name];
    }

    private function insertMunicipality(string $code, string $prefCode, string $name, ?string $districtName = null, ?string $parentCode = null): void
    {
        Municipality::insert([[
            'code' => $code, 'prefecture_code' => $prefCode, 'name' => $name, 'name_kana' => '',
            'district_name' => $districtName, 'parent_code' => $parentCode, 'address_name' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]]);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function makeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'jlg_district_');
        $handle = fopen($path, 'w');
        fputcsv($handle, ['code', 'district_name', 'parent_code', 'address_name'], escape: '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, escape: '');
        }
        fclose($handle);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }

    private function writeTemp(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'jlg_');
        file_put_contents($path, $content);

        return $path;
    }
}
