import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';
import 'package:printing/printing.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:uuid/uuid.dart';

import '../api/aria_api.dart';
import '../widgets/addrbook_picker_field.dart';
import '../widgets/transaction_form_fields.dart';
import '../printing/transaction_print_actions.dart';
import 'scanner_screen.dart';

class _Line {
  _Line({required this.itemId, required this.name, required this.quantity, required this.price});

  int itemId;
  String name;
  double quantity;
  double price;
}

class PosSellScreen extends StatefulWidget {
  const PosSellScreen({super.key, required this.api, this.preferences});

  final AriaApi api;
  final Map<String, dynamic>? preferences;

  @override
  State<PosSellScreen> createState() => _PosSellScreenState();
}

class _PosSellScreenState extends State<PosSellScreen> {
  final _date = TextEditingController(text: DateFormat('yyyy-MM-dd').format(DateTime.now()));
  final _invoice = TextEditingController();
  final _note = TextEditingController();
  final _discountPercent = TextEditingController(text: '0');
  final _adjustment = TextEditingController(text: '0');
  final _whatsappPhone = TextEditingController();

  int? _senderId;
  String? _senderName;
  int? _receiverId;
  String? _receiverName;

  final List<_Line> _lines = [];
  bool _submitting = false;
  bool _rememberDefaults = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _applyPosDefaults();
  }

  void _applyPosDefaults() {
    final p = widget.preferences;
    if (p == null) {
      return;
    }
    final pos = p['pos'];
    if (pos is Map) {
      final sid = pos['sender_id'];
      final rid = pos['receiver_id'];
      final sName = pos['sender'];
      final rName = pos['receiver'];
      final wa = pos['whatsapp_phone'];
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
        if (wa != null && wa.toString().isNotEmpty) {
          _whatsappPhone.text = wa.toString();
        }
      });
      return;
    }
    final prefill = p['prefill'];
    if (prefill is Map) {
      final sell = prefill['sell'];
      if (sell is Map) {
        final sid = sell['sender_id'];
        final rid = sell['receiver_id'];
        setState(() {
          if (sid != null) {
            _senderId = int.tryParse(sid.toString());
          }
          if (rid != null) {
            _receiverId = int.tryParse(rid.toString());
          }
        });
      }
    }
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
    });
    try {
      if (_senderId == null || _receiverId == null) {
        throw AriaApiException('Select warehouse and customer');
      }
      if (_lines.isEmpty) {
        throw AriaApiException('Add at least one line');
      }
      final body = <String, dynamic>{
        'date': _date.text.trim(),
        'type': 'sell',
        'pos': true,
        'remember_defaults': _rememberDefaults,
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
      final wa = _whatsappPhone.text.trim();
      if (wa.isNotEmpty) {
        body['whatsapp_phone'] = wa.replaceAll(RegExp(r'\D'), '');
      }
      final key = const Uuid().v4();
      final res = await widget.api.postSell(body, idempotencyKey: key);
      final id = int.parse(res['id'].toString());
      final pos = res['pos'];
      if (!mounted) {
        return;
      }
      await _showSuccessSheet(id, pos is Map ? Map<String, dynamic>.from(pos) : null);
      if (!mounted) {
        return;
      }
      setState(() {
        _lines.clear();
        _invoice.clear();
        _note.clear();
        _discountPercent.text = '0';
        _adjustment.text = '0';
      });
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  Future<void> _showSuccessSheet(int transactionId, Map<String, dynamic>? pos) async {
    final reminder = pos?['jubelio_reminder'];
    final wa = pos?['whatsapp'];
    String? waUrl;
    if (wa is Map && wa['url'] != null) {
      waUrl = wa['url'].toString();
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (ctx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('Sell #$transactionId saved', style: Theme.of(ctx).textTheme.titleLarge),
              const SizedBox(height: 12),
              if (reminder is Map && reminder['show'] == true)
                Card(
                  color: Theme.of(ctx).colorScheme.tertiaryContainer,
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Text(
                      reminder['message']?.toString() ?? 'Push ke Jubelio dari web jika diperlukan.',
                      style: Theme.of(ctx).textTheme.bodySmall,
                    ),
                  ),
                ),
              const SizedBox(height: 8),
              FilledButton.icon(
                onPressed: () async {
                  try {
                    final bytes = await widget.api.fetchReceiptPdf(transactionId);
                    if (ctx.mounted) {
                      await Printing.layoutPdf(
                        onLayout: (_) async => Uint8List.fromList(bytes),
                      );
                    }
                  } catch (e) {
                    if (ctx.mounted) {
                      ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text(e.toString())));
                    }
                  }
                },
                icon: const Icon(Icons.picture_as_pdf),
                label: const Text('Print PDF (58mm)'),
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: () async {
                  try {
                    final j = await widget.api.getTransaction(transactionId);
                    final tx = j['transaction'];
                    if (tx is! Map) {
                      throw StateError('Transaksi tidak ditemukan.');
                    }
                    final map = tx is Map<String, dynamic> ? tx : Map<String, dynamic>.from(tx);
                    if (!ctx.mounted) {
                      return;
                    }
                    final rootContext = context;
                    Navigator.pop(ctx);
                    await printTransactionBluetooth(rootContext, map);
                  } catch (e) {
                    if (ctx.mounted) {
                      ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text(e.toString())));
                    }
                  }
                },
                icon: const Icon(Icons.bluetooth),
                label: const Text('Bluetooth ESC/POS print'),
              ),
              if (waUrl != null && waUrl.isNotEmpty) ...[
                const SizedBox(height: 8),
                OutlinedButton.icon(
                  onPressed: () async {
                    final uri = Uri.parse(waUrl!);
                    if (await canLaunchUrl(uri)) {
                      await launchUrl(uri, mode: LaunchMode.externalApplication);
                    }
                  },
                  icon: const Icon(Icons.chat),
                  label: const Text('Send WhatsApp invoice'),
                ),
              ],
              const SizedBox(height: 8),
              TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Done')),
            ],
          ),
        ),
      ),
    );
  }

  @override
  void dispose() {
    _date.dispose();
    _invoice.dispose();
    _note.dispose();
    _discountPercent.dispose();
    _adjustment.dispose();
    _whatsappPhone.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('POS — Sell')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          FilledButton.icon(
            onPressed: _scanAddLine,
            icon: const Icon(Icons.qr_code_scanner),
            label: const Text('Scan barcode'),
          ),
          const SizedBox(height: 12),
          AddrbookPickerField(
            api: widget.api,
            lookupType: 'sell',
            addrbookType: '2',
            label: 'Warehouse (sender)',
            selectedId: _senderId,
            selectedName: _senderName,
            onChanged: (id, name) => setState(() {
              _senderId = id;
              _senderName = name;
            }),
          ),
          AddrbookPickerField(
            api: widget.api,
            lookupType: 'sell',
            addrbookType: '1,7',
            label: 'Customer / reseller',
            selectedId: _receiverId,
            selectedName: _receiverName,
            onChanged: (id, name) => setState(() {
              _receiverId = id;
              _receiverName = name;
            }),
          ),
          TransactionOutlineField(
            controller: _whatsappPhone,
            label: 'WhatsApp (optional)',
            keyboardType: TextInputType.phone,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
          ),
          SwitchListTile(
            value: _rememberDefaults,
            onChanged: (v) => setState(() => _rememberDefaults = v),
            title: const Text('Remember warehouse, customer & WhatsApp'),
            contentPadding: EdgeInsets.zero,
          ),
          TransactionOutlineField(controller: _date, label: 'Date (Y-m-d)'),
          TransactionOutlineField(controller: _invoice, label: 'Invoice (optional)'),
          for (var i = 0; i < _lines.length; i++)
            Card(
              child: ListTile(
                title: Text(_lines[i].name),
                subtitle: Text('Qty ${_lines[i].quantity} × ${_lines[i].price}'),
                trailing: IconButton(
                  icon: const Icon(Icons.delete_outline),
                  onPressed: () => setState(() => _lines.removeAt(i)),
                ),
              ),
            ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: _submitting ? null : _submit,
            child: _submitting
                ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('Complete sale'),
          ),
        ],
      ),
    );
  }
}