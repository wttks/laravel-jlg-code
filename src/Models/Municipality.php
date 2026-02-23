<?php

declare(strict_types=1);

namespace Wttks\JlgCode\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Wttks\JlgCode\Casts\MunicipalityCodeCast;
use Wttks\JlgCode\Casts\PrefectureCast;
use Wttks\JlgCode\Enums\Prefecture;

class Municipality extends Model
{
    use HasFactory;

    /**
     * アプリ側に MunicipalityFactory があれば委譲する
     * （テストでの Municipality::factory() 呼び出し対応）
     */
    protected static function newFactory(): ?\Illuminate\Database\Eloquent\Factories\Factory
    {
        $appFactory = 'Database\\Factories\\MunicipalityFactory';
        if (class_exists($appFactory)) {
            return new $appFactory;
        }

        return null;
    }

    protected $fillable = [
        'code',
        'prefecture_code',
        'name',
        'name_kana',
        'deprecated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => MunicipalityCodeCast::class,
            'prefecture_code' => PrefectureCast::class,
            'deprecated_at' => 'datetime',
        ];
    }

    /**
     * 廃止済み（合併・廃止）かどうか
     */
    public function getIsDeprecatedAttribute(): bool
    {
        return $this->deprecated_at !== null;
    }

    /**
     * 都道府県名を含むフルネーム（例: 東京都新宿区）
     */
    public function fullName(): string
    {
        return $this->prefecture_code->label().$this->name;
    }

    /**
     * 廃止されていない現行の市区町村のみ
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deprecated_at');
    }

    /**
     * 廃止済みの市区町村のみ
     */
    public function scopeDeprecated(Builder $query): Builder
    {
        return $query->whereNotNull('deprecated_at');
    }

    /**
     * 団体コード順にソート
     */
    public function scopeOrderByCode(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('code', $direction);
    }

    /**
     * 都道府県コードで絞り込み
     */
    public function scopeWherePrefecture(Builder $query, Prefecture $prefecture): Builder
    {
        return $query->where('prefecture_code', $prefecture->code());
    }

    /**
     * 都道府県コード配列で絞り込み（地方区分の汎用版）
     *
     * @param  array<string>  $prefectureCodes  都道府県コード（2桁文字列）の配列
     */
    public function scopeWherePrefectureCodes(Builder $query, array $prefectureCodes): Builder
    {
        return $query->whereIn('prefecture_code', $prefectureCodes);
    }

    /**
     * 都道府県 → 市区町村コレクション（コード順）
     *
     * @return Collection<int, static>
     */
    public static function listByPrefecture(Prefecture $prefecture): Collection
    {
        return static::query()
            ->wherePrefecture($prefecture)
            ->orderByCode()
            ->get();
    }
}
