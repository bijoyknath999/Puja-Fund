import 'package:flutter/material.dart';

import '../l10n/context_ext.dart';
import '../utils/theme.dart';

/// Small colored pill badge - mirrors the web app's Bootstrap badges for
/// collection (green) / expense (red) / transfer (blue) / pending (amber).
class StatusBadge extends StatelessWidget {
  final String labelKey;
  final Color color;

  const StatusBadge({super.key, required this.labelKey, required this.color});

  factory StatusBadge.forType(String type) {
    switch (type) {
      case 'collection':
        return StatusBadge(labelKey: 'collection', color: AppColors.collection);
      case 'expense':
        return StatusBadge(labelKey: 'expense', color: AppColors.expense);
      case 'transfer':
        return StatusBadge(labelKey: 'transfer', color: AppColors.transfer);
      default:
        return StatusBadge(labelKey: type, color: Colors.grey);
    }
  }

  factory StatusBadge.forTransferStatus(String status) {
    switch (status) {
      case 'pending':
        return StatusBadge(labelKey: 'pending', color: AppColors.pending);
      case 'completed':
        return StatusBadge(labelKey: 'completed', color: AppColors.collection);
      case 'cancelled':
        return StatusBadge(labelKey: 'rejected', color: AppColors.expense);
      default:
        return StatusBadge(labelKey: status, color: Colors.grey);
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
        context.tr(labelKey),
        style: TextStyle(color: color, fontWeight: FontWeight.w600, fontSize: 12),
      ),
    );
  }
}
