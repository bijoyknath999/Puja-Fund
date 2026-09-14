import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/context_ext.dart';
import '../models/profile_stats.dart';
import '../providers/auth_provider.dart';
import '../providers/profile_provider.dart';
import '../providers/year_provider.dart';
import '../utils/dropdown_utils.dart';
import '../utils/formatters.dart';
import '../utils/theme.dart';
import '../widgets/language_switcher.dart';
import '../widgets/screen_header.dart';
import '../widgets/transaction_tile.dart';

/// My Profile: personal info, a personal collections/expenses/balance
/// overview (GET /api/profile.php), and the user's own transaction history
/// - mirrors the web app's profile.php.
class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  bool _initialized = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_initialized) {
      _initialized = true;
      // Defer past the current build - see dashboard_screen.dart for why.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _load();
      });
    }
  }

  Future<void> _load() async {
    final years = context.read<YearProvider>();
    final myId = context.read<AuthProvider>().currentUser?.id;
    if (myId == null) return;
    if (years.activeYear == null) await years.load();
    if (!mounted) return;
    await context.read<ProfileProvider>().load(year: years.selectedYear ?? years.activeYear, userId: myId);
  }

  Future<void> _confirmLogout(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.tr('log_out_q')),
        content: Text(context.tr('log_out_confirm_desc')),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: Text(context.tr('cancel'))),
          FilledButton(onPressed: () => Navigator.of(context).pop(true), child: Text(context.tr('log_out'))),
        ],
      ),
    );
    if (confirmed == true && context.mounted) {
      await context.read<AuthProvider>().logout();
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final years = context.watch<YearProvider>();
    final profile = context.watch<ProfileProvider>();
    final user = auth.currentUser;

    return Scaffold(
      body: Column(
        children: [
          ScreenHeader(
            title: context.tr('my_profile'),
            actions: [
              if (years.availableYears.isNotEmpty)
                DropdownButtonHideUnderline(
                  child: DropdownButton<int>(
                    value: safeDropdownValue(years.selectedYear ?? years.activeYear, years.availableYears),
                    dropdownColor: AppColors.gradientEnd,
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
                    iconEnabledColor: Colors.white,
                    items: [
                      for (final y in years.availableYears)
                        DropdownMenuItem(value: y, child: Text(y.toString())),
                    ],
                    onChanged: (y) {
                      if (y == null) return;
                      years.selectYear(y);
                      _load();
                    },
                  ),
                ),
            ],
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _load,
              child: LayoutBuilder(
                builder: (context, constraints) {
                  final isWide = constraints.maxWidth >= 700;
                  return ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: EdgeInsets.symmetric(horizontal: isWide ? 32 : 16, vertical: 16),
                    children: [
                      ConstrainedBox(
                        constraints: BoxConstraints(maxWidth: isWide ? 640 : double.infinity),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            _UserCard(userName: user?.name ?? '', role: user?.role ?? '', email: user?.email ?? ''),
                            const SizedBox(height: 16),
                            if (profile.isLoading && profile.stats == null)
                              const Padding(
                                padding: EdgeInsets.symmetric(vertical: 24),
                                child: Center(child: CircularProgressIndicator()),
                              )
                            else if (profile.error != null && profile.stats == null)
                              Padding(
                                padding: const EdgeInsets.symmetric(vertical: 24),
                                child: Center(child: Text(profile.error!)),
                              )
                            else if (profile.stats != null)
                              _MyBalanceCard(stats: profile.stats!),
                            const SizedBox(height: 16),
                            const LanguageSwitcherSegment(),
                            const SizedBox(height: 16),
                            SizedBox(
                              width: double.infinity,
                              child: OutlinedButton.icon(
                                onPressed: () => _confirmLogout(context),
                                style: OutlinedButton.styleFrom(foregroundColor: AppColors.expense),
                                icon: const Icon(Icons.logout),
                                label: Text(context.tr('log_out')),
                              ),
                            ),
                            const SizedBox(height: 24),
                            Text(
                              context.tr('my_transactions'),
                              style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              context.tr('view_my_transaction_history'),
                              style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.grey[600]),
                            ),
                            const SizedBox(height: 12),
                            if (profile.transactions.isEmpty && !profile.isLoading)
                              Padding(
                                padding: const EdgeInsets.symmetric(vertical: 24),
                                child: Center(child: Text(context.tr('no_transactions_yet'))),
                              )
                            else
                              for (final tx in profile.transactions) TransactionTile(transaction: tx),
                            const SizedBox(height: 24),
                          ],
                        ),
                      ),
                    ],
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _UserCard extends StatelessWidget {
  final String userName;
  final String role;
  final String email;

  const _UserCard({required this.userName, required this.role, required this.email});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            CircleAvatar(
              radius: 28,
              backgroundColor: AppColors.gradientEnd,
              child: Text(
                userName.isNotEmpty ? userName[0].toUpperCase() : '?',
                style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.bold),
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(userName,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                      overflow: TextOverflow.ellipsis),
                  Text(email, style: Theme.of(context).textTheme.bodySmall, overflow: TextOverflow.ellipsis),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Chip(label: Text(context.tr(role))),
          ],
        ),
      ),
    );
  }
}

class _MyBalanceCard extends StatelessWidget {
  final ProfileStats stats;
  const _MyBalanceCard({required this.stats});

  @override
  Widget build(BuildContext context) {
    final isNegative = stats.balance < 0;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: AppColors.gradient,
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(color: AppColors.gradientEnd.withValues(alpha: 0.3), blurRadius: 14, offset: const Offset(0, 6)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(context.tr('my_balance'), style: const TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.w500)),
          const SizedBox(height: 6),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              formatCurrency(stats.balance),
              style: TextStyle(
                color: isNegative ? const Color(0xFFFFCDD2) : Colors.white,
                fontSize: 28,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _MiniStat(label: context.tr('my_collections'), value: stats.totalCollections, icon: Icons.arrow_downward),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniStat(label: context.tr('my_expenses'), value: stats.totalExpenses, icon: Icons.arrow_upward),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _MiniStat extends StatelessWidget {
  final String label;
  final double value;
  final IconData icon;
  const _MiniStat({required this.label, required this.value, required this.icon});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: Colors.white, size: 14),
              const SizedBox(width: 6),
              Expanded(child: Text(label, style: const TextStyle(color: Colors.white, fontSize: 11), overflow: TextOverflow.ellipsis)),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            formatCurrency(value),
            style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w700),
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}
