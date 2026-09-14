import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'providers/auth_provider.dart';
import 'providers/categories_provider.dart';
import 'providers/dashboard_provider.dart';
import 'providers/language_provider.dart';
import 'providers/profile_provider.dart';
import 'providers/transactions_provider.dart';
import 'providers/transfers_provider.dart';
import 'providers/users_provider.dart';
import 'providers/year_provider.dart';
import 'screens/login_screen.dart';
import 'screens/main_shell.dart';
import 'services/api_client.dart';
import 'utils/theme.dart';

void main() {
  runApp(const PujaFundApp());
}

class PujaFundApp extends StatelessWidget {
  const PujaFundApp({super.key});

  @override
  Widget build(BuildContext context) {
    final apiClient = ApiClient();

    return MultiProvider(
      providers: [
        Provider<ApiClient>.value(value: apiClient),
        ChangeNotifierProvider(create: (_) => LanguageProvider()),
        ChangeNotifierProvider(create: (_) => AuthProvider(apiClient: apiClient)),
        ChangeNotifierProvider(create: (_) => YearProvider(apiClient: apiClient)),
        ChangeNotifierProvider(create: (_) => DashboardProvider(apiClient: apiClient)),
        ChangeNotifierProvider(create: (_) => ProfileProvider(apiClient: apiClient)),
        ChangeNotifierProvider(create: (_) => TransactionsProvider(apiClient: apiClient)),
        ChangeNotifierProvider(create: (_) => TransfersProvider(apiClient: apiClient)),
        ChangeNotifierProvider(create: (_) => UsersProvider(apiClient: apiClient)),
        ChangeNotifierProvider(create: (_) => CategoriesProvider(apiClient: apiClient)),
      ],
      child: MaterialApp(
        title: 'Puja Fund',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light(),
        darkTheme: AppTheme.dark(),
        home: const _RootGate(),
      ),
    );
  }
}

/// Decides between the login screen and the main app shell based on
/// [AuthProvider.status], and kicks off the token-restore check on launch.
class _RootGate extends StatefulWidget {
  const _RootGate();

  @override
  State<_RootGate> createState() => _RootGateState();
}

class _RootGateState extends State<_RootGate> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AuthProvider>().init();
      context.read<LanguageProvider>().load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    switch (auth.status) {
      case AuthStatus.unknown:
        return const Scaffold(
          body: Center(child: CircularProgressIndicator()),
        );
      case AuthStatus.unauthenticated:
        return const LoginScreen();
      case AuthStatus.authenticated:
        return const MainShell();
    }
  }
}
