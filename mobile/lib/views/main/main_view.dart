import 'package:carlton/controllers/main/main_controller.dart';
import 'package:carlton/customWidgets/custom_bottom_nav.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/views/account/account_view.dart';
import 'package:carlton/views/book/book_view.dart';
import 'package:carlton/views/home/home_view.dart';
import 'package:carlton/views/home/services_view.dart';
import 'package:carlton/views/stays/stays_view.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// The bottom-nav shell: a [PageView] of the five tabs sharing one
/// [AppBottomNav]. Full-screen routes (auth flow, AI Concierge) are pushed
/// over this shell.
class MainView extends GetView<MainController> {
  const MainView({super.key});

  @override
  Widget build(BuildContext context) {
    return GetBuilder<MainController>(
      builder: (_) => CustomScaffold(
        bottomNav: AppBottomNav(
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
