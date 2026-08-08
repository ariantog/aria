<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class InvoiceBrandingService
{
    public const LOGO_RELATIVE_PATH = 'asset/invoice-logo';

    public function branding(): array
    {
        return [
            'company_name' => (string) Setting::getValue('invoice_company_name', 'CORENATION'),
            'address' => (string) Setting::getValue('invoice_address', 'CILANDAK TOWN SQUARE no.171'),
            'phone' => (string) Setting::getValue('invoice_phone', '082244226656'),
            'logo_path' => $this->logoDiskPath(),
            'logo_url' => $this->logoPublicUrl(),
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

    public function update(array $data, ?UploadedFile $logo = null): void
    {
        $this->upsertSetting('invoice_company_name', 'Invoice Company Name', $data['company_name']);
        $this->upsertSetting('invoice_address', 'Invoice Address', $data['address']);
        $this->upsertSetting('invoice_phone', 'Invoice Phone', $data['phone']);

        if ($logo) {
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
    }

    private function upsertSetting(string $slug, string $name, mixed $value): void
    {
        Setting::updateOrCreate(
            ['slug' => $slug],
            ['group' => 'Invoice', 'name' => $name, 'value' => $value]
        );
    }
}
