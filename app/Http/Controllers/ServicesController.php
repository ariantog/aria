<?php

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Http\Requests\StoreServiceItemRequest;
use App\Http\Requests\UpdateServiceItemRequest;
use App\Models\Item;
use App\Services\Items\ServiceItemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ServicesController extends Controller
{
    public function __construct(private readonly ServiceItemService $serviceItems) {}

    public function index(Request $request)
    {
        Gate::authorize(Item::getPermissions()['services-view']);

        $items = Item::query()
            ->where('type', ItemType::SERVICE)
            ->when($request->filled('search'), fn ($q) => $q->search((string) $request->query('search')))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('services.index', [
            'items' => $items,
            'filters' => ['search' => (string) $request->query('search', '')],
            'can' => $this->permissions(),
        ]);
    }

    public function create()
    {
        Gate::authorize(Item::getPermissions()['services-create']);

        return view('services.create', [
            'defaults' => [
                'tanpa_stok' => true,
                'allow_decimal_quantity' => false,
            ],
        ]);
    }

    public function store(StoreServiceItemRequest $request)
    {
        Gate::authorize(Item::getPermissions()['services-create']);

        try {
            $item = $this->serviceItems->create($this->normalizedInput($request));

            return redirect()->route('services.show', $item)->with('success', 'Service created.');
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()])->withInput();
        }
    }

    public function show(Item $item)
    {
        $this->ensureService($item);
        Gate::authorize(Item::getPermissions()['services-view']);

        $item->load('group');

        return view('services.show', [
            'item' => $item,
            'can' => $this->permissions(),
        ]);
    }

    public function edit(Item $item)
    {
        $this->ensureService($item);
        Gate::authorize(Item::getPermissions()['services-edit']);

        $item->load('group');

        return view('services.edit', ['item' => $item]);
    }

    public function update(UpdateServiceItemRequest $request, Item $item)
    {
        $this->ensureService($item);
        Gate::authorize(Item::getPermissions()['services-edit']);

        try {
            $this->serviceItems->update($item, $this->normalizedInput($request));

            return redirect()->route('services.show', $item)->with('success', 'Service updated.');
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()])->withInput();
        }
    }

    public function destroy(Item $item)
    {
        $this->ensureService($item);
        Gate::authorize(Item::getPermissions()['services-delete']);

        $item->delete();

        return redirect()->route('services.index')->with('success', 'Service deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedInput(StoreServiceItemRequest|UpdateServiceItemRequest $request): array
    {
        return [
            'name' => $request->input('name'),
            'code' => $request->input('code'),
            'price' => $request->input('price'),
            'cost' => $request->input('cost'),
            'description' => $request->input('description'),
            'tanpa_stok' => $request->boolean('tanpa_stok'),
            'allow_decimal_quantity' => $request->boolean('allow_decimal_quantity'),
            'description2' => $request->input('description2'),
        ];
    }

    private function ensureService(Item $item): void
    {
        abort_unless($item->type === ItemType::SERVICE, 404);
    }

    /**
     * @return array<string, bool>
     */
    private function permissions(): array
    {
        $p = Item::getPermissions();

        return [
            'create' => Gate::check($p['services-create']),
            'edit' => Gate::check($p['services-edit']),
            'delete' => Gate::check($p['services-delete']),
        ];
    }
}
