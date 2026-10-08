part of 'ai_concierge_view.dart';

/// Circular pick-image button shown left of the chat field.
class _AttachmentButton extends StatelessWidget {
  final VoidCallback? onTap;

  const _AttachmentButton({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.whisperGrey,
      shape: const CircleBorder(),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 48,
          height: 48,
          child: Icon(
            Icons.add_photo_alternate_outlined,
            size: 24,
            color: onTap == null ? AppColors.silverGrey : AppColors.primary,
          ),
        ),
      ),
    );
  }
}

/// Thumbnail + remove chip for the image awaiting send.
class _AttachmentPreview extends StatelessWidget {
  final File file;
  final VoidCallback onRemove;

  const _AttachmentPreview({required this.file, required this.onRemove});

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: AlignmentDirectional.centerStart,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: Image.file(file, width: 64, height: 64, fit: BoxFit.cover),
          ),
          Positioned(
            top: -6,
            right: -6,
            child: GestureDetector(
              onTap: onRemove,
              child: Container(
                padding: const EdgeInsets.all(2),
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.inkBlack,
                ),
                child: const Icon(
                  Icons.close,
                  size: 14,
                  color: AppColors.white,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Quick-reply chips at the top of the Customer Service tab: white outlined
/// pills with dark text (Figma).
class _QuickReplies extends StatelessWidget {
  final AiConciergeController controller;

  const _QuickReplies({required this.controller});

  @override
  Widget build(BuildContext context) {
    final TextTheme textStyle = Get.textTheme;

    final replies = AiConciergeController.quickReplies;
    if (replies.isEmpty) return const SizedBox.shrink();

    // A handful of fixed chips: a Row with `spacing` inside a horizontal
    // scroller instead of ListView.separated with SizedBox separators.
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        spacing: 8,
        children: [
          for (final reply in replies)
            Material(
              color: AppColors.white,
              borderRadius: BorderRadius.circular(30),
              child: InkWell(
                onTap: () => controller.quickReply(reply),
                borderRadius: BorderRadius.circular(30),
                child: PillContainer(
                  height: 34,
                  backgroundColor: AppColors.white,
                  radius: 30,
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  border: Border.all(color: AppColors.black10),
                  child: Center(
                    widthFactor: 1,
                    child: Text(
                      reply,
                      style: textStyle.dmLabelMedium?.copyWith(
                        color: AppColors.inkBlack,
                      ),
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
