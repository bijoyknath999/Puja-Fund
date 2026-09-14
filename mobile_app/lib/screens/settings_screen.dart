import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/context_ext.dart';
import '../providers/year_provider.dart';
import '../utils/data_refresh.dart';
import '../utils/theme.dart';
import '../widgets/screen_header.dart';

/// Manager-only: view/change the active year and start a new year.
/// POST /api/settings.php - API_SPEC.md lines 78-80.
class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  bool _initialized = false;
  int? _switchToYear;
  final _newYearController = TextEditingController();
  bool _saving = false;
  String? _error;
  String? _message;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_initialized) {
      _initialized = true;
      // Defer past the current build - see dashboard_screen.dart for why.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        final years = context.read<YearProvider>();
        if (years.activeYear == null) years.load();
      });
    }
  }

  @override
  void dispose() {
    _newYearController.dispose();
    super.dispose();
  }

  Future<void> _switchActiveYear() async {
    if (_switchToYear == null) return;
    setState(() {
      _saving = true;
      _error = null;
      _message = null;
    });
    final switchedTo = _switchToYear!;
    final err = await context.read<YearProvider>().setActiveYear(switchedTo);
    if (!mounted) return;
    setState(() {
      _saving = false;
      if (err != null) {
        _error = err;
      } else {
        // The switched-to year drops out of `otherYears` now that it's the
        // active year, so the dropdown's stale selection must be cleared -
        // otherwise DropdownButtonFormField asserts because its selected
        // value no longer matches any item.
        _switchToYear = null;
        _message = context.trStatic('active_year_switched', {'year': '$switchedTo'});
      }
    });
    if (err == null && mounted) refreshAllData(context);
  }

  Future<void> _startNewYear() async {
    final year = int.tryParse(_newYearController.text.trim());
    if (year == null || year < 2000 || year > 2100) {
      setState(() => _error = context.trStatic('enter_valid_year'));
      return;
    }
    final years = context.read<YearProvider>();
    if (years.availableYears.contains(year)) {
      setState(() => _error = context.trStatic('year_has_data_error'));
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
      _message = null;
    });
    final err = await years.setActiveYear(year);
    if (!mounted) return;
    setState(() {
      _saving = false;
      if (err != null) {
        _error = err;
      } else {
        _message = context.trStatic('new_year_started', {'year': '$year'});
        _newYearController.clear();
      }
    });
    if (err == null && mounted) refreshAllData(context);
  }

  @override
  Widget build(BuildContext context) {
    final years = context.watch<YearProvider>();
    final otherYears = years.availableYears.where((y) => y != years.activeYear).toList();

    return Scaffold(
      body: Column(
        children: [
          ScreenHeader(title: context.tr('settings')),
          Expanded(
            child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 560),
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (_error != null)
                  Container(
                    margin: const EdgeInsets.only(bottom: 16),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: AppColors.expense.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(_error!, style: const TextStyle(color: AppColors.expense)),
                  ),
                if (_message != null)
                  Container(
                    margin: const EdgeInsets.only(bottom: 16),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: AppColors.collection.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(_message!, style: const TextStyle(color: AppColors.collection)),
                  ),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(context.tr('active_year'), style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
                        const SizedBox(height: 8),
                        Text(
                          years.activeYear?.toString() ?? '-',
                          style: const TextStyle(fontSize: 32, fontWeight: FontWeight.bold, color: AppColors.gradientEnd),
                        ),
                        const SizedBox(height: 16),
                        Text(
                          context.tr('active_year_desc'),
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.grey[600]),
                        ),
                        const SizedBox(height: 12),
                        DropdownButtonFormField<int>(
                          // Guard against `_switchToYear` referring to a year
                          // that no longer appears in `otherYears` (e.g. it
                          // just became the active year) - DropdownButtonFormField
                          // asserts if its value doesn't match exactly one item.
                          initialValue: otherYears.contains(_switchToYear) ? _switchToYear : null,
                          decoration: InputDecoration(labelText: context.tr('change_active_year')),
                          items: [
                            for (final y in otherYears) DropdownMenuItem(value: y, child: Text(y.toString())),
                          ],
                          onChanged: (v) => setState(() => _switchToYear = v),
                        ),
                        const SizedBox(height: 12),
                        ElevatedButton(
                          onPressed: (_saving || _switchToYear == null) ? null : _switchActiveYear,
                          child: Text(context.tr('change_active_year')),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(context.tr('start_new_year'), style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
                        const SizedBox(height: 8),
                        Text(
                          context.tr('start_new_year_desc'),
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.grey[600]),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _newYearController,
                          keyboardType: TextInputType.number,
                          decoration: InputDecoration(labelText: context.tr('new_year_placeholder')),
                        ),
                        const SizedBox(height: 12),
                        ElevatedButton.icon(
                          onPressed: _saving ? null : _startNewYear,
                          icon: const Icon(Icons.add_circle_outline),
                          label: Text(context.tr('start_new_year')),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
            ),
          ),
        ],
      ),
    );
  }
}
