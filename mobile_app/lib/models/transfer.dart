import 'parsing.dart';

/// Mirrors a row from the `transfers` table as returned by
/// GET/POST/DELETE /api/transfers.php (API_SPEC.md lines 110-122).
class TransferModel {
  final int id;
  final int fromUserId;
  final String fromUserName;
  final int toUserId;
  final String toUserName;
  final double amount;
  final String? description;
  final DateTime transferDate;
  final String status; // 'pending' | 'completed' | 'cancelled'

  const TransferModel({
    required this.id,
    required this.fromUserId,
    required this.fromUserName,
    required this.toUserId,
    required this.toUserName,
    required this.amount,
    required this.transferDate,
    required this.status,
    this.description,
  });

  bool get isPending => status == 'pending';
  bool get isCompleted => status == 'completed';
  bool get isCancelled => status == 'cancelled';

  factory TransferModel.fromJson(Map<String, dynamic> json) {
    return TransferModel(
      id: toInt(json['id']),
      fromUserId: toInt(json['from_user_id']),
      fromUserName: json['from_user_name']?.toString() ?? '',
      toUserId: toInt(json['to_user_id']),
      toUserName: json['to_user_name']?.toString() ?? '',
      amount: toDouble(json['amount']),
      description: json['description']?.toString(),
      transferDate: toDateOrNull(json['transfer_date']) ?? DateTime.now(),
      status: json['status']?.toString() ?? 'pending',
    );
  }
}
