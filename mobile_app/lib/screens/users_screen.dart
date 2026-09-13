import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/user.dart';
import '../providers/auth_provider.dart';
import '../providers/users_provider.dart';
import '../providers/year_provider.dart';
import '../utils/formatters.dart';
import '../utils/theme.dart';

/// Manager-only user list with per-user stats, add user, role change and
/// delete. API_SPEC.md lines 124-136. A manager can't change or delete
/// their own account (mirrors users.php's checks).
class UsersScreen extends StatefulWidget {
  const UsersScreen({super.key});

  @override
  State<UsersScreen> createState() => _UsersScreenState();
}

class _UsersScreenState extends State<UsersScreen> {
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
    await context.read<UsersProvider>().load(year: years.selectedYear ?? years.activeYear);
  }

  Future<void> _addUser() async {
    final created = await showDialog<bool>(
      context: context,
      builder: (context) => const _AddUserDialog(),
    );
    if (created == true) _load();
  }

  Future<void> _changeRole(UserWithStats u) async {
    final newRole = u.user.role == 'manager' ? 'member' : 'manager';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Change role?'),
        content: Text('Make ${u.user.name} a $newRole?'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Confirm')),
        ],
      ),
    );
    if (confirmed != true) return;
    if (!mounted) return;
    try {
      await context.read<UsersProvider>().updateRole(u.user.id, newRole);
      if (!mounted) return;
      _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Update failed: $e')));
    }
  }

  Future<void> _deleteUser(UserWithStats u) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete user?'),
        content: Text('This permanently deletes ${u.user.name} and their transactions.'),
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
      await context.read<UsersProvider>().delete(u.user.id);
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
    final provider = context.watch<UsersProvider>();
    final currentUserId = auth.currentUser?.id;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Users', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
        flexibleSpace: const DecoratedBox(decoration: BoxDecoration(gradient: AppColors.gradient)),
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: provider.isLoading && provider.users.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : provider.error != null && provider.users.isEmpty
              ? Center(child: Text(provider.error!))
              : RefreshIndicator(
                  onRefresh: _load,
                  child: LayoutBuilder(
                    builder: (context, constraints) {
                      final isWide = constraints.maxWidth >= 700;
                      return ListView.builder(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: EdgeInsets.symmetric(horizontal: isWide ? 32 : 12, vertical: 8),
                        itemCount: provider.users.length,
                        itemBuilder: (context, i) {
                          final u = provider.users[i];
                          final isSelf = u.user.id == currentUserId;
                          return Card(
                            child: Padding(
                              padding: const EdgeInsets.all(12),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      CircleAvatar(
                                        backgroundColor: AppColors.gradientStart,
                                        child: Text(
                                          u.user.name.isNotEmpty ? u.user.name[0].toUpperCase() : '?',
                                          style: const TextStyle(color: Colors.white),
                                        ),
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Text(u.user.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                                            Text(u.user.email, style: Theme.of(context).textTheme.bodySmall),
                                          ],
                                        ),
                                      ),
                                      Chip(
                                        label: Text(u.user.role),
                                        backgroundColor:
                                            u.user.isManager ? AppColors.gradientEnd.withValues(alpha: 0.15) : null,
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 10),
                                  Row(
                                    children: [
                                      Expanded(
                                        child: _StatChip(label: 'Transactions', value: u.transactionCount.toString()),
                                      ),
                                      const SizedBox(width: 8),
                                      Expanded(
                                        child: _StatChip(label: 'Collections', value: formatCurrency(u.totalCollections)),
                                      ),
                                      const SizedBox(width: 8),
                                      Expanded(
                                        child: _StatChip(label: 'Expenses', value: formatCurrency(u.totalExpenses)),
                                      ),
                                    ],
                                  ),
                                  if (!isSelf) ...[
                                    const SizedBox(height: 8),
                                    Row(
                                      mainAxisAlignment: MainAxisAlignment.end,
                                      children: [
                                        TextButton(
                                          onPressed: () => _changeRole(u),
                                          child: Text(u.user.isManager ? 'Make Member' : 'Make Manager'),
                                        ),
                                        TextButton(
                                          onPressed: () => _deleteUser(u),
                                          style: TextButton.styleFrom(foregroundColor: AppColors.expense),
                                          child: const Text('Delete'),
                                        ),
                                      ],
                                    ),
                                  ] else
                                    const Padding(
                                      padding: EdgeInsets.only(top: 8),
                                      child: Align(
                                        alignment: Alignment.centerRight,
                                        child: Text('This is you', style: TextStyle(color: Colors.grey, fontSize: 12)),
                                      ),
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
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _addUser,
        backgroundColor: AppColors.gradientEnd,
        icon: const Icon(Icons.person_add, color: Colors.white),
        label: const Text('Add User', style: TextStyle(color: Colors.white)),
      ),
    );
  }
}

class _StatChip extends StatelessWidget {
  final String label;
  final String value;
  const _StatChip({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        children: [
          Text(label, style: Theme.of(context).textTheme.bodySmall),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w600), overflow: TextOverflow.ellipsis),
        ],
      ),
    );
  }
}

class _AddUserDialog extends StatefulWidget {
  const _AddUserDialog();

  @override
  State<_AddUserDialog> createState() => _AddUserDialogState();
}

class _AddUserDialogState extends State<_AddUserDialog> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  String _role = 'member';
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await context.read<UsersProvider>().create(
            name: _nameController.text.trim(),
            email: _emailController.text.trim(),
            password: _passwordController.text,
            role: _role,
          );
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Add User'),
      content: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (_error != null) ...[
                Text(_error!, style: const TextStyle(color: AppColors.expense)),
                const SizedBox(height: 8),
              ],
              TextFormField(
                controller: _nameController,
                decoration: const InputDecoration(labelText: 'Name'),
                validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _emailController,
                decoration: const InputDecoration(labelText: 'Email'),
                keyboardType: TextInputType.emailAddress,
                validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _passwordController,
                decoration: const InputDecoration(labelText: 'Password'),
                obscureText: true,
                validator: (v) => (v == null || v.length < 6) ? 'At least 6 characters' : null,
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _role,
                decoration: const InputDecoration(labelText: 'Role'),
                items: const [
                  DropdownMenuItem(value: 'member', child: Text('Member')),
                  DropdownMenuItem(value: 'manager', child: Text('Manager')),
                ],
                onChanged: (v) => setState(() => _role = v ?? 'member'),
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(onPressed: _saving ? null : () => Navigator.of(context).pop(false), child: const Text('Cancel')),
        FilledButton(
          onPressed: _saving ? null : _submit,
          child: _saving
              ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
              : const Text('Create'),
        ),
      ],
    );
  }
}
