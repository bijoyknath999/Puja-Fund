import 'package:flutter/foundation.dart';

import '../models/transfer.dart';
import '../services/api_client.dart';

/// GET/POST/DELETE /api/transfers.php - manager only, API_SPEC.md lines
/// 110-122. Members create transfer requests through
/// TransactionsProvider.create(type: 'transfer') instead - they have no
/// endpoint to list transfers directly.
class TransfersProvider extends ChangeNotifier {
  final ApiClient apiClient;
  TransfersProvider({required this.apiClient});

  List<TransferModel> transfers = [];
  bool isLoading = false;
  String? error;

  Future<void> load({int? year}) async {
    isLoading = true;
    error = null;
    notifyListeners();
    try {
      final res = await apiClient.get('/api/transfers.php', query: {
        if (year != null) 'year': year,
      });
      final data = res['data'] as Map<String, dynamic>;
      transfers = (data['transfers'] as List<dynamic>? ?? [])
          .map((e) => TransferModel.fromJson(e as Map<String, dynamic>))
          .toList();
    } on Exception catch (e) {
      error = e.toString();
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  Future<TransferModel> approve(int id) async {
    final res = await apiClient.post(
      '/api/transfers.php',
      query: {'id': id, 'action': 'approve'},
    );
    final data = res['data'] as Map<String, dynamic>;
    return TransferModel.fromJson(data['transfer'] as Map<String, dynamic>);
  }

  Future<TransferModel> reject(int id) async {
    final res = await apiClient.post(
      '/api/transfers.php',
      query: {'id': id, 'action': 'reject'},
    );
    final data = res['data'] as Map<String, dynamic>;
    return TransferModel.fromJson(data['transfer'] as Map<String, dynamic>);
  }

  Future<void> delete(int id) async {
    await apiClient.delete('/api/transfers.php', query: {'id': id});
  }
}
