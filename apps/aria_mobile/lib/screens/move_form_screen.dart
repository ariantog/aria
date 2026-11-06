import 'package:flutter/material.dart';
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

class MoveFormScreen extends StatefulWidget {
  const MoveFormScreen({super.key, required this.api});

  final AriaApi api;

  @override
  State<MoveFormScreen> createState() => _MoveFormScreenState();
}

class _MoveFormScreenState extends State<MoveFormScreen> {
  final _date = TextEditingController(text: DateFormat('yyyy-MM-dd').format(DateTime.now()));
  final _invoice = TextEditingController();
  final _note = TextEditingController();

  int? _senderId;
  String? _senderName;
  int? _receiverId;
  String? _receiverName;

  final List<_Line> _lines = [];
  bool _submitting = false;
  String? _error;
  String? _success;

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
      final price = double.tryParse(item['price'].toString()) ?? 0;
      final name = item['name']?.toString() ?? '';
      setState(() {
        _lines.add(_Line(itemId: id, name: name, quantity: 1, price: price));
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
        throw AriaApiException('Select from and to warehouse');
      }
      if (_lines.isEmpty) {
        throw AriaApiException('Add at least one line');
      }
      final body = <String, dynamic>{
        'date': _date.text.trim(),
        'type': 'move',
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
      };
      final key = const Uuid().v4();
      final res = await widget.api.postMove(body, idempotencyKey: key);
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
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Move stock')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TextField(
            controller: _date,
            decoration: const InputDecoration(labelText: 'Date (Y-m-d)', border: OutlineInputBorder()),
          ),
          AddrbookPickerField(
            api: widget.api,
            lookupType: 'move',
            addrbookType: '2',
            label: 'From warehouse',
            selectedId: _senderId,
            selectedName: _senderName,
            onChanged: (id, name) => setState(() {
              _senderId = id;
              _senderName = name;
            }),
          ),
          AddrbookPickerField(
            api: widget.api,
            lookupType: 'move',
            addrbookType: '2',
            label: 'To warehouse',
            selectedId: _receiverId,
            selectedName: _receiverName,
            onChanged: (id, name) => setState(() {
              _receiverId = id;
              _receiverName = name;
            }),
          ),
          TextField(
            controller: _invoice,
            decoration: const InputDecoration(labelText: 'Reference (optional)', border: OutlineInputBorder()),
          ),
          TextField(
            controller: _note,
            decoration: const InputDecoration(labelText: 'Note (optional)', border: OutlineInputBorder()),
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
                      content: TextField(controller: t, autofocus: true),
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
          for (var i = 0; i < _lines.length; i++)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(_lines[i].name, style: Theme.of(context).textTheme.titleSmall),
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
            Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
          if (_success != null)
            Text(_success!, style: TextStyle(color: Theme.of(context).colorScheme.primary)),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: _submitting ? null : _submit,
            child: _submitting
                ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('Submit move'),
          ),
        ],
      ),
    );
  }
}
