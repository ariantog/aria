/// Formats transaction JSON from `GET /api/mobile/v1/transactions/{id}` into
/// plain-text receipt lines matching `resources/views/transactions/pdf/receipt.blade.php`.
library;

/// Indonesian-style integer: thousands `.`, no decimals (matches PHP `number_format(..., 0, ',', '.')`).
String formatReceiptAmount(num value) {
  var n = value.round();
  final negative = n < 0;
  if (negative) {
    n = -n;
  }
  if (n == 0) {
    return negative ? '-0' : '0';
  }
  final parts = <String>[];
  while (n > 0) {
    final chunk = n % 1000;
    n ~/= 1000;
    if (n > 0) {
      parts.add(chunk.toString().padLeft(3, '0'));
    } else {
      parts.add(chunk.toString());
    }
  }
  final formatted = parts.reversed.join('.');
  return negative ? '-$formatted' : formatted;
}

String formatReceiptDate(String? isoDate) {
  if (isoDate == null || isoDate.length < 10) {
    return isoDate ?? '';
  }
  final parts = isoDate.substring(0, 10).split('-');
  if (parts.length != 3) {
    return isoDate;
  }
  return '${parts[2]}/${parts[1]}/${parts[0]}';
}

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

String _itemCol(String name) {
  final trimmed = name.length <= 18 ? name : name.substring(0, 18);
  return _padRight(trimmed, 18);
}

String receiptLineRow(String item18, String qty3, String amt9) {
  return '$item18$qty3$amt9';
}

/// Builds monospace receipt body lines (no ESC/POS commands).
List<String> buildReceiptTextLines(Map<String, dynamic> tx) {
  final lines = <String>[];

  final branding = tx['receipt_branding'];
  if (branding is Map) {
    final company = branding['company_name']?.toString() ?? '';
    if (company.isNotEmpty) {
      lines.add(company);
    }
    final address = branding['address']?.toString() ?? '';
    if (address.trim().isNotEmpty) {
      for (final part in address.split('\n')) {
        final t = part.trim();
        if (t.isNotEmpty) {
          lines.add(t);
        }
      }
    }
    final phone = branding['phone']?.toString() ?? '';
    if (phone.trim().isNotEmpty) {
      lines.add(phone.trim());
    }
  }

  lines.add('');
  lines.add('Retail Invoice');
  lines.add('');
  lines.add('Date : ${formatReceiptDate(tx['date']?.toString())}');
  final invoice = tx['invoice']?.toString();
  final id = tx['id']?.toString() ?? '';
  lines.add('Invoice: ${invoice != null && invoice.isNotEmpty ? invoice : id}');

  final sender = tx['sender'];
  if (sender is Map) {
    lines.add(sender['name']?.toString() ?? '');
  }

  lines.add('------------------------------');
  lines.add(receiptLineRow(_padRight('Item', 18), _padLeft('Qty', 3), _padLeft('Amt', 9)));
  lines.add('------------------------------');

  var subtotal = 0.0;
  var subQty = 0.0;
  final details = tx['details'];
  if (details is List) {
    for (final raw in details) {
      if (raw is! Map) {
        continue;
      }
      final lineTotal = (raw['total'] as num?)?.toDouble() ?? 0;
      final absLine = lineTotal.abs();
      final name = raw['display_name']?.toString() ??
          raw['name']?.toString() ??
          '-';
      final qty = (raw['quantity'] as num?)?.toDouble() ?? 0;
      subtotal += absLine;
      subQty += qty;
      final qtyCol = _padLeft(formatReceiptAmount(qty), 3);
      final amtCol = _padLeft(formatReceiptAmount(absLine), 9);
      lines.add(receiptLineRow(_itemCol(name), qtyCol, amtCol));
    }
  }

  lines.add('------------------------------');
  lines.add(receiptLineRow(
    _padRight('SubTotal', 18),
    _padLeft(formatReceiptAmount(subQty), 3),
    _padLeft(formatReceiptAmount(subtotal), 9),
  ));

  final discount = (tx['discount'] as num?)?.toDouble() ?? 0;
  if (discount > 0) {
    lines.add('Disc ${formatReceiptAmount(discount)}%');
  }

  final adjustment = (tx['adjustment'] as num?)?.toDouble() ?? 0;
  if (adjustment != 0) {
    lines.add('Adj: ${formatReceiptAmount(adjustment)}');
  }

  final ppn = (tx['ppn'] as num?)?.toDouble() ?? 0;
  if (ppn > 0) {
    lines.add('PPN: ${formatReceiptAmount(ppn)}');
  }

  lines.add('------------------------------');
  final grand = (tx['grand_total'] as num?)?.toDouble() ??
      ((tx['total'] as num?)?.toDouble() ?? 0).abs();
  lines.add('TOTAL: Rp${formatReceiptAmount(grand)}');
  lines.add('');
  lines.add('Terima kasih');

  return lines;
}