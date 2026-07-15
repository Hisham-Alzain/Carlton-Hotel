import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

class _NavItem {
  final String iconPath;
  final String label;

  const _NavItem({required this.iconPath, required this.label});
}

const _kNavItems = [
  _NavItem(iconPath: 'assets/icons/home.svg', label: 'Home'),
  _NavItem(iconPath: 'assets/icons/bedstays.svg', label: 'Stays'),
  _NavItem(iconPath: 'assets/icons/book.svg', label: 'Book'),
  _NavItem(iconPath: 'assets/icons/ring2.svg', label: 'Services'),
  _NavItem(iconPath: 'assets/icons/profile.svg', label: 'Account'),
];

class AppBottomNav extends StatelessWidget {
  final int currentIndex;
  final ValueChanged<int> onTap;

  const AppBottomNav({
    required this.currentIndex,
    required this.onTap,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 79,
      decoration: const BoxDecoration(
        color: AppColors.bottomNavBg,
        border: Border(top: BorderSide(color: Color(0x14FFFFFF))),
      ),

      child: Row(
        children: List.generate(_kNavItems.length, (index) {
          final child = index == 2
              ? _BookButton(
                  selected: currentIndex == index,
                  onTap: () => onTap(index),
                )
              : _NavButton(
                  item: _kNavItems[index],
                  selected: currentIndex == index,
                  onTap: () => onTap(index),
                );
          return Expanded(child: Center(child: child));
        }),
      ),
    );
  }
}

class _NavButton extends StatelessWidget {
  final _NavItem item;
  final bool selected;
  final VoidCallback onTap;

  const _NavButton({
    required this.item,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final color = selected ? AppColors.primary : AppColors.navLabel;
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        spacing: 5,
        children: [
          SvgPicture.asset(
            item.iconPath,
            height: 24,
            width: 24,
            colorFilter: ColorFilter.mode(color, BlendMode.srcIn),
          ),

          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: FittedBox(
              fit: BoxFit.scaleDown,
              child: Text(
                item.label.toUpperCase(),
                maxLines: 1,
                style:
                    (selected
                            ? Theme.of(
                                context,
                              ).bottomNavigationBarTheme.selectedLabelStyle
                            : Theme.of(
                                context,
                              ).bottomNavigationBarTheme.unselectedLabelStyle)
                        ?.copyWith(color: color),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _BookButton extends StatelessWidget {
  final bool selected;
  final VoidCallback onTap;

  const _BookButton({required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final color = selected ? AppColors.primary : AppColors.navLabel;
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Transform.translate(
            offset: const Offset(0, -14),
            child: Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                color: const Color(0xE6FFFFFF),
                shape: BoxShape.circle,
                border: Border.all(color: Colors.white),
                boxShadow: const [
                  BoxShadow(
                    color: Color(0x33B3B3B3),
                    blurRadius: 20,
                    offset: Offset(0, -4),
                  ),
                ],
              ),
              alignment: Alignment.center,
              child: SvgPicture.asset(
                'assets/icons/book.svg',
                height: 24,
                width: 24,
                colorFilter: ColorFilter.mode(color, BlendMode.srcIn),
              ),
            ),
          ),
          Transform.translate(
            offset: const Offset(0, -14),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4),
              child: FittedBox(
                fit: BoxFit.scaleDown,
                child: Text(
                  'BOOK',
                  maxLines: 1,
                  style: Theme.of(context)
                      .bottomNavigationBarTheme
                      .unselectedLabelStyle
                      ?.copyWith(color: color),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
