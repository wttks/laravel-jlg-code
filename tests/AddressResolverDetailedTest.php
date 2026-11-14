<?php

declare(strict_types=1);

namespace Wttks\JlgCode\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Wttks\JlgCode\AddressResolver;
use Wttks\JlgCode\Enums\AddressResolveStatus;
use Wttks\JlgCode\Enums\Prefecture;
use Wttks\JlgCode\Models\Municipality;
use Wttks\JlgCode\MunicipalityImporter;

/**
 * 索引（先頭最長一致）による住所解決。同梱の全国データで確かめる
 */
class AddressResolverDetailedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $importer = new MunicipalityImporter;
        $importer->import(MunicipalityImporter::defaultCsvPath());
        $importer->importDistricts(MunicipalityImporter::defaultDistrictCsvPath());
    }

    /**
     * @return array<string, array{string, string, Prefecture}>
     */
    public static function resolvedProvider(): array
    {
        return [
            // 正規表現の切り出しだと誤る住所
            '区の直後に市が続く（新宿区市谷）' => ['東京都新宿区市谷本村町5-1', '131041', Prefecture::Tokyo],
            '郡＋同じ字が続く町（杵島郡大町町）' => ['佐賀県杵島郡大町町大字福母', '414239', Prefecture::Saga],
            // 郡・政令市
            '郡を省いた町' => ['佐賀県大町町大字福母', '414239', Prefecture::Saga],
            '郡＋町' => ['福岡県遠賀郡芦屋町幸町', '403814', Prefecture::Fukuoka],
            '政令市＋区' => ['北海道札幌市中央区北1条西2丁目', '011011', Prefecture::Hokkaido],
            '政令市だけ（区なし）は市のコード' => ['北海道札幌市', '011002', Prefecture::Hokkaido],
            '同じ県の同名区も市を付ければ1つ' => ['神奈川県横浜市緑区寺山町', '141135', Prefecture::Kanagawa],
            '市名に郡を含む（大和郡山市）' => ['奈良県大和郡山市北郡山町', '292036', Prefecture::Nara],
            '市名が郡で始まる（郡山市）' => ['福島県郡山市朝日1丁目', '072036', Prefecture::Fukushima],
            // 表記違い
            '島名付き（三宅島三宅村）' => ['東京都三宅島三宅村阿古', '133817', Prefecture::Tokyo],
            '旧字体（檮原町）' => ['高知県高岡郡檮原町梼原', '394050', Prefecture::Kochi],
            '旧字体で郡なし' => ['高知県檮原町梼原', '394050', Prefecture::Kochi],
            '新字体（梼原町）' => ['高知県高岡郡梼原町梼原', '394050', Prefecture::Kochi],
            'ヶとケのゆれ' => ['埼玉県鶴ケ島市大字三ツ木', '112411', Prefecture::Saitama],
            '空白入り' => ['福岡県 福岡市　中央区 天神1丁目', '401331', Prefecture::Fukuoka],
            // 県名なし（全国で1つなら解決・県も分かる）
            '県名なしの政令市＋区' => ['福岡市中央区天神1丁目', '401331', Prefecture::Fukuoka],
            '県名なしの郡＋町' => ['杵島郡大町町大字福母', '414239', Prefecture::Saga],
            '県名なしの特別区' => ['新宿区西新宿2-8-1', '131041', Prefecture::Tokyo],
        ];
    }

    #[Test]
    #[DataProvider('resolvedProvider')]
    public function 市区町村まで解決できる(string $address, string $code, Prefecture $prefecture): void
    {
        $result = AddressResolver::resolveDetailed($address);

        $this->assertSame(AddressResolveStatus::Resolved, $result->status);
        $this->assertSame($code, $result->code);
        $this->assertSame($prefecture, $result->prefecture);
        $this->assertSame([], $result->candidates);
    }

    /**
     * 先頭に郵便番号が付いた住所（受注データで実際に多い形）
     *
     * @return array<string, array{string}>
     */
    public static function postalCodePrefixedProvider(): array
    {
        return [
            '〒＋ハイフン＋空白' => ['〒811-4304 福岡県遠賀郡芦屋町幸町1-1'],
            '〒も空白もなし' => ['811-4304福岡県遠賀郡芦屋町幸町1-1'],
            '区切りなし7桁' => ['8114304 福岡県遠賀郡芦屋町幸町1-1'],
            '全角の数字と区切り' => ['〒８１１－４３０４　福岡県遠賀郡芦屋町幸町1-1'],
            '区切りが長音・県名なし' => ['811ー4304 遠賀郡芦屋町幸町1-1'],
        ];
    }

    #[Test]
    #[DataProvider('postalCodePrefixedProvider')]
    public function 先頭の郵便番号を外して解決する(string $address): void
    {
        $result = AddressResolver::resolveDetailed($address);

        $this->assertSame(AddressResolveStatus::Resolved, $result->status);
        $this->assertSame('403814', $result->code);
        $this->assertSame(Prefecture::Fukuoka, $result->prefecture);
        $this->assertSame('幸町1-1', $result->rest);
    }

    /**
     * 郵便番号の桁数でない数字列は外さない（下限の1つ外・上限の1つ外）
     *
     * @return array<string, array{string}>
     */
    public static function notPostalCodeProvider(): array
    {
        return [
            '6桁（1桁足りない）' => ['811-430 福岡県遠賀郡芦屋町幸町1-1'],
            '8桁（1桁多い）' => ['811-43041福岡県遠賀郡芦屋町幸町1-1'],
        ];
    }

    #[Test]
    #[DataProvider('notPostalCodeProvider')]
    public function 郵便番号の形でない数字列は外さない(string $address): void
    {
        $this->assertSame(AddressResolveStatus::Unresolved, AddressResolver::resolveDetailed($address)->status);
    }

    #[Test]
    public function 郵便番号付きでも_resolveCode_でコードが返る(): void
    {
        $this->assertSame('403814', AddressResolver::resolveCode('〒811-4304 福岡県遠賀郡芦屋町幸町1-1'));
        $this->assertSame(Prefecture::Fukuoka, AddressResolver::resolvePrefecture('〒811-4304 福岡県遠賀郡芦屋町'));
    }

    #[Test]
    public function 市区町村として読んだ部分と残りを返す(): void
    {
        $result = AddressResolver::resolveDetailed('福岡県遠賀郡芦屋町幸町1-1');

        $this->assertSame('遠賀郡芦屋町', $result->municipalityName);
        $this->assertSame('幸町1-1', $result->rest);
    }

    #[Test]
    public function 県名なしで全国に複数あれば候補を返す(): void
    {
        $result = AddressResolver::resolveDetailed('府中市宮西町');

        $this->assertSame(AddressResolveStatus::Ambiguous, $result->status);
        $this->assertNull($result->code);
        $this->assertNull($result->prefecture);
        $this->assertEqualsCanonicalizing(['132063', '342084'], $result->candidates);
    }

    #[Test]
    public function 同じ県の同名区は候補を返し県は分かる(): void
    {
        $result = AddressResolver::resolveDetailed('神奈川県緑区');

        $this->assertSame(AddressResolveStatus::Ambiguous, $result->status);
        $this->assertSame(Prefecture::Kanagawa, $result->prefecture);
        $this->assertEqualsCanonicalizing(['141135', '141518'], $result->candidates);
    }

    #[Test]
    public function 市区町村が引けなければ県だけ返す(): void
    {
        $result = AddressResolver::resolveDetailed('東京都存在しない市');

        $this->assertSame(AddressResolveStatus::PrefectureOnly, $result->status);
        $this->assertSame(Prefecture::Tokyo, $result->prefecture);
        $this->assertNull($result->code);
    }

    #[Test]
    public function 何も分からなければ未解決(): void
    {
        $result = AddressResolver::resolveDetailed('1-2-3 どこか');

        $this->assertSame(AddressResolveStatus::Unresolved, $result->status);
        $this->assertNull($result->prefecture);
        $this->assertNull($result->code);
    }

    #[Test]
    public function 空文字は未解決(): void
    {
        $this->assertSame(AddressResolveStatus::Unresolved, AddressResolver::resolveDetailed('')->status);
    }

    #[Test]
    public function 現行に無い名前は廃止済みのコードで解決する(): void
    {
        Municipality::insert([[
            'code' => '019991', 'prefecture_code' => '01', 'name' => '旧テスト村', 'name_kana' => '',
            'deprecated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]]);

        $this->assertSame('019991', AddressResolver::resolveCode('北海道旧テスト村1番地'));
    }

    #[Test]
    public function 同じ名前なら現行のコードを優先する(): void
    {
        Municipality::insert([[
            'code' => '139991', 'prefecture_code' => '13', 'name' => '新宿区', 'name_kana' => '',
            'deprecated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]]);

        $this->assertSame('131041', AddressResolver::resolveCode('東京都新宿区西新宿'));
    }

    #[Test]
    public function データが増えたら索引を作り直す(): void
    {
        $this->assertNull(AddressResolver::resolveCode('北海道新設テスト町1番地'));

        Municipality::insert([[
            'code' => '019983', 'prefecture_code' => '01', 'name' => '新設テスト町', 'name_kana' => '',
            'created_at' => now()->addSecond(), 'updated_at' => now()->addSecond(),
        ]]);

        $this->assertSame('019983', AddressResolver::resolveCode('北海道新設テスト町1番地'));
    }

    #[Test]
    public function 郡名等を入れ直したら索引を作り直す(): void
    {
        $this->assertSame('403814', AddressResolver::resolveCode('福岡県遠賀郡芦屋町'));

        Municipality::query()->update(['district_name' => null, 'address_name' => null]);
        AddressResolver::flushIndex();

        // 郡名が無くなると「遠賀郡芦屋町」は索引に無い → 従来の5段フォールバックで「芦屋町」として引ける
        $this->assertSame('403814', AddressResolver::resolveCode('福岡県遠賀郡芦屋町'));
        $this->assertNull(AddressResolver::resolveCode('遠賀郡芦屋町'));
    }
}
