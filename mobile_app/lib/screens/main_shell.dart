import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/context_ext.dart';
import '../providers/auth_provider.dart';
import '../utils/theme.dart';
import '../widgets/language_switcher.dart';
import 'dashboard_screen.dart';
import 'profile_screen.dart';
import 'settings_screen.dart';
import 'transactions_screen.dart';
import 'transfers_screen.dart';
import 'users_screen.dart';

class _Destination {
  final String label;
  // Shorter variant for the bottom bar / rail, where a 5-column layout
  // doesn't leave long words (e.g. "Transactions") enough width and they
  // wrap mid-word. Defaults to [label] when a destination's full name
  // already fits.
  final String navLabel;
  final IconData icon;
  final IconData selectedIcon;
  final Widget Function() builder;
  // Shown as a direct bottom-bar tab (phone width) when true; otherwise
  // only reachable via the drawer's "More" tab. Dashboard/Transactions/
  // Transfers/Profile are the ones members and managers alike use most,
  // so those stay direct even for managers, who get two extra manager-only
  // destinations (Users, Settings) that would otherwise push the bar past
  // Material's ~5-destination guidance.
  final bool inBottomBar;

  const _Destination({
    required this.label,
    String? navLabel,
    required this.icon,
    required this.selectedIcon,
    required this.builder,
    this.inBottomBar = true,
  }) : navLabel = navLabel ?? label;
}

/// Top-level app shell: a NavigationRail on tablet-width screens (which
/// shows every destination directly - there's room), a Drawer + a bottom
/// NavigationBar on phone width. The bottom bar always stays at 4 tabs
/// (Dashboard/Transactions/Transfers/Profile); manager-only destinations
/// (Users, Settings) fold behind a "More" tab that opens the drawer instead
/// of pushing the bar past Material's ~5-destination guidance. Both hide
/// Users/Settings entirely for `role == 'member'`, matching the web app's
/// role gating.
class MainShell extends StatefulWidget {
  const MainShell({super.key});

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  int _selectedIndex = 0;
  final _scaffoldKey = GlobalKey<ScaffoldState>();

  List<_Destination> _destinations(BuildContext context, bool isManager) {
    return [
      _Destination(
        label: context.tr('dashboard'),
        icon: Icons.dashboard_outlined,
        selectedIcon: Icons.dashboard,
        builder: () => const DashboardScreen(),
      ),
      _Destination(
        label: context.tr('transactions'),
        navLabel: context.tr('nav_transactions'),
        icon: Icons.receipt_long_outlined,
        selectedIcon: Icons.receipt_long,
        builder: () => const TransactionsScreen(),
      ),
      _Destination(
        label: context.tr('transfers'),
        icon: Icons.swap_horiz_outlined,
        selectedIcon: Icons.swap_horiz,
        builder: () => const TransfersScreen(),
      ),
      if (isManager)
        _Destination(
          label: context.tr('users'),
          icon: Icons.people_outline,
          selectedIcon: Icons.people,
          builder: () => const UsersScreen(),
          inBottomBar: false,
        ),
      if (isManager)
        _Destination(
          label: context.tr('settings'),
          icon: Icons.settings_outlined,
          selectedIcon: Icons.settings,
          builder: () => const SettingsScreen(),
          inBottomBar: false,
        ),
      _Destination(
        label: context.tr('profile'),
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
    final destinations = _destinations(context, isManager);
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
                  leading: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    child: Column(
                      children: const [
                        FlutterLogoOrBrand(),
                        SizedBox(height: 8),
                        LanguageSwitcherRailButton(),
                      ],
                    ),
                  ),
                  destinations: [
                    for (final d in destinations)
                      NavigationRailDestination(
                        icon: Icon(d.icon),
                        selectedIcon: Icon(d.selectedIcon),
                        label: Text(d.navLabel, overflow: TextOverflow.ellipsis),
                      ),
                  ],
                ),
                const VerticalDivider(width: 1),
                Expanded(child: body),
              ],
            ),
          );
        }

        // Direct bottom-bar tabs (Dashboard/Transactions/Transfers/Profile),
        // keeping their real index into `destinations`/the IndexedStack.
        final directEntries = [
          for (var i = 0; i < destinations.length; i++)
            if (destinations[i].inBottomBar) (index: i, dest: destinations[i]),
        ];
        // Anything not in the bottom bar (Users/Settings, manager-only) is
        // still in the drawer - fold it behind a "More" tab instead of
        // dropping the bottom bar entirely, which used to leave managers
        // with only the hamburger icon to navigate at all.
        final hasOverflow = directEntries.length < destinations.length;
        final overflowTabIndex = directEntries.length; // "More" tab's position, if present
        final isOnOverflowScreen = !destinations[_selectedIndex].inBottomBar;

        return Scaffold(
          key: _scaffoldKey,
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
                        Text(context.tr('app_name'),
                            style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold)),
                        Text(auth.currentUser?.name ?? '',
                            style: const TextStyle(color: Colors.white70, fontSize: 13),
                            overflow: TextOverflow.ellipsis),
                      ],
                    ),
                  ),
                  Expanded(
                    child: ListView(
                      padding: EdgeInsets.zero,
                      children: [
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
                  const Divider(height: 1),
                  Padding(
                    padding: const EdgeInsets.all(12),
                    child: LanguageSwitcherSegment(compact: true),
                  ),
                ],
              ),
            ),
          ),
          // Slim brand bar: just the drawer hamburger + language switcher.
          // Each destination screen renders its own title/controls via
          // ScreenHeader in its body - see that widget's doc comment for why
          // (this used to double up into two stacked title bars).
          appBar: AppBar(
            title: Text(context.tr('app_name'), overflow: TextOverflow.ellipsis),
            flexibleSpace: const DecoratedBox(decoration: BoxDecoration(gradient: AppColors.gradient)),
            foregroundColor: Colors.white,
            actions: const [LanguageSwitcherButton(), SizedBox(width: 4)],
          ),
          body: body,
          bottomNavigationBar: NavigationBar(
            // While a drawer-only screen (opened via "More") is showing,
            // keep the "More" tab looking selected instead of nothing.
            selectedIndex: isOnOverflowScreen
                ? overflowTabIndex
                : directEntries.indexWhere((e) => e.index == _selectedIndex),
            onDestinationSelected: (tabIndex) {
              if (hasOverflow && tabIndex == overflowTabIndex) {
                _scaffoldKey.currentState?.openDrawer();
                return;
              }
              setState(() => _selectedIndex = directEntries[tabIndex].index);
            },
            destinations: [
              for (final e in directEntries)
                NavigationDestination(icon: Icon(e.dest.icon), selectedIcon: Icon(e.dest.selectedIcon), label: e.dest.navLabel),
              if (hasOverflow)
                NavigationDestination(
                  icon: const Icon(Icons.menu_outlined),
                  selectedIcon: const Icon(Icons.menu),
                  label: context.tr('more'),
                ),
            ],
          ),
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
