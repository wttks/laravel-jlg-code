<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 市区町村に「郡」「政令市の区の親」「住所での表記」を足し、日時列を datetime にそろえる
 *
 * - district_name: 郡に属する町村の郡名（例: 遠賀郡）。郡に属さないものは NULL
 * - parent_code:   政令指定都市の区 → 親の市の団体コード（例: 中央区 011011 → 札幌市 011002）
 * - address_name:  住所に書かれる表記（日本郵便 KEN_ALL の市区町村欄。例: 遠賀郡芦屋町／札幌市中央区／三宅島三宅村）
 *
 * 日時列は timestamp（4バイト・2038年問題）から datetime へ変更する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('municipalities', function (Blueprint $table) {
            $table->string('district_name', 20)->nullable()->after('name_kana')->comment('郡名（郡に属する町村のみ。例: 遠賀郡）');
            $table->char('parent_code', 6)->nullable()->index()->after('district_name')->comment('政令指定都市の区の親（市）の団体コード');
            $table->string('address_name', 50)->nullable()->after('parent_code')->comment('住所での表記（郡・政令市名を含む。例: 遠賀郡芦屋町）');
        });

        Schema::table('municipalities', function (Blueprint $table) {
            // change() は指定しなかった属性を落とすため、nullable とコメントを付け直す
            $table->dateTime('deprecated_at')->nullable()->comment('廃止日時（合併・廃止されたコードに設定）')->change();
            $table->dateTime('created_at')->nullable()->change();
            $table->dateTime('updated_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('municipalities', function (Blueprint $table) {
            $table->timestamp('deprecated_at')->nullable()->comment('廃止日時（合併・廃止されたコードに設定）')->change();
            $table->timestamp('created_at')->nullable()->change();
            $table->timestamp('updated_at')->nullable()->change();
        });

        Schema::table('municipalities', function (Blueprint $table) {
            $table->dropIndex(['parent_code']);
            $table->dropColumn(['district_name', 'parent_code', 'address_name']);
        });
    }
};
