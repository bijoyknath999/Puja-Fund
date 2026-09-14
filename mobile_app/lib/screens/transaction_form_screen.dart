import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/context_ext.dart';
import '../models/transaction.dart';
import '../providers/categories_provider.dart';
import '../providers/dashboard_provider.dart';
import '../providers/language_provider.dart';
import '../providers/transactions_provider.dart';
import '../services/api_exception.dart';
import '../utils/formatters.dart';
import '../utils/theme.dart';

/// Add/edit screen for `collection` and `expense` transactions.
/// (`transfer` creation lives in TransferFormScreen since it has a
/// completely different shape - a recipient instead of a category - and
/// transfers can never be edited, per API_SPEC.md line 103-104.)
class TransactionFormScreen extends StatefulWidget {
  final String initialType; // 'collection' | 'expense'
  final TransactionModel? existing;

  const TransactionFormScreen({super.key, this.initialType = 'collection', this.existing});

  @override
  State<TransactionFormScreen> createState() => _TransactionFormScreenState();
}

class _TransactionFormScreenState extends State<TransactionFormScreen> {
  final _formKey = GlobalKey<FormState>();
  late String _type;
  late TextEditingController _descriptionController;
  late TextEditingController _amountController;
  late DateTime _date;
  String? _category;
  bool _saving = false;
  String? _error;

  bool get _isEdit => widget.existing != null;

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _type = existing?.type ?? widget.initialType;
    _descriptionController = TextEditingController(text: existing?.description ?? '');
    _amountController = TextEditingController(text: existing != null ? _trimZeros(existing.amount) : '');
    _date = existing?.date ?? DateTime.now();
    _category = existing?.category;
    if (_type == 'expense') {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        context.read<CategoriesProvider>().loadIfNeeded();
      });
    }
  }

  String _trimZeros(double v) {
    if (v == v.roundToDouble()) return v.toStringAsFixed(0);
    return v.toStringAsFixed(2);
  }

  @override
  void dispose() {
    _descriptionController.dispose();
    _amountController.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
    );
    if (picked != null) setState(() => _date = picked);
  }

  /// Mirrors index.php's `confirmIfNegative` JS: for an expense whose
  /// amount exceeds the currently-known fund balance for that year, ask
  /// for confirmation before submitting instead of blocking.
  Future<bool> _confirmIfNegative(double amount) async {
    if (_type != 'expense') return true;
    double fundBalance;
    try {
      final dashboard = await context.read<DashboardProvider>().fetch(year: _date.year);
      fundBalance = dashboard.balance;
      // If editing an expense that already counted against this same
      // year's balance, add its old amount back so the comparison reflects
      // the balance *before* this edit, not after.
      final existing = widget.existing;
      if (existing != null && existing.isExpense && existing.date.year == _date.year) {
        fundBalance += existing.amount;
      }
    } catch (_) {
      // If we can't reach the balance, don't block the user - the server
      // still enforces nothing (expenses are never blocked) and will
      // return a `warning` if relevant.
      return true;
    }

    if (amount <= fundBalance) return true;

    final resultingBalance = fundBalance - amount;
    if (!mounted) return false;
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.tr('balance_negative_title')),
        content: Text(
          context.tr('balance_will_go_negative', {'balance': formatCurrency(resultingBalance)}),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: Text(context.tr('cancel'))),
          FilledButton(onPressed: () => Navigator.of(context).pop(true), child: Text(context.tr('continue_label'))),
        ],
      ),
    );
    return ok ?? false;
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final amount = double.tryParse(_amountController.text.trim()) ?? 0;

    final proceed = await _confirmIfNegative(amount);
    if (!proceed) return;
    if (!mounted) return;

    setState(() {
      _saving = true;
      _error = null;
    });

    final provider = context.read<TransactionsProvider>();
    try {
      final result = _isEdit
          ? await provider.update(
              id: widget.existing!.id,
              type: _type,
              description: _descriptionController.text.trim(),
              amount: amount,
              date: _date,
              category: _type == 'expense' ? _category : null,
            )
          : await provider.create(
              type: _type,
              description: _descriptionController.text.trim(),
              amount: amount,
              date: _date,
              category: _type == 'expense' ? _category : null,
            );

      if (!mounted) return;
      Navigator.of(context).pop(true);
      if (result.warning != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(result.warning!), backgroundColor: AppColors.expense),
        );
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final categories = context.watch<CategoriesProvider>();
    final isBn = context.watch<LanguageProvider>().isBangla;

    return Scaffold(
      appBar: AppBar(
        title: Text(context.tr(_isEdit ? 'edit_transaction' : 'add_transaction'),
            style: const TextStyle(color: Colors.white)),
        flexibleSpace: const DecoratedBox(decoration: BoxDecoration(gradient: AppColors.gradient)),
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 560),
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (_error != null) ...[
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppColors.expense.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Text(_error!, style: const TextStyle(color: AppColors.expense)),
                      ),
                      const SizedBox(height: 16),
                    ],
                    if (!_isEdit)
                      SegmentedButton<String>(
                        segments: [
                          ButtonSegment(
                              value: 'collection',
                              label: Text(context.tr('collection')),
                              icon: const Icon(Icons.add_circle_outline)),
                          ButtonSegment(
                              value: 'expense',
                              label: Text(context.tr('expense')),
                              icon: const Icon(Icons.remove_circle_outline)),
                        ],
                        selected: {_type},
                        onSelectionChanged: (s) {
                          setState(() {
                            _type = s.first;
                            if (_type == 'expense') {
                              context.read<CategoriesProvider>().loadIfNeeded();
                            } else {
                              _category = null;
                            }
                          });
                        },
                      ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _descriptionController,
                      decoration: InputDecoration(labelText: context.tr('description'), prefixIcon: const Icon(Icons.notes)),
                      maxLines: 2,
                      validator: (v) =>
                          (v == null || v.trim().isEmpty) ? context.trStatic('description_required') : null,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _amountController,
                      decoration: InputDecoration(
                          labelText: '${context.tr('amount')} (৳)', prefixIcon: const Icon(Icons.currency_exchange)),
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      validator: (v) {
                        final parsed = double.tryParse((v ?? '').trim());
                        if (parsed == null || parsed <= 0) return context.trStatic('enter_valid_amount');
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    InkWell(
                      onTap: _pickDate,
                      child: InputDecorator(
                        decoration: InputDecoration(labelText: context.tr('date'), prefixIcon: const Icon(Icons.calendar_today)),
                        child: Text(formatDisplayDate(_date)),
                      ),
                    ),
                    if (_type == 'expense') ...[
                      const SizedBox(height: 16),
                      DropdownButtonFormField<String>(
                        initialValue: _category,
                        decoration:
                            InputDecoration(labelText: context.tr('category'), prefixIcon: const Icon(Icons.category_outlined)),
                        items: [
                          DropdownMenuItem(value: null, child: Text(context.tr('none'))),
                          for (final c in categories.categories)
                            DropdownMenuItem(value: c.key, child: Text(isBn ? c.bn : c.en)),
                        ],
                        onChanged: (v) => setState(() => _category = v),
                      ),
                    ],
                    const SizedBox(height: 24),
                    ElevatedButton(
                      onPressed: _saving ? null : _submit,
                      child: _saving
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                            )
                          : Text(context.tr(_isEdit ? 'save_changes' : 'add_transaction')),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
