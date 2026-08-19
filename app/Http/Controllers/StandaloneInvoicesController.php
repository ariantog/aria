<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\StandaloneInvoice;
use App\Services\InvoiceMakerSettingsService;
use App\Services\StandaloneInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StandaloneInvoicesController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize(StandaloneInvoice::getPermissions()['view']);

        $query = StandaloneInvoice::query()->with(['sender', 'user']);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%");
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

        $defaults = $settingsService->defaults();

        return view('invoice-maker.form', [
            'invoice' => null,
            'defaults' => $defaults,
            'templates' => StandaloneInvoice::TEMPLATES,
            'customerLookupUrl' => route('transactions.lookup', ['type' => 'sell', 'role' => 'receiver']).'&addrbook_type='.Addrbook::TYPE_CUSTOMER,
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

        $invoice->load(['lines', 'sender', 'recipient', 'user']);

        return view('invoice-maker.show', [
            'invoice' => $invoice,
            'hasInvoicePdf' => $service->invoicePdfExists($invoice),
            'invoicePdfUrl' => $service->invoicePdfUrl($invoice),
            'can' => $this->permissions(),
        ]);
    }

    public function edit(StandaloneInvoice $invoice, InvoiceMakerSettingsService $settingsService)
    {
        Gate::authorize(StandaloneInvoice::getPermissions()['edit']);

        $invoice->load(['lines', 'sender', 'recipient']);
        $defaults = $settingsService->defaults();

        return view('invoice-maker.form', [
            'invoice' => $invoice,
            'defaults' => $defaults,
            'templates' => StandaloneInvoice::TEMPLATES,
            'customerLookupUrl' => route('transactions.lookup', ['type' => 'sell', 'role' => 'receiver']).'&addrbook_type='.Addrbook::TYPE_CUSTOMER,
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
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_addrbook_id' => ['nullable', 'integer', 'exists:customers,id'],
            'sender_addrbook_id' => ['nullable', 'integer', 'exists:customers,id'],
            'template' => ['required', 'string', Rule::in(array_keys(StandaloneInvoice::TEMPLATES))],
            'terms_of_payment' => ['nullable', 'string', 'max:5000'],
            'pay_to' => ['nullable', 'string', 'max:1000'],
            'signatory_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $data = collect($validated)->except('lines')->all();

        if (! $existing && empty($data['number'])) {
            $data['number'] = StandaloneInvoice::generateNumber($data['date']);
        }

        $lines = array_values($validated['lines']);

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
        ];
    }
}
