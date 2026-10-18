<?php

namespace App\Services\Lark;

use App\Models\LarkRestockRequest;
use App\Models\User;
use App\Services\Restock\RestockRecommendationApplyService;
use Illuminate\Support\Facades\DB;

class LarkRestockSubmitService
{
    public function __construct(
        protected RestockRecommendationApplyService $apply,
        protected LarkWebhookClient $webhook,
        protected LarkCardBuilder $cards,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function applyFromCallback(string $token, int $qty, ?User $submitter): array
    {
        return DB::transaction(function () use ($token, $qty, $submitter) {
            $request = LarkRestockRequest::query()
                ->where('token', $token)
                ->lockForUpdate()
                ->first();

            if ($request === null) {
                return ['ok' => false, 'message' => 'Permintaan restock tidak ditemukan.'];
            }

            if ($request->status !== LarkRestockRequest::STATUS_PENDING) {
                return ['ok' => false, 'message' => 'Permintaan sudah diproses ('.$request->status.').'];
            }

            if ($request->expires_at !== null && $request->expires_at->isPast()) {
                $request->update(['status' => LarkRestockRequest::STATUS_EXPIRED]);

                return ['ok' => false, 'message' => 'Permintaan kedaluwarsa.'];
            }

            $qty = max(0, $qty);
            $user = $submitter ?? User::query()->find(config('lark.system_user_id'));

            if ($user === null) {
                return ['ok' => false, 'message' => 'User sistem tidak ditemukan.'];
            }

            $result = $this->apply->saveRestockQuantities([
                ['item_id' => $request->item_id, 'qty_restock' => $qty],
            ], $user);

            if ($result['applied'] === []) {
                $reason = $result['skipped'][0]['reason'] ?? 'Tidak dapat menerapkan restock.';

                return ['ok' => false, 'message' => $reason];
            }

            $applied = $result['applied'][0];
            $request->update([
                'status' => LarkRestockRequest::STATUS_APPLIED,
                'applied_qty' => $applied['qty'],
                'submitted_by' => $user->id,
                'submitted_at' => now(),
                'result_message' => 'Restock '.$applied['qty'].' pada '.$applied['sheet_name'],
            ]);

            $card = $this->cards->restockResultCard(
                'Restock diterapkan',
                $request->result_message.' — '.$applied['sheet_url'],
            );
            $this->webhook->sendInteractiveCard($card, 'stock.restock.pending', 'restock:applied:'.$request->token);

            return ['ok' => true, 'message' => (string) $request->result_message];
        });
    }
}
