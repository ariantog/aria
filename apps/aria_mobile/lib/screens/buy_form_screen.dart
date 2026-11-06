import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';
import 'package:uuid/uuid.dart';

import '../api/aria_api.dart';
import '../widgets/addrbook_picker_field.dart';
import 'scanner_screen.dart';

class _Line {
  _Line({required this.itemId, required this.name, required this.quantity, required this.price});

  int itemId;
  String name;
  double quantity;
  double price;
}

/// Buy: sender = supplier, receiver = warehouse (same as web `StoreItemTransactionRequest` / transaction_rules.buy).
class BuyFormScreen extends StatefulWidget {
  const BuyFormScreen({super.key, required this.api, this.preferences});

  final AriaApi api;
  final Map<String, dynamic>? preferences;

  @override
  State<BuyFormScreen> createState() => _BuyFormScreenState();
}

class _BuyFormScreenState extends State<BuyFormScreen> {
  final _date = TextEditingController(text: DateFormat('yyyy-MM-dd').format(DateTime.now()));
  final _invoice = TextEditingController();
  final _note = TextEditingController();
  final _discountPercent = TextEditingController(text: '0');
  final _adjustment = TextEditingController(text: '0');

  int? _senderId;
  String? _senderName;
  int? _receiverId;
  String? _receiverName;

  final List<_Line> _lines = [];
  bool _submitting = false;
  String? _error;
  String? _success;

  @override
  void initState() {
    super.initState();
    _applyPrefill();
  }

  void _applyPrefill() {
    final p = widget.preferences;
    if (p == null) {
      return;
    }
    final prefill = p['prefill'];
    if (prefill is! Map) {
      return;
    }
    final buy = prefill['buy'];
    if (buy is! Map) {
      return;
    }
    final sid = buy['sender_id'];
    final rid = buy['receiver_id'];
    final sName = buy['sender'];
    final rName = buy['receiver'];
    setState(() {
      if (sid != null) {
        _senderId = int.tryParse(sid.toString());
      }
      if (rid != null) {
        _receiverId = int.tryParse(rid.toString());
      }
      if (sName is Map && sName['name'] != null) {
        _senderName = sName['name'].toString();
      }
      if (rName is Map && rName['name'] != null) {
        _receiverName = rName['name'].toString();
      }
    });
  }

  Future<void> _scanAddLine() async {
    final code = await Navigator.push<String>(
      context,
      MaterialPageRoute(builder: (c) => const ScannerScreen()),
    );
    if (code == null || code.isEmpty) {
      return;
    }
    await _resolveAndAdd(code);
  }

  Future<void> _resolveAndAdd(String barcode) async {
    setState(() => _error = null);
    try {
      final item = await widget.api.lookupItemBarcode(barcode);
      if (item == null) {
        setState(() => _error = 'Item not found for "$barcode"');
        return;
      }
      final id = int.parse(item['id'].toString());
      final cost = double.tryParse((item['cost'] ?? item['price']).toString()) ?? 0;
      final name = item['name']?.toString() ?? '';
      setState(() {
        _lines.add(_Line(itemId: id, name: name, quantity: 1, price: cost));
      });
    } catch (e) {
      setState(() => _error = e.toString());
    }
  }

  Future<void> _submit() async {
    setState(() {
      _submitting = true;
      _error = null;
      _success = null;
    });
    try {
      if (_senderId == null || _receiverId == null) {
        throw AriaApiException('Select supplier and warehouse');
      }
      if (_lines.isEmpty) {
        throw AriaApiException('Add at least one line');
      }
      final body = <String, dynamic>{
        'date': _date.text.trim(),
        'type': 'buy',
        'sender_id': _senderId,
        'receiver_id': _receiverId,
        if (_invoice.text.trim().isNotEmpty) 'invoice': _invoice.text.trim(),
        if (_note.text.trim().isNotEmpty) 'note': _note.text.trim(),
        'items': _lines
            .map((l) => {
                  'item_id': l.itemId,
                  'quantity': l.quantity,
                  'price': l.price,
                  'discount': 0,
                })
            .toList(),
        'discount_percent': double.tryParse(_discountPercent.text) ?? 0,
        'adjustment': double.tryParse(_adjustment.text) ?? 0,
      };
      final key = const Uuid().v4();
      final res = await widget.api.postItemTransaction(body, idempotencyKey: key);
      setState(() => _success = 'Created transaction #${res['id']}');
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  void dispose() {
    _date.dispose();
    _invoice.dispose();
    _note.dispose();
    _discountPercent.dispose();
    _adjustment.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Buy')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TextField(
            controller: _date,
            decoration: const InputDecoration(labelText: 'Date (Y-m-d)', border: OutlineInputBorder()),
          ),
          AddrbookPickerField(
            api: widget.api,
            lookupType: 'buy',
            addrbookType: '4',
            label: 'Supplier (sender)',
            selectedId: _senderId,
            selectedName: _senderName,
            onChanged: (id, name) => setState(() {
              _senderId = id;
              _senderName = name;
            }),
          ),
          AddrbookPickerField(
            api: widget.api,
            lookupType: 'buy',
            addrbookType: '2',
            label: 'Warehouse (receiver)',
            selectedId: _receiverId,
            selectedName: _receiverName,
            onChanged: (id, name) => setState(() {
              _receiverId = id;
              _receiverName = name;
            }),
          ),
          TextField(
            controller: _invoice,
            decoration: const InputDecoration(labelText: 'Invoice (optional)', border: OutlineInputBorder()),
          ),
          TextField(
            controller: _note,
            decoration: const InputDecoration(labelText: 'Note (optional)', border: OutlineInputBorder()),
          ),
          TextField(
            controller: _discountPercent,
            decoration: const InputDecoration(labelText: 'Invoice discount %', border: OutlineInputBorder()),
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.]'))],
          ),
          TextField(
            controller: _adjustment,
            decoration: const InputDecoration(labelText: 'Adjustment (optional)', border: OutlineInputBorder()),
            keyboardType: const TextInputType.numberWithOptions(decimal: true, signed: true),
            inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.-]'))],
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              FilledButton.icon(
                onPressed: _scanAddLine,
                icon: const Icon(Icons.qr_code_scanner),
                label: const Text('Scan barcode'),
              ),
              OutlinedButton(
                onPressed: () async {
                  final t = TextEditingController();
                  await showDialog<void>(
                    context: context,
                    builder: (c) => AlertDialog(
                      title: const Text('SKU / code'),
                      content: TextField(
                        controller: t,
                        decoration: const InputDecoration(hintText: 'Type code then OK'),
                        autofocus: true,
                      ),
                      actions: [
                        TextButton(onPressed: () => Navigator.pop(c), child: const Text('Cancel')),
                        TextButton(
                          onPressed: () {
                            Navigator.pop(c);
                            _resolveAndAdd(t.text.trim());
                          },
                          child: const Text('OK'),
                        ),
                      ],
                    ),
                  );
                },
                child: const Text('Manual code'),
              ),
            ],
          ),
          const SizedBox(height: 8),
          for (var i = 0; i < _lines.length; i++)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(_lines[i].name, style: Theme.of(context).textTheme.titleSmall),
                    Text('Item #${_lines[i].itemId}', style: Theme.of(context).textTheme.bodySmall),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: TextField(
                            decoration: const InputDecoration(labelText: 'Qty', border: OutlineInputBorder()),
                            keyboardType: const TextInputType.numberWithOptions(decimal: true),
                            controller: TextEditingController(text: _lines[i].quantity.toString()),
                            onChanged: (v) => _lines[i].quantity = double.tryParse(v) ?? _lines[i].quantity,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: TextField(
                            decoration: const InputDecoration(labelText: 'Cost', border: OutlineInputBorder()),
                            keyboardType: const TextInputType.numberWithOptions(decimal: true),
                            controller: TextEditingController(text: _lines[i].price.toString()),
                            onChanged: (v) => _lines[i].price = double.tryParse(v) ?? _lines[i].price,
                          ),
                        ),
                        IconButton(
                          icon: const Icon(Icons.delete_outline),
                          onPressed: () => setState(() => _lines.removeAt(i)),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ),
          if (_success != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(_success!, style: TextStyle(color: Theme.of(context).colorScheme.primary)),
            ),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: _submitting ? null : _submit,
            child: _submitting
                ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('Submit buy'),
          ),
        ],
      ),
    );
  }
}
