import 'parsing.dart';

/// Mirrors a row from the `transactions` table as returned by
/// GET/POST/PUT /api/transactions.php (API_SPEC.md lines 87-106).
class TransactionModel {
  final int id;
  final String type; // 'collection' | 'expense' | 'transfer'
  final String description;
  final double amount;
  final DateTime date;
  final String? category;
  final int addedBy;
  final String? addedByName;
  final DateTime? createdAt;

  const TransactionModel({
    required this.id,
    required this.type,
    required this.description,
    required this.amount,
    required this.date,
    required this.addedBy,
    this.category,
    this.addedByName,
    this.createdAt,
  });

  bool get isCollection => type == 'collection';
  bool get isExpense => type == 'expense';
  bool get isTransfer => type == 'transfer';

  factory TransactionModel.fromJson(Map<String, dynamic> json) {
    return TransactionModel(
      id: toInt(json['id']),
      type: json['type']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
      amount: toDouble(json['amount']),
      date: toDateOrNull(json['date']) ?? DateTime.now(),
      category: json['category']?.toString(),
      addedBy: toInt(json['added_by']),
      addedByName: json['added_by_name']?.toString(),
      createdAt: toDateOrNull(json['created_at']),
    );
  }

  /// Body for POST /api/transactions.php (create). `transferUserId` is only
  /// sent when type == 'transfer'.
  static Map<String, dynamic> createBody({
    required String type,
    required String description,
    required double amount,
    required DateTime date,
    String? category,
    int? transferUserId,
  }) {
    return {
      'type': type,
      'description': description,
      'amount': amount,
      'date': _formatDate(date),
      'category': category,
      'transfer_user_id': transferUserId,
    };
  }

  /// Body for PUT /api/transactions.php?id=... (edit) - same shape minus
  /// transfer_user_id (transfers cannot be edited per the spec).
  static Map<String, dynamic> updateBody({
    required String type,
    required String description,
    required double amount,
    required DateTime date,
    String? category,
  }) {
    return {
      'type': type,
      'description': description,
      'amount': amount,
      'date': _formatDate(date),
      'category': category,
    };
  }

  static String _formatDate(DateTime d) {
    final y = d.year.toString().padLeft(4, '0');
    final m = d.month.toString().padLeft(2, '0');
    final day = d.day.toString().padLeft(2, '0');
    return '$y-$m-$day';
  }
}
