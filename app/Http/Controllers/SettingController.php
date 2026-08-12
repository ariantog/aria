<?php

namespace App\Http\Controllers;

use App\Enums\AddrbookType;
use App\Models\Addrbook;
use App\Models\ScheduledTask;
use App\Models\Setting;
use App\Support\SettingRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SettingController extends Controller
{
    public function index()
    {
        Gate::authorize(Setting::getPermissions()['view']);

        $settings = Setting::query()
            ->whereIn('slug', SettingRegistry::slugs())
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        $displayValues = $this->displayValuesFor($settings);

        return view('system-settings.index', [
            'settings' => $settings,
            'displayValues' => $displayValues,
            'groups' => collect(SettingRegistry::groups()),
            'can' => [
                'create' => request()->user()?->can(Setting::getPermissions()['create']) ?? false,
                'edit' => request()->user()?->can(Setting::getPermissions()['edit']) ?? false,
                'delete' => request()->user()?->can(Setting::getPermissions()['delete']) ?? false,
                'cron_view' => request()->user()?->can(ScheduledTask::getPermissions()['view']) ?? false,
            ],
        ]);
    }

    public function create()
    {
        Gate::authorize(Setting::getPermissions()['create']);

        return view('system-settings.create');
    }

    public function store(\App\Http\Requests\StoreSettingRequest $request)
    {
        Gate::authorize(Setting::getPermissions()['create']);

        Setting::create($request->validated());

        return redirect()->route('system-settings.index')->with('success', 'Setting created successfully.');
    }

    public function edit(Setting $system_setting)
    {
        Gate::authorize(Setting::getPermissions()['edit']);

        abort_unless(SettingRegistry::isManaged($system_setting->slug), 404);

        $definition = SettingRegistry::definition($system_setting->slug);
        $warehouses = collect();

        if (($definition['type'] ?? null) === 'warehouse_ids') {
            $warehouses = Addrbook::query()
                ->where('type', AddrbookType::Warehouse->value)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return view('system-settings.edit', [
            'setting' => $system_setting,
            'definition' => $definition,
            'warehouses' => $warehouses,
            'lookupUrls' => [
                'supplier' => route('system-settings.lookup', ['type' => 'supplier']),
                'warehouse' => route('system-settings.lookup', ['type' => 'warehouse']),
                'account' => route('system-settings.lookup', ['type' => 'account']),
            ],
            'addrbookInitial' => $this->addrbookInitialFor($system_setting),
        ]);
    }

    public function update(\App\Http\Requests\UpdateSettingRequest $request, Setting $system_setting)
    {
        Gate::authorize(Setting::getPermissions()['edit']);

        abort_unless(SettingRegistry::isManaged($system_setting->slug), 404);

        $validated = $request->validated();
        $definition = SettingRegistry::definition($system_setting->slug);

        try {
            $validated['value'] = $this->normalizeValue(
                $system_setting->slug,
                $definition['type'] ?? 'text',
                $request,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['value' => $e->getMessage()]);
        }

        $system_setting->update($validated);

        Setting::query()
            ->where('slug', $system_setting->slug)
            ->where('id', '!=', $system_setting->id)
            ->delete();

        return redirect()->route('system-settings.index')->with('success', 'Setting updated successfully.');
    }

    public function destroy(Setting $system_setting)
    {
        Gate::authorize(Setting::getPermissions()['delete']);

        abort_unless(SettingRegistry::isManaged($system_setting->slug), 404);

        return redirect()
            ->route('system-settings.index')
            ->with('error', 'Managed settings cannot be deleted. Reset the value instead.');
    }

    public function lookup(Request $request, string $type)
    {
        Gate::authorize(Setting::getPermissions()['view']);

        abort_unless(in_array($type, ['supplier', 'warehouse', 'account'], true), 404);

        $addrbookType = match ($type) {
            'supplier' => AddrbookType::Supplier->value,
            'warehouse' => AddrbookType::Warehouse->value,
            'account' => AddrbookType::Account->value,
        };

        $query = Addrbook::query()->where('type', $addrbookType);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->orderBy('name')->limit(20)->get(['id', 'name'])
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Setting>  $settings
     * @return array<int, string>
     */
    private function displayValuesFor($settings): array
    {
        $values = [];

        foreach ($settings as $setting) {
            $values[$setting->id] = $this->formatDisplayValue($setting);
        }

        return $values;
    }

    private function formatDisplayValue(Setting $setting): string
    {
        $definition = SettingRegistry::definition($setting->slug);
        $type = $definition['type'] ?? 'text';
        $value = $setting->value;

        if ($value === null || $value === '') {
            return '—';
        }

        return match ($type) {
            'addrbook_supplier', 'addrbook_warehouse', 'account' => Addrbook::find((int) $value)?->name ?? (string) $value,
            'warehouse_ids' => $this->formatWarehouseIdsDisplay(is_array($value) ? $value : []),
            default => is_array($value) || is_object($value)
                ? json_encode($value)
                : (string) $value,
        };
    }

    /**
     * @param  list<int|string>  $ids
     */
    private function formatWarehouseIdsDisplay(array $ids): string
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if ($ids === []) {
            return 'All warehouses';
        }

        $names = Addrbook::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return $names !== [] ? implode(', ', $names) : 'Selected warehouses';
    }

    /**
     * @return array{id: int|string, name: string}|null
     */
    private function addrbookInitialFor(Setting $setting): ?array
    {
        $definition = SettingRegistry::definition($setting->slug);
        $type = $definition['type'] ?? 'text';

        if (! in_array($type, ['addrbook_supplier', 'addrbook_warehouse', 'account'], true)) {
            return null;
        }

        $value = $setting->value;
        if ($value === null || $value === '') {
            return null;
        }

        $addrbook = Addrbook::find((int) $value);

        return [
            'id' => $value,
            'name' => $addrbook?->name ?? (string) $value,
        ];
    }

    private function normalizeValue(string $slug, string $type, Request $request): mixed
    {
        return match ($type) {
            'warehouse_ids' => $this->normalizeWarehouseIds($request),
            'addrbook_supplier' => $this->normalizeAddrbookId($request->input('value'), AddrbookType::Supplier),
            'addrbook_warehouse' => $this->normalizeAddrbookId($request->input('value'), AddrbookType::Warehouse),
            'account' => $this->normalizeAddrbookId($request->input('value'), AddrbookType::Account),
            'number', 'tutup_buku' => $request->input('value'),
            default => $request->input('value'),
        };
    }

    /**
     * @return list<int>
     */
    private function normalizeWarehouseIds(Request $request): array
    {
        $warehouseIds = collect($request->input('warehouse_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($warehouseIds === []) {
            return [];
        }

        $validCount = Addrbook::query()
            ->where('type', AddrbookType::Warehouse->value)
            ->whereIn('id', $warehouseIds)
            ->count();

        if ($validCount !== count($warehouseIds)) {
            throw new InvalidArgumentException('One or more stock display warehouses are invalid.');
        }

        return $warehouseIds;
    }

    private function normalizeAddrbookId(mixed $value, AddrbookType $expectedType): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $addrbook = Addrbook::find((int) $value);

        if (! $addrbook) {
            throw new InvalidArgumentException('Selected contact was not found.');
        }

        $type = $addrbook->type instanceof AddrbookType
            ? $addrbook->type
            : AddrbookType::from((int) $addrbook->type);

        if ($type !== $expectedType) {
            throw new InvalidArgumentException('Selected contact has the wrong type.');
        }

        return $addrbook->id;
    }
}
