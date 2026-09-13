import 'package:flutter/material.dart';

import '../utils/theme.dart';

/// Small colored pill badge - mirrors the web app's Bootstrap badges for
/// collection (green) / expense (red) / transfer (blue) / pending (amber).
class StatusBadge extends StatelessWidget {
  final String label;
  final Color color;

  const StatusBadge({super.key, required this.label, required this.color});

  factory StatusBadge.forType(String type) {
    switch (type) {
      case 'collection':
        return StatusBadge(label: 'Collection', color: AppColors.collection);
      case 'expense':
        return StatusBadge(label: 'Expense', color: AppColors.expense);
      case 'transfer':
        return StatusBadge(label: 'Transfer', color: AppColors.transfer);
      default:
        return StatusBadge(label: type, color: Colors.grey);
    }
  }

  factory StatusBadge.forTransferStatus(String status) {
    switch (status) {
      case 'pending':
        return StatusBadge(label: 'Pending', color: AppColors.pending);
      case 'completed':
        return StatusBadge(label: 'Completed', color: AppColors.collection);
      case 'cancelled':
        return StatusBadge(label: 'Rejected', color: AppColors.expense);
      default:
        return StatusBadge(label: status, color: Colors.grey);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(color: color, fontWeight: FontWeight.w600, fontSize: 12),
      ),
    );
  }
}
