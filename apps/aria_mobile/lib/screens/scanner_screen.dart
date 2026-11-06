import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

class ScannerScreen extends StatefulWidget {
  const ScannerScreen({super.key});

  @override
  State<ScannerScreen> createState() => _ScannerScreenState();
}

class _ScannerScreenState extends State<ScannerScreen> {
  bool _done = false;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Scan barcode')),
      body: MobileScanner(
        onDetect: (capture) {
          if (_done) {
            return;
          }
          final barcodes = capture.barcodes;
          if (barcodes.isEmpty) {
            return;
          }
          final raw = barcodes.first.rawValue;
          if (raw == null || raw.isEmpty) {
            return;
          }
          _done = true;
          Navigator.pop(context, raw);
        },
      ),
    );
  }
}
