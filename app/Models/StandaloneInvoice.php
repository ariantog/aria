<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StandaloneInvoice extends Model
{
    /** @use HasFactory<\Database\Factories\StandaloneInvoiceFactory> */
    use HasFactory;

    public const TEMPLATE_CLASSIC = 'classic';

    public const TEMPLATE_MODERN = 'modern';

    public const TEMPLATE_MINIMAL = 'minimal';

    /** @var array<string, string> */
    public const TEMPLATES = [
        self::TEMPLATE_CLASSIC => 'Classic (Corenation)',
        self::TEMPLATE_MODERN => 'Modern',
        self::TEMPLATE_MINIMAL => 'Minimal',
    ];

    protected $fillable = [
        'number',
        'date',
        'recipient',
        'sender_addrbook_id',
        'template',
        'terms_of_payment',
        'pay_to',
        'signatory_name',
        'preset_id',
        'signature_path',
        'logo_path',
        'total_qty',
        'subtotal',
        'dp_amount',
        'discount_amount',
        'paid_at',
        'paid_by',
        'notes',
        'user_id',
    ];

    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_UNPAID => 'Unpaid',
        self::STATUS_PARTIAL => 'Partial',
        self::STATUS_PAID => 'Paid',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'total_qty' => 'decimal:4',
            'subtotal' => 'decimal:2',
            'dp_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view' => 'invoice-maker-list',
            'create' => 'invoice-maker-create',
            'edit' => 'invoice-maker-edit',
            'delete' => 'invoice-maker-delete',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StandaloneInvoiceLine::class)->orderBy('line_order');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'sender_addrbook_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public static function findByNumber(?string $number): ?self
    {
        $number = trim((string) $number);
        if ($number === '') {
            return null;
        }

        return static::query()->where('number', $number)->orderByDesc('id')->first();
    }

    public function formattedDate(): string
    {
        return $this->date?->locale('id')->translatedFormat('d F Y') ?? '-';
    }

    public function hasDownPayment(): bool
    {
        return $this->dp_amount !== null && (float) $this->dp_amount > 0;
    }

    public function balanceDue(): float
    {
        return (float) $this->subtotal - ($this->hasDownPayment() ? (float) $this->dp_amount : 0);
    }

    public function isMarkedPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function discountAmount(): float
    {
        return max(0, (float) ($this->discount_amount ?? 0));
    }

    public static function generateNumber(?\DateTimeInterface $date = null): string
    {
        $date ??= now();
        $year = $date->format('Y');
        $prefix = "INV/CA/{$year}/";

        $latest = static::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('number');

        $sequence = 1;
        if ($latest && preg_match('/(\d+)$/', $latest, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
