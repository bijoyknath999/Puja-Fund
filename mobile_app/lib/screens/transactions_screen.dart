import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/context_ext.dart';
import '../models/transaction.dart';
import '../models/user.dart';
import '../providers/auth_provider.dart';
import '../providers/transactions_provider.dart';
import '../providers/users_provider.dart';
import '../providers/year_provider.dart';
import '../utils/data_refresh.dart';
import '../utils/dropdown_utils.dart';
import '../utils/formatters.dart';
import '../utils/theme.dart';
import '../widgets/screen_header.dart';
import '../widgets/status_badge.dart';
import 'transaction_form_screen.dart';

class TransactionsScreen extends StatefulWidget {
  const TransactionsScreen({super.key});

  @override
  State<TransactionsScreen> createState() => _TransactionsScreenState();
}

class _TransactionsScreenState extends State<TransactionsScreen> {
  bool _initialized = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_initialized) {
      _initialized = true;
      // Defer past the current build - see dashboard_screen.dart for why.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _bootstrap();
      });
    }
  }

  Future<void> _bootstrap() async {
    final years = context.read<YearProvider>();
    if (years.activeYear == null) await years.load();
    if (!mounted) return;
    final tx = context.read<TransactionsProvider>();
    tx.setFilter(tx.filter.copyWith(year: years.selectedYear ?? years.activeYear));
    await tx.load();
    if (!mounted) return;
    if (context.read<AuthProvider>().isManager) {
      context.read<UsersProvider>().load();
    }
  }

  Future<void> _openFilters() async {
    final tx = context.read<TransactionsProvider>();
    final years = context.read<YearProvider>();
    final isManager = context.read<AuthProvider>().isManager;
    final users = context.read<UsersProvider>().users;
    if (!mounted) return;

    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (context) => _FilterSheet(
        initial: tx.filter,
        availableYears: years.availableYears,
        isManager: isManager,
        users: users,
        onApply: (f) {
          tx.setFilter(f);
          tx.load();
        },
      ),
    );
  }

  Future<void> _openAdd(String type) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => TransactionFormScreen(initialType: type)),
    );
    if (!mounted) return;
    if (changed == true) refreshAllData(context);
  }

  Future<void> _openEdit(TransactionModel tx) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => TransactionFormScreen(existing: tx)),
    );
    if (!mounted) return;
    if (changed == true) refreshAllData(context);
  }

  Future<void> _delete(TransactionModel tx) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.tr('delete_transaction_q')),
        content: Text(context.tr('delete_transaction_confirm', {'description': tx.description})),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: Text(context.tr('cancel'))),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: AppColors.expense),
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(context.tr('delete')),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    if (!mounted) return;
    try {
      await context.read<TransactionsProvider>().delete(tx.id);
      if (!mounted) return;
      refreshAllData(context);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(context.trStatic('delete_failed', {'error': '$e'}))));
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final tx = context.watch<TransactionsProvider>();
    final currentUserId = auth.currentUser?.id;

    return Scaffold(
      body: Column(
        children: [
          ScreenHeader(
            title: context.tr('transactions'),
            actions: [
              IconButton(
                icon: const Icon(Icons.filter_list, color: Colors.white),
                onPressed: _openFilters,
              ),
            ],
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => tx.load(),
              child: tx.isLoading && tx.transactions.isEmpty
                  ? const Center(child: CircularProgressIndicator())
                  : tx.error != null && tx.transactions.isEmpty
                      ? Center(child: Text(tx.error!))
                      : tx.transactions.isEmpty
                          ? ListView(
                              physics: const AlwaysScrollableScrollPhysics(),
                              children: [
                                Padding(
                                  padding: const EdgeInsets.only(top: 80),
                                  child: Center(child: Text(context.tr('no_transactions_match_filters'))),
                                ),
                              ],
                            )
                          : LayoutBuilder(
                        builder: (context, constraints) {
                          final isWide = constraints.maxWidth >= 700;
                          return ListView.builder(
                            physics: const AlwaysScrollableScrollPhysics(),
                            padding: EdgeInsets.fromLTRB(isWide ? 32 : 12, 8, isWide ? 32 : 12, 96),
                            itemCount: tx.transactions.length,
                            itemBuilder: (context, i) {
                              final t = tx.transactions[i];
                              final canEdit = !t.isTransfer && (auth.isManager || t.addedBy == currentUserId);
                              final canDelete = auth.isManager; // DELETE is manager-only per API_SPEC.md line 107
                              return Card(
                                child: ListTile(
                                  title: Text(t.description, maxLines: 2, overflow: TextOverflow.ellipsis),
                                  subtitle: Padding(
                                    padding: const EdgeInsets.only(top: 4),
                                    child: Wrap(
                                      spacing: 8,
                                      runSpacing: 4,
                                      children: [
                                        StatusBadge.forType(t.type),
                                        Text(formatDisplayDate(t.date), style: Theme.of(context).textTheme.bodySmall),
                                        if (t.addedByName != null)
                                          Text('· ${t.addedByName}', style: Theme.of(context).textTheme.bodySmall),
                                      ],
                                    ),
                                  ),
                                  trailing: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Text(
                                        '${t.isExpense ? '-' : (t.isCollection ? '+' : '')}${formatCurrency(t.amount)}',
                                        style: TextStyle(
                                          fontWeight: FontWeight.bold,
                                          color: t.isExpense
                                              ? AppColors.expense
                                              : (t.isCollection ? AppColors.collection : AppColors.transfer),
                                        ),
                                      ),
                                      if (canEdit || canDelete)
                                        PopupMenuButton<String>(
                                          onSelected: (v) {
                                            if (v == 'edit') _openEdit(t);
                                            if (v == 'delete') _delete(t);
                                          },
                                          itemBuilder: (context) => [
                                            if (canEdit) PopupMenuItem(value: 'edit', child: Text(context.trStatic('edit'))),
                                            if (canDelete)
                                              PopupMenuItem(value: 'delete', child: Text(context.trStatic('delete'))),
                                          ],
                                        ),
                                    ],
                                  ),
                                ),
                              );
                            },
                          );
                        },
                      ),
            ),
          ),
        ],
      ),
      floatingActionButton: PopupMenuButton<String>(
        onSelected: _openAdd,
        itemBuilder: (context) => [
          PopupMenuItem(value: 'collection', child: Text(context.trStatic('add_collection'))),
          PopupMenuItem(value: 'expense', child: Text(context.trStatic('add_expense'))),
        ],
        child: FloatingActionButton(
          heroTag: 'transactions_add_fab',
          onPressed: null,
          backgroundColor: AppColors.gradientEnd,
          child: const Icon(Icons.add, color: Colors.white),
        ),
      ),
    );
  }
}

class _FilterSheet extends StatefulWidget {
  final TransactionFilter initial;
  final List<int> availableYears;
  final bool isManager;
  final List<UserWithStats> users;
  final void Function(TransactionFilter) onApply;

  const _FilterSheet({
    required this.initial,
    required this.availableYears,
    required this.isManager,
    required this.users,
    required this.onApply,
  });

  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  late int? _year;
  DateTime? _from;
  DateTime? _to;
  int? _userId;
  String? _type;

  @override
  void initState() {
    super.initState();
    _year = widget.initial.year;
    _from = widget.initial.from;
    _to = widget.initial.to;
    _userId = widget.initial.userId;
    _type = widget.initial.type;
  }

  Future<void> _pickDate({required bool isFrom}) async {
    final picked = await showDatePicker(
      context: context,
      initialDate: (isFrom ? _from : _to) ?? DateTime.now(),
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
    );
    if (picked != null) {
      setState(() {
        if (isFrom) {
          _from = picked;
        } else {
          _to = picked;
        }
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        left: 16,
        right: 16,
        top: 16,
        bottom: MediaQuery.of(context).viewInsets.bottom + 16,
      ),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(context.tr('filter_transactions'), style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 16),
            DropdownButtonFormField<int?>(
              initialValue: safeDropdownValue(_year, widget.availableYears),
              decoration: InputDecoration(labelText: context.tr('year')),
              items: [
                DropdownMenuItem(value: null, child: Text(context.tr('any'))),
                for (final y in widget.availableYears) DropdownMenuItem(value: y, child: Text(y.toString())),
              ],
              onChanged: (v) => setState(() => _year = v),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => _pickDate(isFrom: true),
                    child: Text(_from == null ? context.tr('from_date') : formatDisplayDate(_from!)),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => _pickDate(isFrom: false),
                    child: Text(_to == null ? context.tr('to_date') : formatDisplayDate(_to!)),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String?>(
              initialValue: safeDropdownValue(_type, const ['collection', 'expense', 'transfer']),
              decoration: InputDecoration(labelText: context.tr('type')),
              items: [
                DropdownMenuItem(value: null, child: Text(context.tr('any'))),
                DropdownMenuItem(value: 'collection', child: Text(context.tr('collection'))),
                DropdownMenuItem(value: 'expense', child: Text(context.tr('expense'))),
                DropdownMenuItem(value: 'transfer', child: Text(context.tr('transfer'))),
              ],
              onChanged: (v) => setState(() => _type = v),
            ),
            if (widget.isManager) ...[
              const SizedBox(height: 12),
              DropdownButtonFormField<int?>(
                initialValue: safeDropdownValue(_userId, widget.users.map((u) => u.user.id)),
                decoration: InputDecoration(labelText: context.tr('user')),
                items: [
                  DropdownMenuItem(value: null, child: Text(context.tr('everyone'))),
                  for (final u in widget.users)
                    DropdownMenuItem(value: u.user.id, child: Text(u.user.name)),
                ],
                onChanged: (v) => setState(() => _userId = v),
              ),
            ],
            const SizedBox(height: 20),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () {
                      setState(() {
                        _from = null;
                        _to = null;
                        _type = null;
                        _userId = null;
                      });
                    },
                    child: Text(context.tr('clear')),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton(
                    onPressed: () {
                      widget.onApply(TransactionFilter(
                        year: _year,
                        from: _from,
                        to: _to,
                        userId: _userId,
                        type: _type,
                      ));
                      Navigator.of(context).pop();
                    },
                    child: Text(context.tr('apply')),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
