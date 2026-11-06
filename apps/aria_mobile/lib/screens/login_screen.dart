import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../api/aria_api.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.onLoggedIn});

  final VoidCallback onLoggedIn;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _baseUrl = TextEditingController();
  final _user = TextEditingController();
  final _pass = TextEditingController();
  final _totp = TextEditingController();
  final _recovery = TextEditingController();

  bool _loading = false;
  String? _error;
  bool _need2fa = false;

  @override
  void dispose() {
    _baseUrl.dispose();
    _user.dispose();
    _pass.dispose();
    _totp.dispose();
    _recovery.dispose();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    _hydrate();
  }

  Future<void> _hydrate() async {
    final p = await SharedPreferences.getInstance();
    final saved = p.getString('aria_base_url');
    if (saved != null) {
      _baseUrl.text = saved;
    }
  }

  Future<void> _submit() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final base = _baseUrl.text.trim();
      if (base.isEmpty) {
        throw AriaApiException('Enter base URL');
      }
      final result = await AriaApi.login(
        baseUrl: base,
        username: _user.text.trim(),
        password: _pass.text,
        twoFactorCode: _totp.text.trim().isEmpty ? null : _totp.text.trim(),
        recoveryCode: _recovery.text.trim().isEmpty ? null : _recovery.text.trim(),
        deviceName: 'flutter-aria-mobile',
      );
      final token = result['token'] as String?;
      if (token == null || token.isEmpty) {
        throw AriaApiException('No token in response');
      }
      final p = await SharedPreferences.getInstance();
      await p.setString('aria_base_url', base);
      await p.setString('aria_token', token);
      widget.onLoggedIn();
    } on AriaApiException catch (e) {
      if (e.message == 'two_factor_required') {
        setState(() {
          _need2fa = true;
          _error = 'Enter your authenticator code or a recovery code.';
        });
      } else {
        setState(() => _error = e.message);
      }
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Aria — Sign in')),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: ListView(
          children: [
            TextField(
              controller: _baseUrl,
              decoration: const InputDecoration(
                labelText: 'Aria base URL',
                hintText: 'https://your-host or http://10.0.2.2:8000',
                border: OutlineInputBorder(),
              ),
              keyboardType: TextInputType.url,
              autocorrect: false,
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _user,
              decoration: const InputDecoration(
                labelText: 'Username',
                border: OutlineInputBorder(),
              ),
              autocorrect: false,
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _pass,
              decoration: const InputDecoration(
                labelText: 'Password',
                border: OutlineInputBorder(),
              ),
              obscureText: true,
            ),
            if (_need2fa) ...[
              const SizedBox(height: 12),
              TextField(
                controller: _totp,
                decoration: const InputDecoration(
                  labelText: 'Authenticator code (6 digits)',
                  border: OutlineInputBorder(),
                ),
                keyboardType: TextInputType.number,
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _recovery,
                decoration: const InputDecoration(
                  labelText: 'Or recovery code',
                  border: OutlineInputBorder(),
                ),
                autocorrect: false,
              ),
            ],
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ],
            const SizedBox(height: 24),
            FilledButton(
              onPressed: _loading ? null : _submit,
              child: _loading ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Sign in'),
            ),
          ],
        ),
      ),
    );
  }
}
