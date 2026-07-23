import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

const _mainFontFamily = 'Plus Jakarta Sans';

class Themes {
  //TODO: add static when done
  ThemeData get theme => ThemeData(
    colorScheme: ColorScheme.fromSeed(
      seedColor: AppColors.primary,
      // The color of the spinning arrow/arc
      primary: AppColors.primary,
    ),
    scaffoldBackgroundColor: AppColors.ghostWhite,

    fontFamily: _mainFontFamily,
    textTheme: const TextTheme(
      displayLarge: TextStyle(
        fontSize: 57,
        fontWeight: FontWeight.w400,
        letterSpacing: -0.25,
        color: Colors.black,
      ),
      displayMedium: TextStyle(
        fontSize: 45,
        fontWeight: FontWeight.w400,
        color: Colors.black,
      ),
      displaySmall: TextStyle(
        fontSize: 36,
        fontWeight: FontWeight.w400,
        color: Colors.black,
      ),
      headlineLarge: TextStyle(
        fontSize: 32,
        fontWeight: FontWeight.w400,
        color: Colors.black,
      ),
      headlineMedium: TextStyle(
        fontSize: 28,
        fontWeight: FontWeight.w400,
        color: Colors.black,
      ),
      headlineSmall: TextStyle(
        fontSize: 24,
        fontWeight: FontWeight.w400,
        color: Colors.black,
      ),
      titleLarge: TextStyle(
        fontSize: 20,
        fontWeight: FontWeight.w500,
        color: Colors.black,
      ),
      titleMedium: TextStyle(
        fontSize: 16,
        fontWeight: FontWeight.w500,
        letterSpacing: 0.15,
        color: Colors.black,
      ),
      titleSmall: TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w500,
        letterSpacing: 0.1,
        color: Colors.black,
      ),
      labelLarge: TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w500,
        letterSpacing: 0.1,
        color: Colors.black,
      ),
      labelMedium: TextStyle(
        fontSize: 12,
        fontWeight: FontWeight.w500,
        letterSpacing: 0.5,
        color: Colors.black,
      ),
      labelSmall: TextStyle(
        fontSize: 11,
        fontWeight: FontWeight.w500,
        letterSpacing: 0.5,
        color: Colors.black,
      ),
      bodyLarge: TextStyle(
        fontSize: 16,
        fontWeight: FontWeight.w400,
        letterSpacing: 0.5,
        color: Colors.black,
      ),
      bodyMedium: TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w400,
        letterSpacing: 0.25,
        color: Colors.black,
      ),
      bodySmall: TextStyle(
        fontSize: 12,
        fontWeight: FontWeight.w400,
        color: Colors.black,
      ),
    ),

    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(
        foregroundColor: AppColors.primary,
        textStyle: const TextStyle(
          fontFamily: 'DM Sans',
          fontSize: 14,
          fontWeight: FontWeight.w500,
          letterSpacing: 0.1,
        ),
      ),
    ),

    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: AppColors.primary90,
        foregroundColor: Colors.white,
        disabledForegroundColor: Colors.grey,
        elevation: 0,
        textStyle: const TextStyle(
          fontFamily: 'DM Sans',
          fontSize: 15,
          fontWeight: FontWeight.w700,
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),
    ),

    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        backgroundColor: Colors.transparent,
        foregroundColor: AppColors.pineTeal,
        disabledForegroundColor: Colors.grey,
        elevation: 0,
        shadowColor: AppColors.mistGrey,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        side: const BorderSide(color: Colors.white, width: 0.8),
        textStyle: const TextStyle(
          fontFamily: 'DM Sans',
          fontSize: 15,
          fontWeight: FontWeight.w500,
        ),
      ),
    ),

    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: AppColors.platinumGrey90,
        foregroundColor: AppColors.nearBlack,
        disabledBackgroundColor: AppColors.platinumGrey90,
        disabledForegroundColor: Colors.grey,
        shadowColor: AppColors.mistGrey,
        elevation: 2,
        textStyle: const TextStyle(
          fontFamily: 'DM Sans',
          fontSize: 15,
          fontWeight: FontWeight.w600,
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),
    ),

    segmentedButtonTheme: SegmentedButtonThemeData(
      style: SegmentedButton.styleFrom(
        backgroundColor: Colors.transparent,
        foregroundColor: Colors.white,
        selectedBackgroundColor: AppColors.cream,
        selectedForegroundColor: AppColors.espressoInk,
        side: const BorderSide(color: Colors.white, width: 1.5),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        padding: EdgeInsets.zero,
        minimumSize: const Size(0, 40),
        elevation: 0,
        textStyle: const TextStyle(
          fontFamily: 'DM Sans',
          fontSize: 10,
          fontWeight: FontWeight.w500,
          letterSpacing: 0.1,
        ),
      ),
    ),

    floatingActionButtonTheme: FloatingActionButtonThemeData(
      backgroundColor: Colors.white,
      // foregroundColor: ,
    ),

    progressIndicatorTheme: ProgressIndicatorThemeData(
      color: AppColors.primary,
      // borderRadius: BorderRadius.circular(),
      circularTrackColor: AppColors.iceBlue,
      refreshBackgroundColor: Colors.white,
      linearTrackColor: AppColors.iceBlue,
    ),

    dialogTheme: const DialogThemeData(
      titleTextStyle: TextStyle(
        fontFamily: _mainFontFamily,
        fontSize: 24,
        fontWeight: FontWeight.w400,
      ),
      contentTextStyle: TextStyle(
        fontFamily: _mainFontFamily,
        fontSize: 14,
        fontWeight: FontWeight.w400,
        letterSpacing: 0.25,
      ),
    ),

    appBarTheme: AppBarTheme(
      backgroundColor: AppColors.ghostWhite,
      surfaceTintColor: AppColors.ghostWhite,
      scrolledUnderElevation: 0,
      elevation: 0,
      iconTheme: const IconThemeData(color: Colors.white),
      centerTitle: true,
      titleTextStyle: const TextStyle(
        fontFamily: _mainFontFamily,
        fontSize: 20,
        fontWeight: FontWeight.w600,
        color: Colors.black,
      ),
      actionsIconTheme: const IconThemeData(color: AppColors.primary),
      actionsPadding: const EdgeInsets.only(right: 10),
    ),

    drawerTheme: DrawerThemeData(backgroundColor: Colors.white),

    listTileTheme: ListTileThemeData(
      // iconColor:
      titleTextStyle: const TextStyle(
        fontFamily: _mainFontFamily,
        fontSize: 14,
        // color:
        fontWeight: FontWeight.w700,
      ),
    ),

    iconTheme: IconThemeData(
      // color:
    ),

    iconButtonTheme: IconButtonThemeData(
      style: ButtonStyle(
        // iconColor:
        //  WidgetStatePropertyAll(
        //   AppColors.primaryColor
        // ),
      ),
    ),

    textSelectionTheme: TextSelectionThemeData(
      // cursorColor:
      // selectionHandleColor:
    ),

    tabBarTheme: TabBarThemeData(
      dividerColor: AppColors.iceBlue,
      labelColor: AppColors.primary,
      unselectedLabelColor: AppColors.primary,
      indicatorColor: AppColors.primary,
      labelPadding: const EdgeInsets.all(10),
      labelStyle: const TextStyle(
        fontFamily: _mainFontFamily,
        fontSize: 14,
        fontWeight: FontWeight.w500,
      ),
      unselectedLabelStyle: const TextStyle(
        fontFamily: _mainFontFamily,
        fontSize: 14,
        fontWeight: FontWeight.w400,
      ),
      indicator: const UnderlineTabIndicator(
        borderSide: BorderSide(color: AppColors.primary, width: 2.5),
      ),
      indicatorSize: TabBarIndicatorSize.tab,
    ),

    bottomNavigationBarTheme: BottomNavigationBarThemeData(
      type: BottomNavigationBarType.fixed,
      showUnselectedLabels: true,
      showSelectedLabels: true,
      backgroundColor: AppColors.snowGrey,
      elevation: 0,
      selectedItemColor: AppColors.primary,
      unselectedItemColor: AppColors.inkBlack,
      selectedLabelStyle: const TextStyle(
        fontFamily: _mainFontFamily,
        fontSize: 9,
        fontWeight: FontWeight.w700,
        letterSpacing: 1.62,
      ),
      unselectedLabelStyle: const TextStyle(
        fontFamily: _mainFontFamily,
        fontSize: 9,
        fontWeight: FontWeight.w400,
        letterSpacing: 1.62,
      ),
    ),

    navigationRailTheme: NavigationRailThemeData(
      backgroundColor: Colors.white,
      indicatorColor: Colors.transparent,
      indicatorShape: OutlineInputBorder(
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(10)),
        borderSide: BorderSide(
          width: 2,
          // color:
        ),
      ),
      labelType: NavigationRailLabelType.selected,
      selectedIconTheme: IconThemeData(
        // color:
      ),
      unselectedIconTheme: IconThemeData(color: Colors.black),
      selectedLabelTextStyle: const TextStyle(
        fontFamily: _mainFontFamily,
        fontSize: 12,
        fontWeight: FontWeight.w500,
        letterSpacing: 0.5,
      ),
      unselectedLabelTextStyle: const TextStyle(
        fontFamily: _mainFontFamily,
        fontSize: 12,
        fontWeight: FontWeight.w500,
        letterSpacing: 0.5,
      ),
    ),

    checkboxTheme: CheckboxThemeData(
      fillColor: WidgetStateProperty.resolveWith<Color?>((states) {
        if (states.contains(WidgetState.selected)) {
          // return AppColors.primaryColor; // Color when checked
        }
        return Colors.transparent; // Transparent when unchecked
      }),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(5)),
      checkColor: const WidgetStatePropertyAll(Colors.white),
      side: const BorderSide(
        // color:
      ),
    ),

    chipTheme: ChipThemeData(
      backgroundColor: AppColors.featherGrey,
      selectedColor: AppColors.primary,
      showCheckmark: false,
      labelStyle: const TextStyle(
        fontFamily: _mainFontFamily,
        fontSize: 12,
        fontWeight: FontWeight.w400,
        color: AppColors.charcoalTeal,
      ),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: const BorderSide(color: AppColors.linenTaupe30),
      ),
    ),

    dividerTheme: DividerThemeData(
      color: AppColors.primary,
      indent: 10,
      endIndent: 10,
      thickness: 1,
    ),

    expansionTileTheme: ExpansionTileThemeData(
      // iconColor:
      // collapsedIconColor:
      shape: Border.all(color: Colors.transparent),
    ),

    toggleButtonsTheme: ToggleButtonsThemeData(
      // selectedBorderColor:
      fillColor: Colors.transparent,
      // borderRadius:
      textStyle: const TextStyle(fontFamily: _mainFontFamily, fontSize: 15),
    ),

    cardTheme: CardThemeData(
      shape: BoxBorder.all(color: AppColors.linenGrey),
      color: AppColors.featherGrey,
      elevation: 0,
      margin: EdgeInsets.zero,
      shadowColor: AppColors.silverShadow25,
    ),

    radioTheme: RadioThemeData(
      // fillColor: WidgetStatePropertyAll()
    ),
    datePickerTheme: DatePickerThemeData(),

    searchBarTheme: SearchBarThemeData(
      shape: WidgetStatePropertyAll(
        RoundedRectangleBorder(borderRadius: BorderRadiusGeometry.circular(28)),
      ),
      // backgroundColor: WidgetStatePropertyAll(),
      shadowColor: WidgetStatePropertyAll(Colors.transparent),
      hintStyle: const WidgetStatePropertyAll(
        TextStyle(
          fontFamily: _mainFontFamily,
          fontSize: 16,
          fontWeight: FontWeight.w400,
          letterSpacing: 0.5,
        ),
      ),
      textStyle: const WidgetStatePropertyAll(
        TextStyle(
          fontFamily: _mainFontFamily,
          fontSize: 16,
          fontWeight: FontWeight.w400,
          letterSpacing: 0.5,
        ),
      ),
    ),

    popupMenuTheme: PopupMenuThemeData(
      iconColor: AppColors.taupeBrown,
      color: Colors.white,
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    ),

    bottomSheetTheme: BottomSheetThemeData(
      backgroundColor: AppColors.white,
      showDragHandle: true,
      modalBackgroundColor: AppColors.white,
      // Top corners only — the bottom two are off-screen.
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      // constraints: BoxConstraints(maxHeight: 500),
      dragHandleSize: Size(36, 4),
      dragHandleColor: AppColors.smokeGrey,
    ),

    sliderTheme: SliderThemeData(
      // activeTrackColor:
      // inactiveTrackColor:
      thumbColor: Colors.white,
    ),
    switchTheme: SwitchThemeData(
      thumbColor: WidgetStateProperty.resolveWith<Color?>((states) {
        if (states.contains(WidgetState.selected)) {
          return Colors.white; // Color when checked
        }
        return Colors.white; // Transparent when unchecked
      }),
      // trackColor: WidgetStateProperty.resolveWith<Color?>((states) {
      //   if (states.contains(WidgetState.selected)) {
      //     return  // Color when checked
      //   }
      //   return  // Transparent when unchecked
      // }),
      trackOutlineColor: WidgetStatePropertyAll(Colors.transparent),
      trackOutlineWidth: WidgetStatePropertyAll(0),
    ),
    menuButtonTheme: MenuButtonThemeData(
      style: ButtonStyle(
        textStyle: const WidgetStatePropertyAll(
          TextStyle(
            fontFamily: _mainFontFamily,
            fontSize: 16,
            fontWeight: FontWeight.w400,
            letterSpacing: 0.5,
          ),
        ),
      ),
    ),
    menuBarTheme: MenuBarThemeData(),
  );
}
