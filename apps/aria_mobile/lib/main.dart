import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'screens/login_screen.dart';
import 'screens/shell_screen.dart';
import 'theme/appearance_prefs.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const AriaMobileApp());
}

class AriaMobileApp extends StatefulWidget {
  const AriaMobileApp({super.key});

  @override
  State<AriaMobileApp> createState() => _AriaMobileAppState();
}

class _AriaMobileAppState extends State<AriaMobileApp> {
  ThemeMode _themeMode = ThemeMode.system;

  @override
  void initState() {
    super.initState();
    _loadCachedTheme();
  }

  Future<void> _loadCachedTheme() async {
    final local = await loadThemeModeLocally();
    if (mounted && local != null) {
      setState(() => _themeMode = local);
    }
  }

  void _applyServerAppearance(String? appearance) {
    final mode = appearanceStringToThemeMode(appearance);
    setState(() => _themeMode = mode);
    saveThemeModeLocally(mode);
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Aria',
      themeMode: _themeMode,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.teal),
        useMaterial3: true,
      ),
      darkTheme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.teal, brightness: Brightness.dark),
        useMaterial3: true,
      ),
      home: _AuthGate(onServerAppearance: _applyServerAppearance),
    );
  }
}

class _AuthGate extends StatefulWidget {
  const _AuthGate({required this.onServerAppearance});

  final void Function(String? appearance) onServerAppearance;

  @override
  State<_AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<_AuthGate> {
  bool _loading = true;
  String? _token;
  String? _baseUrl;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final p = await SharedPreferences.getInstance();
    setState(() {
      _token = p.getString('aria_token');
      _baseUrl = p.getString('aria_base_url');
      _loading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }
    if (_token == null || _token!.isEmpty || _baseUrl == null || _baseUrl!.isEmpty) {
      return LoginScreen(onLoggedIn: () => _load());
    }
    return ShellScreen(
      baseUrl: _baseUrl!,
      token: _token!,
      onServerAppearance: widget.onServerAppearance,
      onLogout: () async {
        final p = await SharedPreferences.getInstance();
        await p.remove('aria_token');
        await _load();
      },
    );
  }
}
