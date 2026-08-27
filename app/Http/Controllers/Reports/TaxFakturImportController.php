<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Addrbook;
use App\Models\Report;
use App\Models\ReportingEntity;
use App\Models\TaxFakturImport;
use App\Services\Reporting\TaxReportService;
use App\Services\Tax\ExpectedPaymentDateCalculator;
use App\Services\Tax\FakturCashInMatcher;
use App\Services\Tax\FakturLineItemMatcher;
use App\Services\Tax\FakturPajakDirectionResolver;
use App\Services\Tax\FakturPajakPdfParser;
use App\Services\Tax\ParsedFakturPajak;
use App\Services\Tax\PostFakturSell;
use App\Services\Tax\TaxFakturImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TaxFakturImportController extends Controller
{
    private const SESSION_KEY = 'tax_faktur_import_preview';

    public function index(Request $request)
    {
        Gate::authorize(Report::getPermissions()['view-tax-faktur']);

        $filters = [
            'year' => $request->query('year'),
            'month' => $request->query('month'),
            'entity' => $request->query('entity'),
            'direction' => $request->query('direction'),
            'overdue' => $request->query('overdue') === '1',
        ];

        $imports = TaxFakturImport::query()
            ->select('tax_faktur_imports.*')
            ->with(['reportingEntity', 'counterparty', 'varianceExpenseAccount', 'user'])
            ->when($filters['overdue'], fn ($query) => $query->paymentOverdue())
            ->when($filters['year'], fn ($query, $year) => $query->where('report_year', (int) $year))
            ->when($filters['month'], fn ($query, $month) => $query->where('report_month', (int) $month))
            ->when($filters['entity'], fn ($query, $entity) => $query->where('reporting_entity_id', (int) $entity))
            ->when($filters['direction'], fn ($query, $direction) => $query->where('direction', $direction))
            ->orderByDesc('faktur_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $entities = ReportingEntity::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('reports.tax.faktur.index', [
            'imports' => $imports,
            'filters' => $filters,
            'entities' => $entities,
            'years' => range((int) date('Y'), TaxReportService::MIN_YEAR),
            'canImport' => $request->user()?->can(Report::getPermissions()['import-tax-faktur']) ?? false,
        ]);
    }

    public function show(TaxFakturImport $import)
    {
        Gate::authorize(Report::getPermissions()['view-tax-faktur']);

        $import->load(['reportingEntity', 'counterparty', 'varianceExpenseAccount', 'user', 'cashInTransaction', 'varianceTransaction', 'sellTransaction']);

        $lineItemMatches = app(FakturLineItemMatcher::class)->propose($import->line_items ?? []);

        return view('reports.tax.faktur.show', [
            'import' => $import,
            'hasPdf' => $this->resolvePdfPath($import) !== null,
            'canImport' => request()->user()?->can(Report::getPermissions()['import-tax-faktur']) ?? false,
            'expenseAccounts' => Addrbook::query()
                ->where('type', Addrbook::TYPE_ACCOUNT)
                ->orderBy('name')
                ->get(['id', 'name']),
            'warehouses' => Addrbook::query()
                ->whereIn('type', [Addrbook::TYPE_WAREHOUSE, Addrbook::TYPE_V_WAREHOUSE])
                ->orderBy('name')
                ->get(['id', 'name', 'type']),
            'items' => \App\Models\Item::query()
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'name', 'code', 'pcode']),
            'lineItemMatches' => $lineItemMatches,
        ]);
    }

    public function downloadPdf(TaxFakturImport $import): BinaryFileResponse
    {
        Gate::authorize(Report::getPermissions()['view-tax-faktur']);

        $absolutePath = $this->resolvePdfPath($import);
        if ($absolutePath === null) {
            abort(404);
        }

        return response()->download(
            $absolutePath,
            'faktur-'.$import->faktur_number.'.pdf',
        );
    }

    public function create()
    {
        Gate::authorize(Report::getPermissions()['import-tax-faktur']);

        return view('reports.tax.faktur.create');
    }

    public function parse(
        Request $request,
        FakturPajakPdfParser $parser,
        FakturPajakDirectionResolver $directionResolver,
        ExpectedPaymentDateCalculator $paymentDates,
    ) {
        Gate::authorize(Report::getPermissions()['import-tax-faktur']);

        $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $path = $request->file('pdf')->store('tax-faktur-imports', 'local');
        $absolutePath = storage_path('app/private/'.$path);
        if (! is_file($absolutePath)) {
            $absolutePath = storage_path('app/'.$path);
        }

        $parsed = $parser->parseFile($absolutePath);
        $suggestion = $directionResolver->suggest($parsed);

        $counterpartyNpwp = $suggestion['direction'] === TaxFakturImport::DIRECTION_KELUARAN
            ? $parsed->buyerNpwp
            : $parsed->sellerNpwp;

        $counterpartyGuess = Addrbook::query()
            ->whereIn('type', [
                Addrbook::TYPE_CUSTOMER,
                Addrbook::TYPE_RESELLER,
                Addrbook::TYPE_SUPPLIER,
            ])
            ->whereRaw('REPLACE(REPLACE(REPLACE(npwp, ".", ""), "-", ""), " ", "") = ?', [
                preg_replace('/\D+/', '', $counterpartyNpwp),
            ])
            ->first();

        $expectedPaymentDate = $counterpartyGuess && $counterpartyGuess->payment_due_day
            ? $paymentDates->fromFakturDate($parsed->fakturDate ?? now(), (int) $counterpartyGuess->payment_due_day)?->toDateString()
            : null;

        session([
            self::SESSION_KEY => [
                'pdf_path' => $path,
                'parsed' => $this->serializeParsed($parsed),
                'suggestion' => $suggestion,
                'counterparty_guess_id' => $counterpartyGuess?->id,
                'expected_payment_date' => $expectedPaymentDate,
            ],
        ]);

        return redirect()->route('reports.tax.faktur.review');
    }

    public function review(FakturCashInMatcher $cashInMatcher)
    {
        Gate::authorize(Report::getPermissions()['import-tax-faktur']);

        $preview = session(self::SESSION_KEY);
        if (! $preview) {
            return redirect()->route('reports.tax.faktur.create')
                ->with('error', 'Upload a PDF first.');
        }

        $parsed = $this->hydrateParsed($preview['parsed']);
        $counterpartyGuessId = (int) old('counterparty_id', $preview['counterparty_guess_id'] ?? 0);
        $entityId = (int) old('reporting_entity_id', $preview['suggestion']['reporting_entity_id'] ?? 0);
        $cashInSuggestions = $counterpartyGuessId > 0
            ? $cashInMatcher->suggest(
                $counterpartyGuessId,
                $entityId > 0 ? $entityId : null,
                null,
                null,
                $parsed->fakturNumber,
            )->all()
            : [];

        return view('reports.tax.faktur.review', [
            'parsed' => $parsed,
            'suggestion' => $preview['suggestion'],
            'pdfPath' => $preview['pdf_path'],
            'entities' => ReportingEntity::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'is_pkp']),
            'customers' => Addrbook::query()
                ->whereIn('type', [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER])
                ->orderBy('name')
                ->get(['id', 'name', 'npwp', 'payment_due_day', 'payment_grace_days']),
            'suppliers' => Addrbook::query()
                ->where('type', Addrbook::TYPE_SUPPLIER)
                ->orderBy('name')
                ->get(['id', 'name', 'npwp', 'payment_due_day', 'payment_grace_days']),
            'expenseAccounts' => Addrbook::query()
                ->where('type', Addrbook::TYPE_ACCOUNT)
                ->orderBy('name')
                ->get(['id', 'name']),
            'counterpartyGuessId' => $preview['counterparty_guess_id'],
            'expectedPaymentDate' => $preview['expected_payment_date'],
            'cashInSuggestions' => $cashInSuggestions,
        ]);
    }

    public function cashInSuggestions(Request $request, FakturCashInMatcher $cashInMatcher)
    {
        Gate::authorize(Report::getPermissions()['import-tax-faktur']);

        $data = $request->validate([
            'counterparty_id' => ['required', 'integer', 'exists:customers,id'],
            'reporting_entity_id' => ['nullable', 'integer', 'exists:reporting_entities,id'],
            'payment_received_amount' => ['nullable', 'numeric'],
            'payment_received_date' => ['nullable', 'date'],
            'faktur_number' => ['nullable', 'string', 'max:20'],
            'exclude_import_id' => ['nullable', 'integer', 'exists:tax_faktur_imports,id'],
        ]);

        return response()->json([
            'suggestions' => $cashInMatcher->suggest(
                (int) $data['counterparty_id'],
                isset($data['reporting_entity_id']) ? (int) $data['reporting_entity_id'] : null,
                isset($data['payment_received_amount']) ? (float) $data['payment_received_amount'] : null,
                $data['payment_received_date'] ?? null,
                $data['faktur_number'] ?? null,
                isset($data['exclude_import_id']) ? (int) $data['exclude_import_id'] : null,
            )->values(),
        ]);
    }

    public function store(Request $request, TaxFakturImportService $importService)
    {
        Gate::authorize(Report::getPermissions()['import-tax-faktur']);

        $preview = session(self::SESSION_KEY);
        if (! $preview) {
            return redirect()->route('reports.tax.faktur.create')
                ->with('error', 'Session expired — upload the PDF again.');
        }

        $data = $request->validate([
            'direction' => ['required', 'in:keluaran,masukan'],
            'reporting_entity_id' => ['required', 'integer', 'exists:reporting_entities,id'],
            'counterparty_id' => ['required', 'integer', 'exists:customers,id'],
            'payment_received_amount' => ['nullable', 'numeric'],
            'payment_received_date' => ['nullable', 'date'],
            'cash_in_transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
            'variance_expense_addrbook_id' => ['nullable', 'integer', 'exists:customers,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $parsed = $this->hydrateParsed($preview['parsed']);

        try {
            $import = $importService->storeFromParsed(
                $parsed,
                $data,
                $preview['pdf_path'],
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        session()->forget(self::SESSION_KEY);

        return redirect()
            ->route('reports.tax.faktur.show', $import)
            ->with('success', "Faktur {$import->faktur_number} imported for PPN reporting.");
    }

    public function updatePayment(Request $request, TaxFakturImport $import, TaxFakturImportService $importService)
    {
        Gate::authorize(Report::getPermissions()['import-tax-faktur']);

        $data = $request->validate([
            'payment_received_amount' => ['nullable', 'numeric'],
            'payment_received_date' => ['nullable', 'date'],
            'cash_in_transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
            'variance_expense_addrbook_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);

        try {
            if (array_key_exists('cash_in_transaction_id', $data)) {
                $import = $importService->linkCashIn(
                    $import,
                    $data['cash_in_transaction_id'] ? (int) $data['cash_in_transaction_id'] : null,
                );
            }

            if (isset($data['payment_received_amount']) && $data['payment_received_amount'] !== null && $data['payment_received_amount'] !== '') {
                $import = $importService->recordPayment(
                    $import->fresh(),
                    (float) $data['payment_received_amount'],
                    $data['payment_received_date'] ?? null,
                    isset($data['cash_in_transaction_id']) && $data['cash_in_transaction_id']
                        ? (int) $data['cash_in_transaction_id']
                        : $import->cash_in_transaction_id,
                    isset($data['variance_expense_addrbook_id']) && $data['variance_expense_addrbook_id']
                        ? (int) $data['variance_expense_addrbook_id']
                        : $import->variance_expense_addrbook_id,
                );
            } elseif (isset($data['variance_expense_addrbook_id'])) {
                $import->variance_expense_addrbook_id = $data['variance_expense_addrbook_id'] ?: null;
                $import->save();
                app(\App\Services\Tax\PostFakturPaymentVariance::class)->execute($import->fresh());
            }
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('reports.tax.faktur.show', $import)
            ->with('success', 'Pembayaran faktur diperbarui.');
    }

    public function postSell(Request $request, TaxFakturImport $import, PostFakturSell $postFakturSell)
    {
        Gate::authorize(Report::getPermissions()['import-tax-faktur']);

        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:customers,id'],
            'date_source' => ['required', 'in:faktur,cash_in'],
            'invoice_source' => ['required', 'in:faktur,cash_in'],
            'line_mode' => ['required', 'in:summary,mapped'],
            'summary_item_id' => ['nullable', 'integer', 'exists:items,id'],
            'mapped_lines' => ['nullable', 'array'],
            'mapped_lines.*.line_no' => ['required_with:mapped_lines', 'integer', 'min:1'],
            'mapped_lines.*.item_id' => ['required_with:mapped_lines', 'integer', 'exists:items,id'],
        ]);

        if ($data['line_mode'] === PostFakturSell::LINE_MODE_SUMMARY && empty($data['summary_item_id'])) {
            return back()->withInput()->with('error', 'Select an item for the summary Sell line.');
        }

        try {
            $transaction = $postFakturSell->execute($import, [
                'warehouse_id' => (int) $data['warehouse_id'],
                'date_source' => $data['date_source'],
                'invoice_source' => $data['invoice_source'],
                'line_mode' => $data['line_mode'],
                'summary_item_id' => isset($data['summary_item_id']) ? (int) $data['summary_item_id'] : null,
                'mapped_lines' => $data['mapped_lines'] ?? [],
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('reports.tax.faktur.show', $import->fresh())
            ->with('success', "Sell #{$transaction->id} posted from faktur {$import->faktur_number}.");
    }

    public function lineItemMatches(TaxFakturImport $import, FakturLineItemMatcher $matcher)
    {
        Gate::authorize(Report::getPermissions()['import-tax-faktur']);

        return response()->json([
            'lines' => $matcher->propose($import->line_items ?? []),
        ]);
    }

    private function resolvePdfPath(TaxFakturImport $import): ?string
    {
        if (! $import->pdf_path) {
            return null;
        }

        $privatePath = storage_path('app/private/'.$import->pdf_path);
        if (is_file($privatePath)) {
            return $privatePath;
        }

        $defaultPath = storage_path('app/'.$import->pdf_path);
        if (is_file($defaultPath)) {
            return $defaultPath;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeParsed(ParsedFakturPajak $parsed): array
    {
        return [
            'faktur_number' => $parsed->fakturNumber,
            'faktur_date' => $parsed->fakturDate?->toDateString(),
            'faktur_date_place' => $parsed->fakturDatePlace,
            'seller_name' => $parsed->sellerName,
            'seller_npwp' => $parsed->sellerNpwp,
            'buyer_name' => $parsed->buyerName,
            'buyer_npwp' => $parsed->buyerNpwp,
            'gross_total' => $parsed->grossTotal,
            'discount_total' => $parsed->discountTotal,
            'dpp' => $parsed->dpp,
            'ppn' => $parsed->ppn,
            'ppnbm' => $parsed->ppnbm,
            'signatory_name' => $parsed->signatoryName,
            'source_format' => $parsed->sourceFormat,
            'line_items' => $parsed->lineItems,
        ];
    }

    private function hydrateParsed(array $data): ParsedFakturPajak
    {
        return new ParsedFakturPajak(
            fakturNumber: $data['faktur_number'],
            fakturDate: $data['faktur_date'] ? \Illuminate\Support\Carbon::parse($data['faktur_date']) : null,
            fakturDatePlace: $data['faktur_date_place'],
            sellerName: $data['seller_name'],
            sellerNpwp: $data['seller_npwp'],
            buyerName: $data['buyer_name'],
            buyerNpwp: $data['buyer_npwp'],
            grossTotal: (float) $data['gross_total'],
            discountTotal: (float) $data['discount_total'],
            dpp: (float) $data['dpp'],
            ppn: (float) $data['ppn'],
            ppnbm: (float) $data['ppnbm'],
            signatoryName: $data['signatory_name'],
            sourceFormat: $data['source_format'],
            lineItems: $data['line_items'] ?? [],
        );
    }
}
