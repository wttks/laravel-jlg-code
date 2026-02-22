<?php

declare(strict_types=1);

namespace Wttks\JlgCode\Tests;

use PHPUnit\Framework\Attributes\Test;
use Wttks\JlgCode\AddressResolver;
use Wttks\JlgCode\Enums\Prefecture;
use Wttks\JlgCode\Models\Municipality;

class AddressResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // テスト用市区町村データを投入
        Municipality::insert([
            // 東京都 新宿区
            ['code' => '131041', 'prefecture_code' => '13', 'name' => '新宿区', 'name_kana' => 'シンジュクク', 'created_at' => now(), 'updated_at' => now()],
            // 北海道 札幌市東区（政令指定都市の区）
            ['code' => '011029', 'prefecture_code' => '01', 'name' => '東区', 'name_kana' => 'ヒガシク', 'created_at' => now(), 'updated_at' => now()],
            // 宮城県 利府町（郡部）
            ['code' => '041351', 'prefecture_code' => '04', 'name' => '利府町', 'name_kana' => 'リフチョウ', 'created_at' => now(), 'updated_at' => now()],
            // 奈良県 大和郡山市（誤抽出パターン）
            ['code' => '292052', 'prefecture_code' => '29', 'name' => '大和郡山市', 'name_kana' => 'ヤマトコオリヤマシ', 'created_at' => now(), 'updated_at' => now()],
            // 三重県 四日市市（末尾"市"付加パターン）
            ['code' => '242012', 'prefecture_code' => '24', 'name' => '四日市市', 'name_kana' => 'ヨッカイチシ', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    // =========================================================================
    // resolveCode()
    // =========================================================================

    #[Test]
    public function フォールバック1_直接検索で解決できる(): void
    {
        $code = AddressResolver::resolveCode('東京都新宿区西新宿2-8-1');

        $this->assertSame('131041', $code);
    }

    #[Test]
    public function フォールバック2_政令指定都市の区で解決できる(): void
    {
        // "札幌市東区" → "東区" として検索
        $code = AddressResolver::resolveCode('北海道札幌市東区北8条東');

        $this->assertSame('011029', $code);
    }

    #[Test]
    public function フォールバック3_郡部の町村で解決できる(): void
    {
        // "宮城郡利府町" → "利府町" として検索
        $code = AddressResolver::resolveCode('宮城県宮城郡利府町');

        $this->assertSame('041351', $code);
    }

    #[Test]
    public function フォールバック4_市名誤抽出の修正で解決できる(): void
    {
        // "大和郡山市下三橋町" → "大和郡山市" として検索
        $code = AddressResolver::resolveCode('奈良県大和郡山市下三橋町');

        $this->assertSame('292052', $code);
    }

    #[Test]
    public function フォールバック5_末尾市付加で解決できる(): void
    {
        // パーサーが "四日市" と抽出 → "四日市市" として検索
        // 注: AddressParserが "四日市" を抽出するよう住所を構成
        $code = AddressResolver::resolveCode('三重県四日市浜一色町');

        $this->assertSame('242012', $code);
    }

    #[Test]
    public function 解決できない住所はnullを返す(): void
    {
        $code = AddressResolver::resolveCode('東京都存在しない市');

        $this->assertNull($code);
    }

    #[Test]
    public function 都道府県なしの住所はnullを返す(): void
    {
        $code = AddressResolver::resolveCode('新宿区西新宿');

        $this->assertNull($code);
    }

    // =========================================================================
    // resolvePrefecture()
    // =========================================================================

    #[Test]
    public function 都道府県を解決できる(): void
    {
        $prefecture = AddressResolver::resolvePrefecture('東京都新宿区西新宿2-8-1');

        $this->assertSame(Prefecture::Tokyo, $prefecture);
    }

    #[Test]
    public function 都道府県なしの住所はnullを返す_resolvePrefecture(): void
    {
        $prefecture = AddressResolver::resolvePrefecture('新宿区西新宿');

        $this->assertNull($prefecture);
    }

    // =========================================================================
    // resolve()
    // =========================================================================

    #[Test]
    public function resolve_は都道府県とコードの両方を返す(): void
    {
        $result = AddressResolver::resolve('東京都新宿区西新宿2-8-1');

        $this->assertSame(Prefecture::Tokyo, $result['prefecture']);
        $this->assertSame('131041', $result['code']);
    }

    #[Test]
    public function resolve_解決できない場合はcodeがnull(): void
    {
        $result = AddressResolver::resolve('東京都存在しない市');

        $this->assertSame(Prefecture::Tokyo, $result['prefecture']);
        $this->assertNull($result['code']);
    }
}
