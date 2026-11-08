import 'package:flutter/material.dart';

import '../api/aria_api.dart';

class TransactionDetailScreen extends StatefulWidget {
  const TransactionDetailScreen({
    super.key,
    required this.api,
    required this.transactionId,
  });

  final AriaApi api;
  final int transactionId;

  @override
  State<TransactionDetailScreen> createState() => _TransactionDetailScreenState();
}

class _TransactionDetailScreenState extends State<TransactionDetailScreen> {
  Map<String, dynamic>? _tx;
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
      final j = await widget.api.getTransaction(widget.transactionId);
      final tx = j['transaction'];
      if (!mounted) return;
      setState(() {
        _tx = tx is Map<String, dynamic> ? tx : (tx is Map ? Map<String, dynamic>.from(tx) : null);
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Transaction')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: _buildBody(context),
                  ),
                ),
    );
  }

  List<Widget> _buildBody(BuildContext context) {
    final tx = _tx;
    if (tx == null) {
      return [const Text('Not found')];
    }

    final type = tx['type'];
    final typeLabel = type is Map ? type['label']?.toString() ?? '—' : '—';

    return [
      Text(tx['invoice']?.toString() ?? '—', style: Theme.of(context).textTheme.headlineSmall),
      const SizedBox(height: 8),
      Text('${tx['date'] ?? ''} · $typeLabel'),
      const SizedBox(height: 16),
      _row('Total', tx['total']?.toString() ?? '—'),
      _row('Items qty', tx['total_items']?.toString() ?? '—'),
      if (tx['note'] != null && tx['note'].toString().trim().isNotEmpty) _row('Note', tx['note'].toString()),
      const Divider(height: 32),
      _row('Sender', _partyLine(tx['sender'])),
      _row('Receiver', _partyLine(tx['receiver'])),
      const SizedBox(height: 16),
      Text('Line items', style: Theme.of(context).textTheme.titleMedium),
      const SizedBox(height: 8),
      ..._detailLines(tx['details']),
    ];
  }

  String _partyLine(dynamic party) {
    if (party is! Map) return '—';
    final name = party['name'] ?? '—';
    final bal = party['balance'];
    if (bal == null) return '$name';
    return '$name (bal: $bal)';
  }

  List<Widget> _detailLines(dynamic details) {
    if (details is! List || details.isEmpty) {
      return [const Text('No line items')];
    }
    return details.map<Widget>((d) {
      if (d is! Map) return const SizedBox.shrink();
      final code = d['code'] ?? '';
      final name = d['name'] ?? '';
      final qty = d['quantity'] ?? '';
      final total = d['total'] ?? '';
      return ListTile(
        title: Text(code.toString().isNotEmpty ? '$code' : name.toString()),
        subtitle: Text(name.toString()),
        trailing: Text('$qty @ ${d['price'] ?? ''}\n$total', textAlign: TextAlign.end),
        isThreeLine: true,
      );
    }).toList();
  }

  Widget _row(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 100, child: Text(label, style: const TextStyle(fontWeight: FontWeight.w500))),
          Expanded(child: Text(value)),
        ],
      ),
    );
  }
}
