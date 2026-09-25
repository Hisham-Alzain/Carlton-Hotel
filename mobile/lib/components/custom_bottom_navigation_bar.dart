import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

class BottomNavBarItem {
  final String iconPath;
  final String label;

  const BottomNavBarItem({required this.iconPath, required this.label});
}

/// Resolved per build so the labels follow `Get.updateLocale`; a top-level
/// `const` list would pin them to the launch locale.
List<BottomNavBarItem> get items => [
  BottomNavBarItem(
    iconPath: 'assets/icons/home.svg',
    label: AppTranslations.navHome,
  ),
  BottomNavBarItem(
    iconPath: 'assets/icons/doublebed.svg',
    label: AppTranslations.navStays,
  ),
  BottomNavBarItem(
    iconPath: 'assets/icons/book.svg',
    label: AppTranslations.navBook,
  ),
  BottomNavBarItem(
    iconPath: 'assets/icons/ring2.svg',
    label: AppTranslations.navServices,
  ),
  BottomNavBarItem(
    iconPath: 'assets/icons/profile.svg',
    label: AppTranslations.navAccount,
  ),
];

/// Plain icon used by every tab except the raised "Book" tab.
Widget _navIcon(String path, Color color) => Padding(
  padding: const EdgeInsets.all(10),
  child: SvgPicture.asset(
    path,
    height: 25,
    width: 25,
    colorFilter: ColorFilter.mode(color, BlendMode.srcIn),
  ),
);

class CustoBottomNavigationBar extends StatelessWidget {
  final int currentIndex;
  final ValueChanged<int> onTap;

  const CustoBottomNavigationBar({
    required this.currentIndex,
    required this.onTap,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return BottomNavigationBar(
      currentIndex: currentIndex,
      onTap: onTap,
      items: [
        BottomNavigationBarItem(
          icon: _navIcon(items[0].iconPath, AppColors.inkBlack),
          activeIcon: _navIcon(items[0].iconPath, AppColors.primary),
          label: items[0].label.toUpperCase(),
        ),
        BottomNavigationBarItem(
          icon: _navIcon(items[1].iconPath, AppColors.inkBlack),
          activeIcon: _navIcon(items[1].iconPath, AppColors.primary),
          label: items[1].label.toUpperCase(),
        ),
        BottomNavigationBarItem(
          icon: _BookNavIcon(
            iconPath: items[2].iconPath,
            color: AppColors.inkBlack,
          ),
          activeIcon: _BookNavIcon(
            iconPath: items[2].iconPath,
            color: AppColors.primary,
          ),
          label: items[2].label.toUpperCase(),
        ),
        BottomNavigationBarItem(
          icon: _navIcon(items[3].iconPath, AppColors.inkBlack),
          activeIcon: _navIcon(items[3].iconPath, AppColors.primary),
          label: items[3].label.toUpperCase(),
        ),
        BottomNavigationBarItem(
          icon: _navIcon(items[4].iconPath, AppColors.inkBlack),
          activeIcon: _navIcon(items[4].iconPath, AppColors.primary),
          label: items[4].label.toUpperCase(),
        ),
      ],
    );
  }
}

/// The raised, circular "Book" tab icon — white disc with a shadow, sitting
/// above the bar. Same shape for both selected/unselected states; only the
/// glyph color inside changes.
class _BookNavIcon extends StatelessWidget {
  final String iconPath;
  final Color color;

  const _BookNavIcon({required this.iconPath, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      width: 55,
      height: 55,
      decoration: BoxDecoration(
        color: Colors.white,
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white),
        boxShadow: const [
          BoxShadow(
            color: AppColors.ashShadow20,
            blurRadius: 20,
            offset: Offset(0, -4),
          ),
        ],
      ),
      alignment: Alignment.center,
      child: _navIcon(iconPath, color),
    );
  }
}
