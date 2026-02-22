<?php

declare(strict_types=1);

namespace Wttks\JlgCode\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Wttks\JlgCode\AddressParser;
use Wttks\JlgCode\Enums\Prefecture;

class AddressParserTest extends TestCase
{
    // =========================================================================
    // parse()
    // =========================================================================

    #[Test]
    public function 都道府県と市区町村を正しくパースできる(): void
    {
        $result = AddressParser::parse('東京都新宿区西新宿2-8-1');

        $this->assertSame(Prefecture::Tokyo, $result['prefecture']);
        $this->assertSame('新宿区', $result['municipality']);
        $this->assertSame('西新宿2-8-1', $result['rest']);
    }

    #[Test]
    public function 政令指定都市の区を正しくパースできる(): void
    {
        $result = AddressParser::parse('北海道札幌市東区北8条東');

        $this->assertSame(Prefecture::Hokkaido, $result['prefecture']);
        $this->assertSame('札幌市東区', $result['municipality']);
    }

    #[Test]
    public function 郡部の町村を正しくパースできる(): void
    {
        $result = AddressParser::parse('宮城県宮城郡利府町');

        $this->assertSame(Prefecture::Miyagi, $result['prefecture']);
        $this->assertSame('宮城郡利府町', $result['municipality']);
    }

    #[Test]
    public function 都道府県のみの住所をパースできる(): void
    {
        $result = AddressParser::parse('東京都');

        $this->assertSame(Prefecture::Tokyo, $result['prefecture']);
        $this->assertNull($result['municipality']);
        $this->assertSame('', $result['rest']);
    }

    #[Test]
    public function 都道府県なし住所はprefectureがnullになる(): void
    {
        $result = AddressParser::parse('新宿区西新宿2-8-1');

        $this->assertNull($result['prefecture']);
        $this->assertSame('新宿区', $result['municipality']);
    }

    // =========================================================================
    // extractPrefecture()
    // =========================================================================

    #[Test]
    #[DataProvider('prefectureProvider')]
    public function 各都道府県を正しく抽出できる(string $address, Prefecture $expected): void
    {
        $this->assertSame($expected, AddressParser::extractPrefecture($address));
    }

    /**
     * @return array<string, array{string, Prefecture}>
     */
    public static function prefectureProvider(): array
    {
        return [
            '北海道' => ['北海道札幌市', Prefecture::Hokkaido],
            '東京都' => ['東京都新宿区', Prefecture::Tokyo],
            '京都府' => ['京都府京都市', Prefecture::Kyoto],
            '大阪府' => ['大阪府大阪市', Prefecture::Osaka],
            '神奈川県' => ['神奈川県横浜市', Prefecture::Kanagawa],
            '鹿児島県' => ['鹿児島県鹿児島市', Prefecture::Kagoshima],
            '沖縄県' => ['沖縄県那覇市', Prefecture::Okinawa],
        ];
    }

    #[Test]
    public function 都道府県なしの住所はnullを返す(): void
    {
        $this->assertNull(AddressParser::extractPrefecture('新宿区西新宿'));
    }

    // =========================================================================
    // extractMunicipalityName()
    // =========================================================================

    #[Test]
    public function 一般市を抽出できる(): void
    {
        $this->assertSame('旭川市', AddressParser::extractMunicipalityName('旭川市'));
    }

    #[Test]
    public function 区を抽出できる(): void
    {
        $this->assertSame('新宿区', AddressParser::extractMunicipalityName('新宿区西新宿2-8-1'));
    }

    #[Test]
    public function 町を抽出できる(): void
    {
        $this->assertSame('利府町', AddressParser::extractMunicipalityName('利府町'));
    }

    #[Test]
    public function 空文字列はnullを返す(): void
    {
        $this->assertNull(AddressParser::extractMunicipalityName(''));
    }
}
