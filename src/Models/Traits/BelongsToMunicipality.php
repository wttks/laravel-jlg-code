<?php

declare(strict_types=1);

namespace Wttks\JlgCode\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Wttks\JlgCode\Enums\Prefecture;
use Wttks\JlgCode\Models\Municipality;

/**
 * 市区町村に紐づくモデル用トレイト
 *
 * 使用するモデルには municipality_code カラム（char(6)）が必要
 */
trait BelongsToMunicipality
{
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'municipality_code', 'code');
    }

    /**
     * 市区町村コード順にソート
     */
    public function scopeOrderByMunicipalityCode(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('municipality_code', $direction);
    }

    /**
     * 都道府県コードで絞り込み
     */
    public function scopeWherePrefectureCode(Builder $query, Prefecture $prefecture): Builder
    {
        return $query->whereHas('municipality', function (Builder $q) use ($prefecture) {
            $q->where('prefecture_code', $prefecture->code());
        });
    }

    /**
     * 都道府県コード配列で絞り込み（地方区分の汎用版）
     *
     * @param  array<string>  $prefectureCodes  都道府県コード（2桁文字列）の配列
     */
    public function scopeWhereMunicipalityPrefectureCodes(Builder $query, array $prefectureCodes): Builder
    {
        return $query->whereHas('municipality', function (Builder $q) use ($prefectureCodes) {
            $q->whereIn('prefecture_code', $prefectureCodes);
        });
    }
}
