import 'package:flutter/foundation.dart';

import '../models/dashboard.dart';
import '../services/api_client.dart';
import '../services/api_exception.dart';

/// GET /api/years.php + POST /api/settings.php (manager only) -
/// API_SPEC.md lines 75-80.
class YearProvider extends ChangeNotifier {
  final ApiClient apiClient;
  YearProvider({required this.apiClient});

  int? activeYear;
  List<int> availableYears = [];

  /// The year currently selected for browsing dashboard/transactions -
  /// defaults to [activeYear] but the user can pick an older year without
  /// changing what's active for new entries.
  int? selectedYear;

  bool isLoading = false;
  String? error;

  Future<void> load() async {
    isLoading = true;
    error = null;
    notifyListeners();
    try {
      final res = await apiClient.get('/api/years.php');
      final years = YearsData.fromJson(res['data'] as Map<String, dynamic>);
      activeYear = years.activeYear;
      availableYears = years.availableYears;
      selectedYear ??= activeYear;
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  void selectYear(int year) {
    selectedYear = year;
    notifyListeners();
  }

  /// Manager only - POST /api/settings.php {"active_year": year}
  Future<String?> setActiveYear(int year) async {
    try {
      final res = await apiClient.post('/api/settings.php', body: {
        'active_year': year,
      });
      final data = res['data'] as Map<String, dynamic>;
      activeYear = data['active_year'] as int? ?? year;
      selectedYear = activeYear;
      if (!availableYears.contains(activeYear)) {
        availableYears = [activeYear!, ...availableYears]..sort((a, b) => b - a);
      }
      notifyListeners();
      return null;
    } on ApiException catch (e) {
      return e.message;
    }
  }
}
