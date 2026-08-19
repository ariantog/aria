<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\StandaloneInvoice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class InvoiceMakerSettingsService
{
    public const SIGNATURE_RELATIVE_PATH = 'asset/invoice-signature';

    public const SETTING_TERMS = 'invoice_maker.terms_of_payment';

    public const SETTING_PAY_TO = 'invoice_maker.pay_to';

    public const SETTING_SIGNATORY = 'invoice_maker.signatory_name';

    public const SETTING_TEMPLATE = 'invoice_maker.default_template';

    private const DEFAULT_TERMS = "Pembayaran lunas sebelum barang dikirim.\nHarga belum termasuk PPN 11%.";

    private const DEFAULT_PAY_TO = "BCA\n5105251588\nCV ACTIVEWEAR GLOBAL MANDIRI";

    private const DEFAULT_SIGNATORY = 'Arianto Gunawan';

    /**
     * @return array{
     *     terms_of_payment: string,
     *     pay_to: string,
     *     signatory_name: string,
     *     default_template: string,
     *     signature_path: ?string,
     *     signature_url: ?string,
     * }
     */
    public function defaults(): array
    {
        return [
            'terms_of_payment' => (string) (Setting::getValue(self::SETTING_TERMS) ?? self::DEFAULT_TERMS),
            'pay_to' => (string) (Setting::getValue(self::SETTING_PAY_TO) ?? self::DEFAULT_PAY_TO),
            'signatory_name' => (string) (Setting::getValue(self::SETTING_SIGNATORY) ?? self::DEFAULT_SIGNATORY),
            'default_template' => (string) (Setting::getValue(self::SETTING_TEMPLATE) ?? StandaloneInvoice::TEMPLATE_CLASSIC),
            'signature_path' => $this->signatureDiskPath(),
            'signature_url' => $this->signaturePublicUrl(),
        ];
    }

    /**
     * @return list<string>
     */
    public function termsBullets(?string $terms): array
    {
        $lines = preg_split('/\R+/', (string) $terms) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $line) => $line !== ''));
    }

    /**
     * @return array{bank: string, account_number: string, account_name: string}
     */
    public function parsePayTo(?string $payTo): array
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\R+/', (string) $payTo) ?: []),
            fn (string $line) => $line !== ''
        ));

        return [
            'bank' => $lines[0] ?? '',
            'account_number' => $lines[1] ?? '',
            'account_name' => $lines[2] ?? '',
        ];
    }

    public function signatureDiskPath(): ?string
    {
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $path = public_path(self::SIGNATURE_RELATIVE_PATH.'.'.$ext);
            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }

    public function signaturePublicUrl(): ?string
    {
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $relative = self::SIGNATURE_RELATIVE_PATH.'.'.$ext;
            if (File::exists(public_path($relative))) {
                return asset($relative);
            }
        }

        return null;
    }

    /**
     * @param  array{
     *     terms_of_payment?: ?string,
     *     pay_to?: ?string,
     *     signatory_name?: ?string,
     *     default_template?: ?string,
     * }  $data
     */
    public function updateDefaults(array $data, ?UploadedFile $signature = null): void
    {
        if (array_key_exists('terms_of_payment', $data)) {
            $this->upsertSetting(self::SETTING_TERMS, 'Invoice Maker — Terms of Payment', $data['terms_of_payment'] ?? '');
        }
        if (array_key_exists('pay_to', $data)) {
            $this->upsertSetting(self::SETTING_PAY_TO, 'Invoice Maker — Pay To', $data['pay_to'] ?? '');
        }
        if (array_key_exists('signatory_name', $data)) {
            $this->upsertSetting(self::SETTING_SIGNATORY, 'Invoice Maker — Signatory Name', $data['signatory_name'] ?? '');
        }
        if (array_key_exists('default_template', $data)) {
            $this->upsertSetting(self::SETTING_TEMPLATE, 'Invoice Maker — Default Template', $data['default_template'] ?? StandaloneInvoice::TEMPLATE_CLASSIC);
        }

        if ($signature) {
            $this->updateSignature($signature);
        }
    }

    public function updateSignature(UploadedFile $signature): void
    {
        File::ensureDirectoryExists(public_path('asset'));
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $old = public_path(self::SIGNATURE_RELATIVE_PATH.'.'.$ext);
            if (File::exists($old)) {
                File::delete($old);
            }
        }

        $extension = strtolower($signature->getClientOriginalExtension() ?: 'png');
        if (! in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $extension = 'png';
        }

        $signature->move(public_path('asset'), 'invoice-signature.'.$extension);
        $this->upsertSetting('invoice_signature_path', 'Invoice Signature Path', self::SIGNATURE_RELATIVE_PATH.'.'.$extension);
    }

    private function upsertSetting(string $slug, string $name, mixed $value): void
    {
        Setting::updateOrCreate(
            ['slug' => $slug],
            ['group' => 'Invoice', 'name' => $name, 'value' => $value]
        );
    }
}
