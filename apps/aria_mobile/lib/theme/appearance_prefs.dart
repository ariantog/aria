import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

const _kAriaThemeModeKey = 'aria_theme_mode';

/// Local cache only; server [`/me`] preferences are authoritative when logged in.
Future<void> saveThemeModeLocally(ThemeMode mode) async {
  final p = await SharedPreferences.getInstance();
  await p.setString(_kAriaThemeModeKey, _serialize(mode));
}

Future<ThemeMode?> loadThemeModeLocally() async {
  final p = await SharedPreferences.getInstance();
  final s = p.getString(_kAriaThemeModeKey);
  return s != null ? _deserialize(s) : null;
}

ThemeMode appearanceStringToThemeMode(String? appearance) {
  switch (appearance) {
    case 'light':
      return ThemeMode.light;
    case 'dark':
      return ThemeMode.dark;
    case 'system':
    default:
      return ThemeMode.system;
  }
}

String themeModeToAppearanceString(ThemeMode mode) => _serialize(mode);

String _serialize(ThemeMode mode) {
  switch (mode) {
    case ThemeMode.light:
      return 'light';
    case ThemeMode.dark:
      return 'dark';
    case ThemeMode.system:
      return 'system';
  }
}

ThemeMode _deserialize(String s) {
  switch (s) {
    case 'light':
      return ThemeMode.light;
    case 'dark':
      return ThemeMode.dark;
    default:
      return ThemeMode.system;
  }
}
