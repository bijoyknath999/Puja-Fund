import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/transfer.dart';
import '../providers/auth_provider.dart';
import '../providers/transfers_provider.dart';
import '../providers/year_provider.dart';
import '../utils/formatters.dart';
import '../utils/theme.dart';
import '../widgets/status_badge.dart';
import 'transfer_form_screen.dart';

/// Manager: full list of all transfers with approve/reject/delete
/// (GET/POST/DELETE /api/transfers.php - API_SPEC.md lines 110-122).
/// Member: there is no member-facing "list transfers" endpoint in the
/// spec (GET is manager-only), so members only get the "new request"
/// action here - their pending/completed transfers surface later as
/// ordinary transactions once approved.
class TransfersScreen extends StatefulWidget {
  const TransfersScreen({super.key});

  @override
  State<TransfersScreen> createState() => _TransfersScreenState();
}

class _TransfersScreenState extends State<TransfersScreen> {
  bool _initialized = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_initialized) {
      _initialized = true;
      final isManager = context.read<AuthProvider>().isManager;
      // Defer past the current build - see dashboard_screen.dart for why.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && isManager) _load();
      });
    }
  }

  Future<void> _load() async {
    final years = context.read<YearProvider>();
    await context.read<TransfersProvider>().load(year: years.selectedYear ?? years.activeYear);
  }

  Future<void> _openNewRequest() async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => const TransferFormScreen()),
    );
    if (!mounted) return;
    if (changed == true && context.read<AuthProvider>().isManager) {
      _load();
    }
  }

  Future<void> _approve(TransferModel t) async {
    try {
      await context.read<TransfersProvider>().approve(t.id);
      if (!mounted) return;
      _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Approve failed: $e')));
    }
  }

  Future<void> _reject(TransferModel t) async {
    try {
      await context.read<TransfersProvider>().reject(t.id);
      if (!mounted) return;
      _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Reject failed: $e')));
    }
  }

  Future<void> _delete(TransferModel t) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete transfer?'),
        content: const Text('This removes the transfer and its linked transactions.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: AppColors.expense),
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    if (!mounted) return;
    try {
      await context.read<TransfersProvider>().delete(t.id);
      if (!mounted) return;
      _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Delete failed: $e')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Transfers', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
        flexibleSpace: const DecoratedBox(decoration: BoxDecoration(gradient: AppColors.gradient)),
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: auth.isManager ? _ManagerTransfersList(onApprove: _approve, onReject: _reject, onDelete: _delete) : _MemberTransfersInfo(onNew: _openNewRequest),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _openNewRequest,
        backgroundColor: AppColors.gradientEnd,
        icon: const Icon(Icons.add, color: Colors.white),
        label: const Text('New Transfer', style: TextStyle(color: Colors.white)),
      ),
    );
  }
}

class _MemberTransfersInfo extends StatelessWidget {
  final VoidCallback onNew;
  const _MemberTransfersInfo({required this.onNew});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.swap_horiz, size: 56, color: Colors.grey),
            const SizedBox(height: 16),
            Text(
              'Request a transfer to another member',
              style: Theme.of(context).textTheme.titleMedium,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            Text(
              'A manager needs to approve it before it shows up in transactions.',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: Colors.grey[600]),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 20),
            ElevatedButton.icon(
              onPressed: onNew,
              icon: const Icon(Icons.add),
              label: const Text('New Transfer Request'),
            ),
          ],
        ),
      ),
    );
  }
}

class _ManagerTransfersList extends StatelessWidget {
  final void Function(TransferModel) onApprove;
  final void Function(TransferModel) onReject;
  final void Function(TransferModel) onDelete;

  const _ManagerTransfersList({required this.onApprove, required this.onReject, required this.onDelete});

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<TransfersProvider>();

    if (provider.isLoading && provider.transfers.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (provider.error != null && provider.transfers.isEmpty) {
      return Center(child: Text(provider.error!));
    }
    if (provider.transfers.isEmpty) {
      return const Center(child: Text('No transfers yet'));
    }

    return RefreshIndicator(
      onRefresh: () => context.read<TransfersProvider>().load(),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final isWide = constraints.maxWidth >= 700;
          return ListView.builder(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: EdgeInsets.symmetric(horizontal: isWide ? 32 : 12, vertical: 8),
            itemCount: provider.transfers.length,
            itemBuilder: (context, i) {
              final t = provider.transfers[i];
              return Card(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              '${t.fromUserName} → ${t.toUserName}',
                              style: const TextStyle(fontWeight: FontWeight.w600),
                            ),
                          ),
                          Text(formatCurrency(t.amount), style: const TextStyle(fontWeight: FontWeight.bold)),
                        ],
                      ),
                      const SizedBox(height: 6),
                      if (t.description != null && t.description!.isNotEmpty)
                        Text(t.description!, style: Theme.of(context).textTheme.bodyMedium),
                      const SizedBox(height: 6),
                      Wrap(
                        spacing: 8,
                        runSpacing: 4,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: [
                          StatusBadge.forTransferStatus(t.status),
                          Text(formatDisplayDate(t.transferDate), style: Theme.of(context).textTheme.bodySmall),
                        ],
                      ),
                      if (t.isPending) ...[
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            Expanded(
                              child: OutlinedButton(
                                onPressed: () => onReject(t),
                                style: OutlinedButton.styleFrom(foregroundColor: AppColors.expense),
                                child: const Text('Reject'),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: FilledButton(
                                onPressed: () => onApprove(t),
                                style: FilledButton.styleFrom(backgroundColor: AppColors.collection),
                                child: const Text('Approve'),
                              ),
                            ),
                          ],
                        ),
                      ] else if (t.isCompleted) ...[
                        const SizedBox(height: 4),
                        Align(
                          alignment: Alignment.centerRight,
                          child: TextButton.icon(
                            onPressed: () => onDelete(t),
                            icon: const Icon(Icons.delete_outline, color: AppColors.expense),
                            label: const Text('Delete', style: TextStyle(color: AppColors.expense)),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
