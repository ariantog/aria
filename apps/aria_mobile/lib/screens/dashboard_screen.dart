import 'package:flutter/material.dart';

import '../api/aria_api.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({
    super.key,
    required this.api,
    required this.userName,
  });

  final AriaApi api;
  final String userName;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  Map<String, dynamic>? _data;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final j = await widget.api.getDashboard();
      if (!mounted) return;
      setState(() {
        _data = j;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  String _fmtNum(num? v) {
    if (v == null) return '—';
    final n = v.toDouble();
    if (n == n.roundToDouble()) {
      return n.toInt().toString();
    }
    return n.toStringAsFixed(0);
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _data == null) {
      return const Center(child: CircularProgressIndicator());
    }

    final kpis = _data?['kpis'];
    final activity = kpis is Map ? kpis['activity_today'] : null;
    final queue = kpis is Map ? kpis['queue'] : null;
    final jubelio = kpis is Map ? kpis['jubelio'] : null;
    final stock = kpis is Map ? kpis['stock_alerts'] : null;
    final closing = kpis is Map ? kpis['book_closing'] : null;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        children: [
          Text('Welcome, ${widget.userName}', style: Theme.of(context).textTheme.titleMedium),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ),
          const SizedBox(height: 16),
          if (activity is Map) ...[
            _KpiCard(
              title: 'Today\'s activity',
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Sell: ${_fmtNum(activity['sell_count'])} tx · ${_fmtNum(activity['sell_total'])}'),
                  Text('Buy: ${_fmtNum(activity['buy_count'])} tx · ${_fmtNum(activity['buy_total'])}'),
                  Text('Cash in: ${_fmtNum(activity['cash_in_total'])}'),
                  Text('Cash out: ${_fmtNum(activity['cash_out_total'])}'),
                ],
              ),
            ),
          ],
          if (queue is Map)
            _KpiCard(
              title: 'Queue',
              child: Text(
                '${queue['pending_jobs'] ?? 0} pending · ${queue['level'] ?? 'ok'}',
              ),
            ),
          if (jubelio is Map)
            _KpiCard(
              title: 'Jubelio',
              child: Text(
                '${jubelio['connection_state'] ?? '—'} · pending ${jubelio['order_pending'] ?? 0} · errors ${jubelio['order_error'] ?? 0}',
              ),
            ),
          if (stock is Map)
            _KpiCard(
              title: 'Stock alerts',
              child: Text('Unread: ${stock['unread_count'] ?? 0}'),
            ),
          if (closing is Map)
            _KpiCard(
              title: 'Book closing',
              child: Text('${closing['days_until_closing'] ?? '—'} days until closing (day ${closing['closing_day'] ?? '—'})'),
            ),
          if (activity == null && queue == null && jubelio == null && stock == null && closing == null && _data != null)
            const Text('No KPI panels for your permissions.'),
        ],
      ),
    );
  }
}

class _KpiCard extends StatelessWidget {
  const _KpiCard({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            child,
          ],
        ),
      ),
    );
  }
}
