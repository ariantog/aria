import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'screens/home_screen.dart';
import 'screens/login_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const AriaMobileApp());
}

class AriaMobileApp extends StatelessWidget {
  const AriaMobileApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Aria',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.teal),
        useMaterial3: true,
      ),
      home: const _AuthGate(),
    );
  }
}

class _AuthGate extends StatefulWidget {
  const _AuthGate();

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
    return HomeScreen(
      baseUrl: _baseUrl!,
      token: _token!,
      onLogout: () async {
        final p = await SharedPreferences.getInstance();
        await p.remove('aria_token');
        await _load();
      },
    );
  }
}
