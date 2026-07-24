import 'package:carlton/components/cards/custom_service_option_tile.dart';
import 'package:carlton/components/custom_bottom_navigation_bar.dart';
import 'package:carlton/controllers/home/services_controller.dart';
import 'package:carlton/controllers/main/main_controller.dart';
import 'package:carlton/customWidgets/custom_containers.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/models/service_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';

/// Nav order in [CustoBottomNavigationBar]: Home 0 · Stays 1 · Book 2 ·
/// Services 3 · Account 4. This screen is always reached from Services.
const _servicesTabIndex = 3;

/// Lists a services-hub category's requestable options (Room Service,
/// Housekeeping, ...). The category arrives via `Get.arguments`.
class ServiceCategoryDetailView extends StatelessWidget {
  const ServiceCategoryDetailView({super.key});

  @override
  Widget build(BuildContext context) {
    final args = Get.arguments;
    if (args is! ServiceDetailCategory) {
      WidgetsBinding.instance.addPostFrameCallback((_) => Get.back());
      return const CustomScaffold(body: SizedBox.shrink());
    }
    final category = args;
    final controller = Get.find<ServicesController>();

    return CustomScaffold(
      // The shell's own nav is covered by this pushed route, so the screen
      // renders its own copy: tapping a tab pops back and switches there.
      bottomNav: CustoBottomNavigationBar(
        currentIndex: _servicesTabIndex,
        onTap: (index) {
          Get.back();
          Get.find<MainController>().changeTab(index);
        },
      ),
      // CustomScaffold opts out of the top inset for its app-bar screens; this
      // one has no app bar, so it guards the status bar itself.
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            spacing: 20,
            children: [
              Row(
                spacing: 12,
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  InkWell(
                    onTap: () => Get.back(),
                    child: SvgPicture.asset(
                      'assets/icons/arrow_left.svg',
                      height: 20,
                      width: 20,
                      colorFilter: const ColorFilter.mode(
                        AppColors.inkBlack,
                        BlendMode.srcIn,
                      ),
                    ),
                  ),
                  PillContainer(
                    width: 52,
                    height: 52,
                    radius: 14,
                    backgroundColor: AppColors.pearlCream,
                    padding: const EdgeInsets.all(8),
                    child: CustomImage(
                      path: category.imagePath,
                      fit: BoxFit.contain,
                    ),
                  ),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      spacing: 2,
                      children: [
                        Text(
                          category.name,
                          style: Get.textTheme.titleMedium?.copyWith(
                            fontSize: 20,
                            fontWeight: FontWeight.w700,
                            color: AppColors.inkBlack,
                          ),
                        ),
                        Text(
                          category.subtitle,
                          style: Get.textTheme.labelMedium?.copyWith(
                            fontSize: 13,
                            color: AppColors.dimGrey,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              Expanded(
                child: ListView.separated(
                  padding: EdgeInsets.zero,
                  itemCount: category.options.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 12),
                  itemBuilder: (context, index) {
                    final option = category.options[index];
                    return CustomServiceOptionTile(
                      iconPath: option.iconPath,
                      title: option.title,
                      description: option.description,
                      eta: option.eta,
                      onTap: () => controller.openServiceRequest(option),
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
