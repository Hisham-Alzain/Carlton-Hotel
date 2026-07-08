import 'package:carlton/customWidgets/custom_text_field.dart';
import 'package:carlton/customWidgets/custom_validation.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:dropdown_button2/dropdown_button2.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/svg.dart';
import 'package:get/get.dart';

class CustomDropDown<T> extends StatefulWidget {
  final double? width;
  final IconData? icon;
  final String? iconPath;
  final String? labelText;
  final List<T> items;
  final T? value;
  final String Function(T)? itemLabel;
  final void Function(T?) onChanged;
  final bool enableSearch;

  const CustomDropDown({
    this.width,
    this.icon,
    this.iconPath,
    this.labelText,
    required this.items,
    required this.value,
    this.itemLabel,
    required this.onChanged,
    this.enableSearch = false,
    super.key,
  });

  @override
  State<CustomDropDown<T>> createState() => _CustomDropDownState<T>();
}

class _CustomDropDownState<T> extends State<CustomDropDown<T>> {
  final TextEditingController searchController = TextEditingController();
  late final ValueNotifier<T?> valueListenable;

  @override
  void initState() {
    super.initState();
    valueListenable = ValueNotifier<T?>(widget.value); // <-- init once
  }

  @override
  void didUpdateWidget(covariant CustomDropDown<T> oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.value != widget.value) {
      valueListenable.value = widget.value; // <-- sync on parent rebuild
    }
  }

  @override
  void dispose() {
    searchController.dispose();
    valueListenable.dispose(); // <-- dispose it
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Get.theme;
    final labelStyle = theme.textTheme.bodySmall;
    final inputStyle = theme.textTheme.bodyMedium;
    final hintStyle = theme.textTheme.bodyLarge?.copyWith(
      // color: AppColors.grey13,
    );
    final errorStyle = theme.textTheme.bodySmall?.copyWith(color: Colors.red);

    OutlineInputBorder border(Color color) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(10),
      borderSide: BorderSide(width: 2, color: color),
    );

    return Column(
      children: [
        if (widget.labelText != null && widget.labelText!.isNotEmpty ||
            widget.icon != null ||
            widget.iconPath != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 5),
            child: Row(
              spacing: 10,
              children: [
                if (widget.icon != null) Icon(widget.icon),
                if (widget.iconPath != null)
                  SvgPicture.asset(
                    widget.iconPath!,
                    // colorFilter: ColorFilter.mode(
                    //   AppColors.primaryColor,
                    //   BlendMode.srcIn,
                    // ),
                  ),
                if (widget.labelText != null && widget.labelText!.isNotEmpty)
                  Text(widget.labelText!, style: labelStyle),
              ],
            ),
          ),

        SizedBox(
          width: widget.width,
          child: DropdownButtonFormField2<T>(
            isExpanded: true,
            valueListenable: valueListenable,
            style: inputStyle,
            decoration: InputDecoration(
              hintText: AppTranslations.select,
              filled: true,
              // fillColor: AppColors.grey11,
              hintStyle: hintStyle,
              // border: border(AppColors.grey11),
              // enabledBorder: border(AppColors.grey11),
              // focusedBorder: border(AppColors.primaryColor),
              errorBorder: border(Colors.red),
              focusedErrorBorder: border(Colors.red),
              errorStyle: errorStyle,
            ),
            hint: Text(AppTranslations.select, style: hintStyle),
            items: widget.items.map((item) {
              return DropdownItem<T>(
                value: item,
                child: Text(
                  widget.itemLabel != null
                      ? widget.itemLabel!(item)
                      : item.toString(),
                  style: inputStyle,
                  softWrap: true,
                ),
              );
            }).toList(),
            validator: (value) =>
                CustomValidation().validateRequiredDropDown(value),
            onChanged: widget.onChanged,
            dropdownStyleData: DropdownStyleData(
              maxHeight: 300,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(10),
              ),
            ),
            menuItemStyleData: const MenuItemStyleData(
              padding: EdgeInsets.all(10),
            ),
            dropdownSearchData: widget.enableSearch
                ? DropdownSearchData(
                    searchController: searchController,
                    searchBarWidgetHeight: 50,
                    searchBarWidget: Padding(
                      padding: const EdgeInsets.all(10),
                      child: CustomTextField(
                        width: widget.width,
                        controller: searchController,
                        textInputType: TextInputType.text,
                        obsecureText: false,
                        prefixIcon: Icons.search,
                        hintText: AppTranslations.search,
                      ),
                    ),
                    searchMatchFn: (item, searchValue) {
                      return item.value.toString().toLowerCase().contains(
                        searchValue.toLowerCase(),
                      );
                    },
                  )
                : null,
            onMenuStateChange: widget.enableSearch
                ? (isOpen) {
                    if (!isOpen) searchController.clear();
                  }
                : null,
          ),
        ),
      ],
    );
  }
}
