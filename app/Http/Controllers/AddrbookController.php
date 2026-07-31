    public function show(Addrbook $a)
    {
        $slug = $this->resolveTypeSlug($a);
        Gate::authorize(Addrbook::getPermissions($slug)['view']);

        $load = ['stat', 'dailies' => fn ($q) => $q->latest('date')->limit(50)];

        if ($a->type instanceof AddrbookType && $a->type->isWarehouse()) {
            $load[] = 'items';
        }

        $a->load($load);

        if ($a->type instanceof AddrbookType && $a->type->isWarehouse() && $a->relationLoaded('items')) {
            $a->items->each(function ($i) {
                $c = ($i->type instanceof ItemType && $i->type === ItemType::ASSET_LANCAR)
                    ? (float) $i->cost
                    : (float) $i->price * 0.3;
                $i->calculated_cost = $c;
                $i->total_calculated_cost = $c * (float) ($i->pivot->quantity ?? 0);
            });
        }

        return Inertia::render('Addrbook/Show', [
            'addrbook' => $a,
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
        ]);
    }