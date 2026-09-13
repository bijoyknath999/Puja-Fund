class AppUser {
  final int id;
  final String name;
  final String email;
  final String role; // 'manager' | 'member'

  const AppUser({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
  });

  bool get isManager => role == 'manager';

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: json['id'] as int,
      name: json['name']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      role: json['role']?.toString() ?? 'member',
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'email': email,
        'role': role,
      };
}

/// A user row as returned by GET /api/users.php - includes per-year stats.
class UserWithStats {
  final AppUser user;
  final int transactionCount;
  final double totalCollections;
  final double totalExpenses;

  const UserWithStats({
    required this.user,
    required this.transactionCount,
    required this.totalCollections,
    required this.totalExpenses,
  });

  factory UserWithStats.fromJson(Map<String, dynamic> json) {
    return UserWithStats(
      user: AppUser.fromJson(json),
      transactionCount: (json['transaction_count'] as num?)?.toInt() ?? 0,
      totalCollections: _toDouble(json['total_collections']),
      totalExpenses: _toDouble(json['total_expenses']),
    );
  }
}

/// A row from GET /api/user-directory.php - the non-sensitive id/name list
/// any authenticated user (not just managers) can fetch to pick a transfer
/// recipient.
class UserDirectoryEntry {
  final int id;
  final String name;

  const UserDirectoryEntry({required this.id, required this.name});

  factory UserDirectoryEntry.fromJson(Map<String, dynamic> json) {
    return UserDirectoryEntry(
      id: json['id'] as int,
      name: json['name']?.toString() ?? '',
    );
  }
}

double _toDouble(dynamic v) {
  if (v == null) return 0.0;
  if (v is num) return v.toDouble();
  return double.tryParse(v.toString()) ?? 0.0;
}
