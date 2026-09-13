/// Raised whenever the API responds with `{"success": false, "error": "..."}`
/// (API_SPEC.md "Envelope" section) or the HTTP layer fails outright.
class ApiException implements Exception {
  final String message;
  final int statusCode;

  const ApiException(this.message, this.statusCode);

  bool get isAuthError => statusCode == 401;
  bool get isForbidden => statusCode == 403;
  bool get isNotFound => statusCode == 404;

  @override
  String toString() => message;
}
