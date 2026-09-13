import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';
import '../utils/theme.dart';
import 'dashboard_screen.dart';
import 'profile_screen.dart';
import 'settings_screen.dart';
import 'transactions_screen.dart';
import 'transfers_screen.dart';
import 'users_screen.dart';

class _Destination {
  final String label;
  final IconData icon;
  final IconData selectedIcon;
  final Widget Function() builder;

  const _Destination({
    required this.label,
    required this.icon,
    required this.selectedIcon,
    required this.builder,
  });
}

/// Top-level app shell: a NavigationRail on tablet-width screens, a Drawer
/// on phone width - both hide manager-only destinations (Users, Settings)
/// for `role == 'member'`, matching the web app's role gating.
class MainShell extends StatefulWidget {
  const MainShell({super.key});

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  int _selectedIndex = 0;

  List<_Destination> _destinations(bool isManager) {
    return [
      _Destination(
        label: 'Dashboard',
        icon: Icons.dashboard_outlined,
        selectedIcon: Icons.dashboard,
        builder: () => const DashboardScreen(),
      ),
      _Destination(
        label: 'Transactions',
        icon: Icons.receipt_long_outlined,
        selectedIcon: Icons.receipt_long,
        builder: () => const TransactionsScreen(),
      ),
      _Destination(
        label: 'Transfers',
        icon: Icons.swap_horiz_outlined,
        selectedIcon: Icons.swap_horiz,
        builder: () => const TransfersScreen(),
      ),
      if (isManager)
        _Destination(
          label: 'Users',
          icon: Icons.people_outline,
          selectedIcon: Icons.people,
          builder: () => const UsersScreen(),
        ),
      if (isManager)
        _Destination(
          label: 'Settings',
          icon: Icons.settings_outlined,
          selectedIcon: Icons.settings,
          builder: () => const SettingsScreen(),
        ),
      _Destination(
        label: 'Profile',
        icon: Icons.person_outline,
        selectedIcon: Icons.person,
        builder: () => const ProfileScreen(),
      ),
    ];
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final isManager = auth.isManager;
    final destinations = _destinations(isManager);
    if (_selectedIndex >= destinations.length) {
      _selectedIndex = 0;
    }

    return LayoutBuilder(
      builder: (context, constraints) {
        final isWide = constraints.maxWidth >= 700;
        final body = IndexedStack(
          index: _selectedIndex,
          children: [for (final d in destinations) d.builder()],
        );

        if (isWide) {
          return Scaffold(
            body: Row(
              children: [
                NavigationRail(
                  extended: constraints.maxWidth >= 1000,
                  selectedIndex: _selectedIndex,
                  onDestinationSelected: (i) => setState(() => _selectedIndex = i),
                  labelType: constraints.maxWidth >= 1000
                      ? NavigationRailLabelType.none
                      : NavigationRailLabelType.all,
                  leading: const Padding(
                    padding: EdgeInsets.symmetric(vertical: 16),
                    child: FlutterLogoOrBrand(),
                  ),
                  destinations: [
                    for (final d in destinations)
                      NavigationRailDestination(
                        icon: Icon(d.icon),
                        selectedIcon: Icon(d.selectedIcon),
                        label: Text(d.label),
                      ),
                  ],
                ),
                const VerticalDivider(width: 1),
                Expanded(child: body),
              ],
            ),
          );
        }

        return Scaffold(
          drawer: Drawer(
            child: SafeArea(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: const BoxDecoration(gradient: AppColors.gradient),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(Icons.temple_hindu, color: Colors.white, size: 32),
                        const SizedBox(height: 8),
                        const Text('Puja Fund',
                            style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold)),
                        Text(auth.currentUser?.name ?? '',
                            style: const TextStyle(color: Colors.white70, fontSize: 13)),
                      ],
                    ),
                  ),
                  for (int i = 0; i < destinations.length; i++)
                    ListTile(
                      leading: Icon(i == _selectedIndex ? destinations[i].selectedIcon : destinations[i].icon),
                      title: Text(destinations[i].label),
                      selected: i == _selectedIndex,
                      onTap: () {
                        setState(() => _selectedIndex = i);
                        Navigator.of(context).pop();
                      },
                    ),
                ],
              ),
            ),
          ),
          appBar: AppBar(
            title: Text(destinations[_selectedIndex].label),
            flexibleSpace: const DecoratedBox(decoration: BoxDecoration(gradient: AppColors.gradient)),
            foregroundColor: Colors.white,
          ),
          body: body,
          bottomNavigationBar: destinations.length <= 5
              ? NavigationBar(
                  selectedIndex: _selectedIndex,
                  onDestinationSelected: (i) => setState(() => _selectedIndex = i),
                  destinations: [
                    for (final d in destinations)
                      NavigationDestination(icon: Icon(d.icon), selectedIcon: Icon(d.selectedIcon), label: d.label),
                  ],
                )
              : null,
        );
      },
    );
  }
}

class FlutterLogoOrBrand extends StatelessWidget {
  const FlutterLogoOrBrand({super.key});

  @override
  Widget build(BuildContext context) {
    return const CircleAvatar(
      radius: 20,
      backgroundColor: AppColors.gradientEnd,
      child: Icon(Icons.temple_hindu, color: Colors.white),
    );
  }
}
