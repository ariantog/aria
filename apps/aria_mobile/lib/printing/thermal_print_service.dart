import 'dart:io' show Platform;

import 'package:esc_pos_utils_plus/esc_pos_utils_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:print_bluetooth_thermal/print_bluetooth_thermal.dart';

import 'receipt_formatter.dart';

class BluetoothPrinterDevice {
  BluetoothPrinterDevice({required this.name, required this.mac});

  final String name;
  final String mac;
}

class ThermalPrintService {
  static Future<bool> ensureBluetoothPermissions() async {
    if (kIsWeb) {
      return false;
    }
    if (!Platform.isAndroid) {
      return true;
    }
    final connect = await Permission.bluetoothConnect.request();
    if (!connect.isGranted) {
      return false;
    }
    final scan = await Permission.bluetoothScan.request();
    return scan.isGranted;
  }

  static Future<bool> isBluetoothReady() async {
    if (kIsWeb) {
      return false;
    }
    final granted = await PrintBluetoothThermal.isPermissionBluetoothGranted;
    if (!granted) {
      final ok = await ensureBluetoothPermissions();
      if (!ok) {
        return false;
      }
    }
    return PrintBluetoothThermal.bluetoothEnabled;
  }

  static Future<List<BluetoothPrinterDevice>> pairedDevices() async {
    final list = await PrintBluetoothThermal.pairedBluetooths;
    return list
        .map((e) => BluetoothPrinterDevice(
              name: e.name,
              mac: e.macAdress,
            ))
        .toList();
  }

  static Future<List<int>> buildEscPosBytes(Map<String, dynamic> tx) async {
    final profile = await CapabilityProfile.load();
    final generator = Generator(PaperSize.mm58, profile);
    var bytes = <int>[];

    final textLines = buildReceiptTextLines(tx);
    var i = 0;
    while (i < textLines.length) {
      final line = textLines[i];
      final brandingEnd = _indexAfterBranding(textLines);
      final isHeaderCompany = i == 0 && line.isNotEmpty;
      final isTotal = line.startsWith('TOTAL:');

      if (isHeaderCompany) {
        bytes = [
          ...bytes,
          ...generator.text(
            line,
            styles: const PosStyles(align: PosAlign.center, bold: true),
          ),
        ];
      } else if (i > 0 && i < brandingEnd) {
        bytes = [...bytes, ...generator.text(line, styles: const PosStyles(align: PosAlign.center))];
      } else if (line == 'Retail Invoice') {
        bytes = [...bytes, ...generator.text(line, styles: const PosStyles(align: PosAlign.center))];
      } else if (line == 'Terima kasih') {
        bytes = [...bytes, ...generator.text(line, styles: const PosStyles(align: PosAlign.center))];
      } else if (isTotal) {
        bytes = [...bytes, ...generator.text(line, styles: const PosStyles(bold: true))];
      } else if (line.startsWith('---')) {
        bytes = [...bytes, ...generator.hr()];
      } else if (line.isEmpty) {
        bytes = [...bytes, ...generator.feed(1)];
      } else {
        bytes = [...bytes, ...generator.text(line)];
      }
      i++;
    }

    bytes = [...bytes, ...generator.feed(2), ...generator.cut()];
    return bytes;
  }

  static int _indexAfterBranding(List<String> lines) {
    var i = 0;
    if (lines.isEmpty) {
      return 0;
    }
    i++;
    while (i < lines.length && lines[i].isNotEmpty && lines[i] != 'Retail Invoice') {
      i++;
    }
    return i;
  }

  static Future<void> printToMac(String mac, List<int> bytes) async {
    final ready = await isBluetoothReady();
    if (!ready) {
      throw StateError('Bluetooth tidak aktif atau izin ditolak.');
    }

    final connected = await PrintBluetoothThermal.connectionStatus;
    if (!connected) {
      final ok = await PrintBluetoothThermal.connect(macPrinterAddress: mac);
      if (!ok) {
        throw StateError('Gagal terhubung ke printer ($mac).');
      }
    }

    final wrote = await PrintBluetoothThermal.writeBytes(bytes);
    if (!wrote) {
      throw StateError('Gagal mengirim data ke printer.');
    }
  }

  static Future<void> printTestPage(String mac) async {
    final profile = await CapabilityProfile.load();
    final generator = Generator(PaperSize.mm58, profile);
    final bytes = <int>[
      ...generator.text(
        'Aria Mobile',
        styles: const PosStyles(align: PosAlign.center, bold: true),
      ),
      ...generator.text('Test print 58mm', styles: const PosStyles(align: PosAlign.center)),
      ...generator.feed(2),
      ...generator.cut(),
    ];
    await printToMac(mac, bytes);
  }

  static Future<void> printTransactionReceipt(String mac, Map<String, dynamic> tx) async {
    final bytes = await buildEscPosBytes(tx);
    await printToMac(mac, bytes);
  }
}