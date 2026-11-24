import 'package:shared_preferences/shared_preferences.dart';

const _keyMac = 'thermal_printer_mac';
const _keyName = 'thermal_printer_name';

class PrinterPrefs {
  PrinterPrefs(this._prefs);

  final SharedPreferences _prefs;

  static Future<PrinterPrefs> load() async {
    return PrinterPrefs(await SharedPreferences.getInstance());
  }

  String? get mac => _prefs.getString(_keyMac);

  String? get name => _prefs.getString(_keyName);

  bool get hasPrinter => mac != null && mac!.trim().isNotEmpty;

  Future<void> save({required String mac, required String name}) async {
    await _prefs.setString(_keyMac, mac.trim());
    await _prefs.setString(_keyName, name.trim());
  }

  Future<void> clear() async {
    await _prefs.remove(_keyMac);
    await _prefs.remove(_keyName);
  }
}