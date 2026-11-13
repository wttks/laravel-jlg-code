<?php

declare(strict_types=1);

namespace Wttks\JlgCode\Tests;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Wttks\JlgCode\MunicipalityDistrictBuilder;
use Wttks\JlgCode\MunicipalityImporter;

/**
 * KEN_ALL から郡名・政令市の親コード・住所での表記を作る
 */
class MunicipalityDistrictBuilderTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    #[Test]
    public function 郡と政令市の区と表記違いを読み分ける(): void
    {
        $rows = $this->buildByCode();

        // 郡に属する町村
        $this->assertSame(['code' => '403814', 'district_name' => '遠賀郡', 'parent_code' => null, 'address_name' => '遠賀郡芦屋町'], $rows['403814']);
        // 政令市の区 → 同じ県の市のコード
        $this->assertSame(['code' => '011011', 'district_name' => null, 'parent_code' => '011002', 'address_name' => '札幌市中央区'], $rows['011011']);
        // 郡・区なし
        $this->assertSame(['code' => '402214', 'district_name' => null, 'parent_code' => null, 'address_name' => '太宰府市'], $rows['402214']);
        // 町村名の字体が違っても郡名は取り、表記は KEN_ALL のまま残す
        $this->assertSame(['code' => '394050', 'district_name' => '高岡郡', 'parent_code' => null, 'address_name' => '高岡郡檮原町'], $rows['394050']);
        // 郡でない前置き（島名）は郡にしない
        $this->assertSame(['code' => '133817', 'district_name' => null, 'parent_code' => null, 'address_name' => '三宅島三宅村'], $rows['133817']);
    }

    #[Test]
    public function 郵便番号データに無い政令市そのものは名前のまま(): void
    {
        $rows = $this->buildByCode();

        $this->assertSame(['code' => '011002', 'district_name' => null, 'parent_code' => null, 'address_name' => '札幌市'], $rows['011002']);
    }

    #[Test]
    public function 郵便番号データに無い北方領土の村は郡名を補う(): void
    {
        $rows = $this->buildByCode();

        $this->assertSame(['code' => '016951', 'district_name' => '色丹郡', 'parent_code' => null, 'address_name' => '色丹郡色丹村'], $rows['016951']);
    }

    #[Test]
    public function 市区町村データの全行を出力する(): void
    {
        $this->assertCount(8, $this->buildByCode());
    }

    #[Test]
    public function 書き出したファイルを読み戻せる(): void
    {
        $builder = new MunicipalityDistrictBuilder;
        $output = $this->tempPath();
        $builder->write($builder->build($this->kenAllCsv(), $this->municipalitiesCsv()), $output);

        $lines = file($output, FILE_IGNORE_NEW_LINES);
        $this->assertSame('code,district_name,parent_code,address_name', $lines[0]);
        $this->assertContains('011011,,011002,札幌市中央区', $lines);
        $this->assertContains('403814,遠賀郡,,遠賀郡芦屋町', $lines);
    }

    #[Test]
    public function 郵便番号データが無ければ例外(): void
    {
        $this->expectException(RuntimeException::class);

        (new MunicipalityDistrictBuilder)->build('/nonexistent/utf_ken_all.csv', $this->municipalitiesCsv());
    }

    #[Test]
    public function 郵便番号データが空なら例外(): void
    {
        $this->expectException(RuntimeException::class);

        (new MunicipalityDistrictBuilder)->build($this->writeTemp(''), $this->municipalitiesCsv());
    }

    #[Test]
    public function 同梱データは市区町村データの全コードを持つ(): void
    {
        $codes = $this->readColumn(MunicipalityImporter::defaultCsvPath());
        $districtCodes = $this->readColumn(MunicipalityImporter::defaultDistrictCsvPath());

        $this->assertSame($codes, $districtCodes);
    }

    /**
     * @return array<string, array{code: string, district_name: ?string, parent_code: ?string, address_name: string}>
     */
    private function buildByCode(): array
    {
        $rows = (new MunicipalityDistrictBuilder)->build($this->kenAllCsv(), $this->municipalitiesCsv());

        return array_column($rows, null, 'code');
    }

    private function kenAllCsv(): string
    {
        // KEN_ALL と同じ並び（0=団体コード5桁 … 6=都道府県 7=市区町村 8=町域）。同じコードが複数行あってもよい
        $lines = [
            '01101,"060  ","0600000","ﾎｯｶｲﾄﾞｳ","ｻｯﾎﾟﾛｼﾁｭｳｵｳｸ","ｲｶﾆｹｲｻｲｶﾞﾅｲﾊﾞｱｲ","北海道","札幌市中央区","以下に掲載がない場合",0,0,0,0,0,0',
            '01101,"064  ","0640941","ﾎｯｶｲﾄﾞｳ","ｻｯﾎﾟﾛｼﾁｭｳｵｳｸ","ｱｻﾋｶﾞｵｶ","北海道","札幌市中央区","旭ケ丘",0,0,1,0,0,0',
            '40381,"807  ","8070000","ﾌｸｵｶｹﾝ","ｵﾝｶﾞｸﾞﾝｱｼﾔﾏﾁ","ｲｶﾆｹｲｻｲｶﾞﾅｲﾊﾞｱｲ","福岡県","遠賀郡芦屋町","以下に掲載がない場合",0,0,0,0,0,0',
            '40221,"818  ","8180100","ﾌｸｵｶｹﾝ","ﾀﾞｻﾞｲﾌｼ","ｲｶﾆｹｲｻｲｶﾞﾅｲﾊﾞｱｲ","福岡県","太宰府市","以下に掲載がない場合",0,0,0,0,0,0',
            '39405,"78515","7851600","ｺｳﾁｹﾝ","ﾀｶｵｶｸﾞﾝﾕｽﾊﾗﾁｮｳ","ｲｶﾆｹｲｻｲｶﾞﾅｲﾊﾞｱｲ","高知県","高岡郡檮原町","以下に掲載がない場合",0,0,0,0,0,0',
            '13381,"10011","1001100","ﾄｳｷｮｳﾄ","ﾐﾔｹｼﾞﾏﾐﾔｹﾑﾗ","ｲｶﾆｹｲｻｲｶﾞﾅｲﾊﾞｱｲ","東京都","三宅島三宅村","以下に掲載がない場合",0,0,0,0,0,0',
        ];

        return $this->writeTemp(implode("\n", $lines)."\n");
    }

    private function municipalitiesCsv(): string
    {
        $lines = [
            'code,prefecture_code,name,name_kana',
            '011002,01,札幌市,サッポロシ',
            '011011,01,中央区,チュウオウク',
            '016951,01,色丹村,シコタンムラ',
            '133817,13,三宅村,ミヤケムラ',
            '394050,39,梼原町,ユスハラチョウ',
            '402214,40,太宰府市,ダザイフシ',
            '403814,40,芦屋町,アシヤマチ',
            '403440,40,須恵町,スエマチ',
        ];

        return $this->writeTemp(implode("\n", $lines)."\n");
    }

    /**
     * @return list<string>
     */
    private function readColumn(string $path): array
    {
        $handle = fopen($path, 'r');
        fgetcsv($handle, escape: '');
        $codes = [];
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $codes[] = $row[0];
        }
        fclose($handle);

        return $codes;
    }

    private function writeTemp(string $content): string
    {
        $path = $this->tempPath();
        file_put_contents($path, $content);

        return $path;
    }

    private function tempPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'jlg');
        $this->tempFiles[] = $path;

        return $path;
    }
}
