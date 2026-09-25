import 'package:carlton/l10n/app_translations.dart';
import 'dart:convert';
import 'dart:developer';
import 'dart:io';
import 'package:carlton/constants/storage_keys.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/get_storage_service.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:get/get.dart';

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
}

/// Pure builder for the `POST /device-tokens` body (`{token, platform}`).
/// Extracted so it can be asserted hermetically — reverting to the old CartX
/// `{device_token}` shape fails the Phase-6 test.
Map<String, dynamic> deviceTokenPayload(
  String token, {
  required String platform,
}) => {'token': token, 'platform': platform};

class NotificationService extends GetxService {
  final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();

  late ApiService apiService;
  bool _isSetupDone = false;
  final RxInt unreadNotificationsCount = RxInt(0);

  static NotificationService get find => Get.find();

  @override
  onInit() {
    apiService = Get.find<ApiService>();
    super.onInit();
  }

  @override
  onReady() async {
    super.onReady();
    await setup();
  }

  Future<void> setup() async {
    if (_isSetupDone) return;
    _isSetupDone = true;

    await _setupLocalNotifications();

    FirebaseMessaging.onMessage.listen(_showForegroundNotification);

    await _messaging.setForegroundNotificationPresentationOptions(
      alert: true,
      badge: true,
      sound: true,
    );

    FirebaseMessaging.onMessageOpenedApp.listen(_handleNotificationTap);

    final initialMessage = await _messaging.getInitialMessage();
    if (initialMessage != null) {
      Future.microtask(() => _handleNotificationTap(initialMessage));
    }

    _messaging.onTokenRefresh.listen((newToken) async {
      final authToken = StorageService.getString(StorageKeys.token);
      if (authToken == null) return;

      await StorageService.setString(StorageKeys.fcmToken, newToken);
      await _sendTokenToServer(newToken);
    });
  }

  Future<void> requestPermission() async {
    final settings = await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );

    // On iOS, also handle .provisional (granted without explicit user approval)
    if (settings.authorizationStatus == AuthorizationStatus.authorized ||
        settings.authorizationStatus == AuthorizationStatus.provisional) {
      await _registerToken();
    }
  }

  Future<void> registerTokenIfPermitted() async {
    final settings = await _messaging.getNotificationSettings();

    if (settings.authorizationStatus == AuthorizationStatus.authorized ||
        settings.authorizationStatus == AuthorizationStatus.provisional) {
      await _registerToken();
    }
    // If not authorized, do nothing — don't re-prompt
  }

  Future<void> _setupLocalNotifications() async {
    const androidSettings = AndroidInitializationSettings(
      '@mipmap/ic_launcher',
    );
    const iosSettings = DarwinInitializationSettings(
      requestAlertPermission: false,
      requestBadgePermission: false,
      requestSoundPermission: false,
    );

    await _localNotifications.initialize(
      settings: const InitializationSettings(
        android: androidSettings,
        iOS: iosSettings,
      ),
      onDidReceiveNotificationResponse: (response) {
        if (response.payload != null) {
          final data = jsonDecode(response.payload!);
          _handleDataNavigation(data);
        }
      },
    );

    // Android updates an existing channel's NAME when it is re-created, so
    // recreating it each launch is what keeps the label in the guest's
    // current language. The id stays `cartx_orders` on purpose: changing it
    // orphans the channel (and its user-set preferences) on every device
    // that already installed the app.
    final channel = AndroidNotificationChannel(
      'cartx_orders',
      AppTranslations.notificationChannelName,
      importance: Importance.high,
    );

    await _localNotifications
        .resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin
        >()
        ?.createNotificationChannel(channel);
  }

  Future<void> _registerToken() async {
    final authToken = StorageService.getString(StorageKeys.token);
    if (authToken == null) return;

    // Add this — prevents the crash on iOS before APNs is ready
    try {
      if (Platform.isIOS) {
        String? apnsToken;
        for (int i = 0; i < 5; i++) {
          apnsToken = await _messaging.getAPNSToken();
          if (apnsToken != null) break;
          await Future.delayed(const Duration(seconds: 2));
        }
        if (apnsToken == null) {
          log('APNs not ready after retries — skipping FCM token registration');
          return;
        }
      }
      final currentToken = await _messaging.getToken();
      if (!kReleaseMode) {
        log("FCM_TOKEN_FOR_TESTING: $currentToken");
      }
      if (currentToken == null) return;

      final storedToken = StorageService.getString(StorageKeys.fcmToken);
      if (storedToken == currentToken) return;

      await StorageService.setString(StorageKeys.fcmToken, currentToken);
      await _sendTokenToServer(currentToken);
    } catch (e) {
      log('FCM token error: $e');
    }
  }

  Future<void> _sendTokenToServer(String token) async {
    final authToken = StorageService.getString(StorageKeys.token);

    if (authToken == null) return; // <-- critical

    // Background best-effort call: suppress the default error dialog. (The old
    // bare try/catch was dead — ApiService.post never throws, it returns.)
    final platform = kIsWeb ? 'web' : (Platform.isIOS ? 'ios' : 'android');
    await apiService.post(
      path: '/device-tokens',
      data: deviceTokenPayload(token, platform: platform),
      showErrorDialog: false,
    );
  }

  Future<void> removeToken() async {
    // Server-side push-token cleanup on logout has no documented endpoint in
    // the guide's Notifications module (the old DELETE /user/device-token was a
    // CartX leftover) — confirm with backend if push cleanup becomes required.
    try {
      await _messaging.deleteToken();
    } catch (_) {}
  }

  void _showForegroundNotification(RemoteMessage message) {
    final notification = message.notification;
    if (notification == null) return;

    _localNotifications.show(
      id: notification.hashCode,
      title: notification.title,
      body: notification.body,
      notificationDetails: NotificationDetails(
        android: AndroidNotificationDetails(
          'cartx_orders',
          AppTranslations.notificationChannelName,
          icon: '@drawable/ic_notification',
          importance: Importance.high,
          priority: Priority.high,
        ),
        iOS: DarwinNotificationDetails(),
      ),
      payload: jsonEncode(message.data),
    );
  }

  void _handleNotificationTap(RemoteMessage message) {
    _handleDataNavigation(message.data);
  }

  void _handleDataNavigation(Map<String, dynamic> data) {
    // Get.toNamed(Routes.splashScreen);
    // final type = data['type'];
    // final orderId = data['order_id'];

    // switch (type) {
    //   case 'order_status':
    //   case 'delivery_otp':
    //     Get.toNamed('/orders/$orderId');
    //     break;

    //   case 'driver_assignment':
    //     Get.toNamed('/assignments/$orderId');
    //     break;
    // }
  }
}
