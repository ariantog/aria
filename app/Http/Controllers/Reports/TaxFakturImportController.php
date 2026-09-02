<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Report;
use App\Models\ReportingEntity;
use App\Models\ReportingWarehouseFulfillment;
use App\Models\TaxFakturImport;
use App\Models\User;
use App\Services\Reporting\TaxReportService;
use App\Services\Tax\ExpectedPaymentDateCalculator;
use App\Services\Tax\FakturCashInMatcher;
use App\Services\Tax\FakturLineItemMatcher;
use App\Services\Tax\FakturSellMatcher;
use App\Services\Tax\LinkFakturSells;
use App\Services\Tax\FakturPajakDirectionResolver;
use App\Services\Tax\FakturPajakPdfParser;
use App\Services\Tax\ParsedFakturPajak;
use App\Services\Tax\PostFakturSell;
use App\Services\Tax\TaxFakturImportService;
use App\Services\UserPreferenceService;
use App\Support\LikeSearch;
use Illuminate\Http\JsonResponse;
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

        $link = $request->query('link');
        $linkFilter = in_array($link, [
            TaxFakturImport::LINK_FILTER_UNLINKED,
            TaxFakturImport::LINK_FILTER_REMAINING,
            TaxFakturImport::LINK_FILTER_INCOMPLETE,
        ], true) ? $link : null;

        $filters = [
            'year' => $request->query('year'),
            'month' => $request->query('month'),
            'entity' => $request->query('entity'),
            'direction' => $request->query('direction'),
            'overdue' => $request->query('overdue') === '1',
            'link' => $linkFilter,
        ];

        $imports = TaxFakturImport::query()
            ->select('tax_faktur_imports.*')
            ->with(['reportingEntity', 'counterparty', 'varianceExpenseAccount', 'user', 'sellTransactions'])
            ->when($filters['overdue'], fn ($query) => $query->paymentOverdue())
            ->when($filters['link'], fn ($query, $link) => $query->linkFilter($link))
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

        $import->load(['reportingEntity', 'counterparty', 'varianceExpenseAccount', 'user', 'cashInTransaction', 'varianceTransaction', 'sellTransaction', 'sellTransactions.sender']);

        $lineItemMatches = app(FakturLineItemMatcher::class)->propose($import->line_items ?? []);

        return view('reports.tax.faktur.show', [
            'import' => $import,
            'hasPdf' => $this->resolvePdfPath($import) !== null,
            'canImport' => request()->user()?->can(Report::getPermissions()['import-tax-faktur']) ?? false,
            'expenseAccounts' => Addrbook::query()
                ->where('type', Addrbook::TYPE_ACCOUNT)
                ->orderBy('name')
                ->get(['id', 'name']),
            ...$this->sellFormContext($import->counterparty_id, $lineItemMatches, request()->user()),
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
            ->whereIn('type', Addrbook::fakturCounterpartyTypes())
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
        $counterpartyGuess = $counterpartyGuessId > 0
            ? Addrbook::query()->find($counterpartyGuessId, ['id', 'name', 'type', 'payment_due_day'])
            : null;
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

        $lineItemMatches = app(FakturLineItemMatcher::class)->propose($parsed->lineItems);

        return view('reports.tax.faktur.review', [
            'parsed' => $parsed,
            'suggestion' => $preview['suggestion'],
            'pdfPath' => $preview['pdf_path'],
            'entities' => ReportingEntity::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'is_pkp']),
            'counterpartyGuess' => $counterpartyGuess,
            'consignmentIds' => Addrbook::query()
                ->whereIn('type', [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER])
                ->whereNotNull('payment_due_day')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values(),
            'expenseAccounts' => Addrbook::query()
                ->where('type', Addrbook::TYPE_ACCOUNT)
                ->orderBy('name')
                ->get(['id', 'name']),
            'counterpartyGuessId' => $preview['counterparty_guess_id'],
            'expectedPaymentDate' => $preview['expected_payment_date'],
            'cashInSuggestions' => $cashInSuggestions,
            'sellSuggestions' => $counterpartyGuessId > 0
                ? app(FakturSellMatcher::class)->suggest(
                    $counterpartyGuessId,
                    $parsed->fakturDate?->toDateString(),
                    $parsed->fakturNumber,
                    $parsed->dpp,
                )->all()
                : [],
            ...$this->sellFormContext($counterpartyGuessId ?: null, $lineItemMatches, request()->user()),
            'lineItemMatches' => $lineItemMatches,
        ]);
    }

    public function counterpartyLookup(Request $request): JsonResponse
    {
        Gate::authorize(Report::getPermissions()['import-tax-faktur']);

        $search = trim((string) $request->query('search', ''));
        if (strlen($search) <= 2) {
            return response()->json([]);
        }

        $pattern = LikeSearch::contains($search);
        $results = Addrbook::query()
            ->visibleToUser($request->user())
            ->whereIn('customers.type', Addrbook::fakturCounterpartyTypes())
            ->where(function ($q) use ($pattern) {
                $q->where('customers.name', 'like', $pattern)
                    ->orWhere('customers.id', 'like', $pattern);
            })
            ->leftJoin('customerstat', 'customers.id', '=', 'customerstat.customer_id')
            ->select(
                'customers.id',
                'customers.name',
                'customers.type',
                'customers.payment_due_day',
                'customers.ledger_hint',
                'customerstat.balance'
            )
            ->orderBy('customers.name')
            ->limit(8)
            ->get();

        return response()->json($results);
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

    public function store(Request $request, TaxFakturImportService $importService, PostFakturSell $postFakturSell)
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
            'sell_transaction_ids' => ['nullable', 'array'],
            'sell_transaction_ids.*' => ['integer', 'exists:transactions,id'],
            'post_sell' => ['nullable', 'boolean'],
            ...$this->postSellRules(),
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

        $linkedSellIds = collect($request->input('sell_transaction_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        if ($linkedSellIds !== []) {
            try {
                app(LinkFakturSells::class)->attach($import, $linkedSellIds);
            } catch (InvalidArgumentException $e) {
                return redirect()
                    ->route('reports.tax.faktur.show', $import)
                    ->with('success', "Faktur {$import->faktur_number} imported for PPN reporting.")
                    ->with('error', $e->getMessage());
            }
        }

        if ($request->boolean('post_sell') && $linkedSellIds === []) {
            if (! $request->input('warehouse_id')) {
                return redirect()
                    ->route('reports.tax.faktur.show', $import)
                    ->with('success', "Faktur {$import->faktur_number} imported for PPN reporting.")
                    ->with('error', 'Select a warehouse to post Sell, or post it from this page.');
            }

            return $this->attemptPostSell($request, $import, $postFakturSell, $import->faktur_number);
        }

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

    public function sellSuggestions(Request $request, FakturSellMatcher $sellMatcher)
    {
        Gate::authorize(Report::getPermissions()['import-tax-faktur']);

        $data = $request->validate([
            'counterparty_id' => ['required', 'integer', 'exists:customers,id'],
            'faktur_date' => ['nullable', 'date'],
            'faktur_number' => ['nullable', 'string', 'max:20'],
            'remaining_dpp' => ['nullable', 'numeric'],
            'exclude_import_id' => ['nullable', 'integer', 'exists:tax_faktur_imports,id'],
            'invoice' => ['nullable', 'string', 'max:80'],
        ]);

        return response()->json([
            'suggestions' => $sellMatcher->suggest(
                (int) $data['counterparty_id'],
                $data['faktur_date'] ?? null,
                $data['faktur_number'] ?? null,
                isset($data['remaining_dpp']) ? (float) $data['remaining_dpp'] : null,
                isset($data['exclude_import_id']) ? (int) $data['exclude_import_id'] : null,
                $data['invoice'] ?? null,
            )->values(),
        ]);
    }

    public function linkSells(Request $request, TaxFakturImport $import, LinkFakturSells $linkFakturSells)
    {
        Gate::authorize(Report::getPermissions()['import-tax-faktur']);

        $data = $request->validate([
            'sell_transaction_ids' => ['required', 'array', 'min:1'],
            'sell_transaction_ids.*' => ['integer', 'exists:transactions,id'],
        ]);

        try {
            $linkFakturSells->attach($import, $data['sell_transaction_ids']);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $count = count($data['sell_transaction_ids']);

        return redirect()
            ->route('reports.tax.faktur.show', $import->fresh())
            ->with('success', $count === 1 ? 'Sell di-link ke faktur.' : "{$count} Sell di-link ke faktur.");
    }

    public function unlinkSell(TaxFakturImport $import, int $transaction, LinkFakturSells $linkFakturSells)
    {
        Gate::authorize(Report::getPermissions()['import-tax-faktur']);

        $linkFakturSells->detach($import, $transaction);

        return redirect()
            ->route('reports.tax.faktur.show', $import->fresh())
            ->with('success', 'Sell dilepas dari faktur.');
    }

    public function postSell(Request $request, TaxFakturImport $import, PostFakturSell $postFakturSell)
    {
        Gate::authorize(Report::getPermissions()['import-tax-faktur']);

        $rules = $this->postSellRules(required: true);
        if ($request->input('line_mode') === PostFakturSell::LINE_MODE_MAPPED) {
            $rules['mapped_lines'] = ['required', 'array', 'min:1'];
            $rules['mapped_lines.*.line_no'] = ['required', 'integer', 'min:1'];
            $rules['mapped_lines.*.item_id'] = ['nullable', 'integer', 'exists:items,id'];
        }

        $request->validate($rules);

        return $this->attemptPostSell($request, $import, $postFakturSell, $import->faktur_number);
    }

    public function destroy(TaxFakturImport $import, TaxFakturImportService $importService)
    {
        Gate::authorize(Report::getPermissions()['import-tax-faktur']);

        $fakturNumber = $import->faktur_number;
        $importService->delete($import);

        return redirect()
            ->route('reports.tax.faktur.index')
            ->with('success', "Faktur {$fakturNumber} dihapus dari laporan PPN. Sell dan Cash In terkait tidak dihapus.");
    }

    /**
     * @param  list<array{line_no?: int|string, item_id?: int|string}>  $submitted
     * @return list<array{line_no: int, item_id: int}>|\Illuminate\Http\RedirectResponse
     */
    private function normalizeMappedLines(TaxFakturImport $import, array $submitted)
    {
        $byLineNo = collect($submitted)
            ->filter(fn (array $row) => ! empty($row['line_no']))
            ->keyBy(fn (array $row) => (int) $row['line_no']);

        $missing = [];
        $normalized = [];

        foreach ($import->line_items ?? [] as $index => $line) {
            $lineNo = (int) ($line['line_no'] ?? ($index + 1));
            $mapping = $byLineNo->get($lineNo);
            $itemId = isset($mapping['item_id']) ? (int) $mapping['item_id'] : 0;

            if ($itemId <= 0) {
                $label = trim((string) ($line['name'] ?? ''));
                $missing[] = $label !== '' ? "#{$lineNo} ({$label})" : "#{$lineNo}";

                continue;
            }

            $normalized[] = [
                'line_no' => $lineNo,
                'item_id' => $itemId,
            ];
        }

        if ($missing !== []) {
            return back()
                ->withInput()
                ->withErrors([
                    'mapped_lines' => 'Pilih item inventory untuk baris faktur: '.implode(', ', $missing).'.',
                ]);
        }

        return $normalized;
    }

    public function lineItemMatches(TaxFakturImport $import, FakturLineItemMatcher $matcher)
    {
        Gate::authorize(Report::getPermissions()['import-tax-faktur']);

        return response()->json([
            'lines' => $matcher->propose($import->line_items ?? []),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function postSellRules(bool $required = false): array
    {
        $presence = $required ? 'required' : 'nullable';

        return [
            'warehouse_id' => [$presence, 'integer', 'exists:customers,id'],
            'date_source' => [$presence, 'in:faktur,cash_in'],
            'invoice_source' => [$presence, 'in:faktur,cash_in'],
            'line_mode' => [$presence, 'in:summary,mapped'],
            'summary_item_id' => ['nullable', 'integer', 'exists:items,id'],
        ];
    }

    private function attemptPostSell(
        Request $request,
        TaxFakturImport $import,
        PostFakturSell $postFakturSell,
        string $fakturNumber,
    ) {
        $lineMode = $request->input('line_mode', PostFakturSell::LINE_MODE_SUMMARY);
        if ($lineMode === PostFakturSell::LINE_MODE_SUMMARY && ! $request->input('summary_item_id')) {
            return redirect()
                ->route('reports.tax.faktur.show', $import)
                ->withInput()
                ->with('error', 'Select an item for the summary Sell line.');
        }

        $mappedLines = [];
        if ($lineMode === PostFakturSell::LINE_MODE_MAPPED) {
            $mappedLines = $this->normalizeMappedLines($import, $request->input('mapped_lines', []));
            if ($mappedLines instanceof \Illuminate\Http\RedirectResponse) {
                return $mappedLines;
            }
        }

        try {
            $transaction = $postFakturSell->execute($import, [
                'warehouse_id' => (int) $request->input('warehouse_id'),
                'date_source' => $request->input('date_source', PostFakturSell::DATE_SOURCE_FAKTUR),
                'invoice_source' => $request->input('invoice_source', PostFakturSell::INVOICE_SOURCE_FAKTUR),
                'line_mode' => $lineMode,
                'summary_item_id' => $request->input('summary_item_id') ? (int) $request->input('summary_item_id') : null,
                'mapped_lines' => $mappedLines,
            ]);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('reports.tax.faktur.show', $import->fresh())
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('reports.tax.faktur.show', $import->fresh())
            ->with('success', "Sell #{$transaction->id} posted from faktur {$fakturNumber}.");
    }

    /**
     * @param  list<array{best_match?: array{id: int}|null}>  $lineItemMatches
     * @return array{
     *     warehouses: \Illuminate\Support\Collection<int, Addrbook>,
     *     items: \Illuminate\Support\Collection<int, Item>,
     *     defaultWarehouseId: int|null,
     *     lineItemMatches: list<array<string, mixed>>
     * }
     */
    private function sellFormContext(?int $counterpartyId, array $lineItemMatches, ?User $user): array
    {
        $items = Item::query()
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'code', 'pcode']);

        $matchIds = collect($lineItemMatches)
            ->pluck('best_match.id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $missingIds = $matchIds->diff($items->pluck('id'));
        if ($missingIds->isNotEmpty()) {
            $extra = Item::query()
                ->whereIn('id', $missingIds)
                ->get(['id', 'name', 'code', 'pcode']);
            $items = $items->concat($extra)->unique('id')->sortBy('name')->values();
        }

        return [
            'warehouses' => Addrbook::query()
                ->whereIn('type', [Addrbook::TYPE_WAREHOUSE, Addrbook::TYPE_V_WAREHOUSE])
                ->orderBy('name')
                ->get(['id', 'name', 'type']),
            'items' => $items,
            'defaultWarehouseId' => $this->defaultWarehouseId($counterpartyId, $user),
            'lineItemMatches' => $lineItemMatches,
        ];
    }

    private function defaultWarehouseId(?int $counterpartyId, ?User $user): ?int
    {
        if ($counterpartyId) {
            $fulfillment = ReportingWarehouseFulfillment::query()
                ->where('customer_id', $counterpartyId)
                ->orderBy('id')
                ->first();
            if ($fulfillment) {
                return (int) $fulfillment->warehouse_id;
            }
        }

        if (! $user) {
            return null;
        }

        $prefill = app(UserPreferenceService::class)->transactionPrefill($user, 'sell');

        return isset($prefill['sender_id']) ? (int) $prefill['sender_id'] : null;
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
            'down_payment_total' => $parsed->downPaymentTotal,
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
            downPaymentTotal: (float) ($data['down_payment_total'] ?? 0),
        );
    }
}
