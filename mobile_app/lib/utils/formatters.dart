import 'package:intl/intl.dart';

/// Matches the web app's currency style: ৳ symbol, thousands separators,
/// 2 decimal places (see index.php's `number_format($balance, 2)` usage).
final NumberFormat _currencyFormat = NumberFormat.currency(
  locale: 'en_US',
  symbol: '৳', // ৳
  decimalDigits: 2,
);

String formatCurrency(num amount) => _currencyFormat.format(amount);

final DateFormat _displayDateFormat = DateFormat('MMM d, yyyy');
String formatDisplayDate(DateTime date) => _displayDateFormat.format(date);

final DateFormat _apiDateFormat = DateFormat('yyyy-MM-dd');
String formatApiDate(DateTime date) => _apiDateFormat.format(date);

String formatDateTime(DateTime date) =>
    DateFormat('MMM d, yyyy h:mm a').format(date);
