import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/language_provider.dart';

/// Compact EN/বাংলা toggle - mirrors the web app's language switcher, which
/// appears in every page's navbar. Usable as an AppBar action or inline.
class LanguageSwitcherButton extends StatelessWidget {
  const LanguageSwitcherButton({super.key});

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LanguageProvider>();
    return PopupMenuButton<String>(
      tooltip: lang.t('language'),
      icon: const Icon(Icons.translate, color: Colors.white),
      onSelected: (code) => context.read<LanguageProvider>().setLanguage(code),
      itemBuilder: (context) => [
        CheckedPopupMenuItem(
          value: 'en',
          checked: lang.code == 'en',
          child: Text(lang.t('english')),
        ),
        CheckedPopupMenuItem(
          value: 'bn',
          checked: lang.code == 'bn',
          child: Text(lang.t('bangla')),
        ),
      ],
    );
  }
}

/// Icon-only variant for placement over a non-gradient background (e.g. the
/// NavigationRail's leading area), where the white-icon AppBar version
/// wouldn't have enough contrast.
class LanguageSwitcherRailButton extends StatelessWidget {
  const LanguageSwitcherRailButton({super.key});

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LanguageProvider>();
    return PopupMenuButton<String>(
      tooltip: lang.t('language'),
      icon: const Icon(Icons.translate),
      onSelected: (code) => context.read<LanguageProvider>().setLanguage(code),
      itemBuilder: (context) => [
        CheckedPopupMenuItem(
          value: 'en',
          checked: lang.code == 'en',
          child: Text(lang.t('english')),
        ),
        CheckedPopupMenuItem(
          value: 'bn',
          checked: lang.code == 'bn',
          child: Text(lang.t('bangla')),
        ),
      ],
    );
  }
}

/// Segmented EN/বাংলা toggle for a settings-style page (e.g. Profile) or the
/// drawer footer. [compact] hides the "Language" label to save width.
class LanguageSwitcherSegment extends StatelessWidget {
  final bool compact;
  const LanguageSwitcherSegment({super.key, this.compact = false});

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LanguageProvider>();
    final segmented = SegmentedButton<String>(
      segments: [
        ButtonSegment(value: 'en', label: Text(lang.t('english'))),
        ButtonSegment(value: 'bn', label: Text(lang.t('bangla'))),
      ],
      selected: {lang.code},
      onSelectionChanged: (s) => context.read<LanguageProvider>().setLanguage(s.first),
    );
    if (compact) return Center(child: segmented);
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(lang.t('language'), style: Theme.of(context).textTheme.bodyMedium),
        const SizedBox(width: 12),
        segmented,
      ],
    );
  }
}
