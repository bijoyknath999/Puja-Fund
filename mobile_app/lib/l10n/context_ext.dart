import 'package:flutter/widgets.dart';
import 'package:provider/provider.dart';

import '../providers/language_provider.dart';

/// `context.tr('key')` - watches LanguageProvider so text rebuilds when the
/// language is switched. Use `context.trStatic('key')` in places that
/// shouldn't rebuild on language change (e.g. inside a build method that
/// already watches LanguageProvider itself, or a one-off dialog callback).
extension Localized on BuildContext {
  String tr(String key, [Map<String, String>? params]) {
    return watch<LanguageProvider>().t(key, params);
  }

  String trStatic(String key, [Map<String, String>? params]) {
    return read<LanguageProvider>().t(key, params);
  }
}
