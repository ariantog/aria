import 'dart:convert';

import 'package:http/http.dart' as http;

class AriaApiException implements Exception {
  AriaApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

class AriaApi {
  AriaApi({required this.baseUrl, required this.token});

  final String baseUrl;
  final String token;

  String get _root => baseUrl.endsWith('/') ? baseUrl.substring(0, baseUrl.length - 1) : baseUrl;

  Uri _u(String path, [Map<String, String>? query]) {
    return Uri.parse('$_root/api/mobile/v1$path').replace(queryParameters: query);
  }

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      };

  Future<Map<String, dynamic>> getMe() async {
    final r = await http.get(_u('/me'), headers: _headers);
    return _decodeObject(r);
  }

  Future<Map<String, dynamic>?> lookupItemBarcode(String barcode) async {
    final r = await http.get(
      _u('/items/barcode-lookup', {'barcode': barcode}),
      headers: _headers,
    );
    final j = _decodeObject(r);
    final item = j['item'];
    if (item is Map<String, dynamic>) {
      return item;
    }
    return null;
  }

  Future<List<dynamic>> searchAddrbook(String type, String search, {String? addrbookType}) async {
    final q = <String, String>{'search': search};
    if (addrbookType != null) {
      q['addrbook_type'] = addrbookType;
    }
    final r = await http.get(_u('/addrbook/$type', q), headers: _headers);
    final j = _decodeJson(r);
    if (j is List) {
      return j;
    }
    return [];
  }

  Future<Map<String, dynamic>> postItemTransaction(Map<String, dynamic> body, {String? idempotencyKey}) async {
    return _postItemTransaction(body, idempotencyKey: idempotencyKey);
  }

  Future<Map<String, dynamic>> postSell(Map<String, dynamic> body, {String? idempotencyKey}) async {
    return postItemTransaction(body, idempotencyKey: idempotencyKey);
  }

  Future<Map<String, dynamic>> postMove(Map<String, dynamic> body, {String? idempotencyKey}) async {
    return _postItemTransaction(body, idempotencyKey: idempotencyKey);
  }

  Future<Map<String, dynamic>> _postItemTransaction(Map<String, dynamic> body, {String? idempotencyKey}) async {
    final headers = Map<String, String>.from(_headers);
    headers['Content-Type'] = 'application/json';
    if (idempotencyKey != null && idempotencyKey.isNotEmpty) {
      headers['X-Idempotency-Key'] = idempotencyKey;
    }
    final r = await http.post(
      _u('/transactions/item'),
      headers: headers,
      body: jsonEncode(body),
    );
    return _decodeObject(r);
  }

  Future<Map<String, dynamic>> postCashIn(Map<String, dynamic> body, {String? idempotencyKey}) async {
    return _postCash(body, isIn: true, idempotencyKey: idempotencyKey);
  }

  Future<Map<String, dynamic>> postCashOut(Map<String, dynamic> body, {String? idempotencyKey}) async {
    return _postCash(body, isIn: false, idempotencyKey: idempotencyKey);
  }

  Future<Map<String, dynamic>> _postCash(Map<String, dynamic> body,
      {required bool isIn, String? idempotencyKey}) async {
    final headers = Map<String, String>.from(_headers);
    headers['Content-Type'] = 'application/json';
    if (idempotencyKey != null && idempotencyKey.isNotEmpty) {
      headers['X-Idempotency-Key'] = idempotencyKey;
    }
    final path = isIn ? '/transactions/cash-in' : '/transactions/cash-out';
    final r = await http.post(
      _u(path),
      headers: headers,
      body: jsonEncode(body),
    );
    return _decodeObject(r);
  }

  Future<Map<String, dynamic>> postTransfer(Map<String, dynamic> body, {String? idempotencyKey}) async {
    final headers = Map<String, String>.from(_headers);
    headers['Content-Type'] = 'application/json';
    if (idempotencyKey != null && idempotencyKey.isNotEmpty) {
      headers['X-Idempotency-Key'] = idempotencyKey;
    }
    final r = await http.post(
      _u('/transactions/transfer'),
      headers: headers,
      body: jsonEncode(body),
    );
    return _decodeObject(r);
  }

  Future<void> logout() async {
    await http.post(_u('/auth/logout'), headers: _headers);
  }

  Future<Map<String, dynamic>> patchAppearance(String appearance) async {
    final headers = Map<String, String>.from(_headers);
    headers['Content-Type'] = 'application/json';
    final r = await http.patch(
      _u('/preferences/appearance'),
      headers: headers,
      body: jsonEncode({'appearance': appearance}),
    );
    return _decodeObject(r);
  }

  static dynamic _decodeJson(http.Response r) {
    if (r.body.isEmpty) {
      return null;
    }
    return jsonDecode(utf8.decode(r.bodyBytes));
  }

  static Map<String, dynamic> _decodeObject(http.Response r) {
    final s = r.statusCode;
    final j = _decodeJson(r);
    if (s >= 200 && s < 300) {
      if (j is Map<String, dynamic>) {
        return j;
      }
      return {};
    }
    if (j is Map<String, dynamic>) {
      final msg = j['message'];
      if (msg is String) {
        throw AriaApiException(msg, statusCode: s);
      }
      if (j['errors'] is Map) {
        final errs = j['errors'] as Map;
        final first = errs.values.first;
        if (first is List && first.isNotEmpty) {
          throw AriaApiException(first.first.toString(), statusCode: s);
        }
      }
    }
    throw AriaApiException('Request failed ($s)', statusCode: s);
  }

  static Future<Map<String, dynamic>> login({
    required String baseUrl,
    required String username,
    required String password,
    String? twoFactorCode,
    String? recoveryCode,
    String? deviceName,
  }) async {
    final root = baseUrl.endsWith('/') ? baseUrl.substring(0, baseUrl.length - 1) : baseUrl;
    final uri = Uri.parse('$root/api/mobile/v1/auth/login');
    final body = <String, dynamic>{
      'username': username,
      'password': password,
      if (twoFactorCode != null) 'two_factor_code': twoFactorCode,
      if (recoveryCode != null) 'recovery_code': recoveryCode,
      if (deviceName != null) 'device_name': deviceName,
    };
    final r = await http.post(
      uri,
      headers: const {'Accept': 'application/json', 'Content-Type': 'application/json'},
      body: jsonEncode(body),
    );
    final j = jsonDecode(utf8.decode(r.bodyBytes));
    if (r.statusCode == 428 && j is Map<String, dynamic> && j['two_factor'] == true) {
      throw AriaApiException('two_factor_required', statusCode: 428);
    }
    if (r.statusCode >= 200 && r.statusCode < 300 && j is Map<String, dynamic>) {
      return j;
    }
    return _decodeObject(r);
  }
}
