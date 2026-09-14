import 'package:flutter/material.dart';

import '../utils/theme.dart';

/// Gradient header used at the top of each destination screen's body,
/// instead of each screen owning its own `Scaffold.appBar`.
///
/// MainShell already provides the single real AppBar (hamburger/back +
/// language switcher) - screens used to *also* wrap themselves in their own
/// Scaffold+AppBar, which rendered as two stacked title bars once shown
/// inside MainShell's IndexedStack. This widget gives each screen a
/// branded title area + space for screen-specific controls (a year
/// dropdown, a filter button, ...) without a second Material AppBar.
class ScreenHeader extends StatelessWidget {
  final String title;
  final List<Widget> actions;
  final Widget? bottom;

  const ScreenHeader({super.key, required this.title, this.actions = const [], this.bottom});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      decoration: const BoxDecoration(
        gradient: AppColors.gradient,
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(20)),
      ),
      padding: const EdgeInsets.fromLTRB(20, 18, 12, 18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.bold),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              ...actions,
            ],
          ),
          if (bottom != null) ...[const SizedBox(height: 12), bottom!],
        ],
      ),
    );
  }
}
