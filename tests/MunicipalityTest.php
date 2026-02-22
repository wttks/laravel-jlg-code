<?php

declare(strict_types=1);

namespace Wttks\JlgCode\Tests;

use PHPUnit\Framework\Attributes\Test;
use Wttks\JlgCode\Enums\Prefecture;
use Wttks\JlgCode\Models\Municipality;
use Wttks\JlgCode\ValueObjects\MunicipalityCode;

class MunicipalityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Municipality::insert([
            ['code' => '131041', 'prefecture_code' => '13', 'name' => '新宿区', 'name_kana' => 'シンジュクク', 'created_at' => now(), 'updated_at' => now()],
            ['code' => '131059', 'prefecture_code' => '13', 'name' => '文京区', 'name_kana' => 'ブンキョウク', 'created_at' => now(), 'updated_at' => now()],
            ['code' => '011002', 'prefecture_code' => '01', 'name' => '札幌市', 'name_kana' => 'サッポロシ', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    // =========================================================================
    // casts
    // =========================================================================

    #[Test]
    public function codeカラムがMunicipalityCodeにキャストされる(): void
    {
        $municipality = Municipality::where('code', '131041')->first();

        $this->assertInstanceOf(MunicipalityCode::class, $municipality->code);
        $this->assertSame('131041', $municipality->code->value);
    }

    #[Test]
    public function prefecture_codeカラムがPrefectureにキャストされる(): void
    {
        $municipality = Municipality::where('code', '131041')->first();

        $this->assertSame(Prefecture::Tokyo, $municipality->prefecture_code);
    }

    // =========================================================================
    // fullName()
    // =========================================================================

    #[Test]
    public function fullNameが都道府県名と市区町村名を結合して返す(): void
    {
        $municipality = Municipality::where('code', '131041')->first();

        $this->assertSame('東京都新宿区', $municipality->fullName());
    }

    // =========================================================================
    // scopes
    // =========================================================================

    #[Test]
    public function scopeWherePrefectureで都道府県を絞り込める(): void
    {
        $results = Municipality::query()->wherePrefecture(Prefecture::Tokyo)->get();

        $this->assertCount(2, $results);
        foreach ($results as $m) {
            $this->assertSame(Prefecture::Tokyo, $m->prefecture_code);
        }
    }

    #[Test]
    public function scopeOrderByCodeでコード順にソートできる(): void
    {
        $results = Municipality::query()
            ->wherePrefecture(Prefecture::Tokyo)
            ->orderByCode()
            ->get();

        $this->assertSame('131041', $results[0]->getRawOriginal('code'));
        $this->assertSame('131059', $results[1]->getRawOriginal('code'));
    }

    // =========================================================================
    // listByPrefecture()
    // =========================================================================

    #[Test]
    public function listByPrefectureで都道府県の市区町村一覧をコード順で取得できる(): void
    {
        $municipalities = Municipality::listByPrefecture(Prefecture::Tokyo);

        $this->assertCount(2, $municipalities);
        $this->assertSame('新宿区', $municipalities[0]->name);
        $this->assertSame('文京区', $municipalities[1]->name);
    }

    #[Test]
    public function listByPrefectureで該当なしの場合は空コレクションを返す(): void
    {
        $municipalities = Municipality::listByPrefecture(Prefecture::Okinawa);

        $this->assertCount(0, $municipalities);
    }
}
