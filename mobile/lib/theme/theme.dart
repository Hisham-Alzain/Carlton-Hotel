import 'package:flutter/material.dart';

class Themes {
  //TODO:add static when done
  ThemeData get theme => ThemeData(
    //Progress indicaator color
    // colorScheme: ColorScheme.fromSeed(
    //   // seedColor:
    //   // The color of the spinning arrow/arc
    //   // primary:
    // ),
    scaffoldBackgroundColor: Colors.white,

    fontFamily: '',
    textTheme: TextTheme(
      displayLarge: const TextStyle(
        fontFamily: '',
        fontSize: 57,
        fontWeight: FontWeight.w400,
        letterSpacing: -0.25,
        color: Colors.black,
      ),
      displayMedium: const TextStyle(
        fontFamily: '',
        fontSize: 45,
        fontWeight: FontWeight.w400,
        color: Colors.black,
      ),
      displaySmall: const TextStyle(
        fontFamily: '',
        fontSize: 36,
        fontWeight: FontWeight.w400,
        color: Colors.black,
      ),
      headlineLarge: const TextStyle(
        fontFamily: '',
        fontSize: 32,
        fontWeight: FontWeight.w400,
        color: Colors.black,
      ),
      headlineMedium: const TextStyle(
        fontFamily: '',
        fontSize: 28,
        fontWeight: FontWeight.w400,
        color: Colors.black,
      ),
      headlineSmall: const TextStyle(
        fontFamily: '',
        fontSize: 24,
        fontWeight: FontWeight.w400,
        color: Colors.black,
      ),
      titleLarge: const TextStyle(
        fontFamily: '',
        fontSize: 22,
        fontWeight: FontWeight.w400,
        color: Colors.black,
      ),
      titleMedium: const TextStyle(
        fontFamily: '',
        fontSize: 16,
        fontWeight: FontWeight.w500,
        letterSpacing: 0.15,
        color: Colors.black,
      ),
      titleSmall: const TextStyle(
        fontFamily: '',
        fontSize: 14,
        fontWeight: FontWeight.w500,
        letterSpacing: 0.1,
        color: Colors.black,
      ),
      labelLarge: const TextStyle(
        fontFamily: '',
        fontSize: 14,
        fontWeight: FontWeight.w500,
        letterSpacing: 0.1,
        color: Colors.black,
      ),
      labelMedium: const TextStyle(
        fontFamily: '',
        fontSize: 12,
        fontWeight: FontWeight.w500,
        letterSpacing: 0.5,
        color: Colors.black,
      ),
      labelSmall: const TextStyle(
        fontFamily: '',
        fontSize: 11,
        fontWeight: FontWeight.w500,
        letterSpacing: 0.5,
        color: Colors.black,
      ),
      bodyLarge: const TextStyle(
        fontFamily: '',
        fontSize: 16,
        fontWeight: FontWeight.w400,
        letterSpacing: 0.5,
        color: Colors.black,
      ),
      bodyMedium: const TextStyle(
        fontFamily: '',
        fontSize: 14,
        fontWeight: FontWeight.w400,
        letterSpacing: 0.25,
        color: Colors.black,
      ),
      bodySmall: const TextStyle(
        fontFamily: '',
        fontSize: 12,
        fontWeight: FontWeight.w400,
        color: Colors.black,
      ),
    ),

    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(
        textStyle: const TextStyle(
          fontFamily: '',
          fontSize: 14,
          fontWeight: FontWeight.w500,
          letterSpacing: 0.1,
        ),
        // foregroundColor:
        // disabledForegroundColor:
      ),
    ),

    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        // backgroundColor: Colors.black,
        // foregroundColor: Colors.white,
        // disabledForegroundColor: Colors.grey,
        // elevation: 0,
        textStyle: const TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.bold,
          fontFamily: '',
          letterSpacing: 0.1,
          // color: AppColors.black,
        ),
        // shape: const RoundedRectangleBorder(borderRadius: BorderRadius.zero),
      ),
    ),

    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        // backgroundColor: AppColors.black,
        // foregroundColor: AppColors.gold,
        // disabledForegroundColor: Colors.grey,
        // shape: const RoundedRectangleBorder(borderRadius: BorderRadius.zero),
        // side: const BorderSide(color: AppColors.gold, width: 1.5),
        // padding: const EdgeInsets.all(0),
        textStyle: const TextStyle(
          fontFamily: '',
          fontSize: 14,
          fontWeight: FontWeight.w500,
          letterSpacing: 0.1,
        ),
      ),
    ),

    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        // backgroundColor: AppColors.creame,
        // foregroundColor: Colors.black,
        // disabledBackgroundColor: AppColors.creame,
        // disabledForegroundColor: Colors.grey,
        // shadowColor: Color(0x1A16124D),
        // elevation: 10,
        textStyle: const TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.bold,
          letterSpacing: 0.1,
          fontFamily: '',
          // color: AppColors.black,
        ),
        // shape: const RoundedRectangleBorder(
        //   borderRadius: BorderRadius.zero,
        //   side: BorderSide(color: Colors.black, width: 1.5),
        // ),
      ),
    ),

    segmentedButtonTheme: SegmentedButtonThemeData(
      style: SegmentedButton.styleFrom(
        textStyle: const TextStyle(
          fontFamily: '',
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
      color: Colors.white,
      // borderRadius: BorderRadius.circular(),
      // circularTrackColor:
      refreshBackgroundColor: Colors.white,
      // linearTrackColor:
    ),

    dialogTheme: DialogThemeData(
      titleTextStyle: const TextStyle(
        fontFamily: '',
        fontSize: 24,
        fontWeight: FontWeight.w400,
      ),
      contentTextStyle: const TextStyle(
        fontFamily: '',
        fontSize: 14,
        fontWeight: FontWeight.w400,
        letterSpacing: 0.25,
      ),
    ),

    appBarTheme: AppBarTheme(
      backgroundColor: Colors.white,
      surfaceTintColor: Colors.white,
      scrolledUnderElevation: 0,
      iconTheme: IconThemeData(
        // color:
      ),
      centerTitle: true,
      titleTextStyle: const TextStyle(
        fontFamily: '',
        fontSize: 22,
        fontWeight: FontWeight.w400,
      ),
      actionsIconTheme: IconThemeData(color: Colors.white),
    ),

    drawerTheme: DrawerThemeData(backgroundColor: Colors.white),

    listTileTheme: ListTileThemeData(
      // iconColor:
      titleTextStyle: const TextStyle(
        fontFamily: '',
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
      dividerColor: Colors.white,
      // labelColor:
      // unselectedLabelColor:
      // indicatorColor:
      labelStyle: const TextStyle(
        fontFamily: '',
        fontSize: 14,
        fontWeight: FontWeight.w500,
        letterSpacing: 0.1,
      ),
      unselectedLabelStyle: const TextStyle(
        fontFamily: '',
        fontSize: 14,
        fontWeight: FontWeight.w500,
        letterSpacing: 0.1,
      ),
      indicator: UnderlineTabIndicator(
        borderSide: BorderSide(
          // color:
          width: 5,
        ),
        borderRadius: BorderRadius.only(
          topLeft: Radius.circular(10),
          topRight: Radius.circular(10),
        ),
      ),
    ),

    bottomNavigationBarTheme: BottomNavigationBarThemeData(
      type: BottomNavigationBarType.fixed,
      showUnselectedLabels: true,
      showSelectedLabels: true,
      backgroundColor: Colors.white,
      // selectedItemColor:
      // unselectedItemColor:
      selectedLabelStyle: const TextStyle(
        fontFamily: '',
        fontSize: 12,
        fontWeight: FontWeight.w500,
        letterSpacing: 0.5,
      ),
      unselectedLabelStyle: const TextStyle(
        fontFamily: '',
        fontSize: 12,
        fontWeight: FontWeight.w500,
        letterSpacing: 0.5,
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
        fontFamily: '',
        fontSize: 12,
        fontWeight: FontWeight.w500,
        letterSpacing: 0.5,
      ),
      unselectedLabelTextStyle: const TextStyle(
        fontFamily: '',
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
      // backgroundColor: AppColors.creame,
      // selectedColor: Colors.black,
      showCheckmark: false,
      labelStyle: TextStyle(
        fontFamily: '',
        fontSize: 14,
        fontWeight: FontWeight.w500,
        // color: WidgetStateColor.resolveWith((states) {
        //   if (states.contains(WidgetState.selected)) {
        //     return Colors.white; // Text color when selected
        //   }
        //   return Colors.black;
        // }),
      ),
      // shape: const RoundedRectangleBorder(
      //   side: BorderSide(color: Colors.black, width: 1.5),
      // ),
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
      textStyle: const TextStyle(fontFamily: '', fontSize: 15),
    ),

    cardTheme: CardThemeData(
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadiusGeometry.circular(15),
      ),
      color: Colors.white,
      elevation: 2,
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
      hintStyle: WidgetStatePropertyAll(
        const TextStyle(
          fontFamily: '',
          fontSize: 16,
          fontWeight: FontWeight.w400,
          letterSpacing: 0.5,
        ),
      ),
      textStyle: WidgetStatePropertyAll(
        const TextStyle(
          fontFamily: '',
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
        textStyle: WidgetStatePropertyAll(
          const TextStyle(
            fontFamily: '',
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
