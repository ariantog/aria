<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\StandaloneInvoice;
use App\Services\InvoiceMakerSettingsService;
use App\Services\StandaloneInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StandaloneInvoicesController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize(StandaloneInvoice::getPermissions()['view']);

        $query = StandaloneInvoice::query()->with(['sender', 'user']);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('recipient', 'like', "%{$search}%");
            });
        }

        return view('invoice-maker.index', [
            'invoices' => $query->orderByDesc('date')->orderByDesc('id')->paginate(25)->withQueryString(),
            'search' => $search ?? '',
            'can' => $this->permissions(),
        ]);
    }

    public function create(InvoiceMakerSettingsService $settingsService)
    {
        Gate::authorize(StandaloneInvoice::getPermissions()['create']);

        return view('invoice-maker.form', [
            'invoice' => null,
            'presets' => $settingsService->presets(),
            'selectedPreset' => $settingsService->defaultPreset(),
            'warehouseLookupUrl' => route('transactions.lookup', ['type' => 'sell', 'role' => 'sender']).'&addrbook_type='.Addrbook::TYPE_WAREHOUSE,
            'can' => $this->permissions(),
        ]);
    }

    public function store(Request $request, StandaloneInvoiceService $service)
    {
        Gate::authorize(StandaloneInvoice::getPermissions()['create']);

        [$data, $lines] = $this->validatedPayload($request);

        $invoice = $service->create($data, $lines, (int) $request->user()->id);

        return redirect()
            ->route('invoice-maker.show', $invoice)
            ->with('success', 'Invoice created.');
    }

    public function show(StandaloneInvoice $invoice, StandaloneInvoiceService $service)
    {
        Gate::authorize(StandaloneInvoice::getPermissions()['view']);

        $invoice->load(['lines', 'sender', 'user']);

        return view('invoice-maker.show', [
            'invoice' => $invoice,
            'hasInvoicePdf' => $service->invoicePdfExists($invoice),
            'invoicePdfUrl' => $service->invoicePdfUrl($invoice),
            'invoicePdfDownloadUrl' => $service->invoicePdfDownloadUrl($invoice),
            'can' => $this->permissions(),
        ]);
    }

    public function edit(StandaloneInvoice $invoice, InvoiceMakerSettingsService $settingsService)
    {
        Gate::authorize(StandaloneInvoice::getPermissions()['edit']);

        $invoice->load(['lines', 'sender']);
        $selectedPreset = $invoice->preset_id
            ? $settingsService->findPreset($invoice->preset_id)
            : null;

        return view('invoice-maker.form', [
            'invoice' => $invoice,
            'presets' => $settingsService->presets(),
            'selectedPreset' => $selectedPreset ?? $settingsService->defaultPreset(),
            'warehouseLookupUrl' => route('transactions.lookup', ['type' => 'sell', 'role' => 'sender']).'&addrbook_type='.Addrbook::TYPE_WAREHOUSE,
            'can' => $this->permissions(),
        ]);
    }

    public function update(Request $request, StandaloneInvoice $invoice, StandaloneInvoiceService $service)
    {
        Gate::authorize(StandaloneInvoice::getPermissions()['edit']);

        [$data, $lines] = $this->validatedPayload($request, $invoice);

        $service->update($invoice, $data, $lines);

        return redirect()
            ->route('invoice-maker.show', $invoice)
            ->with('success', 'Invoice updated.');
    }

    public function destroy(StandaloneInvoice $invoice, StandaloneInvoiceService $service)
    {
        Gate::authorize(StandaloneInvoice::getPermissions()['delete']);

        $fileName = $service->invoiceFileName($invoice);
        $filePath = $service->invoiceDiskPath($fileName);
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $invoice->delete();

        return redirect()
            ->route('invoice-maker.index')
            ->with('success', 'Invoice deleted.');
    }

    public function showPdf(StandaloneInvoice $invoice, StandaloneInvoiceService $service)
    {
        Gate::authorize(StandaloneInvoice::getPermissions()['view']);
        abort_unless($service->invoicePdfExists($invoice), 404);

        $filePath = $service->invoiceDiskPath($service->invoiceFileName($invoice));

        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$service->invoiceFileName($invoice).'"',
        ]);
    }

    public function downloadPdf(StandaloneInvoice $invoice, StandaloneInvoiceService $service)
    {
        Gate::authorize(StandaloneInvoice::getPermissions()['view']);
        abort_unless($service->invoicePdfExists($invoice), 404);

        $filePath = $service->invoiceDiskPath($service->invoiceFileName($invoice));
        $fileName = $service->invoiceDownloadFileName($invoice);

        return response()->download($filePath, $fileName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function storePdf(StandaloneInvoice $invoice, StandaloneInvoiceService $service)
    {
        Gate::authorize(StandaloneInvoice::getPermissions()['view']);

        $existed = $service->invoicePdfExists($invoice);
        $service->createInvoicePdf($invoice, regenerate: true);

        return redirect()
            ->route('invoice-maker.show', $invoice)
            ->with('success', $existed ? 'Invoice PDF regenerated.' : 'Invoice PDF saved.');
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<array{description: string, quantity: float|int|string, price: float|int|string}>}
     */
    protected function validatedPayload(Request $request, ?StandaloneInvoice $existing = null): array
    {
        $validated = $request->validate([
            'number' => ['required', 'string', 'max:100'],
            'date' => ['required', 'date'],
            'recipient' => ['required', 'string', 'max:5000'],
            'sender_addrbook_id' => ['nullable', 'integer', 'exists:customers,id'],
            'preset_id' => ['required', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'dp_enabled' => ['nullable', 'boolean'],
            'dp_amount' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $lines = array_values($validated['lines']);
        $subtotal = app(StandaloneInvoiceService::class)->calculateLineTotals($lines)['subtotal'];
        $dpEnabled = $request->boolean('dp_enabled');

        if ($dpEnabled) {
            $dpAmount = $validated['dp_amount'] ?? null;
            if ($dpAmount === null || (float) $dpAmount <= 0) {
                throw ValidationException::withMessages([
                    'dp_amount' => 'Down payment amount is required when DP is enabled.',
                ]);
            }
            if ((float) $dpAmount > $subtotal) {
                throw ValidationException::withMessages([
                    'dp_amount' => 'Down payment cannot exceed the subtotal.',
                ]);
            }
            $validated['dp_amount'] = $dpAmount;
        } else {
            $validated['dp_amount'] = null;
        }

        unset($validated['dp_enabled']);

        $preset = app(InvoiceMakerSettingsService::class)->findPreset($validated['preset_id']);
        abort_unless($preset, 422, 'Selected invoice preset was not found.');

        $data = collect($validated)->except('lines')->all();
        $data['template'] = $preset['template'];
        $data['terms_of_payment'] = $preset['terms_of_payment'];
        $data['pay_to'] = $preset['pay_to'];
        $data['signatory_name'] = $preset['signatory_name'];
        $data['signature_path'] = $preset['signature_path'];
        $data['logo_path'] = $preset['logo_path'] ?? null;

        if (! $existing && empty($data['number'])) {
            $data['number'] = StandaloneInvoice::generateNumber($data['date']);
        }

        return [$data, $lines];
    }

    /**
     * @return array{create: bool, edit: bool, delete: bool}
     */
    protected function permissions(): array
    {
        $user = request()->user();

        return [
            'create' => $user?->can(StandaloneInvoice::getPermissions()['create']) ?? false,
            'edit' => $user?->can(StandaloneInvoice::getPermissions()['edit']) ?? false,
            'delete' => $user?->can(StandaloneInvoice::getPermissions()['delete']) ?? false,
            'settings' => $user?->can(StandaloneInvoice::getPermissions()['edit']) ?? false,
        ];
    }
}
