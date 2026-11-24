import 'package:flutter/material.dart';

import '../api/aria_api.dart';
import 'create_transaction_sheet.dart';
import 'dashboard_screen.dart';
import 'pos_sell_screen.dart';
import 'resi_scan_screen.dart';
import 'printer_settings_screen.dart';
import 'transactions_list_screen.dart';

class ShellScreen extends StatefulWidget {
  const ShellScreen({
    super.key,
    required this.baseUrl,
    required this.token,
    required this.onLogout,
    required this.onServerAppearance,
  });

  final String baseUrl;
  final String token;
  final Future<void> Function() onLogout;
  final void Function(String? appearance) onServerAppearance;

  @override
  State<ShellScreen> createState() => _ShellScreenState();
}

class _ShellScreenState extends State<ShellScreen> {
  int _tabIndex = 0;
  Map<String, dynamic>? _me;

  AriaApi get _api => AriaApi(baseUrl: widget.baseUrl, token: widget.token);

  @override
  void initState() {
    super.initState();
    _loadMe();
  }

  Future<void> _loadMe() async {
    try {
      final j = await _api.getMe();
      if (!mounted) return;
      setState(() => _me = j);
      final prefs = j['preferences'];
      if (prefs is Map && prefs['appearance'] != null) {
        widget.onServerAppearance(prefs['appearance'].toString());
      }
    } catch (_) {
      // me reload is best-effort for create permissions
    }
  }

  List<String> _mobileActions() {
    final list = _me?['mobile_actions'];
    if (list is List) {
      return list.map((e) => e.toString()).toList();
    }
    return [];
  }

  Map<String, dynamic>? _prefs() {
    final p = _me?['preferences'];
    return p is Map<String, dynamic> ? p : (p is Map ? Map<String, dynamic>.from(p) : null);
  }

  Future<void> _logout() async {
    try {
      await _api.logout();
    } catch (_) {
      // ignore
    }
    await widget.onLogout();
  }

  Future<void> _setAppearance(String value) async {
    try {
      await _api.patchAppearance(value);
      widget.onServerAppearance(value);
      await _loadMe();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    }
  }

  bool _canCreateTransactions(List<String> actions) {
    return actions.any((a) =>
        ['sell', 'buy', 'move', 'cash_in', 'cash_out', 'transfer'].contains(a));
  }

  Widget? _buildFab() {
    final actions = _mobileActions();
    final canPos = actions.contains('pos');
    final canCreate = _canCreateTransactions(actions);
    final canScan = actions.contains('scan_resi');

    final actionKinds = <String>[
      if (canPos) 'pos',
      if (canScan) 'scan',
      if (canCreate) 'create',
    ];

    if (actionKinds.isEmpty) {
      return null;
    }

    if (actionKinds.length > 1) {
      return FloatingActionButton.extended(
        onPressed: _openActionsMenu,
        icon: const Icon(Icons.add),
        label: const Text('Actions'),
      );
    }

    if (canPos) {
      return FloatingActionButton.extended(
        onPressed: _openPosSell,
        icon: const Icon(Icons.storefront),
        label: const Text('POS'),
      );
    }

    if (canScan) {
      return FloatingActionButton.extended(
        onPressed: _openResiScan,
        icon: const Icon(Icons.qr_code_scanner),
        label: const Text('Scan resi'),
      );
    }

    return FloatingActionButton.extended(
      onPressed: _openCreate,
      icon: const Icon(Icons.add),
      label: const Text('Create'),
    );
  }

  void _openPosSell() {
    Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (c) => PosSellScreen(api: _api, preferences: _prefs()),
      ),
    );
  }

  void _openResiScan() {
    Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (c) => ResiScanScreen(api: _api),
      ),
    );
  }

  void _openActionsMenu() {
    final actions = _mobileActions();
    final canPos = actions.contains('pos');
    final canScan = actions.contains('scan_resi');
    final canCreate = _canCreateTransactions(actions);

    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (canPos)
              ListTile(
                leading: const Icon(Icons.storefront),
                title: const Text('POS — Sell'),
                onTap: () {
                  Navigator.pop(ctx);
                  _openPosSell();
                },
              ),
            if (canScan)
              ListTile(
                leading: const Icon(Icons.qr_code_scanner),
                title: const Text('Scan resi'),
                onTap: () {
                  Navigator.pop(ctx);
                  _openResiScan();
                },
              ),
            if (canCreate)
              ListTile(
                leading: const Icon(Icons.point_of_sale),
                title: const Text('Create transaction'),
                onTap: () {
                  Navigator.pop(ctx);
                  _openCreate();
                },
              ),
          ],
        ),
      ),
    );
  }

  void _openCreate() {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (ctx) => CreateTransactionSheet(
        api: _api,
        preferences: _prefs(),
        actions: _mobileActions(),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = _me?['user'];
    final name = user is Map ? (user['name'] ?? user['username'] ?? '?').toString() : '…';
    final currentAppearance = (_prefs()?['appearance'] ?? 'system').toString();

    final pages = [
      DashboardScreen(api: _api, userName: name),
      TransactionsListScreen(api: _api),
    ];

    return Scaffold(
      appBar: AppBar(
        title: Text(_tabIndex == 0 ? 'Dashboard' : 'Transactions'),
        actions: [
          PopupMenuButton<String>(
            tooltip: 'Theme',
            icon: const Icon(Icons.palette_outlined),
            onSelected: (v) => _setAppearance(v),
            itemBuilder: (c) => [
              CheckedPopupMenuItem(
                value: 'system',
                checked: currentAppearance == 'system',
                child: const Text('Match system'),
              ),
              CheckedPopupMenuItem(
                value: 'light',
                checked: currentAppearance == 'light',
                child: const Text('Light'),
              ),
              CheckedPopupMenuItem(
                value: 'dark',
                checked: currentAppearance == 'dark',
                child: const Text('Dark'),
              ),
            ],
          ),
          IconButton(
            tooltip: 'Bluetooth printer',
            icon: const Icon(Icons.print_outlined),
            onPressed: () => Navigator.push<void>(
              context,
              MaterialPageRoute(builder: (c) => const PrinterSettingsScreen()),
            ),
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: _logout,
          ),
        ],
      ),
      body: IndexedStack(
        index: _tabIndex,
        children: pages,
      ),
      floatingActionButton: _buildFab(),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tabIndex,
        onDestinationSelected: (i) => setState(() => _tabIndex = i),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.dashboard_outlined),
            selectedIcon: Icon(Icons.dashboard),
            label: 'Dashboard',
          ),
          NavigationDestination(
            icon: Icon(Icons.receipt_long_outlined),
            selectedIcon: Icon(Icons.receipt_long),
            label: 'Transactions',
          ),
        ],
      ),
    );
  }
}
