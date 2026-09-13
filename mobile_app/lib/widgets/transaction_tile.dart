import 'package:flutter/material.dart';

import '../models/transaction.dart';
import '../utils/formatters.dart';
import '../utils/theme.dart';
import 'status_badge.dart';

class TransactionTile extends StatelessWidget {
  final TransactionModel transaction;
  final VoidCallback? onTap;

  const TransactionTile({super.key, required this.transaction, this.onTap});

  @override
  Widget build(BuildContext context) {
    final tx = transaction;
    final isExpense = tx.isExpense;
    final amountColor = isExpense
        ? AppColors.expense
        : (tx.isCollection ? AppColors.collection : AppColors.transfer);
    final sign = isExpense ? '-' : (tx.isCollection ? '+' : '');

    return Card(
      child: ListTile(
        onTap: onTap,
        title: Text(tx.description, maxLines: 2, overflow: TextOverflow.ellipsis),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Wrap(
            spacing: 8,
            runSpacing: 4,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              StatusBadge.forType(tx.type),
              Text(formatDisplayDate(tx.date), style: Theme.of(context).textTheme.bodySmall),
              if (tx.addedByName != null)
                Text('· ${tx.addedByName}', style: Theme.of(context).textTheme.bodySmall),
            ],
          ),
        ),
        trailing: Text(
          '$sign${formatCurrency(tx.amount)}',
          style: TextStyle(color: amountColor, fontWeight: FontWeight.bold, fontSize: 15),
        ),
      ),
    );
  }
}
