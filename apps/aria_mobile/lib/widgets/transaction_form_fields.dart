import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Vertical gap between stacked outline fields so floating labels do not overlap
/// the field above (especially in landscape).
const double kTransactionFormFieldGap = 12;

/// Shared [InputDecoration] for transaction entry screens.
InputDecoration transactionFormDecoration(
  String labelText, {
  String? hintText,
}) {
  return InputDecoration(
    labelText: labelText,
    hintText: hintText,
    border: const OutlineInputBorder(),
    floatingLabelBehavior: FloatingLabelBehavior.auto,
    isDense: false,
    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
  );
}

/// Outline [TextField] with consistent decoration and trailing field gap.
class TransactionOutlineField extends StatelessWidget {
  const TransactionOutlineField({
    super.key,
    required this.controller,
    required this.label,
    this.keyboardType,
    this.inputFormatters,
    this.hint,
    this.gapAfter = kTransactionFormFieldGap,
  });

  final TextEditingController controller;
  final String label;
  final TextInputType? keyboardType;
  final List<TextInputFormatter>? inputFormatters;
  final String? hint;
  final double gapAfter;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: gapAfter),
      child: TextField(
        controller: controller,
        decoration: transactionFormDecoration(label, hintText: hint),
        keyboardType: keyboardType,
        inputFormatters: inputFormatters,
      ),
    );
  }
}

/// Groups invoice-level discount / adjustment fields with internal spacing.
class TransactionInvoiceOptionsCard extends StatelessWidget {
  const TransactionInvoiceOptionsCard({super.key, required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: kTransactionFormFieldGap),
      child: Card(
        margin: EdgeInsets.zero,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('Invoice options', style: theme.textTheme.titleSmall),
              const SizedBox(height: 12),
              ...children,
            ],
          ),
        ),
      ),
    );
  }
}
