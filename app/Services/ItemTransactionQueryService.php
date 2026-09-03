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
     * @return array{from: string, to: string, invoice: string, sender: string, receiver: string}
     */
    public function filtersFromRequest(Request $request): array
    {
        return [
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
            'invoice' => trim((string) $request->query('invoice', '')),
            'sender' => trim((string) $request->query('sender', '')),
            'receiver' => trim((string) $request->query('receiver', '')),
        ];
    }

    public function apply(Builder $query, Request $request): Builder
    {
        $filters = $this->filtersFromRequest($request);
        $from = $this->dateValue($filters['from']);
        $to = $this->dateValue($filters['to']);
        $senderId = $this->partyId($filters['sender']);
        $receiverId = $this->partyId($filters['receiver']);

        return $query
            ->when($from !== null, fn (Builder $q) => $q->whereDate('transaction_details.date', '>=', $from))
            ->when($to !== null, fn (Builder $q) => $q->whereDate('transaction_details.date', '<=', $to))
            ->when($filters['invoice'] !== '', function (Builder $q) use ($filters) {
                $pattern = LikeSearch::contains($filters['invoice']);

                return $q->whereHas('transaction', fn (Builder $tq) => $tq->where('invoice', 'like', $pattern));
            })
            ->when($senderId !== null, fn (Builder $q) => $this->applyPartyIdFilter($q, 'sender', $senderId))
            ->when($receiverId !== null, fn (Builder $q) => $this->applyPartyIdFilter($q, 'receiver', $receiverId))
            ->orderByDesc('transaction_details.date')
            ->orderByDesc('transaction_id');
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

    private function applyPartyIdFilter(Builder $query, string $role, int $partyId): Builder
    {
        $transactionColumn = $role === 'sender' ? 'sender_id' : 'receiver_id';

        return $query->whereHas('transaction', fn (Builder $tq) => $tq->where($transactionColumn, $partyId));
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
