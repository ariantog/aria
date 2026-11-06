import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:permission_handler/permission_handler.dart';

class ScannerScreen extends StatefulWidget {
  const ScannerScreen({super.key});

  @override
  State<ScannerScreen> createState() => _ScannerScreenState();
}

class _ScannerScreenState extends State<ScannerScreen> {
  bool _done = false;
  bool _ready = false;
  String? _deniedMessage;

  @override
  void initState() {
    super.initState();
    _ensureCamera();
  }

  Future<void> _ensureCamera() async {
    final status = await Permission.camera.request();
    if (!mounted) {
      return;
    }
    if (status.isGranted) {
      setState(() => _ready = true);
      return;
    }
    if (status.isPermanentlyDenied) {
      setState(() {
        _deniedMessage =
            'Camera access is required to scan barcodes. Open Settings and enable Camera permission for Aria, then try again.';
      });
      return;
    }
    setState(() {
      _deniedMessage = 'Camera permission was denied. Tap Retry to allow scanning.';
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Scan barcode')),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_deniedMessage != null) {
      return Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(_deniedMessage!, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: () async {
                setState(() {
                  _deniedMessage = null;
                  _ready = false;
                });
                final opened = await openAppSettings();
                if (!opened && mounted) {
                  await _ensureCamera();
                }
              },
              child: const Text('Open settings'),
            ),
            const SizedBox(height: 8),
            OutlinedButton(
              onPressed: () async {
                setState(() {
                  _deniedMessage = null;
                  _ready = false;
                });
                await _ensureCamera();
              },
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }
    if (!_ready) {
      return const Center(child: CircularProgressIndicator());
    }
    return MobileScanner(
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
    );
  }
}
