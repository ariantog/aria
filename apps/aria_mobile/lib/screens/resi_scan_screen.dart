import 'package:flutter/material.dart';

import '../api/aria_api.dart';
import 'scanner_screen.dart';

class ResiScanScreen extends StatefulWidget {
  const ResiScanScreen({super.key, required this.api});

  final AriaApi api;

  @override
  State<ResiScanScreen> createState() => _ResiScanScreenState();
}

class _ResiScanScreenState extends State<ResiScanScreen> {
  Map<String, dynamic>? _options;
  String? _error;
  bool _loading = true;
  bool _submitting = false;

  int? _warehouseId;
  String? _marketplace;
  bool _rememberDefaults = true;

  final List<String> _barcodes = [];

  @override
  void initState() {
    super.initState();
    _loadOptions();
  }

  Future<void> _loadOptions() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final j = await widget.api.getResiScanOptions();
      if (!mounted) return;

      final defaults = j['defaults'];
      int? wh;
      String? mp;
      if (defaults is Map) {
        final rawWh = defaults['warehouse_id'];
        if (rawWh is int) {
          wh = rawWh;
        } else if (rawWh != null) {
          wh = int.tryParse(rawWh.toString());
        }
        final rawMp = defaults['marketplace'];
        if (rawMp is String && rawMp.isNotEmpty) {
          mp = rawMp;
        }
      }

      setState(() {
        _options = j;
        _warehouseId = wh;
        _marketplace = mp;
        _loading = false;
      });
      _ensureMarketplaceValid();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  List<Map<String, dynamic>> _warehouses() {
    final list = _options?['warehouses'];
    if (list is List) {
      return list
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
    }
    return [];
  }

  List<Map<String, dynamic>> _marketplacesForWarehouse(int warehouseId) {
    for (final wh in _warehouses()) {
      if (wh['id'] == warehouseId) {
        final mps = wh['marketplaces'];
        if (mps is List) {
          return mps
              .whereType<Map>()
              .map((e) => Map<String, dynamic>.from(e))
              .toList();
        }
      }
    }
    return [];
  }

  void _ensureMarketplaceValid() {
    if (_warehouseId == null) return;
    final mps = _marketplacesForWarehouse(_warehouseId!);
    if (mps.isEmpty) {
      setState(() => _marketplace = null);
      return;
    }
    if (_marketplace != null && mps.any((m) => m['key'] == _marketplace)) {
      return;
    }
    setState(() => _marketplace = mps.first['key']?.toString());
  }

  Future<void> _scan() async {
    final code = await Navigator.push<String>(
      context,
      MaterialPageRoute(builder: (c) => const ScannerScreen()),
    );
    if (code == null || code.trim().isEmpty) return;
    final trimmed = code.trim();
    if (_barcodes.contains(trimmed)) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Barcode already in list')),
        );
      }
      return;
    }
    setState(() => _barcodes.add(trimmed));
  }

  Future<void> _submit() async {
    if (_warehouseId == null || _marketplace == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pick warehouse and marketplace')),
      );
      return;
    }
    if (_barcodes.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Scan at least one resi')),
      );
      return;
    }

    setState(() => _submitting = true);
    try {
      final result = await widget.api.submitResiScan(
        warehouseId: _warehouseId!,
        marketplace: _marketplace!,
        barcodes: List<String>.from(_barcodes),
        rememberDefaults: _rememberDefaults,
      );
      if (!mounted) return;

      final created = result['created'];
      final skipped = result['skipped'];
      final createdN = created is List ? created.length : 0;
      final skippedN = skipped is List ? skipped.length : 0;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(result['message']?.toString() ?? 'Submitted')),
      );

      if (createdN > 0) {
        setState(() => _barcodes.clear());
      }

      if (skippedN > 0 && skipped is List) {
        final details = skipped
            .whereType<Map>()
            .map((s) => '${s['invoice'] ?? s['raw']}: ${s['reason']}')
            .take(3)
            .join('\n');
        if (details.isNotEmpty && mounted) {
          await showDialog<void>(
            context: context,
            builder: (c) => AlertDialog(
              title: const Text('Skipped'),
              content: Text(details),
              actions: [
                TextButton(onPressed: () => Navigator.pop(c), child: const Text('OK')),
              ],
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Scan resi')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : Column(
                  children: [
                    Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          DropdownButtonFormField<int>(
                            value: _warehouseId,
                            decoration: const InputDecoration(
                              labelText: 'Warehouse',
                              border: OutlineInputBorder(),
                            ),
                            items: _warehouses()
                                .map(
                                  (w) => DropdownMenuItem<int>(
                                    value: int.parse(w['id'].toString()),
                                    child: Text(w['name']?.toString() ?? 'Warehouse'),
                                  ),
                                )
                                .toList(),
                            onChanged: (v) {
                              setState(() {
                                _warehouseId = v;
                                _marketplace = null;
                              });
                              _ensureMarketplaceValid();
                            },
                          ),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<String>(
                            value: _marketplace,
                            decoration: const InputDecoration(
                              labelText: 'Marketplace',
                              border: OutlineInputBorder(),
                            ),
                            items: (_warehouseId == null
                                    ? <Map<String, dynamic>>[]
                                    : _marketplacesForWarehouse(_warehouseId!))
                                .map(
                                  (m) => DropdownMenuItem<String>(
                                    value: m['key']?.toString(),
                                    child: Text(m['label']?.toString() ?? m['key']?.toString() ?? '?'),
                                  ),
                                )
                                .toList(),
                            onChanged: _warehouseId == null
                                ? null
                                : (v) => setState(() => _marketplace = v),
                          ),
                          const SizedBox(height: 8),
                          SwitchListTile(
                            contentPadding: EdgeInsets.zero,
                            title: const Text('Remember warehouse & marketplace'),
                            value: _rememberDefaults,
                            onChanged: (v) => setState(() => _rememberDefaults = v),
                          ),
                        ],
                      ),
                    ),
                    Expanded(
                      child: _barcodes.isEmpty
                          ? const Center(child: Text('No scans yet — tap Scan'))
                          : ListView.builder(
                              itemCount: _barcodes.length,
                              itemBuilder: (c, i) {
                                return ListTile(
                                  title: Text(_barcodes[i]),
                                  trailing: IconButton(
                                    icon: const Icon(Icons.delete_outline),
                                    onPressed: () => setState(() => _barcodes.removeAt(i)),
                                  ),
                                );
                              },
                            ),
                    ),
                    Padding(
                      padding: const EdgeInsets.all(16),
                      child: Row(
                        children: [
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: _submitting ? null : _scan,
                              icon: const Icon(Icons.qr_code_scanner),
                              label: const Text('Scan'),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: FilledButton(
                              onPressed: _submitting ? null : _submit,
                              child: _submitting
                                  ? const SizedBox(
                                      width: 20,
                                      height: 20,
                                      child: CircularProgressIndicator(strokeWidth: 2),
                                    )
                                  : const Text('Submit to Aria'),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
    );
  }
}
