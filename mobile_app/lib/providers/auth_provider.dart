import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models/user.dart';
import '../services/api_client.dart';
import '../services/api_exception.dart';

enum AuthStatus { unknown, authenticated, unauthenticated }

/// Owns the bearer token + current user, persists the token across restarts
/// (SharedPreferences), and is the single place that knows how to log in
/// and out per API_SPEC.md's `/api/auth/*` and `/api/me.php` endpoints.
class AuthProvider extends ChangeNotifier {
  static const _tokenKey = 'auth_token';
  static const _userKey = 'auth_user';

  final ApiClient apiClient;

  AuthProvider({required this.apiClient});

  AuthStatus status = AuthStatus.unknown;
  AppUser? currentUser;
  String? _token;
  bool isBusy = false;
  String? lastError;

  bool get isManager => currentUser?.isManager ?? false;

  /// Loads a persisted token (if any) and validates it against the server.
  Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(_tokenKey);
    final userJson = prefs.getString(_userKey);
    if (token == null || userJson == null) {
      status = AuthStatus.unauthenticated;
      notifyListeners();
      return;
    }

    _token = token;
    apiClient.setToken(token);
    try {
      currentUser = AppUser.fromJson(
        jsonDecode(userJson) as Map<String, dynamic>,
      );
      // Verify the token is still valid and refresh user details.
      final res = await apiClient.get('/api/me.php');
      currentUser = AppUser.fromJson(res['data'] as Map<String, dynamic>);
      status = AuthStatus.authenticated;
    } catch (_) {
      await _clear();
      status = AuthStatus.unauthenticated;
    }
    notifyListeners();
  }

  /// POST /api/auth/login.php - returns null on success, or an error
  /// message to display.
  Future<String?> login(String email, String password) async {
    isBusy = true;
    lastError = null;
    notifyListeners();
    try {
      final res = await apiClient.post('/api/auth/login.php', body: {
        'email': email,
        'password': password,
      });
      final data = res['data'] as Map<String, dynamic>;
      _token = data['token']?.toString();
      currentUser = AppUser.fromJson(data['user'] as Map<String, dynamic>);
      apiClient.setToken(_token);

      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_tokenKey, _token ?? '');
      await prefs.setString(_userKey, jsonEncode(currentUser!.toJson()));

      status = AuthStatus.authenticated;
      return null;
    } on ApiException catch (e) {
      lastError = e.message;
      return e.message;
    } finally {
      isBusy = false;
      notifyListeners();
    }
  }

  /// POST /api/auth/logout.php then clears local state regardless of the
  /// server call's outcome.
  Future<void> logout() async {
    try {
      await apiClient.post('/api/auth/logout.php');
    } catch (_) {
      // Ignore - we still want to clear local state.
    }
    await _clear();
    status = AuthStatus.unauthenticated;
    notifyListeners();
  }

  Future<void> _clear() async {
    _token = null;
    currentUser = null;
    apiClient.setToken(null);
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    await prefs.remove(_userKey);
  }
}
