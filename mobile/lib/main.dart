import 'package:carlton/l10n/local.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:carlton/services/permission_service.dart';
import 'package:carlton/services/settings_service.dart';
import 'package:carlton/theme/theme.dart';
import 'package:country_code_picker/country_code_picker.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:get/get.dart';
import 'services/get_storage_service.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
  // register the background handler as early as possible
  // FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

  await StorageService.init();
  Get.put(SettingsService(), permanent: true);
  Get.put(ApiService(), permanent: true);
  // await Get.put(NotificationService(), permanent: true).setup();
  Get.put(MiddlewareService(), permanent: true);
  Get.put(PermissionService(), permanent: true);

  runApp(const MainApp());
}

class MainApp extends StatelessWidget {
  const MainApp({super.key});

  @override
  Widget build(BuildContext context) {
    final settings = Get.find<SettingsService>();

    return GetMaterialApp(
      debugShowCheckedModeBanner: false,
      initialRoute: Routes.splashScreen,
      getPages: Pages.getPages,
      theme: Themes().theme,
      supportedLocales: const [Locale('en'), Locale('ar')],
      locale: settings.locale.value,
      onReady: () async {
        WidgetsBinding.instance.addPostFrameCallback((_) async {
          // await NotificationService.find.requestPermission();
          // await PermissionService.find.requestLocationPermission();
          // await PermissionService.find.requestNotificationPermission();
          // await PermissionService.find.requestCameraPermission();
        });
      },
      fallbackLocale: const Locale('en', 'US'),
      translations: Local(),
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        CountryLocalizations.delegate,
      ],
    );
  }
}
