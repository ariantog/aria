<?php

namespace App\Services;

use App\Models\Addrbook;
use App\Models\User;
use App\Support\LikeSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ItemTransactionQueryService
{
    /**
     * @return array{from: string, to: string, invoice: string, party: string}
     */
    public function filtersFromRequest(Request $request): array
    {
        $party = trim((string) $request->query('party', ''));
        if ($party === '') {
            $party = trim((string) $request->query('sender', ''));
        }
        if ($party === '') {
            $party = trim((string) $request->query('receiver', ''));
        }

        return [
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
            'invoice' => trim((string) $request->query('invoice', '')),
            'party' => $party,
        ];
    }

    public function apply(Builder $query, Request $request): Builder
    {
        $filters = $this->filtersFromRequest($request);
        $from = $this->dateValue($filters['from']);
        $to = $this->dateValue($filters['to']);
        $partyId = $this->partyId($filters['party']);

        return $query
            ->when($from !== null, fn (Builder $q) => $q->whereDate('transaction_details.date', '>=', $from))
            ->when($to !== null, fn (Builder $q) => $q->whereDate('transaction_details.date', '<=', $to))
            ->when($filters['invoice'] !== '', function (Builder $q) use ($filters) {
                $pattern = LikeSearch::contains($filters['invoice']);

                return $q->whereHas('transaction', fn (Builder $tq) => $tq->where('invoice', 'like', $pattern));
            })
            ->when($partyId !== null, fn (Builder $q) => $this->applyPartyIdFilter($q, $partyId));
    }

    /**
     * @return array{id: int, name: string}|null
     */
    public function resolveSelectedParty(mixed $value, ?User $user): ?array
    {
        $id = $this->partyId($value);
        if ($id === null) {
            return null;
        }

        $addrbook = Addrbook::query()
            ->visibleToUser($user)
            ->whereIn('type', Addrbook::itemTransactionPartyTypes())
            ->find($id);

        if (! $addrbook) {
            return null;
        }

        return [
            'id' => $addrbook->id,
            'name' => $addrbook->name,
        ];
    }

    public function hasActiveFilters(array $filters): bool
    {
        return collect($filters)->contains(fn ($value) => trim((string) $value) !== '');
    }

    private function applyPartyIdFilter(Builder $query, int $partyId): Builder
    {
        return $query->whereHas('transaction', function (Builder $tq) use ($partyId) {
            $tq->where(function (Builder $partyQuery) use ($partyId) {
                $partyQuery->where('sender_id', $partyId)
                    ->orWhere('receiver_id', $partyId);
            });
        });
    }

    private function partyId(mixed $value): ?int
    {
        $term = trim((string) ($value ?? ''));
        if ($term === '' || ! ctype_digit($term)) {
            return null;
        }

        return (int) $term;
    }

    private function dateValue(string $value): ?string
    {
        if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }
}
