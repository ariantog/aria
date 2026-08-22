<?php

namespace App\Services;

use App\Models\Addrbook;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class InvoiceBrandingService
{
    public const LOGO_RELATIVE_PATH = 'asset/invoice-logo';

    private const DEFAULT_COMPANY_NAME = 'CORENATION';

    private const DEFAULT_ADDRESS = 'CILANDAK TOWN SQUARE no.171';

    private const DEFAULT_PHONE = '082244226656';

    public function branding(): array
    {
        return [
            'company_name' => self::DEFAULT_COMPANY_NAME,
            'address' => self::DEFAULT_ADDRESS,
            'phone' => self::DEFAULT_PHONE,
            'logo_path' => $this->logoDiskPath(),
            'logo_url' => $this->logoPublicUrl(),
        ];
    }

    public function forTransaction(Transaction $transaction): array
    {
        $transaction->loadMissing('sender');

        return $this->forAddrbook($transaction->sender);
    }

    public function forAddrbook(?Addrbook $addrbook): array
    {
        $defaults = $this->branding();

        if (! $addrbook) {
            return $defaults;
        }

        $parsed = $this->parseDescriptionHeader($addrbook->description);

        return [
            'company_name' => $parsed['company_name'] ?: ($addrbook->name ?: $defaults['company_name']),
            'address' => $parsed['address'] ?: ($addrbook->address ?: $defaults['address']),
            'phone' => $addrbook->phone ?: $defaults['phone'],
            'logo_path' => $defaults['logo_path'],
            'logo_url' => $defaults['logo_url'],
        ];
    }

    /**
     * @return array{company_name: string, address: string}
     */
    public function parseDescriptionHeader(?string $description): array
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\R+/', (string) $description) ?: []),
            fn (string $line) => $line !== ''
        ));

        if ($lines === []) {
            return ['company_name' => '', 'address' => ''];
        }

        $companyName = array_shift($lines);

        return [
            'company_name' => $companyName,
            'address' => implode("\n", $lines),
        ];
    }

    public function logoDiskPath(): ?string
    {
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $path = public_path(self::LOGO_RELATIVE_PATH.'.'.$ext);
            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }

    public function logoPublicUrl(): ?string
    {
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $relative = self::LOGO_RELATIVE_PATH.'.'.$ext;
            if (File::exists(public_path($relative))) {
                return asset($relative);
            }
        }

        return null;
    }

    public function update(?UploadedFile $logo = null): void
    {
        if (! $logo) {
            return;
        }

        File::ensureDirectoryExists(public_path('asset'));
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $old = public_path(self::LOGO_RELATIVE_PATH.'.'.$ext);
            if (File::exists($old)) {
                File::delete($old);
            }
        }

        $extension = strtolower($logo->getClientOriginalExtension() ?: 'png');
        if (! in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $extension = 'png';
        }

        $logo->move(public_path('asset'), 'invoice-logo.'.$extension);
        $this->upsertSetting('invoice_logo_path', 'Invoice Logo Path', self::LOGO_RELATIVE_PATH.'.'.$extension);
    }

    private function upsertSetting(string $slug, string $name, mixed $value): void
    {
        Setting::updateOrCreate(
            ['slug' => $slug],
            ['group' => 'Invoice', 'name' => $name, 'value' => $value]
        );
    }
}
