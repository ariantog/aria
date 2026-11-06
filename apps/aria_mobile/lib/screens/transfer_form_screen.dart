import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';
import 'package:uuid/uuid.dart';

import '../api/aria_api.dart';
import '../widgets/addrbook_picker_field.dart';

class TransferFormScreen extends StatefulWidget {
  const TransferFormScreen({super.key, required this.api});

  final AriaApi api;

  @override
  State<TransferFormScreen> createState() => _TransferFormScreenState();
}

class _TransferFormScreenState extends State<TransferFormScreen> {
  final _date = TextEditingController(text: DateFormat('yyyy-MM-dd').format(DateTime.now()));
  final _total = TextEditingController();
  final _invoice = TextEditingController();
  final _description = TextEditingController();

  int? _senderId;
  String? _senderName;
  int? _receiverId;
  String? _receiverName;

  bool _submitting = false;
  String? _error;
  String? _success;

  Future<void> _submit() async {
    setState(() {
      _submitting = true;
      _error = null;
      _success = null;
    });
    try {
      if (_senderId == null || _receiverId == null) {
        throw AriaApiException('Select from and to accounts');
      }
      final total = double.tryParse(_total.text);
      if (total == null || total < 0.01) {
        throw AriaApiException('Enter a valid amount');
      }
      final body = <String, dynamic>{
        'date': _date.text.trim(),
        'sender': _senderId,
        'receiver': _receiverId,
        'total': total,
        if (_invoice.text.trim().isNotEmpty) 'invoice': _invoice.text.trim(),
        if (_description.text.trim().isNotEmpty) 'description': _description.text.trim(),
      };
      final key = const Uuid().v4();
      final res = await widget.api.postTransfer(body, idempotencyKey: key);
      setState(() => _success = 'Created transfer #${res['id']}');
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
    _total.dispose();
    _invoice.dispose();
    _description.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Transfer')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TextField(
            controller: _date,
            decoration: const InputDecoration(labelText: 'Date', border: OutlineInputBorder()),
          ),
          AddrbookPickerField(
            api: widget.api,
            lookupType: 'transfer',
            addrbookType: '3,6',
            label: 'From (sender)',
            selectedId: _senderId,
            selectedName: _senderName,
            onChanged: (id, name) => setState(() {
              _senderId = id;
              _senderName = name;
            }),
          ),
          AddrbookPickerField(
            api: widget.api,
            lookupType: 'transfer',
            addrbookType: '3,6',
            label: 'To (receiver)',
            selectedId: _receiverId,
            selectedName: _receiverName,
            onChanged: (id, name) => setState(() {
              _receiverId = id;
              _receiverName = name;
            }),
          ),
          TextField(
            controller: _total,
            decoration: const InputDecoration(labelText: 'Amount', border: OutlineInputBorder()),
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.]'))],
          ),
          TextField(
            controller: _invoice,
            decoration: const InputDecoration(labelText: 'Reference (optional)', border: OutlineInputBorder()),
          ),
          TextField(
            controller: _description,
            decoration: const InputDecoration(labelText: 'Description (optional)', border: OutlineInputBorder()),
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
                : const Text('Submit transfer'),
          ),
        ],
      ),
    );
  }
}
