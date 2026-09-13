import 'package:flutter/foundation.dart';

import '../models/dashboard.dart';
import '../services/api_client.dart';
import '../services/api_exception.dart';

/// GET /api/dashboard.php?year=YYYY - API_SPEC.md lines 82-85.
class DashboardProvider extends ChangeNotifier {
  final ApiClient apiClient;
  DashboardProvider({required this.apiClient});

  DashboardData? data;
  bool isLoading = false;
  String? error;

  Future<void> load({int? year}) async {
    isLoading = true;
    error = null;
    notifyListeners();
    try {
      data = await fetch(year: year);
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  /// Fetches dashboard data for an arbitrary year without touching the
  /// cached [data] - used by the add/edit transaction form to look up the
  /// pooled fund balance for the year of the transaction being entered, so
  /// the negative-balance confirm dialog is accurate even if it differs
  /// from the year currently shown on the dashboard screen.
  Future<DashboardData> fetch({int? year}) async {
    final res = await apiClient.get('/api/dashboard.php', query: {
      if (year != null) 'year': year,
    });
    return DashboardData.fromJson(res['data'] as Map<String, dynamic>);
  }
}
