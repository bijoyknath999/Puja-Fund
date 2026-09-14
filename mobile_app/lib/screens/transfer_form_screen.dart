import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/context_ext.dart';
import '../providers/auth_provider.dart';
import '../providers/transactions_provider.dart';
import '../providers/users_provider.dart';
import '../services/api_exception.dart';
import '../utils/formatters.dart';
import '../utils/theme.dart';

/// Creates a transfer request - POST /api/transactions.php with
/// type='transfer' (API_SPEC.md lines 93-100). It lands as a `pending`
/// row in `transfers`, not `transactions`, until a manager approves it.
///
/// Recipient picker uses GET /api/user-directory.php, the non-sensitive
/// id/name list available to any authenticated user (mirrors the web app's
/// plain `SELECT id, name FROM users` dropdown in transactions.php).
class TransferFormScreen extends StatefulWidget {
  const TransferFormScreen({super.key});

  @override
  State<TransferFormScreen> createState() => _TransferFormScreenState();
}

class _TransferFormScreenState extends State<TransferFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _descriptionController = TextEditingController();
  final _amountController = TextEditingController();
  DateTime _date = DateTime.now();
  int? _selectedUserId;
  bool _saving = false;
  String? _error;
  bool _loadedUsers = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_loadedUsers) {
      _loadedUsers = true;
      // Defer past the current build - see dashboard_screen.dart for why.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) context.read<UsersProvider>().loadDirectory();
      });
    }
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

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    final recipientId = _selectedUserId;

    if (recipientId == null) {
      setState(() => _error = context.trStatic('please_choose_recipient'));
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    final amount = double.tryParse(_amountController.text.trim()) ?? 0;
    try {
      final result = await context.read<TransactionsProvider>().create(
            type: 'transfer',
            description: _descriptionController.text.trim(),
            amount: amount,
            date: _date,
            transferUserId: recipientId,
          );
      if (!mounted) return;
      final message = result.warning ?? context.trStatic('transfer_submitted_pending');
      Navigator.of(context).pop(true);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message)),
      );
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final usersProvider = context.watch<UsersProvider>();
    final currentUserId = auth.currentUser?.id;
    final recipients = usersProvider.directory.where((u) => u.id != currentUserId).toList();

    return Scaffold(
      appBar: AppBar(
        title: Text(context.tr('new_transfer'), style: const TextStyle(color: Colors.white)),
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
                    Text(
                      context.tr('transfer_approval_note'),
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.grey[600]),
                    ),
                    const SizedBox(height: 16),
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
                    if (usersProvider.directoryLoading)
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 8),
                        child: LinearProgressIndicator(),
                      )
                    else if (usersProvider.directoryError != null)
                      Text(
                        usersProvider.directoryError!,
                        style: const TextStyle(color: AppColors.expense),
                      )
                    else
                      DropdownButtonFormField<int>(
                        initialValue: _selectedUserId,
                        decoration:
                            InputDecoration(labelText: context.tr('transfer_to'), prefixIcon: const Icon(Icons.person_outline)),
                        items: [
                          for (final u in recipients)
                            DropdownMenuItem(value: u.id, child: Text(u.name)),
                        ],
                        onChanged: (v) => setState(() => _selectedUserId = v),
                        validator: (v) => v == null ? context.trStatic('please_choose_recipient') : null,
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
                    const SizedBox(height: 24),
                    ElevatedButton(
                      onPressed: _saving ? null : _submit,
                      child: _saving
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                            )
                          : Text(context.tr('submit_request')),
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
