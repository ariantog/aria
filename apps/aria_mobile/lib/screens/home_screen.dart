import 'package:flutter/material.dart';

import '../api/aria_api.dart';
import 'cash_form_screen.dart';
import 'move_form_screen.dart';
import 'sell_form_screen.dart';
import 'transfer_form_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({
    super.key,
    required this.baseUrl,
    required this.token,
    required this.onLogout,
  });

  final String baseUrl;
  final String token;
  final Future<void> Function() onLogout;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  Map<String, dynamic>? _me;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _error = null;
    });
    try {
      final api = AriaApi(baseUrl: widget.baseUrl, token: widget.token);
      final j = await api.getMe();
      setState(() => _me = j);
    } catch (e) {
      setState(() => _error = e.toString());
    }
  }

  List<String> _actions() {
    final list = _me?['mobile_actions'];
    if (list is List) {
      return list.map((e) => e.toString()).toList();
    }
    return [];
  }

  @override
  Widget build(BuildContext context) {
    final user = _me?['user'];
    final name = user is Map ? (user['name'] ?? user['username'] ?? '?').toString() : '…';

    return Scaffold(
      appBar: AppBar(
        title: const Text('Aria'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () async {
              try {
                final api = AriaApi(baseUrl: widget.baseUrl, token: widget.token);
                await api.logout();
              } catch (_) {
                // ignore
              }
              await widget.onLogout();
            },
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text('Signed in as $name', style: Theme.of(context).textTheme.titleMedium),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
              ),
            const SizedBox(height: 16),
            const Text('Create transaction', style: TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            if (_actions().contains('sell'))
              ListTile(
                leading: const Icon(Icons.point_of_sale),
                title: const Text('Sell'),
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute<void>(
                    builder: (c) => SellFormScreen(api: AriaApi(baseUrl: widget.baseUrl, token: widget.token)),
                  ),
                ),
              ),
            if (_actions().contains('move'))
              ListTile(
                leading: const Icon(Icons.swap_horiz),
                title: const Text('Move stock'),
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute<void>(
                    builder: (c) => MoveFormScreen(api: AriaApi(baseUrl: widget.baseUrl, token: widget.token)),
                  ),
                ),
              ),
            if (_actions().contains('cash_in'))
              ListTile(
                leading: const Icon(Icons.account_balance_wallet),
                title: const Text('Cash in'),
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute<void>(
                    builder: (c) => CashFormScreen(
                      api: AriaApi(baseUrl: widget.baseUrl, token: widget.token),
                      cashIn: true,
                    ),
                  ),
                ),
              ),
            if (_actions().contains('cash_out'))
              ListTile(
                leading: const Icon(Icons.payments_outlined),
                title: const Text('Cash out'),
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute<void>(
                    builder: (c) => CashFormScreen(
                      api: AriaApi(baseUrl: widget.baseUrl, token: widget.token),
                      cashIn: false,
                    ),
                  ),
                ),
              ),
            if (_actions().contains('transfer'))
              ListTile(
                leading: const Icon(Icons.compare_arrows),
                title: const Text('Transfer'),
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute<void>(
                    builder: (c) => TransferFormScreen(api: AriaApi(baseUrl: widget.baseUrl, token: widget.token)),
                  ),
                ),
              ),
            if (_actions().isEmpty && _me != null)
              const Text('No transaction permissions on this account.'),
          ],
        ),
      ),
    );
  }
}
