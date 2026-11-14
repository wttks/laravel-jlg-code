# wttks/laravel-jlg-code

全国地方公共団体コード（JLG コード）の Laravel サポートパッケージ。

- 都道府県 Enum（`Prefecture`）― 47 都道府県、ラベル・カナ付き
- 市区町村モデル（`Municipality`）― DB 管理、廃止コード追跡付き、郡名・政令市の親コード・住所での表記付き
- 住所パーサー（`AddressParser`）― 住所文字列から都道府県・市区町村名を抽出
- 住所リゾルバー（`AddressResolver`）― 住所文字列 → 団体コード（索引による先頭最長一致。解決の段階・候補も返せる）
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

# 市区町村データのインポート（パッケージ同梱 CSV を使用。郡名等の CSV も続けて取り込む）
php artisan jlg:import
```

## v1.2 からの更新（v1.3.0）

```bash
composer update wttks/laravel-jlg-code
php artisan migrate      # 列追加＋日時列を timestamp → datetime に型変更
php artisan jlg:import   # 追加した列（郡名等）を埋める
```

- `municipalities` に `district_name`（郡名）・`parent_code`（政令市の区の親の市コード）・`address_name`（住所での表記）を追加します。
- `deprecated_at`・`created_at`・`updated_at` を `timestamp` から `datetime` に変えます（2038年問題対策）。
- **`resolve()`／`resolveCode()`／`resolvePrefecture()` の結果が変わることがあります。**
  - 県名を省いた住所でも、全国で1つに決まる市区町村なら解決します（例: `新宿区西新宿` → `131041`。以前は null）。
  - 県名なしの `府中市` のように候補が複数あるときは、これまでどおり null です。
  - 郡名付き・政令市名付きの住所は、郡名・市名を含めた表記で照合します（`遠賀郡芦屋町`・`札幌市中央区` など）。
- `jlg:import` を流すまでは郡名等が空のため、郡・政令市の住所は従来の5段フォールバックで解決します。

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

`jlg:update --import` は郡名等の CSV も続けて取り込みます。新設された市区町村の郡名等は、次の手順で CSV を作り直すまで空のままです。

### 郡名・政令市の親コード・住所での表記の CSV を作り直す

日本郵便の KEN_ALL（UTF-8 版）から作ります。配布 URL が変わることがあるため、ファイルは自動では取得しません。
[郵便番号データダウンロード（UTF-8 形式）](https://www.post.japanpost.jp/service/search/zipcode/download/utf-zip.html) の `utf_ken_all.zip` を展開して渡します。

```bash
# storage/app/data/municipality_districts.csv に出力（あればこちらが同梱 CSV より優先される）
php artisan jlg:build-districts /path/to/utf_ken_all.csv

# DB に反映
php artisan jlg:import
```

| オプション | 説明 |
|---|---|
| `--municipalities=` | 市区町村 CSV のパス（省略時はデフォルトパス） |
| `--output=` | 出力 CSV のパス（省略時は `storage/app/data/municipality_districts.csv`） |

`jlg:import --districts-path=` で、取り込む郡名等の CSV を指定できます。

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

// 郡・政令市（jlg:import で埋まる）
$municipality->district_name; // '遠賀郡'（郡に属さないものは null）
$municipality->parent_code;   // 政令市の区 → 親の市の団体コード（例: 札幌市中央区 → '011002'）
$municipality->address_name;  // 住所での表記（例: '遠賀郡芦屋町'・'札幌市中央区'）
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

候補が複数ある（県名なしの `府中市` など）・解決できないときは null を返します。

#### 解決の段階・候補まで知りたいとき（resolveDetailed）

```php
use Wttks\JlgCode\AddressResolver;
use Wttks\JlgCode\Enums\AddressResolveStatus;

$result = AddressResolver::resolveDetailed('福岡県遠賀郡芦屋町幸町1-1');
$result->status;           // AddressResolveStatus::Resolved
$result->isResolved();     // true
$result->prefecture;       // Prefecture::Fukuoka
$result->code;             // '403814'
$result->municipalityName; // '遠賀郡芦屋町'（住所の中で市区町村として読んだ部分）
$result->rest;             // '幸町1-1'

$result = AddressResolver::resolveDetailed('府中市宮西町');
$result->status;           // AddressResolveStatus::Ambiguous
$result->candidates;       // ['132063', '342084']（東京都府中市・広島県府中市）
```

| `status` | 意味 | 入る値 |
|---|---|---|
| `Resolved` | 市区町村まで1つに決まった | `prefecture`・`code` |
| `PrefectureOnly` | 都道府県だけ分かった | `prefecture` |
| `Ambiguous` | 市区町村の候補が複数ある | `candidates`（同じ県の候補なら `prefecture` も） |
| `Unresolved` | 何も分からない | なし |

#### 解決のしかた

1. 住所の空白を除き、`ヶ` を `ケ` にそろえる
2. 先頭の都道府県名を読む（なければ全国が対象）
3. 残りの先頭と、市区町村の表記（名前・郡名＋名前・政令市名＋区名・住所での表記・郡を省いた住所での表記）を**一番長く一致するもの**で照合する（例: `大和郡山市下三橋町` は `大和郡山市` に一致し、`郡山市` には取られない）
4. 現行の市区町村で見つからなければ、廃止済みの市区町村で照合する
5. それでも見つからず県名がある場合は、従来の5段フォールバック（政令市の区・郡部の町村・市名誤抽出修正・末尾 "市" 付加）を試す

政令市の区を含まない住所（`北海道札幌市`）は市のコード（`011002`）で解決します。

索引は初回の呼び出しで作り、以降は呼び出しごとに件数と最終更新日時を1回問い合わせて、データが変わっていれば作り直します。
データを直接書き換えた場合は `AddressResolver::flushIndex()` で捨てられます（`jlg:import` 等は自動で捨てます）。

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
