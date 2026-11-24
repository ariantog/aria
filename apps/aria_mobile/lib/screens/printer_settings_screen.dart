import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../printing/printer_prefs.dart';
import '../printing/thermal_print_service.dart';

class PrinterSettingsScreen extends StatefulWidget {
  const PrinterSettingsScreen({super.key});

  @override
  State<PrinterSettingsScreen> createState() => _PrinterSettingsScreenState();
}

class _PrinterSettingsScreenState extends State<PrinterSettingsScreen> {
  PrinterPrefs? _prefs;
  List<BluetoothPrinterDevice> _devices = [];
  String? _error;
  bool _loading = true;
  bool _busy = false;
  String? _selectedMac;

  @override
  void initState() {
    super.initState();
    _init();
  }

  Future<void> _init() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final prefs = await PrinterPrefs.load();
      if (!kIsWeb && Platform.isAndroid) {
        await ThermalPrintService.ensureBluetoothPermissions();
      }
      final devices = kIsWeb ? <BluetoothPrinterDevice>[] : await ThermalPrintService.pairedDevices();
      if (!mounted) return;
      setState(() {
        _prefs = prefs;
        _devices = devices;
        _selectedMac = prefs.mac;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _saveSelection(BluetoothPrinterDevice device) async {
    final prefs = _prefs;
    if (prefs == null) {
      return;
    }
    await prefs.save(mac: device.mac, name: device.name);
    setState(() => _selectedMac = device.mac);
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Printer saved: ${device.name}')),
      );
    }
  }

  Future<void> _testPrint() async {
    final mac = _prefs?.mac;
    if (mac == null || mac.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pilih printer terlebih dahulu.')),
      );
      return;
    }
    setState(() => _busy = true);
    try {
      await ThermalPrintService.printTestPage(mac);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Test print terkirim.')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final savedName = _prefs?.name;

    return Scaffold(
      appBar: AppBar(title: const Text('Bluetooth printer')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (!kIsWeb && !Platform.isAndroid)
                  Card(
                    color: Theme.of(context).colorScheme.tertiaryContainer,
                    child: const Padding(
                      padding: EdgeInsets.all(12),
                      child: Text(
                        'Thermal Bluetooth receipt printing is tested on Android first. '
                        'iOS may work via print_bluetooth_thermal but is not validated in this build.',
                      ),
                    ),
                  ),
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                  ),
                Text('Saved printer', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 4),
                Text(savedName != null && savedName.isNotEmpty ? '$savedName (${_prefs?.mac})' : 'None'),
                const SizedBox(height: 16),
                Row(
                  children: [
                    FilledButton.icon(
                      onPressed: _busy ? null : _testPrint,
                      icon: const Icon(Icons.print),
                      label: const Text('Test print'),
                    ),
                    const SizedBox(width: 8),
                    OutlinedButton(
                      onPressed: _init,
                      child: const Text('Refresh list'),
                    ),
                  ],
                ),
                const Divider(height: 32),
                Text('Paired devices', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                if (_devices.isEmpty)
                  const Text('No paired Bluetooth printers found. Pair the printer in Android Settings first.'),
                for (final d in _devices)
                  RadioListTile<String>(
                    value: d.mac,
                    groupValue: _selectedMac,
                    onChanged: (v) => _saveSelection(d),
                    title: Text(d.name),
                    subtitle: Text(d.mac),
                  ),
              ],
            ),
    );
  }
}