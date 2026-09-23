import 'package:carlton/components/custom_app_bar.dart';
import 'package:carlton/controllers/main/main_controller.dart';
import 'package:carlton/components/custom_bottom_navigation_bar.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/views/account/account_view.dart';
import 'package:carlton/views/book/book_view.dart';
import 'package:carlton/views/home/home_view.dart';
import 'package:carlton/views/services/services_view.dart';
import 'package:carlton/views/stays/stays_view.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class MainView extends GetView<MainController> {
  const MainView({super.key});

  @override
  Widget build(BuildContext context) {
    // The two observers wrap only the chrome that reads `currentIndex`. The
    // PageView sits outside them: it is driven by the PageController, so a tab
    // change must not rebuild five tabs' worth of subtree to repaint a nav bar.
    return CustomScaffold(
      // PreferredSize because Scaffold.appBar needs a PreferredSizeWidget and
      // Obx is not one; the height is CustomAppBar's own.
      appBar: PreferredSize(
        preferredSize: const Size.fromHeight(kToolbarHeight),
        child: Obx(
          () => CustomAppBar(currentIndex: controller.currentIndex.value),
        ),
      ),
      drawer: Drawer(),
      bottomNav: Obx(
        () => CustoBottomNavigationBar(
          currentIndex: controller.currentIndex.value,
          onTap: controller.changeTab,
        ),
      ),
      // No keep-alive wrapper: every tab is stateless and reads its state from
      // a controller owned by MainBinding, which is scoped to this route rather
      // than to the widgets. An off-screen tab's subtree is disposed and rebuilt
      // on return, but nothing is re-fetched and nothing is lost — the
      // controllers, their TabControllers and their scroll controllers all
      // outlive the rebuild. Re-add a keep-alive only if a tab gains widget
      // state that has to survive a switch.
      body: PageView(
        controller: controller.pageController,
        physics: const NeverScrollableScrollPhysics(),
        children: const [
          HomeView(),
          StaysView(),
          BookView(),
          ServicesView(),
          AccountView(),
        ],
      ),
    );
  }
}
