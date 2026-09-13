import 'parsing.dart';
import 'transaction.dart';

/// GET /api/dashboard.php?year=YYYY - API_SPEC.md lines 82-85.
class DashboardData {
  final int year;
  final double totalCollections;
  final double totalExpenses;
  final double balance;
  final List<TransactionModel> recentTransactions;

  const DashboardData({
    required this.year,
    required this.totalCollections,
    required this.totalExpenses,
    required this.balance,
    required this.recentTransactions,
  });

  factory DashboardData.fromJson(Map<String, dynamic> json) {
    final list = (json['recent_transactions'] as List<dynamic>? ?? [])
        .map((e) => TransactionModel.fromJson(e as Map<String, dynamic>))
        .toList();
    return DashboardData(
      year: toInt(json['year']),
      totalCollections: toDouble(json['total_collections']),
      totalExpenses: toDouble(json['total_expenses']),
      balance: toDouble(json['balance']),
      recentTransactions: list,
    );
  }
}

/// GET /api/years.php - API_SPEC.md line 76.
class YearsData {
  final int activeYear;
  final List<int> availableYears;

  const YearsData({required this.activeYear, required this.availableYears});

  factory YearsData.fromJson(Map<String, dynamic> json) {
    return YearsData(
      activeYear: toInt(json['active_year']),
      availableYears: (json['available_years'] as List<dynamic>? ?? [])
          .map((e) => toInt(e))
          .toList(),
    );
  }
}
