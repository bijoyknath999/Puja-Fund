import 'package:flutter/foundation.dart';

import '../models/transaction.dart';
import '../services/api_client.dart';

/// Result of a create/update call - carries the parsed transaction plus an
/// optional server-side `warning` string per the balance rule in
/// API_SPEC.md ("Balance rule" + lines 99-105). `transfer` is populated
/// instead of `transaction` when the create was for type == 'transfer'
/// (API_SPEC.md line 99: `{"transfer": {...}}`).
class TransactionSaveResult {
  final TransactionModel? transaction;
  final String? warning;
  final bool wasTransferRequest;

  const TransactionSaveResult({
    this.transaction,
    this.warning,
    this.wasTransferRequest = false,
  });
}

/// Current filter state for GET /api/transactions.php?year=&from=&to=&user_id=&type=
class TransactionFilter {
  final int? year;
  final DateTime? from;
  final DateTime? to;
  final int? userId;
  final String? type;

  const TransactionFilter({this.year, this.from, this.to, this.userId, this.type});

  TransactionFilter copyWith({
    int? year,
    DateTime? from,
    DateTime? to,
    int? userId,
    String? type,
    bool clearFrom = false,
    bool clearTo = false,
    bool clearUserId = false,
    bool clearType = false,
  }) {
    return TransactionFilter(
      year: year ?? this.year,
      from: clearFrom ? null : (from ?? this.from),
      to: clearTo ? null : (to ?? this.to),
      userId: clearUserId ? null : (userId ?? this.userId),
      type: clearType ? null : (type ?? this.type),
    );
  }
}

class TransactionsProvider extends ChangeNotifier {
  final ApiClient apiClient;
  TransactionsProvider({required this.apiClient});

  List<TransactionModel> transactions = [];
  TransactionFilter filter = const TransactionFilter();
  bool isLoading = false;
  String? error;

  void setFilter(TransactionFilter f) {
    filter = f;
    notifyListeners();
  }

  String _fmtDate(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  Future<void> load() async {
    isLoading = true;
    error = null;
    notifyListeners();
    try {
      final hasRange = filter.from != null || filter.to != null;
      final res = await apiClient.get('/api/transactions.php', query: {
        // from+to override year, per API_SPEC.md line 88.
        if (!hasRange && filter.year != null) 'year': filter.year,
        if (filter.from != null) 'from': _fmtDate(filter.from!),
        if (filter.to != null) 'to': _fmtDate(filter.to!),
        if (filter.userId != null) 'user_id': filter.userId,
        if (filter.type != null) 'type': filter.type,
      });
      final data = res['data'] as Map<String, dynamic>;
      transactions = (data['transactions'] as List<dynamic>? ?? [])
          .map((e) => TransactionModel.fromJson(e as Map<String, dynamic>))
          .toList();
    } on Exception catch (e) {
      error = e.toString();
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  /// POST /api/transactions.php - API_SPEC.md lines 93-100.
  Future<TransactionSaveResult> create({
    required String type,
    required String description,
    required double amount,
    required DateTime date,
    String? category,
    int? transferUserId,
  }) async {
    final res = await apiClient.post('/api/transactions.php', body: {
      'type': type,
      'description': description,
      'amount': amount,
      'date': _fmtDate(date),
      'category': category,
      'transfer_user_id': transferUserId,
    });
    final data = res['data'] as Map<String, dynamic>;
    if (type == 'transfer') {
      return TransactionSaveResult(
        wasTransferRequest: true,
        warning: res['warning']?.toString(),
      );
    }
    return TransactionSaveResult(
      transaction: TransactionModel.fromJson(
        data['transaction'] as Map<String, dynamic>,
      ),
      warning: res['warning']?.toString(),
    );
  }

  /// PUT /api/transactions.php?id=123 - API_SPEC.md lines 102-105.
  /// Owner-or-manager only; transfers cannot be edited.
  Future<TransactionSaveResult> update({
    required int id,
    required String type,
    required String description,
    required double amount,
    required DateTime date,
    String? category,
  }) async {
    final res = await apiClient.put(
      '/api/transactions.php',
      query: {'id': id},
      body: {
        'type': type,
        'description': description,
        'amount': amount,
        'date': _fmtDate(date),
        'category': category,
      },
    );
    final data = res['data'] as Map<String, dynamic>;
    return TransactionSaveResult(
      transaction: TransactionModel.fromJson(
        data['transaction'] as Map<String, dynamic>,
      ),
      warning: res['warning']?.toString(),
    );
  }

  /// DELETE /api/transactions.php?id=123 - manager only (API_SPEC.md line 107).
  Future<void> delete(int id) async {
    await apiClient.delete('/api/transactions.php', query: {'id': id});
  }
}
