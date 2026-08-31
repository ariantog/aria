<?php

namespace App\Services;

use App\Enums\ChecklistFrequency;
use App\Models\ChecklistCompletion;
use App\Models\ChecklistTemplate;
use App\Models\User;
use Illuminate\Support\Carbon;

class StaffChecklistService
{
    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $roleIds = $user->staffRoles()->where('is_active', true)->pluck('staff_roles.id');

        if ($roleIds->isEmpty()) {
            return [
                'has_checklists' => false,
                'roles' => collect(),
                'groups' => [],
                'summary' => ['total' => 0, 'completed' => 0],
            ];
        }

        $templates = ChecklistTemplate::query()
            ->whereIn('staff_role_id', $roleIds)
            ->where('is_active', true)
            ->with('staffRole:id,name,slug')
            ->orderBy('sort_order')
            ->get();

        $periodKeys = $this->currentPeriodKeys();
        $completions = ChecklistCompletion::query()
            ->where('user_id', $user->id)
            ->whereIn('checklist_template_id', $templates->pluck('id'))
            ->whereIn('period_key', array_values($periodKeys))
            ->get()
            ->keyBy(fn (ChecklistCompletion $c) => $c->checklist_template_id.':'.$c->period_key);

        $items = $templates->map(function (ChecklistTemplate $template) use ($periodKeys, $completions) {
            $periodKey = $periodKeys[$template->frequency->value];
            $completionKey = $template->id.':'.$periodKey;
            $completed = $completions->has($completionKey);

            return [
                'id' => $template->id,
                'title' => $template->title,
                'description' => $template->description,
                'frequency' => $template->frequency->value,
                'frequency_label' => $template->frequency->label(),
                'role_name' => $template->staffRole?->name,
                'role_slug' => $template->staffRole?->slug,
                'period_key' => $periodKey,
                'completed' => $completed,
                'completed_at' => $completed ? $completions->get($completionKey)->completed_at : null,
                'url' => $this->resolveUrl($template),
            ];
        });

        $groups = [];
        foreach (ChecklistFrequency::cases() as $frequency) {
            $groupItems = $items->where('frequency', $frequency->value)->values();
            if ($groupItems->isEmpty()) {
                continue;
            }

            $completedCount = $groupItems->where('completed', true)->count();
            $groups[] = [
                'frequency' => $frequency->value,
                'label' => $frequency->dashboardLabel(),
                'period_key' => $periodKeys[$frequency->value],
                'items' => $groupItems->all(),
                'total' => $groupItems->count(),
                'completed' => $completedCount,
            ];
        }

        return [
            'has_checklists' => $items->isNotEmpty(),
            'roles' => $user->staffRoles()->where('is_active', true)->get(['staff_roles.id', 'name', 'slug']),
            'groups' => $groups,
            'summary' => [
                'total' => $items->count(),
                'completed' => $items->where('completed', true)->count(),
            ],
        ];
    }

    public function toggle(User $user, ChecklistTemplate $template): bool
    {
        $roleIds = $user->staffRoles()->pluck('staff_roles.id');
        if (! $roleIds->contains($template->staff_role_id)) {
            abort(403);
        }

        $periodKey = $this->periodKeyFor($template->frequency);

        $existing = ChecklistCompletion::query()
            ->where('user_id', $user->id)
            ->where('checklist_template_id', $template->id)
            ->where('period_key', $periodKey)
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        ChecklistCompletion::create([
            'user_id' => $user->id,
            'checklist_template_id' => $template->id,
            'period_key' => $periodKey,
            'completed_at' => now(),
        ]);

        return true;
    }

    /**
     * @return array<string, string>
     */
    public function currentPeriodKeys($date = null): array
    {
        $date = Carbon::parse($date ?? now());

        return [
            ChecklistFrequency::Daily->value => $this->periodKeyFor(ChecklistFrequency::Daily, $date),
            ChecklistFrequency::Weekly->value => $this->periodKeyFor(ChecklistFrequency::Weekly, $date),
            ChecklistFrequency::Biweekly->value => $this->periodKeyFor(ChecklistFrequency::Biweekly, $date),
            ChecklistFrequency::Monthly->value => $this->periodKeyFor(ChecklistFrequency::Monthly, $date),
        ];
    }

    public function periodKeyFor(ChecklistFrequency $frequency, $date = null): string
    {
        $date = Carbon::parse($date ?? now());

        return match ($frequency) {
            ChecklistFrequency::Daily => $date->toDateString(),
            ChecklistFrequency::Weekly => sprintf('%d-W%02d', $date->isoWeekYear(), $date->isoWeek()),
            ChecklistFrequency::Biweekly => sprintf('%d-B%02d', $date->isoWeekYear(), (int) ceil($date->isoWeek() / 2)),
            ChecklistFrequency::Monthly => $date->format('Y-m'),
        };
    }

    protected function resolveUrl(ChecklistTemplate $template): ?string
    {
        if (! $template->route_name) {
            return null;
        }

        if (! app('router')->has($template->route_name)) {
            return null;
        }

        try {
            return route($template->route_name);
        } catch (\Throwable) {
            return null;
        }
    }
}
