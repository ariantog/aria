<?php

namespace App\Services;

use App\Enums\ChecklistFrequency;
use App\Models\ChecklistCompletion;
use App\Models\ChecklistTemplate;
use App\Models\StaffRole;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StaffChecklistOverviewService
{
    public function __construct(
        protected StaffChecklistService $checklistService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?string $date = null): array
    {
        $date = Carbon::parse($date ?? now());
        $periodKeys = $this->checklistService->currentPeriodKeys($date);

        $roles = StaffRole::query()
            ->where('is_active', true)
            ->withCount([
                'users',
                'checklistTemplates as active_templates_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->with(['users:id,name,username'])
            ->orderBy('sort_order')
            ->get();

        $usersWithoutRoles = User::query()
            ->where('active', true)
            ->whereDoesntHave('staffRoles')
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        $usersWithRoles = User::query()
            ->where('active', true)
            ->whereHas('staffRoles')
            ->with(['staffRoles' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        $roleIds = $roles->pluck('id');
        $templates = ChecklistTemplate::query()
            ->where('is_active', true)
            ->whereIn('staff_role_id', $roleIds)
            ->with('staffRole:id,name,slug')
            ->orderBy('sort_order')
            ->get();

        $completions = ChecklistCompletion::query()
            ->whereIn('user_id', $usersWithRoles->pluck('id'))
            ->whereIn('checklist_template_id', $templates->pluck('id'))
            ->whereIn('period_key', array_values($periodKeys))
            ->get()
            ->groupBy('user_id');

        $userRows = $usersWithRoles->map(function (User $user) use ($templates, $completions, $periodKeys) {
            $roleIds = $user->staffRoles->pluck('id');
            $userTemplates = $templates->whereIn('staff_role_id', $roleIds)->values();
            $userCompletions = $completions->get($user->id, collect())
                ->keyBy(fn (ChecklistCompletion $completion) => $completion->checklist_template_id.':'.$completion->period_key);

            $frequencyStats = [];
            foreach (ChecklistFrequency::cases() as $frequency) {
                $frequencyTemplates = $userTemplates->where('frequency', $frequency);
                $total = $frequencyTemplates->count();
                $completed = 0;

                if ($total > 0) {
                    $periodKey = $periodKeys[$frequency->value];
                    $completed = $frequencyTemplates->filter(
                        fn (ChecklistTemplate $template) => $userCompletions->has($template->id.':'.$periodKey)
                    )->count();
                }

                $frequencyStats[$frequency->value] = [
                    'label' => $frequency->label(),
                    'period_key' => $periodKeys[$frequency->value],
                    'total' => $total,
                    'completed' => $completed,
                ];
            }

            $items = $userTemplates->map(function (ChecklistTemplate $template) use ($periodKeys, $userCompletions) {
                $periodKey = $periodKeys[$template->frequency->value];
                $completionKey = $template->id.':'.$periodKey;
                $completion = $userCompletions->get($completionKey);

                return [
                    'id' => $template->id,
                    'title' => $template->title,
                    'frequency' => $template->frequency->value,
                    'frequency_label' => $template->frequency->label(),
                    'role_name' => $template->staffRole?->name,
                    'period_key' => $periodKey,
                    'completed' => $completion !== null,
                    'completed_at' => $completion?->completed_at,
                ];
            })->values();

            $total = $userTemplates->count();
            $completed = $items->where('completed', true)->count();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'roles' => $user->staffRoles->map(fn (StaffRole $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                ])->values()->all(),
                'frequency_stats' => $frequencyStats,
                'items' => $items->all(),
                'summary' => [
                    'total' => $total,
                    'completed' => $completed,
                    'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
                ],
            ];
        })->values();

        $roleRows = $roles->map(fn (StaffRole $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'users_count' => $role->users_count,
            'templates_count' => $role->active_templates_count,
            'users' => $role->users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
            ])->values()->all(),
            'is_mapped' => $role->users_count > 0,
        ])->values();

        return [
            'as_of' => $date->toDateString(),
            'period_keys' => $periodKeys,
            'summary' => [
                'roles_total' => $roles->count(),
                'roles_mapped' => $roleRows->where('is_mapped', true)->count(),
                'roles_unmapped' => $roleRows->where('is_mapped', false)->count(),
                'users_with_roles' => $usersWithRoles->count(),
                'users_without_roles' => $usersWithoutRoles->count(),
            ],
            'roles' => $roleRows->all(),
            'unmapped_roles' => $roleRows->where('is_mapped', false)->values()->all(),
            'users_without_roles' => $usersWithoutRoles->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
            ])->all(),
            'users' => $userRows->all(),
        ];
    }
}
