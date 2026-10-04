import 'package:carlton/theme/theme.dart';
import 'package:carlton/controllers/discover/experience_controller.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/customWidgets/custom_indicators.dart';
import 'package:carlton/customWidgets/custom_scaffold.dart';
import 'package:carlton/extensions/price_extension.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// One experience, from `GET /public/experiences/{uuid}`.
///
/// Booking is deliberately absent: experiences are not in the `BookableType`
/// enum that `POST /service-bookings` validates against, so a "Book" button here
/// would have nothing to post to. The concierge chat is the real route, and the
/// screen says so.
class ExperienceDetailView extends GetView<ExperienceController> {
  const ExperienceDetailView({super.key});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;
    final item = controller.item;

    return CustomScaffold(
      appBar: AppBar(
        iconTheme: const IconThemeData(color: AppColors.inkBlack),
        title: Text(item.name),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          spacing: 20,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (item.imagePath.isNotEmpty)
              ClipRRect(
                borderRadius: BorderRadius.circular(16),
                child: CustomImage(
                  source: item.imagePath,
                  height: 220,
                  fit: BoxFit.cover,
                ),
              ),
            Column(
              spacing: 10,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.name,
                  style: textStyle.titleLarge?.copyWith(
                    color: AppColors.inkBlack,
                  ),
                ),
                // Group size and duration are prose from the CMS, not numbers —
                // each row hides itself when the field is blank.
                if (item.subtitle.isNotEmpty)
                  _MetaRow(icon: Icons.groups_outlined, label: item.subtitle),
                if (item.hours.isNotEmpty)
                  _MetaRow(icon: Icons.schedule_outlined, label: item.hours),
                // Scoped: the price arrives with the detail fetch.
                Obx(() {
                  final price = controller.priceUsd.value;
                  if (price <= 0) return const SizedBox.shrink();
                  return _MetaRow(
                    icon: Icons.sell_outlined,
                    label: price.formatPrice(),
                  );
                }),
              ],
            ),
            Obx(() {
              if (controller.loading.value) {
                return const Padding(
                  padding: EdgeInsets.all(20),
                  child: Center(child: LogoLoadingIndicator(size: 50)),
                );
              }
              final about = controller.about.value;
              // An experience with no published description is a real state.
              if (about.isEmpty) return const SizedBox.shrink();
              return Text(
                about,
                style: textStyle.dmBodyMedium?.copyWith(
                  color: AppColors.taupeBrown,
                ),
              );
            }),
          ],
        ),
      ),
    );
  }
}

/// Icon + line of prose. Private: the spacing is tuned to this screen's column.
class _MetaRow extends StatelessWidget {
  final IconData icon;
  final String label;

  const _MetaRow({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) {
    return Row(
      spacing: 10,
      children: [
        Icon(icon, size: 16, color: AppColors.walnutGold),
        Expanded(
          child: Text(
            label,
            style: Get.textTheme.dmLabelMedium?.copyWith(
              color: AppColors.taupeBrown,
            ),
          ),
        ),
      ],
    );
  }
}
