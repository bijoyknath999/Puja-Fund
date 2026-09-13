import 'package:flutter/foundation.dart';

import '../models/user.dart';
import '../services/api_client.dart';
import '../services/api_exception.dart';

/// GET/POST/PUT/DELETE /api/users.php - manager only, API_SPEC.md lines
/// 124-136. Also fetches the member-facing directory from
/// GET /api/user-directory.php (any authenticated role) for the transfer
/// recipient picker - see TransferFormScreen.
class UsersProvider extends ChangeNotifier {
  final ApiClient apiClient;
  UsersProvider({required this.apiClient});

  List<UserWithStats> users = [];
  bool isLoading = false;
  String? error;
  bool forbidden = false;

  List<UserDirectoryEntry> directory = [];
  bool directoryLoading = false;
  String? directoryError;

  Future<void> load({int? year}) async {
    isLoading = true;
    error = null;
    forbidden = false;
    notifyListeners();
    try {
      final res = await apiClient.get('/api/users.php', query: {
        if (year != null) 'year': year,
      });
      final data = res['data'] as Map<String, dynamic>;
      users = (data['users'] as List<dynamic>? ?? [])
          .map((e) => UserWithStats.fromJson(e as Map<String, dynamic>))
          .toList();
    } on ApiException catch (e) {
      error = e.message;
      forbidden = e.isForbidden;
    } on Exception catch (e) {
      error = e.toString();
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  /// GET /api/user-directory.php - available to members and managers alike.
  Future<void> loadDirectory() async {
    directoryLoading = true;
    directoryError = null;
    notifyListeners();
    try {
      final res = await apiClient.get('/api/user-directory.php');
      final data = res['data'] as Map<String, dynamic>;
      directory = (data['users'] as List<dynamic>? ?? [])
          .map((e) => UserDirectoryEntry.fromJson(e as Map<String, dynamic>))
          .toList();
    } on ApiException catch (e) {
      directoryError = e.message;
    } on Exception catch (e) {
      directoryError = e.toString();
    } finally {
      directoryLoading = false;
      notifyListeners();
    }
  }

  /// POST /api/users.php
  Future<AppUser> create({
    required String name,
    required String email,
    required String password,
    required String role,
  }) async {
    final res = await apiClient.post('/api/users.php', body: {
      'name': name,
      'email': email,
      'password': password,
      'role': role,
    });
    final data = res['data'] as Map<String, dynamic>;
    return AppUser.fromJson(data['user'] as Map<String, dynamic>);
  }

  /// PUT /api/users.php?id=123 {"role": "..."} - cannot target own id.
  Future<AppUser> updateRole(int id, String role) async {
    final res = await apiClient.put(
      '/api/users.php',
      query: {'id': id},
      body: {'role': role},
    );
    final data = res['data'] as Map<String, dynamic>;
    return AppUser.fromJson(data['user'] as Map<String, dynamic>);
  }

  /// DELETE /api/users.php?id=123 - cannot target own id.
  Future<void> delete(int id) async {
    await apiClient.delete('/api/users.php', query: {'id': id});
  }
}
