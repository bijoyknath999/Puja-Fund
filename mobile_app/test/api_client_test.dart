import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:mobile_app/services/api_client.dart';
import 'package:mobile_app/services/api_exception.dart';

void main() {
  test('GET retries once after a connection failure', () async {
    var calls = 0;
    final client = ApiClient(
      baseUrl: 'https://example.test',
      httpClient: MockClient((req) async {
        calls++;
        if (calls == 1) throw http.ClientException('Connection closed before full header was received');
        return http.Response('{"success":true,"data":{"users":[]}}', 200);
      }),
    );
    final res = await client.get('/api/user-directory.php');
    expect(res['success'], true);
    expect(calls, 2);
  });

  test('GET gives up after the retry also fails', () async {
    var calls = 0;
    final client = ApiClient(
      baseUrl: 'https://example.test',
      httpClient: MockClient((req) async {
        calls++;
        throw http.ClientException('Failed host lookup');
      }),
    );
    await expectLater(client.get('/api/user-directory.php'), throwsA(isA<ApiException>()));
    expect(calls, 2);
  });

  test('POST is never retried', () async {
    var calls = 0;
    final client = ApiClient(
      baseUrl: 'https://example.test',
      httpClient: MockClient((req) async {
        calls++;
        throw http.ClientException('Connection reset');
      }),
    );
    await expectLater(client.post('/api/transactions.php', body: {}), throwsA(isA<ApiException>()));
    expect(calls, 1);
  });
}
