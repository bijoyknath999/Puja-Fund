import 'package:flutter/material.dart';

import '../l10n/context_ext.dart';
import '../utils/formatters.dart';
import '../utils/theme.dart';

/// Dashboard hero card - shows the pooled fund balance for a year, in red
/// when negative (matching the web app's fixed expense-balance behavior).
class BalanceCard extends StatelessWidget {
  final int year;
  final double balance;
  final double totalCollections;
  final double totalExpenses;

  const BalanceCard({
    super.key,
    required this.year,
    required this.balance,
    required this.totalCollections,
    required this.totalExpenses,
  });

  @override
  Widget build(BuildContext context) {
    final isNegative = balance < 0;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        gradient: AppColors.gradient,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: AppColors.gradientEnd.withValues(alpha: 0.35),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '${context.tr('fund_balance')} · $year',
            style: const TextStyle(color: Colors.white70, fontSize: 14, fontWeight: FontWeight.w500),
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 8),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              formatCurrency(balance),
              style: TextStyle(
                color: isNegative ? const Color(0xFFFFCDD2) : Colors.white,
                fontSize: 32,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
          if (isNegative)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                context.tr('balance_is_negative'),
                style: const TextStyle(color: Color(0xFFFFCDD2), fontSize: 12, fontWeight: FontWeight.w600),
              ),
            ),
          const SizedBox(height: 20),
          Row(
            children: [
              Expanded(
                child: _StatTile(label: context.tr('collections_stat'), value: totalCollections, icon: Icons.arrow_downward),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _StatTile(label: context.tr('expenses_stat'), value: totalExpenses, icon: Icons.arrow_upward),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _StatTile extends StatelessWidget {
  final String label;
  final double value;
  final IconData icon;

  const _StatTile({required this.label, required this.value, required this.icon});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: Colors.white, size: 16),
              const SizedBox(width: 6),
              Text(label, style: const TextStyle(color: Colors.white, fontSize: 12)),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            formatCurrency(value),
            style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700),
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}
