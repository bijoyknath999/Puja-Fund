import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/context_ext.dart';
import '../models/dashboard.dart';
import '../providers/auth_provider.dart';
import '../providers/dashboard_provider.dart';
import '../providers/year_provider.dart';
import '../utils/data_refresh.dart';
import '../utils/theme.dart';
import '../widgets/balance_card.dart';
import '../widgets/screen_header.dart';
import '../widgets/transaction_tile.dart';
import 'transaction_form_screen.dart';
import 'transfer_form_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  bool _initialized = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_initialized) {
      _initialized = true;
      // Defer past the current build: the providers' load() methods call
      // notifyListeners() synchronously before their first await, which
      // would otherwise crash with "setState()/markNeedsBuild() called
      // during build" since didChangeDependencies runs while this screen
      // is still being built.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _bootstrap();
      });
    }
  }

  Future<void> _bootstrap() async {
    final years = context.read<YearProvider>();
    if (years.activeYear == null) {
      await years.load();
    }
    await _loadDashboard();
  }

  Future<void> _loadDashboard() async {
    final years = context.read<YearProvider>();
    await context.read<DashboardProvider>().load(year: years.selectedYear ?? years.activeYear);
  }

  void _openQuickAdd(String type) async {
    if (type == 'transfer') {
      await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const TransferFormScreen()));
    } else {
      await Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => TransactionFormScreen(initialType: type)),
      );
    }
    if (!mounted) return;
    refreshAllData(context);
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final years = context.watch<YearProvider>();
    final dashboard = context.watch<DashboardProvider>();

    return Scaffold(
      body: Column(
        children: [
          ScreenHeader(
            title: context.tr('dashboard'),
            actions: [
              if (years.availableYears.isNotEmpty)
                DropdownButtonHideUnderline(
                  child: DropdownButton<int>(
                    value: years.selectedYear ?? years.activeYear,
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
                      _loadDashboard();
                    },
                  ),
                ),
            ],
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _loadDashboard,
              child: dashboard.isLoading && dashboard.data == null
                  ? const Center(child: CircularProgressIndicator())
                  : dashboard.error != null && dashboard.data == null
                      ? _ErrorState(message: dashboard.error!, onRetry: _loadDashboard)
                      : _DashboardBody(data: dashboard.data, isManager: auth.isManager),
            ),
          ),
        ],
      ),
      floatingActionButton: PopupMenuButton<String>(
        onSelected: _openQuickAdd,
        itemBuilder: (context) => [
          PopupMenuItem(value: 'collection', child: Text(context.trStatic('add_collection'))),
          PopupMenuItem(value: 'expense', child: Text(context.trStatic('add_expense'))),
          PopupMenuItem(value: 'transfer', child: Text(context.trStatic('new_transfer'))),
        ],
        child: FloatingActionButton.extended(
          heroTag: 'dashboard_quick_add_fab',
          onPressed: null,
          backgroundColor: AppColors.gradientEnd,
          icon: const Icon(Icons.add, color: Colors.white),
          label: Text(context.tr('quick_add'), style: const TextStyle(color: Colors.white)),
        ),
      ),
    );
  }
}

class _DashboardBody extends StatelessWidget {
  final DashboardData? data;
  final bool isManager;

  const _DashboardBody({required this.data, required this.isManager});

  @override
  Widget build(BuildContext context) {
    final d = data;
    if (d == null) {
      return Center(child: Text(context.tr('no_data_available')));
    }
    return LayoutBuilder(
      builder: (context, constraints) {
        final isWide = constraints.maxWidth >= 700;
        return SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: EdgeInsets.symmetric(horizontal: isWide ? 32 : 16, vertical: 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ConstrainedBox(
                constraints: BoxConstraints(maxWidth: isWide ? 500 : double.infinity),
                child: BalanceCard(
                  year: d.year,
                  balance: d.balance,
                  totalCollections: d.totalCollections,
                  totalExpenses: d.totalExpenses,
                ),
              ),
              const SizedBox(height: 24),
              Text(
                context.tr(isManager ? 'recent_transactions' : 'your_recent_transactions'),
                style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              if (d.recentTransactions.isEmpty)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 24),
                  child: Center(child: Text(context.tr('no_transactions_yet'))),
                )
              else
                for (final tx in d.recentTransactions) TransactionTile(transaction: tx),
              const SizedBox(height: 80),
            ],
          ),
        );
      },
    );
  }
}

class _ErrorState extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;

  const _ErrorState({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline, size: 48, color: Colors.grey),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: onRetry, child: Text(context.tr('retry'))),
          ],
        ),
      ),
    );
  }
}
