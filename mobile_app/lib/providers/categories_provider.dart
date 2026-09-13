import 'package:flutter/foundation.dart';

import '../models/category.dart';
import '../services/api_client.dart';

/// GET /api/categories.php - API_SPEC.md lines 138-139. Loaded once and
/// cached for the session (categories are effectively static, mirroring
/// categories.php on the web side).
class CategoriesProvider extends ChangeNotifier {
  final ApiClient apiClient;
  CategoriesProvider({required this.apiClient});

  List<ExpenseCategory> categories = [];
  bool isLoading = false;
  String? error;
  bool _loaded = false;

  Future<void> loadIfNeeded() async {
    if (_loaded || isLoading) return;
    isLoading = true;
    notifyListeners();
    try {
      final res = await apiClient.get('/api/categories.php');
      final data = res['data'] as Map<String, dynamic>;
      categories = (data['categories'] as List<dynamic>? ?? [])
          .map((e) => ExpenseCategory.fromJson(e as Map<String, dynamic>))
          .toList();
      _loaded = true;
    } catch (e) {
      error = e.toString();
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  String labelFor(String key) {
    final match = categories.where((c) => c.key == key);
    if (match.isEmpty) return key;
    return match.first.en;
  }
}
