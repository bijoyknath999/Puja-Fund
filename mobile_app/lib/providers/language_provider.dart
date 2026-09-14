import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../l10n/app_strings.dart';

/// Current UI language ('en' or 'bn'), persisted across app restarts -
/// mirrors the web app's language switcher (lang.php), same string keys.
class LanguageProvider extends ChangeNotifier {
  static const _prefsKey = 'lang';

  String _code = 'en';
  String get code => _code;
  bool get isBangla => _code == 'bn';

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    _code = prefs.getString(_prefsKey) ?? 'en';
    notifyListeners();
  }

  Future<void> setLanguage(String code) async {
    if (code == _code) return;
    _code = code;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_prefsKey, code);
  }

  String t(String key, [Map<String, String>? params]) {
    var value = AppStrings.of(_code, key);
    if (params != null) {
      params.forEach((k, v) {
        value = value.replaceAll('{$k}', v);
      });
    }
    return value;
  }
}
