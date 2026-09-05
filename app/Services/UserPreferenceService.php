<?php

namespace App\Services;

use App\Models\Addrbook;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserPreference;
use App\Support\UserPreferenceRegistry;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class UserPreferenceService
{
    public function __construct(
        protected LocationAccessService $locationAccess,
    ) {}

    public function get(User $user, string $slug, mixed $default = null): mixed
    {
        if (! $this->tableExists()) {
            return $default;
        }

        $preference = UserPreference::query()
            ->where('user_id', $user->id)
            ->where('slug', $slug)
            ->first();

        return $preference ? $preference->value : $default;
    }

    public function set(User $user, string $slug, mixed $value): void
    {
        if (! $this->tableExists()) {
            return;
        }

        if ($value === null || $value === '') {
            UserPreference::query()
                ->where('user_id', $user->id)
                ->where('slug', $slug)
                ->delete();

            return;
        }

        UserPreference::updateOrCreate(
            ['user_id' => $user->id, 'slug' => $slug],
            ['value' => $value],
        );
    }

    public function appearanceFor(User $user): string
    {
        $value = $this->get($user, UserPreferenceRegistry::APPEARANCE_SLUG, 'system');

        return in_array($value, UserPreferenceRegistry::appearanceOptions(), true)
            ? $value
            : 'system';
    }

    public function setAppearance(User $user, string $appearance): void
    {
        if (! in_array($appearance, UserPreferenceRegistry::appearanceOptions(), true)) {
            throw new InvalidArgumentException('Invalid appearance value.');
        }

        $this->set($user, UserPreferenceRegistry::APPEARANCE_SLUG, $appearance);
    }

    public function fontSizeFor(User $user): string
    {
        $value = $this->get($user, UserPreferenceRegistry::FONT_SIZE_SLUG, 'default');

        return in_array($value, UserPreferenceRegistry::fontSizeOptions(), true)
            ? $value
            : 'default';
    }

    public function fontSizePixelsFor(User $user): string
    {
        return UserPreferenceRegistry::fontSizePixels()[$this->fontSizeFor($user)];
    }

    public function setFontSize(User $user, string $fontSize): void
    {
        if (! in_array($fontSize, UserPreferenceRegistry::fontSizeOptions(), true)) {
            throw new InvalidArgumentException('Invalid font size value.');
        }

        $this->set($user, UserPreferenceRegistry::FONT_SIZE_SLUG, $fontSize);
    }

    public function ppnIncludedDefaultFor(User $user): bool
    {
        $value = $this->get($user, UserPreferenceRegistry::PPN_INCLUDED_SLUG);

        if ($value === null) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function setPpnIncludedDefault(User $user, bool $included): void
    {
        $this->set($user, UserPreferenceRegistry::PPN_INCLUDED_SLUG, $included ? '1' : '0');
    }

    /**
     * @return array<string, mixed>
     */
    public function transactionDefaultsFormData(User $user): array
    {
        $values = [];
        $contacts = [];

        foreach (UserPreferenceRegistry::transactionDefaults() as $slug => $definition) {
            $field = UserPreferenceRegistry::transactionDefaultFieldMap()[$slug];
            $id = $this->getAddrbookId($user, $slug);
            $values[$field] = $id;
            $contacts[$field] = $id ? Addrbook::find($id) : null;
        }

        return [
            'values' => $values,
            'contacts' => $contacts,
            'definitions' => UserPreferenceRegistry::transactionDefaults(),
        ];
    }

    /**
     * @param  array<string, int|string|null>  $data
     */
    public function updateTransactionDefaults(User $user, array $data): void
    {
        foreach (UserPreferenceRegistry::transactionDefaults() as $slug => $definition) {
            $field = UserPreferenceRegistry::transactionDefaultFieldMap()[$slug];
            $raw = $data[$field] ?? null;

            if ($raw === null || $raw === '') {
                $this->set($user, $slug, null);

                continue;
            }

            $id = (int) $raw;
            $addrbook = Addrbook::find($id);

            if (! $addrbook) {
                throw new InvalidArgumentException("{$definition['label']} is not a valid contact.");
            }

            if (! in_array($this->addrbookTypeValue($addrbook), $definition['types'], true)) {
                throw new InvalidArgumentException("{$definition['label']} must be the correct contact type.");
            }

            if (! $this->locationAccess->canAccessAddrbook($user, $addrbook)) {
                throw new InvalidArgumentException("{$definition['label']} is not available for your location.");
            }

            $this->set($user, $slug, $id);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function transactionPrefill(User $user, string $type): ?array
    {
        $prefill = match ($type) {
            'buy' => $this->partyPrefill(
                $user,
                $this->resolveSupplierId($user),
                $this->getAddrbookId($user, 'transactions.default_warehouse_id')
                    ?? $this->globalRestockReceiverId(),
            ),
            'sell' => $this->partyPrefill(
                $user,
                $this->getAddrbookId($user, 'transactions.default_warehouse_id'),
                $this->getAddrbookId($user, 'transactions.default_customer_id'),
            ),
            'move' => $this->partyPrefill(
                $user,
                $this->getAddrbookId($user, 'transactions.default_warehouse_id'),
                $this->getAddrbookId($user, 'transactions.default_move_receiver_id'),
            ),
            'return' => $this->partyPrefill(
                $user,
                null,
                $this->getAddrbookId($user, 'transactions.default_warehouse_id'),
            ),
            'return-supplier' => $this->partyPrefill(
                $user,
                $this->getAddrbookId($user, 'transactions.default_warehouse_id'),
                null,
            ),
            default => null,
        };

        return $prefill ?: null;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    public function defaultCashAccount(User $user, bool $isCashIn): ?array
    {
        $slug = $isCashIn
            ? 'transactions.default_cash_in_bank_id'
            : 'transactions.default_cash_out_bank_id';

        $addrbook = $this->resolveAddrbook($user, $this->getAddrbookId($user, $slug));

        return $addrbook ? ['id' => $addrbook->id, 'name' => $addrbook->name] : null;
    }

    /**
     * @return array{sender_id: ?int, receiver_id: ?int}
     */
    public function defaultTransferAccounts(User $user): array
    {
        $sender = $this->resolveAddrbook($user, $this->getAddrbookId($user, 'transactions.default_transfer_from_id'));
        $receiver = $this->resolveAddrbook($user, $this->getAddrbookId($user, 'transactions.default_transfer_to_id'));

        return [
            'sender_id' => $sender?->id,
            'receiver_id' => $receiver?->id,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function partyPrefill(User $user, ?int $senderId, ?int $receiverId): ?array
    {
        $sender = $this->resolveAddrbook($user, $senderId);
        $receiver = $this->resolveAddrbook($user, $receiverId);

        if (! $sender && ! $receiver) {
            return null;
        }

        $prefill = [];

        if ($sender) {
            $prefill['sender_id'] = (string) $sender->id;
            $prefill['sender'] = $this->partyPayload($sender);
        }

        if ($receiver) {
            $prefill['receiver_id'] = (string) $receiver->id;
            $prefill['receiver'] = $this->partyPayload($receiver);
        }

        return $prefill;
    }

    protected function resolveSupplierId(User $user): ?int
    {
        return $this->getAddrbookId($user, 'transactions.default_supplier_id')
            ?? $this->globalRestockSupplierId();
    }

    protected function globalRestockSupplierId(): ?int
    {
        $value = Setting::getValue('restock.default_supplier_id');

        return $value ? (int) $value : null;
    }

    protected function globalRestockReceiverId(): ?int
    {
        $value = Setting::getValue('restock.default_receiver_id');

        return $value ? (int) $value : null;
    }

    protected function getAddrbookId(User $user, string $slug): ?int
    {
        $value = $this->get($user, $slug);

        return $value ? (int) $value : null;
    }

    protected function resolveAddrbook(User $user, ?int $id): ?Addrbook
    {
        if (! $id) {
            return null;
        }

        $addrbook = Addrbook::find($id);

        if (! $addrbook) {
            return null;
        }

        if (! $this->locationAccess->canAccessAddrbook($user, $addrbook)) {
            return null;
        }

        return $addrbook;
    }

    /**
     * @return array{id: int, name: string, ppn: bool}
     */
    protected function partyPayload(Addrbook $addrbook): array
    {
        return [
            'id' => $addrbook->id,
            'name' => $addrbook->name,
            'ppn' => (bool) $addrbook->ppn,
        ];
    }

    protected function addrbookTypeValue(Addrbook $addrbook): int
    {
        return $addrbook->type instanceof \App\Enums\AddrbookType
            ? $addrbook->type->value
            : (int) $addrbook->type;
    }

    protected function tableExists(): bool
    {
        return Schema::hasTable('user_preferences');
    }
}
