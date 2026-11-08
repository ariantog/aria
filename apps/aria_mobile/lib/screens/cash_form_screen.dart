import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';
import 'package:uuid/uuid.dart';

import '../api/aria_api.dart';
import '../widgets/addrbook_picker_field.dart';
import '../widgets/transaction_form_fields.dart';

class CashFormScreen extends StatefulWidget {
  const CashFormScreen({super.key, required this.api, required this.cashIn, this.preferences});

  final AriaApi api;
  final bool cashIn;
  final Map<String, dynamic>? preferences;

  @override
  State<CashFormScreen> createState() => _CashFormScreenState();
}

class _CashFormScreenState extends State<CashFormScreen> {
  final _date = TextEditingController(text: DateFormat('yyyy-MM-dd').format(DateTime.now()));
  final _total = TextEditingController();
  final _invoice = TextEditingController();
  final _note = TextEditingController();

  int? _bankId;
  String? _bankName;
  int? _partyId;
  String? _partyName;

  bool _submitting = false;
  String? _error;
  String? _success;

  @override
  void initState() {
    super.initState();
    _applyDefaultBank();
  }

  void _applyDefaultBank() {
    final p = widget.preferences;
    if (p == null) {
      return;
    }
    final key = widget.cashIn ? 'cash_in_bank' : 'cash_out_bank';
    final bank = p[key];
    if (bank is Map && bank['id'] != null) {
      setState(() {
        _bankId = int.tryParse(bank['id'].toString());
        _bankName = bank['name']?.toString();
      });
    }
  }

  Future<void> _submit() async {
    setState(() {
      _submitting = true;
      _error = null;
      _success = null;
    });
    try {
      if (_bankId == null || _partyId == null) {
        throw AriaApiException('Select bank and counterparty');
      }
      final total = double.tryParse(_total.text);
      if (total == null || total < 0.01) {
        throw AriaApiException('Enter a valid amount');
      }
      final body = <String, dynamic>{
        'date': _date.text.trim(),
        'account_id': _bankId,
        'items': [
          {
            'customer_id': _partyId,
            'total': total,
            if (_invoice.text.trim().isNotEmpty) 'invoice': _invoice.text.trim(),
            if (_note.text.trim().isNotEmpty) 'note': _note.text.trim(),
          },
        ],
      };
      final key = const Uuid().v4();
      final res = widget.cashIn
          ? await widget.api.postCashIn(body, idempotencyKey: key)
          : await widget.api.postCashOut(body, idempotencyKey: key);
      final ids = res['ids'];
      setState(() => _success = 'Created IDs: $ids');
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
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final title = widget.cashIn ? 'Cash in' : 'Cash out';
    final partyLabel = widget.cashIn ? 'Paying party (customer/supplier/…)' : 'Receiving party';

    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TransactionOutlineField(controller: _date, label: 'Date'),
          AddrbookPickerField(
            api: widget.api,
            lookupType: widget.cashIn ? 'cash-in' : 'cash-out',
            addrbookType: '3',
            label: 'Bank account',
            selectedId: _bankId,
            selectedName: _bankName,
            onChanged: (id, name) => setState(() {
              _bankId = id;
              _bankName = name;
            }),
          ),
          AddrbookPickerField(
            api: widget.api,
            lookupType: widget.cashIn ? 'cash-in' : 'cash-out',
            addrbookType: '8,1,4,7',
            label: partyLabel,
            selectedId: _partyId,
            selectedName: _partyName,
            onChanged: (id, name) => setState(() {
              _partyId = id;
              _partyName = name;
            }),
          ),
          TransactionOutlineField(
            controller: _total,
            label: 'Amount',
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.]'))],
          ),
          TransactionOutlineField(controller: _invoice, label: 'Invoice (optional)'),
          TransactionOutlineField(
            controller: _note,
            label: 'Note (optional)',
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
                : Text('Submit $title'),
          ),
        ],
      ),
    );
  }
}
