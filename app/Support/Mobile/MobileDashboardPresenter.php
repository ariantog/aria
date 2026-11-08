<?php

namespace App\Support\Mobile;

class MobileDashboardPresenter
{
    /**
     * Reduce full DashboardService payload to mobile-friendly KPIs.
     *
     * @param  array<string, mixed>  $dashboard
     * @return array<string, mixed>
     */
    public static function fromDashboardData(array $dashboard): array
    {
        $can = $dashboard['can'] ?? [];

        $payload = [
            'can' => $can,
            'has_ops_panel' => (bool) ($dashboard['has_ops_panel'] ?? false),
            'has_daily_panel' => (bool) ($dashboard['has_daily_panel'] ?? false),
            'kpis' => [],
        ];

        if (($can['activity'] ?? false) && isset($dashboard['activity']['today'])) {
            $today = $dashboard['activity']['today'];
            $payload['kpis']['activity_today'] = [
                'sell_count' => (int) ($today['sell_count'] ?? 0),
                'buy_count' => (int) ($today['buy_count'] ?? 0),
                'sell_total' => (float) ($today['sell_total'] ?? 0),
                'buy_total' => (float) ($today['buy_total'] ?? 0),
                'cash_in_total' => (float) ($today['cash_in_total'] ?? 0),
                'cash_out_total' => (float) ($today['cash_out_total'] ?? 0),
            ];
        }

        if (($can['queue'] ?? false) && isset($dashboard['queue'])) {
            $queue = $dashboard['queue'];
            $payload['kpis']['queue'] = [
                'pending_jobs' => (int) ($queue['pending_jobs'] ?? 0),
                'level' => $queue['level'] ?? 'ok',
                'process_queue_active' => $queue['process_queue_active'] ?? null,
            ];
        }

        if (($can['jubelio'] ?? false) && isset($dashboard['jubelio'])) {
            $jubelio = $dashboard['jubelio'];
            $payload['kpis']['jubelio'] = [
                'connection_state' => $jubelio['connection_state'] ?? 'unknown',
                'order_pending' => (int) ($jubelio['order_stats']['pending'] ?? 0),
                'order_error' => (int) ($jubelio['order_stats']['error'] ?? 0),
                'pending_cancellations' => (int) ($jubelio['pending_cancellations'] ?? 0),
            ];
        }

        if (($can['stock_alerts'] ?? false) && isset($dashboard['stock_alerts'])) {
            $payload['kpis']['stock_alerts'] = [
                'unread_count' => (int) ($dashboard['stock_alerts']['unread_count'] ?? 0),
            ];
        }

        if (($can['book_closing'] ?? false) && isset($dashboard['book_closing'])) {
            $closing = $dashboard['book_closing'];
            $payload['kpis']['book_closing'] = [
                'closing_day' => $closing['closing_day'] ?? null,
                'days_until_closing' => $closing['days_until_closing'] ?? null,
                'current_month_closed' => (bool) ($closing['current_month_closed'] ?? false),
            ];
        }

        if (($can['produksi_list'] ?? false) && isset($dashboard['produksi'])) {
            $payload['kpis']['produksi'] = [
                'recent_produksi_count' => (int) ($dashboard['produksi']['recent_produksi_count'] ?? 0),
            ];
        }

        if (($can['restock'] ?? false) && isset($dashboard['restock'])) {
            $payload['kpis']['restock'] = [
                'alert_count' => (int) ($dashboard['restock']['alert_count'] ?? 0),
                'period_key' => $dashboard['restock']['period_key'] ?? null,
            ];
        }

        return $payload;
    }
}
