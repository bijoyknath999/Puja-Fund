import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/year_provider.dart';
import '../utils/data_refresh.dart';
import '../utils/theme.dart';

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
        _message = 'Active year switched to $switchedTo.';
      }
    });
    if (err == null) refreshAllData(context);
  }

  Future<void> _startNewYear() async {
    final year = int.tryParse(_newYearController.text.trim());
    if (year == null || year < 2000 || year > 2100) {
      setState(() => _error = 'Enter a valid 4-digit year');
      return;
    }
    final years = context.read<YearProvider>();
    if (years.availableYears.contains(year)) {
      setState(() => _error = 'That year already has data - use "Switch active year" instead.');
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
        _message = 'Started new year $year as the active year.';
        _newYearController.clear();
      }
    });
    if (err == null) refreshAllData(context);
  }

  @override
  Widget build(BuildContext context) {
    final years = context.watch<YearProvider>();
    final otherYears = years.availableYears.where((y) => y != years.activeYear).toList();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Settings', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
        flexibleSpace: const DecoratedBox(decoration: BoxDecoration(gradient: AppColors.gradient)),
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: Center(
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
                        Text('Active Year', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
                        const SizedBox(height: 8),
                        Text(
                          years.activeYear?.toString() ?? '-',
                          style: const TextStyle(fontSize: 32, fontWeight: FontWeight.bold, color: AppColors.gradientEnd),
                        ),
                        const SizedBox(height: 16),
                        Text(
                          'Switch back to a year that already has data to make it the active year again.',
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.grey[600]),
                        ),
                        const SizedBox(height: 12),
                        DropdownButtonFormField<int>(
                          // Guard against `_switchToYear` referring to a year
                          // that no longer appears in `otherYears` (e.g. it
                          // just became the active year) - DropdownButtonFormField
                          // asserts if its value doesn't match exactly one item.
                          initialValue: otherYears.contains(_switchToYear) ? _switchToYear : null,
                          decoration: const InputDecoration(labelText: 'Switch active year'),
                          items: [
                            for (final y in otherYears) DropdownMenuItem(value: y, child: Text(y.toString())),
                          ],
                          onChanged: (v) => setState(() => _switchToYear = v),
                        ),
                        const SizedBox(height: 12),
                        ElevatedButton(
                          onPressed: (_saving || _switchToYear == null) ? null : _switchActiveYear,
                          child: const Text('Switch Active Year'),
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
                        Text('Start New Year', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
                        const SizedBox(height: 8),
                        Text(
                          'Starting a new year makes it active for new entries. Only years with no existing data can be started.',
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.grey[600]),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _newYearController,
                          keyboardType: TextInputType.number,
                          decoration: const InputDecoration(labelText: 'New year (e.g. 2027)'),
                        ),
                        const SizedBox(height: 12),
                        ElevatedButton.icon(
                          onPressed: _saving ? null : _startNewYear,
                          icon: const Icon(Icons.add_circle_outline),
                          label: const Text('Start New Year'),
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
    );
  }
}
