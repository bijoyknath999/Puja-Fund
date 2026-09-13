import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';
import '../utils/theme.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  Future<void> _confirmLogout(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Log out?'),
        content: const Text('You will need to sign in again to continue.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Log out')),
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
    final user = auth.currentUser;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Profile', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
        flexibleSpace: const DecoratedBox(decoration: BoxDecoration(gradient: AppColors.gradient)),
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 480),
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                const SizedBox(height: 16),
                CircleAvatar(
                  radius: 44,
                  backgroundColor: AppColors.gradientEnd,
                  child: Text(
                    (user?.name.isNotEmpty ?? false) ? user!.name[0].toUpperCase() : '?',
                    style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.bold),
                  ),
                ),
                const SizedBox(height: 16),
                Text(user?.name ?? '', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
                const SizedBox(height: 4),
                Chip(label: Text(user?.role ?? '')),
                const SizedBox(height: 24),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _InfoRow(icon: Icons.email_outlined, label: 'Email', value: user?.email ?? ''),
                        const Divider(),
                        _InfoRow(icon: Icons.badge_outlined, label: 'Role', value: user?.role ?? ''),
                        const Divider(),
                        _InfoRow(icon: Icons.tag, label: 'User ID', value: user?.id.toString() ?? ''),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 24),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    onPressed: () => _confirmLogout(context),
                    style: OutlinedButton.styleFrom(foregroundColor: AppColors.expense),
                    icon: const Icon(Icons.logout),
                    label: const Text('Log Out'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;

  const _InfoRow({required this.icon, required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Icon(icon, color: AppColors.gradientEnd),
          const SizedBox(width: 12),
          Text(label, style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: Colors.grey[600])),
          const Spacer(),
          Flexible(child: Text(value, textAlign: TextAlign.right, overflow: TextOverflow.ellipsis)),
        ],
      ),
    );
  }
}
