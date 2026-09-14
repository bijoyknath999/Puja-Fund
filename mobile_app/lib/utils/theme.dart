import 'package:flutter/material.dart';

/// Brand colors lifted from the web app's purple gradient
/// (`#667eea -> #764ba2`) so the mobile app reads as the same product.
class AppColors {
  static const gradientStart = Color(0xFF667EEA);
  static const gradientEnd = Color(0xFF764BA2);

  static const collection = Color(0xFF2E7D32); // green badge
  static const expense = Color(0xFFC62828); // red badge
  static const transfer = Color(0xFF1565C0); // blue badge
  static const pending = Color(0xFFF9A825); // amber badge

  static const gradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [gradientStart, gradientEnd],
  );
}

class AppTheme {
  static ThemeData light() {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: AppColors.gradientStart,
      brightness: Brightness.light,
    );
    return ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: const Color(0xFFF5F6FA),
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.transparent,
        // Material 3 tints custom AppBar backgrounds toward colorScheme.surfaceTint
        // by default, which washes the brand gradient out to near-black in dark
        // mode - disable it so `flexibleSpace`/gradient headers render true color.
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        foregroundColor: Colors.white,
        centerTitle: false,
      ),
      cardTheme: CardThemeData(
        elevation: 1,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        margin: const EdgeInsets.symmetric(vertical: 6),
      ),
      inputDecorationTheme: InputDecorationTheme(
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
        filled: true,
        fillColor: Colors.white,
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.gradientEnd,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 20),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        indicatorColor: AppColors.gradientStart.withValues(alpha: 0.18),
      ),
    );
  }

  static ThemeData dark() {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: AppColors.gradientStart,
      brightness: Brightness.dark,
    );
    return ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.transparent,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        foregroundColor: Colors.white,
        centerTitle: false,
      ),
      cardTheme: CardThemeData(
        elevation: 1,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        margin: const EdgeInsets.symmetric(vertical: 6),
      ),
    );
  }
}
