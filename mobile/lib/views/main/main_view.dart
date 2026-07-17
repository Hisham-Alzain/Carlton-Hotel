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
    return GetBuilder<MainController>(
      builder: (_) => CustomScaffold(
        appBar: CustomAppBar(currentIndex: controller.currentIndex),
        drawer: Drawer(),
        bottomNav: CustoBottomNavigationBar(
          currentIndex: controller.currentIndex,
          onTap: controller.changeTab,
        ),
        body: PageView(
          controller: controller.pageController,
          physics: const NeverScrollableScrollPhysics(),
          children: const [
            _KeepAlive(child: HomeView()),
            _KeepAlive(child: StaysView()),
            _KeepAlive(child: BookView()),
            _KeepAlive(child: ServicesView()),
            _KeepAlive(child: AccountView()),
          ],
        ),
      ),
    );
  }
}

/// Keeps a tab's state (scroll position, form input) alive while another tab
/// is showing, so switching back doesn't rebuild it from scratch.
class _KeepAlive extends StatefulWidget {
  final Widget child;

  const _KeepAlive({required this.child});

  @override
  State<_KeepAlive> createState() => _KeepAliveState();
}

class _KeepAliveState extends State<_KeepAlive>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return widget.child;
  }
}
