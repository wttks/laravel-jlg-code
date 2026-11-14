<?php

declare(strict_types=1);

namespace Wttks\JlgCode\Enums;

/**
 * 住所 → 団体コードの解決結果の段階
 */
enum AddressResolveStatus: string
{
    /** 市区町村まで1つに決まった */
    case Resolved = 'resolved';

    /** 都道府県だけ分かった（市区町村が引けない） */
    case PrefectureOnly = 'prefecture_only';

    /** 市区町村の候補が複数ある（県名なしの府中市・同じ県の同名区など） */
    case Ambiguous = 'ambiguous';

    /** 何も分からない */
    case Unresolved = 'unresolved';
}
