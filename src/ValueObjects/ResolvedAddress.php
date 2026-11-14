<?php

declare(strict_types=1);

namespace Wttks\JlgCode\ValueObjects;

use Wttks\JlgCode\Enums\AddressResolveStatus;
use Wttks\JlgCode\Enums\Prefecture;

/**
 * 住所 → 団体コードの解決結果（AddressResolver::resolveDetailed()）
 *
 * - Resolved:       prefecture と code が入る
 * - PrefectureOnly: prefecture だけ入る
 * - Ambiguous:      candidates に候補コードが入る。候補が同じ県なら prefecture も入る
 * - Unresolved:     どれも入らない
 */
final readonly class ResolvedAddress
{
    /**
     * @param  list<string>  $candidates  候補の団体コード（Ambiguous のときだけ2件以上）
     * @param  ?string  $municipalityName  住所の中で市区町村として読んだ部分（例: 遠賀郡芦屋町）
     * @param  string  $rest  市区町村より後ろの部分
     */
    public function __construct(
        public AddressResolveStatus $status,
        public ?Prefecture $prefecture,
        public ?string $code,
        public array $candidates,
        public ?string $municipalityName,
        public string $rest,
    ) {}

    public function isResolved(): bool
    {
        return $this->status === AddressResolveStatus::Resolved;
    }
}
