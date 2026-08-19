<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\StandaloneInvoice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InvoiceMakerSettingsService
{
    public const SETTING_PRESETS = 'invoice_maker.presets';

    public const SETTING_DEFAULT_PRESET_ID = 'invoice_maker.default_preset_id';

    public const SIGNATURE_DIRECTORY = 'asset/invoice-signatures';

    private const LEGACY_SETTING_TERMS = 'invoice_maker.terms_of_payment';

    private const LEGACY_SETTING_PAY_TO = 'invoice_maker.pay_to';

    private const LEGACY_SETTING_SIGNATORY = 'invoice_maker.signatory_name';

    private const LEGACY_SETTING_TEMPLATE = 'invoice_maker.default_template';

    private const DEFAULT_TERMS = "Pembayaran lunas sebelum barang dikirim.\nHarga belum termasuk PPN 11%.";

    private const DEFAULT_PAY_TO = "BCA\n5105251588\nCV ACTIVEWEAR GLOBAL MANDIRI";

    private const DEFAULT_SIGNATORY = 'Arianto Gunawan';

    /**
     * @return list<array{
     *     id: string,
     *     name: string,
     *     terms_of_payment: string,
     *     pay_to: string,
     *     signatory_name: string,
     *     signature_path: ?string,
     *     signature_url: ?string,
     *     template: string,
     * }>
     */
    public function presets(): array
    {
        $stored = Setting::getValue(self::SETTING_PRESETS);
        if (is_array($stored) && $stored !== []) {
            return array_map(fn (array $preset) => $this->hydratePresetUrls($preset), $stored);
        }

        $legacy = $this->buildLegacyPreset();
        $this->persistPresets([$legacy], $legacy['id']);

        return [$this->hydratePresetUrls($legacy)];
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     terms_of_payment: string,
     *     pay_to: string,
     *     signatory_name: string,
     *     signature_path: ?string,
     *     signature_url: ?string,
     *     template: string,
     * }
     */
    public function defaultPreset(): array
    {
        $presets = $this->presets();
        $defaultId = (string) (Setting::getValue(self::SETTING_DEFAULT_PRESET_ID) ?? '');

        if ($defaultId !== '') {
            $match = $this->findPreset($defaultId);
            if ($match) {
                return $match;
            }
        }

        return $presets[0];
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     terms_of_payment: string,
     *     pay_to: string,
     *     signatory_name: string,
     *     signature_path: ?string,
     *     signature_url: ?string,
     *     template: string,
     * }|null
     */
    public function findPreset(string $id): ?array
    {
        foreach ($this->presets() as $preset) {
            if ($preset['id'] === $id) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * @param  array{
     *     name: string,
     *     terms_of_payment: string,
     *     pay_to: string,
     *     signatory_name: string,
     *     template: string,
     * }  $data
     */
    public function createPreset(array $data, ?UploadedFile $signature = null): array
    {
        $preset = [
            'id' => $this->generatePresetId($data['name']),
            'name' => trim($data['name']),
            'terms_of_payment' => $data['terms_of_payment'] ?? '',
            'pay_to' => $data['pay_to'] ?? '',
            'signatory_name' => $data['signatory_name'] ?? '',
            'signature_path' => null,
            'template' => $data['template'] ?? StandaloneInvoice::TEMPLATE_CLASSIC,
        ];

        if ($signature) {
            $preset['signature_path'] = $this->storeSignature($preset['id'], $signature);
        }

        $presets = $this->presets();
        $presets[] = $preset;
        $existingDefault = (string) (Setting::getValue(self::SETTING_DEFAULT_PRESET_ID) ?? '');
        $defaultId = $existingDefault !== '' ? $existingDefault : $preset['id'];
        $this->persistPresets($presets, $defaultId);

        return $this->hydratePresetUrls($preset);
    }

    /**
     * @param  array{
     *     name: string,
     *     terms_of_payment: string,
     *     pay_to: string,
     *     signatory_name: string,
     *     template: string,
     * }  $data
     */
    public function updatePreset(string $id, array $data, ?UploadedFile $signature = null): array
    {
        $presets = $this->presets();
        $updated = null;

        foreach ($presets as $index => $preset) {
            if ($preset['id'] !== $id) {
                continue;
            }

            $preset['name'] = trim($data['name']);
            $preset['terms_of_payment'] = $data['terms_of_payment'] ?? '';
            $preset['pay_to'] = $data['pay_to'] ?? '';
            $preset['signatory_name'] = $data['signatory_name'] ?? '';
            $preset['template'] = $data['template'] ?? StandaloneInvoice::TEMPLATE_CLASSIC;

            if ($signature) {
                $this->deleteSignatureFiles($preset['id']);
                $preset['signature_path'] = $this->storeSignature($preset['id'], $signature);
            }

            $presets[$index] = $preset;
            $updated = $preset;
            break;
        }

        abort_unless($updated, 404);

        $this->persistPresets($presets, (string) (Setting::getValue(self::SETTING_DEFAULT_PRESET_ID) ?? $updated['id']));

        return $this->hydratePresetUrls($updated);
    }

    public function deletePreset(string $id): void
    {
        $presets = array_values(array_filter(
            $this->presets(),
            fn (array $preset) => $preset['id'] !== $id
        ));

        abort_if($presets === [], 422, 'At least one invoice preset must remain.');

        $this->deleteSignatureFiles($id);

        $defaultId = (string) (Setting::getValue(self::SETTING_DEFAULT_PRESET_ID) ?? '');
        if ($defaultId === $id) {
            $defaultId = $presets[0]['id'];
        }

        $this->persistPresets($presets, $defaultId);
    }

    public function setDefaultPreset(string $id): void
    {
        abort_unless($this->findPreset($id), 404);

        $this->upsertSetting(self::SETTING_DEFAULT_PRESET_ID, 'Invoice Maker — Default Preset', $id);
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

    public function signatureDiskPath(?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }

        $path = public_path($relativePath);

        return File::exists($path) ? $path : null;
    }

    public function signaturePublicUrl(?string $relativePath): ?string
    {
        if (! $relativePath || ! File::exists(public_path($relativePath))) {
            return null;
        }

        return asset($relativePath);
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     terms_of_payment: string,
     *     pay_to: string,
     *     signatory_name: string,
     *     signature_path: ?string,
     *     template: string,
     * }
     */
    private function buildLegacyPreset(): array
    {
        $legacySignature = $this->migrateLegacySignatureIfNeeded();

        return [
            'id' => 'default',
            'name' => 'Default',
            'terms_of_payment' => (string) (Setting::getValue(self::LEGACY_SETTING_TERMS) ?? self::DEFAULT_TERMS),
            'pay_to' => (string) (Setting::getValue(self::LEGACY_SETTING_PAY_TO) ?? self::DEFAULT_PAY_TO),
            'signatory_name' => (string) (Setting::getValue(self::LEGACY_SETTING_SIGNATORY) ?? self::DEFAULT_SIGNATORY),
            'signature_path' => $legacySignature,
            'template' => (string) (Setting::getValue(self::LEGACY_SETTING_TEMPLATE) ?? StandaloneInvoice::TEMPLATE_CLASSIC),
        ];
    }

    private function migrateLegacySignatureIfNeeded(): ?string
    {
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $legacy = public_path('asset/invoice-signature.'.$ext);
            if (! File::exists($legacy)) {
                continue;
            }

            File::ensureDirectoryExists(public_path(self::SIGNATURE_DIRECTORY));
            $relative = self::SIGNATURE_DIRECTORY.'/default.'.$ext;
            $target = public_path($relative);

            if (! File::exists($target)) {
                File::copy($legacy, $target);
            }

            return $relative;
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $presets
     */
    private function persistPresets(array $presets, string $defaultPresetId): void
    {
        $normalized = array_map(function (array $preset) {
            return [
                'id' => (string) $preset['id'],
                'name' => (string) $preset['name'],
                'terms_of_payment' => (string) ($preset['terms_of_payment'] ?? ''),
                'pay_to' => (string) ($preset['pay_to'] ?? ''),
                'signatory_name' => (string) ($preset['signatory_name'] ?? ''),
                'signature_path' => $preset['signature_path'] ?? null,
                'template' => (string) ($preset['template'] ?? StandaloneInvoice::TEMPLATE_CLASSIC),
            ];
        }, array_values($presets));

        $this->upsertSetting(self::SETTING_PRESETS, 'Invoice Maker Presets', $normalized);

        if ($defaultPresetId === '' || ! collect($normalized)->contains(fn (array $preset) => $preset['id'] === $defaultPresetId)) {
            $defaultPresetId = $normalized[0]['id'] ?? 'default';
        }

        $this->upsertSetting(self::SETTING_DEFAULT_PRESET_ID, 'Invoice Maker — Default Preset', $defaultPresetId);
    }

    /**
     * @param  array<string, mixed>  $preset
     * @return array<string, mixed>
     */
    private function hydratePresetUrls(array $preset): array
    {
        $preset['signature_url'] = $this->signaturePublicUrl($preset['signature_path'] ?? null);

        return $preset;
    }

    private function generatePresetId(string $name): string
    {
        $base = Str::slug($name) ?: 'preset';
        $id = $base;
        $suffix = 1;

        while ($this->findPreset($id)) {
            $id = $base.'-'.$suffix;
            $suffix++;
        }

        return $id;
    }

    private function storeSignature(string $presetId, UploadedFile $signature): string
    {
        File::ensureDirectoryExists(public_path(self::SIGNATURE_DIRECTORY));

        $extension = strtolower($signature->getClientOriginalExtension() ?: 'png');
        if (! in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $extension = 'png';
        }

        $relative = self::SIGNATURE_DIRECTORY.'/'.$presetId.'.'.$extension;
        $signature->move(public_path(self::SIGNATURE_DIRECTORY), $presetId.'.'.$extension);

        return $relative;
    }

    private function deleteSignatureFiles(string $presetId): void
    {
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $path = public_path(self::SIGNATURE_DIRECTORY.'/'.$presetId.'.'.$ext);
            if (File::exists($path)) {
                File::delete($path);
            }
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
