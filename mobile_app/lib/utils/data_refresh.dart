import 'package:flutter/widgets.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';
import '../providers/dashboard_provider.dart';
import '../providers/profile_provider.dart';
import '../providers/transactions_provider.dart';
import '../providers/transfers_provider.dart';
import '../providers/users_provider.dart';
import '../providers/year_provider.dart';

/// Refreshes every provider that shows transaction/transfer/user data.
///
/// MainShell keeps every tab alive in an IndexedStack (so switching tabs
/// doesn't lose scroll position/filters), which means every screen is
/// already mounted and listening to its provider - it just wasn't being
/// told to reload when a mutation happened on a *different* tab. Call this
/// after any transaction/transfer/user/settings change succeeds so the
/// dashboard balance, transactions list, transfers list, and user stats all
/// stay in sync no matter which screen the change was made from.
void refreshAllData(BuildContext context) {
  final years = context.read<YearProvider>();
  final year = years.selectedYear ?? years.activeYear;

  final auth = context.read<AuthProvider>();

  context.read<DashboardProvider>().load(year: year);
  context.read<TransactionsProvider>().load();
  final myId = auth.currentUser?.id;
  if (myId != null) {
    context.read<ProfileProvider>().load(year: year, userId: myId);
  }
  if (auth.isManager) {
    context.read<TransfersProvider>().load(year: year);
    context.read<UsersProvider>().load(year: year);
  }
}
