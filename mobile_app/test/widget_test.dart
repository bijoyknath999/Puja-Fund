// Basic smoke test: the app boots to the login screen when no token is
// persisted yet (SharedPreferences starts empty in the test environment).

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:mobile_app/main.dart';

void main() {
  testWidgets('App boots to the login screen', (WidgetTester tester) async {
    // AuthProvider.init() reads SharedPreferences on launch; seed an empty
    // mock store so that resolves immediately instead of hitting a real
    // platform channel that doesn't exist in the test environment.
    SharedPreferences.setMockInitialValues({});

    await tester.pumpWidget(const PujaFundApp());

    // First frame: AuthProvider.init() hasn't resolved yet.
    expect(find.byType(CircularProgressIndicator), findsOneWidget);

    // Let the async init() (SharedPreferences read, no token found) settle.
    await tester.pumpAndSettle();

    expect(find.text('Puja Fund'), findsOneWidget);
    expect(find.text('Sign In'), findsOneWidget);
  });
}
