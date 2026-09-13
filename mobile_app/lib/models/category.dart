/// GET /api/categories.php - {"categories": [ {"key":"prothima","en":"Prothima","bn":"..."} ] }
class ExpenseCategory {
  final String key;
  final String en;
  final String bn;

  const ExpenseCategory({required this.key, required this.en, required this.bn});

  factory ExpenseCategory.fromJson(Map<String, dynamic> json) {
    return ExpenseCategory(
      key: json['key']?.toString() ?? '',
      en: json['en']?.toString() ?? '',
      bn: json['bn']?.toString() ?? '',
    );
  }
}
