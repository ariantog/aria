<?php

namespace App\Http\Controllers;

use App\Enums\AddrbookType;
use App\Enums\ItemBrand;
use App\Enums\ItemType;
use App\Enums\TransactionType;
use App\Http\Requests\StoreItemRequest;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\TransactionDetail;
use App\Services\ItemService;
use App\Services\JubelioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ItemsController extends Controller
{
    public function __construct(protected ItemService $itemService) {}

    public function index(Request $request, ItemType $type = ItemType::Item)
    {
        $p = Item::getPermissions();
        Gate::authorize($type === ItemType::AssetLancar ? $p['asset-lancar-view'] : $p['view']);
        $q = Item::with('group');
        if ($this->isJson($request)) { if ($request->filled('type')) $q->whereIn('type', array_map('intval', explode(',', $request->input('type')))); }
        else { $q->where('type', $type); }
        $q->when($request->filled('search'), fn ($q) => $q->search($request->search))
            ->when($request->filled('id'), fn ($q) => $q->where('id', $request->id))
            ->when($request->filled('brand'), fn ($q) => $q->where('brand', $request->brand))
            ->when($request->filled('code'), fn ($q) => $q->where('code', 'like', "{$request->code}%"))
            ->when($request->filled('name'), fn ($q) => $q->where('name', 'like', "%{$request->name}%"))
            ->when($request->filled('alias'), fn ($q) => $q->whereHas('group', fn ($s) => $s->where('alias', 'like', "%{$request->alias}%")))
            ->when($request->filled('desc'), fn ($q) => $q->where(fn ($s) => $s->where('description', 'like', "%{$request->desc}%")->orWhereHas('group', fn ($g) => $g->where('description', 'like', "%{$request->desc}%"))));
        $this->applyTagFilters($q, $request);
        if ($this->isJson($request)) return $q->with('warehouseItems')->limit(50)->get();
        return Inertia::render('Items/Index', ['items' => $q->orderBy('id', 'desc')->paginate(50)->withQueryString(), 'filters' => $request->only(['search', 'brand', 'type', 'jahit', 'size', 'warna', 'item_type', 'code', 'name', 'alias', 'desc']), 'brands' => $this->brandOptions(), 'types' => $this->typeOptions(), 'tags' => \App\Models\Tag::all()->groupBy('type'), 'can' => $this->itemPermissions($type)]);
    }

    public function indexAsset(Request $request) { return $this->index($request, ItemType::AssetLancar); }
    public function create() { Gate::authorize(Item::getPermissions()['create']); return Inertia::render('Items/Create', $this->formProps(ItemType::Item)); }
    public function createAsset() { Gate::authorize(Item::getPermissions()['asset-lancar-create']); return Inertia::render('Items/Create', $this->formProps(ItemType::AssetLancar)); }
    public function store(StoreItemRequest $request) { $type = ItemType::from((int) $request->input('type')); Gate::authorize($type === ItemType::AssetLancar ? Item::getPermissions()['asset-lancar-create'] : Item::getPermissions()['create']); try { $this->itemService->create((object) $request->except(['image', 'tags']), $request->input('tags', []), $request->file('image')); return redirect()->route($type === ItemType::AssetLancar ? 'assetlancar.index' : 'items.index')->with('success', 'Item created.'); } catch (\Exception $e) { return back()->withErrors(['message' => $e->getMessage()])->withInput(); } }

    public function show(Item $item) { $item->load(['group', 'tags', 'warehouseItems' => fn ($q) => $q->whereIn('warehouse_id', fn ($s) => $s->select('id')->from('addrbooks')->where('type', AddrbookType::Warehouse->value))->with('warehouse')]); return Inertia::render('Items/Show', ['item' => $item]); }
    public function edit(Item $item) { $p = Item::getPermissions(); Gate::authorize($item->type === ItemType::AssetLancar ? $p['asset-lancar-edit'] : $p['edit']); return Inertia::render('Items/Edit', ['item' => $item->load(['group', 'tags']), 'brands' => $this->brandOptions(), 'types' => $this->typeOptions(), 'tags' => \App\Models\Tag::all()->groupBy('type')]); }
    public function update(Request $request, Item $item) { $p = Item::getPermissions(); Gate::authorize($item->type === ItemType::AssetLancar ? $p['asset-lancar-edit'] : $p['edit']); $request->validate(['pcode' => 'required']); try { $this->itemService->update($item->id, (object) $request->except(['image', 'tags']), $request->input('tags', []), $request->file('image')); return redirect()->route('items.index')->with('success', 'Item updated.'); } catch (\Exception $e) { return back()->withErrors(['message' => $e->getMessage()]); } }
    public function destroy(Item $item) { $p = Item::getPermissions(); Gate::authorize($item->type === ItemType::AssetLancar ? $p['asset-lancar-delete'] : $p['delete']); $item->delete(); return redirect()->route('items.index')->with('success', 'Item deleted.'); }

    public function jubelio(Item $item, JubelioService $s) { Gate::authorize(Item::getPermissions()['view']); $item->load(['group', 'tags']); [$msg, $data] = $this->fetchJubelio($item, $s); return Inertia::render('Items/Jubelio', ['item' => $item, 'dataJubelio' => $data, 'message' => $msg]); }
    public function updateJubelioId(Item $item, Request $r) { Gate::authorize(Item::getPermissions()['edit']); $r->validate(['jubelio_item_id' => 'nullable|integer']); $item->update(['jubelio_item_id' => $r->jubelio_item_id]); return redirect()->route('items.jubelio', $item->id)->with('success', 'Koneksi Jubelio diperbarui'); }

    private function isJson(Request $r): bool { return ($r->wantsJson() || $r->has('json')) && ! $r->header('X-Inertia'); }
    private function applyTagFilters($q, Request $r): void { $tags = collect([$r->input('jahit'), $r->input('size'), $r->input('warna'), $r->input('item_type')])->flatten()->filter()->toArray(); $explicit = $r->input('tag_ids', []); if (is_array($explicit)) $tags = array_unique(array_merge($tags, $explicit)); if (! empty($tags)) $q->filterByTags($tags); }
    private function formProps(ItemType $t): array { return ['brands' => $this->brandOptions(), 'jahitTags' => \App\Models\Tag::where('type', \App\Models\Tag::TYPE_JAHIT)->get(), 'typeTags' => \App\Models\Tag::where('type', \App\Models\Tag::TYPE_TYPE)->get(), 'sizeTags' => \App\Models\Tag::where('type', \App\Models\Tag::TYPE_SIZE)->get(), 'warnaTags' => \App\Models\Tag::where('type', \App\Models\Tag::TYPE_WARNA)->get(), 'itemType' => $t->value]; }
    private function brandOptions(): array { return collect(ItemBrand::cases())->map(fn ($b) => ['value' => $b->value, 'label' => $b->label()])->values()->all(); }
    private function typeOptions(): array { return collect(ItemType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->values()->all(); }
    private function itemPermissions(ItemType $t): array { $u = auth()->user(); $p = Item::getPermissions(); return ['create' => $u->can($p['create']), 'create_asset' => $u->can($p['asset-lancar-create']), 'edit' => $u->can($p['edit']), 'edit_asset' => $u->can($p['asset-lancar-edit']), 'delete' => $u->can($p['delete']), 'delete_asset' => $u->can($p['asset-lancar-delete'])]; }
    private function fetchJubelio(Item $item, JubelioService $s): array { if ($item->jubelio_item_id <= 0) return ['Item belum terhubung', []]; $t = $s->getCachedToken(); if (! $t) return ['Gagal otentikasi', []]; try { $r = \Illuminate\Support\Facades\Http::withHeaders(['Content-Type' => 'application/json', 'authorization' => $t])->post('https://api2.jubelio.com/inventory/items/all-stocks/', ['ids' => [$item->jubelio_item_id]]); if ($r->successful()) { $j = $r->json(); return empty($j['data']) ? ['Item tidak ditemukan', []] : ['ok', $j['data'][0]]; } return ['Gagal: '.$r->status(), []]; } catch (\Exception $e) { return ['Error: '.$e->getMessage(), []]; } }
}
