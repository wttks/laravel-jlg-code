<?php

declare(strict_types=1);

namespace Wttks\JlgCode\Tests;

use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Wttks\JlgCode\Models\Municipality;

/**
 * 郡・政令市の区の親・住所での表記の列と、日時列の型変更
 */
class MunicipalityDistrictColumnsTest extends TestCase
{
    #[Test]
    public function 郡名と親コードと住所での表記の列がある(): void
    {
        $this->assertTrue(Schema::hasColumns('municipalities', ['district_name', 'parent_code', 'address_name']));
    }

    #[Test]
    public function 日時列はdatetime型になる(): void
    {
        foreach (['deprecated_at', 'created_at', 'updated_at'] as $column) {
            $this->assertSame('datetime', Schema::getColumnType('municipalities', $column), $column);
        }
    }

    #[Test]
    public function 区から親の市を引ける(): void
    {
        $this->insertSapporo();

        $ward = Municipality::query()->where('code', '011011')->firstOrFail();

        $this->assertSame('札幌市', $ward->parent?->name);
    }

    #[Test]
    public function 市から区の一覧を引ける(): void
    {
        $this->insertSapporo();

        $city = Municipality::query()->where('code', '011002')->firstOrFail();

        $this->assertSame(['中央区', '北区'], $city->wards()->orderBy('code')->pluck('name')->all());
    }

    #[Test]
    public function 区でない市区町村は親がない(): void
    {
        $this->insertSapporo();

        $city = Municipality::query()->where('code', '011002')->firstOrFail();

        $this->assertNull($city->parent);
    }

    private function insertSapporo(): void
    {
        $now = now();
        Municipality::insert([
            ['code' => '011002', 'prefecture_code' => '01', 'name' => '札幌市', 'name_kana' => 'サッポロシ', 'parent_code' => null, 'created_at' => $now, 'updated_at' => $now],
            ['code' => '011011', 'prefecture_code' => '01', 'name' => '中央区', 'name_kana' => 'チュウオウク', 'parent_code' => '011002', 'created_at' => $now, 'updated_at' => $now],
            ['code' => '011029', 'prefecture_code' => '01', 'name' => '北区', 'name_kana' => 'キタク', 'parent_code' => '011002', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
