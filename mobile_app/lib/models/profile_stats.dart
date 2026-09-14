import 'parsing.dart';

/// GET /api/profile.php?year=&from=&to= - API_SPEC.md "profile.php" section.
/// The current user's own collection/expense/transfer totals and balance.
class ProfileStats {
  final int year;
  final double totalCollections;
  final double totalExpenses;
  final double transferIn;
  final double transferOut;
  final double balance;

  const ProfileStats({
    required this.year,
    required this.totalCollections,
    required this.totalExpenses,
    required this.transferIn,
    required this.transferOut,
    required this.balance,
  });

  factory ProfileStats.fromJson(Map<String, dynamic> json) {
    return ProfileStats(
      year: toInt(json['year']),
      totalCollections: toDouble(json['total_collections']),
      totalExpenses: toDouble(json['total_expenses']),
      transferIn: toDouble(json['transfer_in']),
      transferOut: toDouble(json['transfer_out']),
      balance: toDouble(json['balance']),
    );
  }
}
