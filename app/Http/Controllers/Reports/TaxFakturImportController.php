<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Addrbook;
use App\Models\Report;
use App\Models\ReportingEntity;
use App\Models\TaxFakturImport;
use App\Services\Reporting\TaxReportService;
use App\Services\Tax\ExpectedPaymentDateCalculator;
use App\Services\Tax\FakturPajakDirectionResolver;
use App\Services\Tax\FakturPajakPdfParser;
use App\Services\Tax\ParsedFakturPajak;
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

        $import->load(['reportingEntity', 'counterparty', 'varianceExpenseAccount', 'user']);

        return view('reports.tax.faktur.show', [
            'import' => $import,
            'hasPdf' => $this->resolvePdfPath($import) !== null,
            'canImport' => request()->user()?->can(Report::getPermissions()['import-tax-faktur']) ?? false,
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

    public function review()
    {
        Gate::authorize(Report::getPermissions()['import-tax-faktur']);

        $preview = session(self::SESSION_KEY);
        if (! $preview) {
            return redirect()->route('reports.tax.faktur.create')
                ->with('error', 'Upload a PDF first.');
        }

        $parsed = $this->hydrateParsed($preview['parsed']);

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
