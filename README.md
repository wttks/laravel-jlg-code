# wttks/laravel-jlg-code

全国地方公共団体コード（JLG コード）の Laravel サポートパッケージ。

- 都道府県 Enum（`Prefecture`）― 47 都道府県、ラベル・カナ付き
- 市区町村モデル（`Municipality`）― DB 管理、廃止コード追跡付き
- 住所パーサー（`AddressParser`）― 住所文字列から都道府県・市区町村名を抽出
- 住所リゾルバー（`AddressResolver`）― 住所文字列 → 団体コード（5 段フォールバック）
- `BelongsToMunicipality` トレイト ― 他モデルへの市区町村リレーション追加
- `MunicipalityCode` バリューオブジェクト ― チェックディジット検証付き

## インストール

```bash
composer require wttks/laravel-jlg-code
```

Laravel 11 以降はパッケージ自動検出により ServiceProvider が自動登録されます。

### マイグレーション・データのセットアップ

```bash
# マイグレーション実行
php artisan migrate

# 市区町村データのインポート（パッケージ同梱 CSV を使用）
php artisan jlg:import
```

## 市区町村データの更新

総務省公式データ（[nojimage/local-gov-code-jp](https://github.com/nojimage/local-gov-code-jp) 経由）から最新データを取得します。

```bash
# CSV のみ更新（storage/app/data/municipalities.csv に出力）
php artisan jlg:update

# CSV 更新 + DB インポートを一括実行（廃止コードに deprecated_at を自動設定）
php artisan jlg:update --import
```

廃止された市区町村コード（合併・廃止）は `deprecated_at` が設定されて DB 上に残ります。
旧住所での検索は引き続き可能で、利用側で廃止かどうかを判断できます。

## 使い方

### 都道府県 Enum

```php
use Wttks\JlgCode\Enums\Prefecture;

Prefecture::Tokyo->label();     // => '東京都'
Prefecture::Tokyo->labelKana(); // => 'トウキョウト'
Prefecture::from('13');         // => Prefecture::Tokyo
```

### 市区町村の検索

```php
use Wttks\JlgCode\Models\Municipality;
use Wttks\JlgCode\Enums\Prefecture;

// 都道府県で一覧取得（コード順）
Municipality::listByPrefecture(Prefecture::Tokyo);

// 現行のみ（廃止コードを除く）
Municipality::query()->active()->get();

// 廃止済みも含めて名前で検索
Municipality::query()->where('name', '旧市町村名')->first();

// 廃止判定
$municipality->is_deprecated; // bool
$municipality->deprecated_at; // Carbon|null
```

### 住所から市区町村コードを解決

```php
use Wttks\JlgCode\AddressResolver;

AddressResolver::resolveCode('東京都新宿区西新宿2-8-1');
// => '131041'

AddressResolver::resolvePrefecture('東京都新宿区西新宿2-8-1');
// => Prefecture::Tokyo

AddressResolver::resolve('東京都新宿区西新宿2-8-1');
// => ['prefecture' => Prefecture::Tokyo, 'code' => '131041']
```

5 段フォールバックで解決します:

1. 直接検索
2. 政令指定都市の区 (`XX市YY区` → `YY区`)
3. 郡部の町村 (`XX郡YY町` → `YY町`)
4. 市名誤抽出修正 (`大和郡山市下三橋町` → `大和郡山市`)
5. 末尾 "市" 付加 (`四日市` → `四日市市`)

### 住所パーサー

```php
use Wttks\JlgCode\AddressParser;

$result = AddressParser::parse('東京都新宿区西新宿2-8-1');
// => ['prefecture' => '東京都', 'municipality' => '新宿区', 'rest' => '西新宿2-8-1']

AddressParser::extractPrefecture('東京都新宿区西新宿2-8-1');
// => '東京都'

AddressParser::extractMunicipalityName('東京都新宿区西新宿2-8-1');
// => '新宿区'
```

### BelongsToMunicipality トレイト

他のモデルに市区町村リレーションを追加します。

```php
use Wttks\JlgCode\Models\Traits\BelongsToMunicipality;

class Store extends Model
{
    use BelongsToMunicipality;
    // municipality_code カラムが必要
}

// 使用例
$store->municipality;               // Municipality モデル
Store::query()->wherePrefectureCode(Prefecture::Tokyo)->get();
Store::query()->whereMunicipalityPrefectureCodes(['13', '14'])->get();
Store::query()->orderByMunicipalityCode()->get();
```

### MunicipalityCode バリューオブジェクト

```php
use Wttks\JlgCode\ValueObjects\MunicipalityCode;

$code = new MunicipalityCode('131041');
$code->value;            // => '131041'
$code->prefectureCode(); // => '13'
$code->prefecture();     // => Prefecture::Tokyo
$code->localCode();      // => '1041'
$code->checkDigit();     // => 1
```

## 他プロジェクトでの利用（GitHub VCS 参照）

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/wttks/laravel-jlg-code" }
    ],
    "require": {
        "wttks/laravel-jlg-code": "^1.0"
    }
}
```

```bash
composer require wttks/laravel-jlg-code
php artisan migrate
php artisan jlg:import
```

## ライセンス

MIT
