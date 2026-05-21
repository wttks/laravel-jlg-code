<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 既存DBへの移行時にテーブルが存在する場合はスキップ
        if (Schema::hasTable('municipalities')) {
            return;
        }

        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->char('code', 6)->unique()->comment('団体コード（6桁）');
            $table->char('prefecture_code', 2)->index()->comment('都道府県コード（01〜47）');
            $table->string('name', 50)->comment('市区町村名（漢字）');
            $table->string('name_kana', 100)->comment('市区町村名（カナ）');
            $table->timestamp('deprecated_at')->nullable()->comment('廃止日時（合併・廃止されたコードに設定）');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('municipalities');
    }
};
