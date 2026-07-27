import 'package:carousel_slider/carousel_slider.dart';
import 'package:carlton/customWidgets/custom_image.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

/// Full-bleed image carousel with prev/next arrows and page dots — generic
/// enough for any multi-photo gallery (room details, listings, etc). Swipe is
/// driven by `carousel_slider`; the caller still owns [index] and is notified
/// of changes through [onIndexChanged], so external state stays in sync.
class CustomImageCarousel extends StatefulWidget {
  final List<String> images;
  final int index;
  final ValueChanged<int> onIndexChanged;
  final double height;

  const CustomImageCarousel({
    required this.images,
    required this.index,
    required this.onIndexChanged,
    this.height = 220,
    super.key,
  });

  @override
  State<CustomImageCarousel> createState() => _CustomImageCarouselState();
}

class _CustomImageCarouselState extends State<CustomImageCarousel> {
  final _controller = CarouselSliderController();
  late int _currentPage = _clampPage(widget.index);

  int _clampPage(int page) =>
      widget.images.isEmpty ? 0 : page.clamp(0, widget.images.length - 1);

  @override
  void didUpdateWidget(CustomImageCarousel oldWidget) {
    super.didUpdateWidget(oldWidget);
    // The owner moved the index externally (e.g. a reset) — follow it. Guarded
    // so the onPageChanged -> onIndexChanged -> rebuild round-trip is a no-op.
    final targetPage = _clampPage(widget.index);
    if (targetPage != _currentPage && widget.images.isNotEmpty) {
      _currentPage = targetPage;
      _controller.animateToPage(targetPage);
    }
  }

  @override
  Widget build(BuildContext context) {
    final images = widget.images;
    if (images.isEmpty) return SizedBox(height: widget.height);
    final hasMany = images.length > 1;

    return SizedBox(
      height: widget.height,
      width: double.infinity,
      child: Stack(
        children: [
          CarouselSlider(
            carouselController: _controller,
            options: CarouselOptions(
              height: widget.height,
              viewportFraction: 1,
              initialPage: _currentPage,
              enableInfiniteScroll: hasMany,
              onPageChanged: (newPage, _) {
                setState(() => _currentPage = newPage);
                widget.onIndexChanged(newPage);
              },
            ),
            items: images
                .map(
                  (imagePath) => CustomImage(
                    source: imagePath,
                    width: double.infinity,
                    height: widget.height,
                    fit: BoxFit.cover,
                  ),
                )
                .toList(),
          ),

          if (hasMany) ...[
            Positioned(
              left: 14,
              top: widget.height / 2 - 18,
              child: _arrow(
                icon: Icons.chevron_left,
                onTap: _controller.previousPage,
              ),
            ),
            Positioned(
              right: 14,
              top: widget.height / 2 - 18,
              child: _arrow(
                icon: Icons.chevron_right,
                onTap: _controller.nextPage,
              ),
            ),
            Positioned(
              bottom: 14,
              left: 0,
              right: 0,
              child: _dots(images.length, _currentPage),
            ),
          ],
        ],
      ),
    );
  }

  Widget _arrow({required IconData icon, required VoidCallback onTap}) {
    return Container(
      width: 36,
      height: 36,
      decoration: const BoxDecoration(
        color: AppColors.white73,
        shape: BoxShape.circle,
      ),
      child: IconButton(
        onPressed: onTap,
        padding: EdgeInsets.zero,
        iconSize: 22,
        icon: Icon(icon, color: AppColors.inkBlack),
      ),
    );
  }

  Widget _dots(int dotCount, int activeIndex) => Row(
    mainAxisAlignment: MainAxisAlignment.center,
    children: List.generate(
      dotCount,
      (dotIndex) => Container(
        margin: const EdgeInsets.symmetric(horizontal: 3),
        width: dotIndex == activeIndex ? 18 : 6,
        height: 6,
        decoration: BoxDecoration(
          color: dotIndex == activeIndex ? Colors.white : AppColors.white50,
          borderRadius: BorderRadius.circular(3),
        ),
      ),
    ),
  );
}
