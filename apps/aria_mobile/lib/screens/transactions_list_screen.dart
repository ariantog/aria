import 'package:flutter/material.dart';

import '../api/aria_api.dart';
import 'transaction_detail_screen.dart';

class TransactionsListScreen extends StatefulWidget {
  const TransactionsListScreen({super.key, required this.api});

  final AriaApi api;

  @override
  State<TransactionsListScreen> createState() => _TransactionsListScreenState();
}

class _TransactionsListScreenState extends State<TransactionsListScreen> {
  final List<Map<String, dynamic>> _rows = [];
  final ScrollController _scroll = ScrollController();
  int _page = 1;
  int? _lastPage;
  bool _loading = false;
  bool _loadingMore = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_onScroll);
    _refresh();
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_loadingMore || _loading) return;
    if (_lastPage != null && _page >= _lastPage!) return;
    if (_scroll.position.pixels >= _scroll.position.maxScrollExtent - 200) {
      _loadMore();
    }
  }

  Future<void> _refresh() async {
    setState(() {
      _error = null;
      _page = 1;
      _lastPage = null;
      _rows.clear();
    });
    await _fetch(page: 1, append: false);
  }

  Future<void> _loadMore() async {
    if (_lastPage != null && _page >= _lastPage!) return;
    await _fetch(page: _page + 1, append: true);
  }

  Future<void> _fetch({required int page, required bool append}) async {
    setState(() {
      if (append) {
        _loadingMore = true;
      } else {
        _loading = true;
      }
    });
    try {
      final j = await widget.api.getTransactions(page: page);
      final data = j['data'];
      final list = data is List
          ? data.map((e) => e is Map<String, dynamic> ? e : Map<String, dynamic>.from(e as Map)).toList()
          : <Map<String, dynamic>>[];
      if (!mounted) return;
      setState(() {
        if (append) {
          _rows.addAll(list);
        } else {
          _rows
            ..clear()
            ..addAll(list);
        }
        _page = page;
        _lastPage = j['last_page'] is int ? j['last_page'] as int : int.tryParse('${j['last_page']}');
        _loading = false;
        _loadingMore = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
        _loadingMore = false;
      });
    }
  }

  String _partyName(dynamic party) {
    if (party is Map && party['name'] != null) {
      return party['name'].toString();
    }
    return '—';
  }

  String _typeLabel(Map<String, dynamic> row) {
    final type = row['type'];
    if (type is Map && type['label'] != null) {
      return type['label'].toString();
    }
    return '—';
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _rows.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    return RefreshIndicator(
      onRefresh: _refresh,
      child: _rows.isEmpty && _error == null
          ? ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: const [
                SizedBox(height: 120),
                Center(child: Text('No transactions')),
              ],
            )
          : ListView.builder(
              controller: _scroll,
              physics: const AlwaysScrollableScrollPhysics(),
              itemCount: _rows.length + (_loadingMore ? 1 : 0) + (_error != null ? 1 : 0),
              itemBuilder: (context, index) {
                if (_error != null && index == 0) {
                  return ListTile(
                    title: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                  );
                }
                final offset = _error != null ? 1 : 0;
                if (index < offset + _rows.length) {
                  final row = _rows[index - offset];
                  final id = row['id'];
                  return ListTile(
                    title: Text(row['invoice']?.toString().isNotEmpty == true ? '${row['invoice']}' : 'Invoice —'),
                    subtitle: Text(
                      '${row['date'] ?? ''} · ${_typeLabel(row)}\n'
                      '${_partyName(row['sender'])} → ${_partyName(row['receiver'])}',
                    ),
                    isThreeLine: true,
                    trailing: Text(row['total']?.toString() ?? ''),
                    onTap: id != null
                        ? () => Navigator.push(
                              context,
                              MaterialPageRoute<void>(
                                builder: (c) => TransactionDetailScreen(
                                  api: widget.api,
                                  transactionId: int.parse('$id'),
                                ),
                              ),
                            )
                        : null,
                  );
                }
                return const Padding(
                  padding: EdgeInsets.all(16),
                  child: Center(child: CircularProgressIndicator()),
                );
              },
            ),
    );
  }
}
