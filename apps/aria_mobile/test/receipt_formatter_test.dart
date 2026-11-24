import 'package:aria_mobile/printing/receipt_formatter.dart';
import 'package:flutter_test/flutter_test.dart';

String _padLeft(String s, int width) {
  if (s.length >= width) {
    return s;
  }
  return '${' ' * (width - s.length)}$s';
}

String _padRight(String s, int width) {
  if (s.length >= width) {
    return s;
  }
  return '$s${' ' * (width - s.length)}';
}

void main() {
  test('formatReceiptAmount uses Indonesian thousands separator', () {
    expect(formatReceiptAmount(934700), '934.700');
    expect(formatReceiptAmount(135000), '135.000');
    expect(formatReceiptAmount(0), '0');
  });

  test('receipt line columns are 18+3+9 monospace', () {
    final row = receiptLineRow(
      _padRight('Item', 18),
      _padLeft('2', 3),
      _padLeft('135.000', 9),
    );
    expect(row.length, 30);
    expect(row, '${'Item${' ' * 14}'}  2  135.000');
  });

  test('buildReceiptTextLines includes branding and total', () {
    final lines = buildReceiptTextLines({
      'id': 1,
      'date': '2026-11-24',
      'invoice': 'INV-1',
      'sender': {'name': 'WH Test'},
      'receipt_branding': {
        'company_name': 'CORENATION',
        'address': 'Jl. Test',
        'phone': '08123',
      },
      'details': [
        {
          'display_name': 'SKU NAME',
          'quantity': 2,
          'total': -100000,
        },
      ],
      'discount': 0,
      'adjustment': 0,
      'ppn': 0,
      'grand_total': 100000,
    });

    expect(lines.first, 'CORENATION');
    expect(lines.any((l) => l.contains('Retail Invoice')), true);
    expect(lines.any((l) => l.startsWith('TOTAL: Rp100.000')), true);
    expect(lines.any((l) => l.contains('SKU NAME')), true);
  });
}