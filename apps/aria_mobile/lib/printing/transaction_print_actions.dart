import 'package:flutter/material.dart';

import 'printer_prefs.dart';
import 'thermal_print_service.dart';
import '../screens/printer_settings_screen.dart';

Future<void> printTransactionBluetooth(
  BuildContext context,
  Map<String, dynamic> tx,
) async {
  final prefs = await PrinterPrefs.load();
  final mac = prefs.mac;
  if (mac == null || mac.isEmpty) {
    if (!context.mounted) {
      return;
    }
    final go = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('No printer'),
        content: const Text('Pilih printer Bluetooth 58mm di pengaturan terlebih dahulu.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Settings')),
        ],
      ),
    );
    if (go == true && context.mounted) {
      await Navigator.push<void>(
        context,
        MaterialPageRoute(builder: (c) => const PrinterSettingsScreen()),
      );
    }
    return;
  }

  if (!context.mounted) {
    return;
  }
  ScaffoldMessenger.of(context).showSnackBar(
    const SnackBar(content: Text('Mencetak ke printer Bluetooth…')),
  );

  try {
    await ThermalPrintService.printTransactionReceipt(mac, tx);
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Struk terkirim ke printer.')),
      );
    }
  } catch (e) {
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }
}