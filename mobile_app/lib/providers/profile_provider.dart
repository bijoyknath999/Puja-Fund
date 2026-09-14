import 'package:flutter/foundation.dart';

import '../models/profile_stats.dart';
import '../models/transaction.dart';
import '../services/api_client.dart';
import '../services/api_exception.dart';

/// The current user's own overview (GET /api/profile.php) plus their own
/// transaction list (GET /api/transactions.php?user_id=self) - mirrors the
/// web app's profile.php, which shows both together.
class ProfileProvider extends ChangeNotifier {
  final ApiClient apiClient;
  ProfileProvider({required this.apiClient});

  ProfileStats? stats;
  List<TransactionModel> transactions = [];
  bool isLoading = false;
  String? error;

  Future<void> load({int? year, required int userId}) async {
    isLoading = true;
    error = null;
    notifyListeners();
    try {
      final results = await Future.wait([
        apiClient.get('/api/profile.php', query: {if (year != null) 'year': year}),
        apiClient.get('/api/transactions.php', query: {
          if (year != null) 'year': year,
          'user_id': userId,
        }),
      ]);
      stats = ProfileStats.fromJson(results[0]['data'] as Map<String, dynamic>);
      final txData = results[1]['data'] as Map<String, dynamic>;
      transactions = (txData['transactions'] as List<dynamic>? ?? [])
          .map((e) => TransactionModel.fromJson(e as Map<String, dynamic>))
          .toList();
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }
}
