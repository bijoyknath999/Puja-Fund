import 'dart:convert';
import 'package:http/http.dart' as http;

import 'api_exception.dart';

/// Thin wrapper around the PHP JSON REST API described in API_SPEC.md.
///
/// - Base URL defaults to [_defaultBaseUrl] below - set it directly in code
///   so `flutter run`/`flutter build` work with no extra flags. It can still
///   be overridden per-build with `--dart-define=API_BASE_URL=...` (e.g. to
///   point a release build at a different server) without editing this file.
/// - Every response follows the envelope: `{"success": true, "data": ...}`
///   or `{"success": false, "error": "..."}` (API_SPEC.md "Envelope").
/// - Auth is a bearer token attached as `Authorization: Bearer <token>`.
class ApiClient {
  // Change this to match your PHP dev server's address on your network.
  // - Android emulator reaching the host machine: http://10.0.2.2:8899
  // - Physical device on the same WiFi as your computer: http://<your-computer's-LAN-IP>:8899
  // - Production: https://your-domain.com
  static const String _defaultBaseUrl = 'http://192.168.1.22:8899';
  static const String _envBaseUrl =
      String.fromEnvironment('API_BASE_URL', defaultValue: _defaultBaseUrl);

  final String baseUrl;
  final http.Client _http;
  String? _token;

  ApiClient({String? baseUrl, http.Client? httpClient})
      : baseUrl = baseUrl ?? _envBaseUrl,
        _http = httpClient ?? http.Client();

  void setToken(String? token) {
    _token = token;
  }

  Map<String, String> get _headers => {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        if (_token != null) 'Authorization': 'Bearer $_token',
      };

  Uri _uri(String path, [Map<String, dynamic>? query]) {
    final filtered = <String, String>{};
    query?.forEach((key, value) {
      if (value != null) filtered[key] = value.toString();
    });
    final full = '$baseUrl$path';
    final parsed = Uri.parse(full);
    return parsed.replace(
      queryParameters: filtered.isEmpty ? null : filtered,
    );
  }

  /// Parses the envelope and returns the full decoded body (so callers can
  /// read both `data` and an optional top-level `warning`, per the balance
  /// rule in API_SPEC.md). Throws [ApiException] on `success: false` or on
  /// a network/parse failure.
  Map<String, dynamic> _parse(http.Response res) {
    Map<String, dynamic> body;
    try {
      final decoded = jsonDecode(res.body.isEmpty ? '{}' : res.body);
      body = decoded is Map<String, dynamic> ? decoded : <String, dynamic>{};
    } catch (_) {
      throw ApiException(
        'Unexpected server response (status ${res.statusCode})',
        res.statusCode,
      );
    }

    if (body['success'] == true) {
      return body;
    }

    final message = body['error']?.toString() ??
        'Request failed with status ${res.statusCode}';
    throw ApiException(message, res.statusCode);
  }

  Future<Map<String, dynamic>> _send(
    String method,
    String path, {
    Map<String, dynamic>? query,
    Map<String, dynamic>? body,
  }) async {
    final uri = _uri(path, query);
    late http.Response res;
    try {
      switch (method) {
        case 'GET':
          res = await _http.get(uri, headers: _headers);
          break;
        case 'POST':
          res = await _http.post(
            uri,
            headers: _headers,
            body: body != null ? jsonEncode(body) : null,
          );
          break;
        case 'PUT':
          res = await _http.put(
            uri,
            headers: _headers,
            body: body != null ? jsonEncode(body) : null,
          );
          break;
        case 'DELETE':
          res = await _http.delete(uri, headers: _headers);
          break;
        default:
          throw ArgumentError('Unsupported method $method');
      }
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ApiException('Could not reach server: $e', 0);
    }
    return _parse(res);
  }

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) =>
      _send('GET', path, query: query);

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? body,
    Map<String, dynamic>? query,
  }) =>
      _send('POST', path, body: body, query: query);

  Future<Map<String, dynamic>> put(
    String path, {
    Map<String, dynamic>? body,
    Map<String, dynamic>? query,
  }) =>
      _send('PUT', path, body: body, query: query);

  Future<Map<String, dynamic>> delete(String path, {Map<String, dynamic>? query}) =>
      _send('DELETE', path, query: query);
}
