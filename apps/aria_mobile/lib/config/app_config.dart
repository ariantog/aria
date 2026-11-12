/// Compile-time defaults (`flutter run --dart-define=KEY=value`).
class AppConfig {
  /// When non-empty, used as the default API base URL if the user has not saved one.
  /// Example: `--dart-define=ARIA_DEFAULT_BASE_URL=https://aria.example.com`
  static const String defaultBaseUrl = String.fromEnvironment(
    'ARIA_DEFAULT_BASE_URL',
    defaultValue: 'https://aria3.corenationactive.com',
  );
}
