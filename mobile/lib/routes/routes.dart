import 'package:carlton/bindings/binding.dart';
import 'package:carlton/views/view.dart';
import 'package:get/get.dart';

abstract class Routes {
  static const view = '/view';
}

abstract class Pages {
  static final List<GetPage<dynamic>> getPages = [
    GetPage(name: Routes.view, page: () => const View(), binding: Binding()),
  ];
}
