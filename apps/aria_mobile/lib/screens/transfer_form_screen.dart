import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';
import 'package:uuid/uuid.dart';

import '../api/aria_api.dart';
import '../widgets/addrbook_picker_field.dart';
import '../widgets/transaction_form_fields.dart';

class TransferFormScreen extends StatefulWidget {
  const TransferFormScreen({super.key, required this.api, this.preferences});

  final AriaApi api;
  final Map<String, dynamic>? preferences;

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

  @override
  void initState() {
    super.initState();
    _applyDefaults();
  }

  void _applyDefaults() {
    final p = widget.preferences;
    if (p == null) {
      return;
    }
    final t = p['transfer'];
    if (t is! Map) {
      return;
    }
    final sid = t['sender_id'];
    final rid = t['receiver_id'];
    final sParty = t['sender'];
    final rParty = t['receiver'];
    setState(() {
      if (sid != null) {
        _senderId = int.tryParse(sid.toString());
      }
      if (rid != null) {
        _receiverId = int.tryParse(rid.toString());
      }
      if (sParty is Map && sParty['name'] != null) {
        _senderName = sParty['name'].toString();
      }
      if (rParty is Map && rParty['name'] != null) {
        _receiverName = rParty['name'].toString();
      }
    });
  }

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
          TransactionOutlineField(controller: _date, label: 'Date'),
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
          TransactionOutlineField(
            controller: _total,
            label: 'Amount',
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.]'))],
          ),
          TransactionOutlineField(controller: _invoice, label: 'Reference (optional)'),
          TransactionOutlineField(
            controller: _description,
            label: 'Description (optional)',
            gapAfter: 0,
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
