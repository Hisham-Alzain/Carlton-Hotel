import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';

const _kFontFamily = 'Plus Jakarta Sans';

class Themes {
  static ThemeData get theme => ThemeData(
    // colorScheme: ColorScheme.fromSeed(
    //   // seedColor:
    //   // The color of the spinning arrow/arc
    //   // primary:
    // ),
    scaffoldBackgroundColor: AppColors.background,

    fontFamily: _kFontFamily,
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
          fontFamily: _kFontFamily,
          fontSize: 14,
          fontWeight: FontWeight.w500,
          letterSpacing: 0.1,
        ),
      ),
    ),

    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: AppColors.primaryButtonBg,
        foregroundColor: Colors.white,
        disabledForegroundColor: Colors.grey,
        elevation: 0,
        textStyle: const TextStyle(
          fontFamily: _kFontFamily,
          fontSize: 15,
          fontWeight: FontWeight.w600,
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),
    ),

    // Create Account (and every outlined button) gets the same soft drop
    // shadow Figma applies across this button family (Sign in / Browse
    // services): offset (0,4), blur 4, color #D4D4D4.
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        backgroundColor: const Color(0x8FECECEC),
        foregroundColor: AppColors.outlinedButtonText,
        disabledForegroundColor: Colors.grey,
        elevation: 2,
        shadowColor: const Color(0xFFD4D4D4),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        side: const BorderSide(
          color: AppColors.outlinedButtonBorder,
          width: 0.8,
        ),
        textStyle: const TextStyle(
          fontFamily: _kFontFamily,
          fontSize: 15,
          fontWeight: FontWeight.w600,
        ),
      ),
    ),

    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: AppColors.neutralButtonBg,
        foregroundColor: AppColors.neutralButtonText,
        disabledBackgroundColor: AppColors.neutralButtonBg,
        disabledForegroundColor: Colors.grey,
        shadowColor: const Color(0xFFD4D4D4),
        elevation: 2,
        textStyle: const TextStyle(
          fontFamily: _kFontFamily,
          fontSize: 15,
          fontWeight: FontWeight.w600,
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),
    ),

    segmentedButtonTheme: SegmentedButtonThemeData(
      style: SegmentedButton.styleFrom(
        textStyle: const TextStyle(
          fontFamily: _kFontFamily,
          fontSize: 14,
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
      circularTrackColor: AppColors.progressTrack,
      refreshBackgroundColor: Colors.white,
      linearTrackColor: AppColors.progressTrack,
    ),

    dialogTheme: const DialogThemeData(
      titleTextStyle: TextStyle(
        fontFamily: _kFontFamily,
        fontSize: 24,
        fontWeight: FontWeight.w400,
      ),
      contentTextStyle: TextStyle(
        fontFamily: _kFontFamily,
        fontSize: 14,
        fontWeight: FontWeight.w400,
        letterSpacing: 0.25,
      ),
    ),

    appBarTheme: AppBarTheme(
      backgroundColor: AppColors.background,
      surfaceTintColor: AppColors.background,
      scrolledUnderElevation: 0,
      elevation: 0,
      iconTheme: const IconThemeData(color: Colors.black87),
      centerTitle: true,
      titleTextStyle: const TextStyle(
        fontFamily: _kFontFamily,
        fontSize: 20,
        fontWeight: FontWeight.w500,
        color: Colors.black,
      ),
      actionsIconTheme: const IconThemeData(color: AppColors.primary),
    ),

    drawerTheme: DrawerThemeData(backgroundColor: Colors.white),

    listTileTheme: ListTileThemeData(
      // iconColor:
      titleTextStyle: const TextStyle(
        fontFamily: _kFontFamily,
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
      dividerColor: AppColors.progressTrack,
      labelColor: AppColors.primary,
      unselectedLabelColor: AppColors.primary,
      indicatorColor: AppColors.primary,
      labelStyle: const TextStyle(
        fontFamily: _kFontFamily,
        fontSize: 14,
        fontWeight: FontWeight.w500,
      ),
      unselectedLabelStyle: const TextStyle(
        fontFamily: _kFontFamily,
        fontSize: 14,
        fontWeight: FontWeight.w400,
      ),
      indicator: const UnderlineTabIndicator(
        borderSide: BorderSide(color: AppColors.primary, width: 2.5),
      ),
      indicatorSize: TabBarIndicatorSize.label,
    ),

    bottomNavigationBarTheme: BottomNavigationBarThemeData(
      type: BottomNavigationBarType.fixed,
      showUnselectedLabels: true,
      showSelectedLabels: true,
      backgroundColor: AppColors.bottomNavBg,
      selectedItemColor: AppColors.primary,
      unselectedItemColor: AppColors.navLabel,
      selectedLabelStyle: const TextStyle(
        fontFamily: _kFontFamily,
        fontSize: 9,
        fontWeight: FontWeight.w700,
        letterSpacing: 1.62,
      ),
      unselectedLabelStyle: const TextStyle(
        fontFamily: _kFontFamily,
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
        fontFamily: _kFontFamily,
        fontSize: 12,
        fontWeight: FontWeight.w500,
        letterSpacing: 0.5,
      ),
      unselectedLabelTextStyle: const TextStyle(
        fontFamily: _kFontFamily,
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
      backgroundColor: AppColors.cardBg,
      selectedColor: AppColors.primary,
      showCheckmark: false,
      labelStyle: const TextStyle(
        fontFamily: _kFontFamily,
        fontSize: 14,
        fontWeight: FontWeight.w500,
        color: AppColors.textPrimary,
      ),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: const BorderSide(color: AppColors.cardBorder),
      ),
    ),

    dividerTheme: DividerThemeData(
      // color:
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
      textStyle: const TextStyle(fontFamily: _kFontFamily, fontSize: 15),
    ),

    cardTheme: CardThemeData(
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadiusGeometry.circular(16),
        side: const BorderSide(color: AppColors.cardBorder),
      ),
      color: AppColors.cardBg,
      elevation: 0,
      margin: EdgeInsets.zero,
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
          fontFamily: _kFontFamily,
          fontSize: 16,
          fontWeight: FontWeight.w400,
          letterSpacing: 0.5,
        ),
      ),
      textStyle: const WidgetStatePropertyAll(
        TextStyle(
          fontFamily: _kFontFamily,
          fontSize: 16,
          fontWeight: FontWeight.w400,
          letterSpacing: 0.5,
        ),
      ),
    ),

    popupMenuTheme: PopupMenuThemeData(color: Colors.white),

    bottomSheetTheme: BottomSheetThemeData(
      backgroundColor: Colors.white,
      // dragHandleColor:
      showDragHandle: true,
      modalBackgroundColor: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadiusGeometry.circular(35),
      ),
      // constraints: BoxConstraints(maxHeight: 500),
      dragHandleSize: Size(32, 4),
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
            fontFamily: _kFontFamily,
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
