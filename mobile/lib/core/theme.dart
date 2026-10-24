import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

// Brand colours. These stay constant in light and dark mode.
const Color appTeal = Color(0xFF0D9488);
const Color appTealDark = Color(0xFF0B6B62);
const Color appTealLight = Color(0xFF2DD4BF);
const Color appGold = Color(0xFFF59E0B);
const Color appAmber = Color(0xFFD97706);
const Color appDanger = Color(0xFFDC2626);
const Color appRose = Color(0xFFE11D48);

// Light palette (kept as constants for widgets that do not need theming).
const Color appMint = Color(0xFFE6F4F1);
const Color appMintStrong = Color(0xFFD2EBE6);
const Color appBackground = Color(0xFFF5F9F8);
const Color appInk = Color(0xFF102A26);
const Color appMuted = Color(0xFF46615B);
const Color appFaint = Color(0xFF68807A);
const Color appLine = Color(0xFFD8E6E2);
const Color appSand = Color(0xFFFFF7EC);

const LinearGradient appHeroGradient = LinearGradient(
  begin: Alignment.topRight,
  end: Alignment.bottomLeft,
  colors: [Color(0xFF053B35), Color(0xFF07635B), Color(0xFF0A8175)],
);

const LinearGradient appHeroGradientDark = LinearGradient(
  begin: Alignment.topRight,
  end: Alignment.bottomLeft,
  colors: [Color(0xFF021E1B), Color(0xFF04403B), Color(0xFF065C54)],
);

const List<BoxShadow> appCardShadow = [
  BoxShadow(color: Color(0x0D0B3B34), blurRadius: 18, offset: Offset(0, 8)),
];

const List<BoxShadow> appSoftShadow = [
  BoxShadow(color: Color(0x140B3B34), blurRadius: 26, offset: Offset(0, 12)),
];

/// Semantic colours for the current brightness.
@immutable
class AppPalette extends ThemeExtension<AppPalette> {
  const AppPalette({
    required this.isDark,
    required this.background,
    required this.surface,
    required this.surfaceAlt,
    required this.inputFill,
    required this.ink,
    required this.muted,
    required this.faint,
    required this.line,
    required this.accent,
    required this.mint,
    required this.mintStrong,
    required this.mintBorder,
    required this.sand,
    required this.warnSurface,
    required this.warnBorder,
    required this.warnFg,
    required this.warnIconBg,
    required this.dangerSurface,
    required this.dangerBorder,
    required this.dangerFg,
    required this.dangerIconBg,
    required this.heroGradient,
    required this.shadow,
  });

  final bool isDark;
  final Color background;
  final Color surface;
  final Color surfaceAlt;
  final Color inputFill;
  final Color ink;
  final Color muted;
  final Color faint;
  final Color line;
  final Color accent;
  final Color mint;
  final Color mintStrong;
  final Color mintBorder;
  final Color sand;
  final Color warnSurface;
  final Color warnBorder;
  final Color warnFg;
  final Color warnIconBg;
  final Color dangerSurface;
  final Color dangerBorder;
  final Color dangerFg;
  final Color dangerIconBg;
  final Gradient heroGradient;
  final List<BoxShadow> shadow;

  static const light = AppPalette(
    isDark: false,
    background: Color(0xFFF5F9F8),
    surface: Colors.white,
    surfaceAlt: Color(0xFFF1F7F5),
    inputFill: Colors.white,
    ink: Color(0xFF102A26),
    muted: Color(0xFF46615B),
    faint: Color(0xFF68807A),
    line: Color(0xFFD8E6E2),
    accent: Color(0xFF0B6B62),
    mint: Color(0xFFE6F4F1),
    mintStrong: Color(0xFFD2EBE6),
    mintBorder: Color(0xFFC4E3DC),
    sand: Color(0xFFFFF7EC),
    warnSurface: Color(0xFFFFF4E2),
    warnBorder: Color(0xFFF2D8AE),
    warnFg: Color(0xFF8A4D06),
    warnIconBg: Color(0xFFFBE8CC),
    dangerSurface: Color(0xFFFFF1F1),
    dangerBorder: Color(0xFFF6C9C6),
    dangerFg: Color(0xFFB42318),
    dangerIconBg: Color(0xFFFBDAD6),
    heroGradient: appHeroGradient,
    shadow: appCardShadow,
  );

  static const dark = AppPalette(
    isDark: true,
    background: Color(0xFF08110F),
    surface: Color(0xFF101C19),
    surfaceAlt: Color(0xFF162621),
    inputFill: Color(0xFF162621),
    ink: Color(0xFFE8F3F0),
    muted: Color(0xFFA7C0BA),
    faint: Color(0xFF89A49E),
    line: Color(0xFF22332E),
    accent: Color(0xFF2DD4BF),
    mint: Color(0xFF142A25),
    mintStrong: Color(0xFF1B3731),
    mintBorder: Color(0xFF255046),
    sand: Color(0xFF2A2318),
    warnSurface: Color(0xFF2A2113),
    warnBorder: Color(0xFF5A4520),
    warnFg: Color(0xFFF3C77A),
    warnIconBg: Color(0xFF3A2D16),
    dangerSurface: Color(0xFF2A1616),
    dangerBorder: Color(0xFF5C2B28),
    dangerFg: Color(0xFFF6A9A2),
    dangerIconBg: Color(0xFF3A1D1B),
    heroGradient: appHeroGradientDark,
    shadow: <BoxShadow>[],
  );

  @override
  AppPalette copyWith({
    bool? isDark,
    Color? background,
    Color? surface,
    Color? surfaceAlt,
    Color? inputFill,
    Color? ink,
    Color? muted,
    Color? faint,
    Color? line,
    Color? accent,
    Color? mint,
    Color? mintStrong,
    Color? mintBorder,
    Color? sand,
    Color? warnSurface,
    Color? warnBorder,
    Color? warnFg,
    Color? warnIconBg,
    Color? dangerSurface,
    Color? dangerBorder,
    Color? dangerFg,
    Color? dangerIconBg,
    Gradient? heroGradient,
    List<BoxShadow>? shadow,
  }) {
    return AppPalette(
      isDark: isDark ?? this.isDark,
      background: background ?? this.background,
      surface: surface ?? this.surface,
      surfaceAlt: surfaceAlt ?? this.surfaceAlt,
      inputFill: inputFill ?? this.inputFill,
      ink: ink ?? this.ink,
      muted: muted ?? this.muted,
      faint: faint ?? this.faint,
      line: line ?? this.line,
      accent: accent ?? this.accent,
      mint: mint ?? this.mint,
      mintStrong: mintStrong ?? this.mintStrong,
      mintBorder: mintBorder ?? this.mintBorder,
      sand: sand ?? this.sand,
      warnSurface: warnSurface ?? this.warnSurface,
      warnBorder: warnBorder ?? this.warnBorder,
      warnFg: warnFg ?? this.warnFg,
      warnIconBg: warnIconBg ?? this.warnIconBg,
      dangerSurface: dangerSurface ?? this.dangerSurface,
      dangerBorder: dangerBorder ?? this.dangerBorder,
      dangerFg: dangerFg ?? this.dangerFg,
      dangerIconBg: dangerIconBg ?? this.dangerIconBg,
      heroGradient: heroGradient ?? this.heroGradient,
      shadow: shadow ?? this.shadow,
    );
  }

  @override
  AppPalette lerp(ThemeExtension<AppPalette>? other, double t) {
    if (other is! AppPalette) return this;
    return AppPalette(
      isDark: t < 0.5 ? isDark : other.isDark,
      background: Color.lerp(background, other.background, t) ?? background,
      surface: Color.lerp(surface, other.surface, t) ?? surface,
      surfaceAlt: Color.lerp(surfaceAlt, other.surfaceAlt, t) ?? surfaceAlt,
      inputFill: Color.lerp(inputFill, other.inputFill, t) ?? inputFill,
      ink: Color.lerp(ink, other.ink, t) ?? ink,
      muted: Color.lerp(muted, other.muted, t) ?? muted,
      faint: Color.lerp(faint, other.faint, t) ?? faint,
      line: Color.lerp(line, other.line, t) ?? line,
      accent: Color.lerp(accent, other.accent, t) ?? accent,
      mint: Color.lerp(mint, other.mint, t) ?? mint,
      mintStrong: Color.lerp(mintStrong, other.mintStrong, t) ?? mintStrong,
      mintBorder: Color.lerp(mintBorder, other.mintBorder, t) ?? mintBorder,
      sand: Color.lerp(sand, other.sand, t) ?? sand,
      warnSurface: Color.lerp(warnSurface, other.warnSurface, t) ?? warnSurface,
      warnBorder: Color.lerp(warnBorder, other.warnBorder, t) ?? warnBorder,
      warnFg: Color.lerp(warnFg, other.warnFg, t) ?? warnFg,
      warnIconBg: Color.lerp(warnIconBg, other.warnIconBg, t) ?? warnIconBg,
      dangerSurface:
          Color.lerp(dangerSurface, other.dangerSurface, t) ?? dangerSurface,
      dangerBorder:
          Color.lerp(dangerBorder, other.dangerBorder, t) ?? dangerBorder,
      dangerFg: Color.lerp(dangerFg, other.dangerFg, t) ?? dangerFg,
      dangerIconBg:
          Color.lerp(dangerIconBg, other.dangerIconBg, t) ?? dangerIconBg,
      heroGradient:
          Gradient.lerp(heroGradient, other.heroGradient, t) ?? heroGradient,
      shadow: t < 0.5 ? shadow : other.shadow,
    );
  }
}

extension AppPaletteContext on BuildContext {
  AppPalette get palette =>
      Theme.of(this).extension<AppPalette>() ?? AppPalette.light;
}

/// Colour ramp used by star / emoji / rating answers: red → amber → teal.
Color scoreColor(num score, {num min = 1, num max = 5}) {
  if (score <= min) return appRose;
  if (score >= max) return appTeal;
  final t = ((score - min) / (max - min)).clamp(0.0, 1.0);
  if (t <= 0.5) {
    return Color.lerp(appRose, appGold, t * 2) ?? appGold;
  }
  return Color.lerp(appGold, appTeal, (t - 0.5) * 2) ?? appTeal;
}

ThemeData buildAppTheme({Brightness brightness = Brightness.light}) {
  final dark = brightness == Brightness.dark;
  final palette = dark ? AppPalette.dark : AppPalette.light;

  final scheme =
      ColorScheme.fromSeed(seedColor: appTeal, brightness: brightness).copyWith(
        primary: dark ? appTealLight : appTeal,
        onPrimary: dark ? const Color(0xFF05201C) : Colors.white,
        secondary: dark ? appTealLight : appTealDark,
        surface: palette.surface,
        onSurface: palette.ink,
        error: dark ? const Color(0xFFFF8A80) : appDanger,
        onError: Colors.white,
      );

  final base = ThemeData(
    useMaterial3: true,
    brightness: brightness,
    colorScheme: scheme,
    scaffoldBackgroundColor: palette.background,
    fontFamily: 'Tajawal',
  );

  final textTheme = base.textTheme
      .apply(
        fontFamily: 'Tajawal',
        bodyColor: palette.ink,
        displayColor: palette.ink,
      )
      .copyWith(
        displaySmall: TextStyle(
          color: palette.ink,
          fontSize: 30,
          fontWeight: FontWeight.w800,
          height: 1.25,
          letterSpacing: -0.2,
        ),
        headlineMedium: TextStyle(
          color: palette.ink,
          fontSize: 24,
          fontWeight: FontWeight.w800,
          height: 1.3,
        ),
        headlineSmall: TextStyle(
          color: palette.ink,
          fontSize: 20,
          fontWeight: FontWeight.w800,
          height: 1.35,
        ),
        titleLarge: TextStyle(
          color: palette.ink,
          fontSize: 17,
          fontWeight: FontWeight.w800,
          height: 1.4,
        ),
        titleMedium: TextStyle(
          color: palette.ink,
          fontSize: 15,
          fontWeight: FontWeight.w700,
          height: 1.45,
        ),
        titleSmall: TextStyle(
          color: palette.ink,
          fontSize: 13.5,
          fontWeight: FontWeight.w700,
          height: 1.45,
        ),
        bodyLarge: TextStyle(
          color: palette.ink,
          fontSize: 14.5,
          fontWeight: FontWeight.w500,
          height: 1.65,
        ),
        bodyMedium: TextStyle(
          color: palette.ink,
          fontSize: 13,
          fontWeight: FontWeight.w500,
          height: 1.65,
        ),
        bodySmall: TextStyle(
          color: palette.muted,
          fontSize: 11.5,
          fontWeight: FontWeight.w500,
          height: 1.6,
        ),
        labelLarge: TextStyle(
          color: palette.ink,
          fontSize: 14,
          fontWeight: FontWeight.w700,
          height: 1.4,
        ),
        labelMedium: TextStyle(
          color: palette.muted,
          fontSize: 12,
          fontWeight: FontWeight.w700,
          height: 1.4,
        ),
      )
      // copyWith replaces whole styles, so re-apply the family to every entry.
      .apply(fontFamily: 'Tajawal');

  return base.copyWith(
    extensions: <ThemeExtension<dynamic>>[palette],
    textTheme: textTheme,
    canvasColor: palette.surface,
    cardColor: palette.surface,
    dividerColor: palette.line,
    iconTheme: IconThemeData(color: palette.muted),
    splashColor: (dark ? appTealLight : appTeal).withValues(alpha: 0.08),
    highlightColor: (dark ? appTealLight : appTeal).withValues(alpha: 0.05),
    textSelectionTheme: TextSelectionThemeData(
      cursorColor: dark ? appTealLight : appTeal,
      selectionColor: (dark ? appTealLight : appTeal).withValues(alpha: 0.3),
      selectionHandleColor: dark ? appTealLight : appTeal,
    ),
    appBarTheme: const AppBarTheme(
      backgroundColor: appTeal,
      foregroundColor: Colors.white,
      elevation: 0,
      scrolledUnderElevation: 0,
      surfaceTintColor: Colors.transparent,
      centerTitle: false,
      titleSpacing: 0,
      toolbarHeight: 64,
      systemOverlayStyle: SystemUiOverlayStyle.light,
      titleTextStyle: TextStyle(
        fontFamily: 'Tajawal',
        color: Colors.white,
        fontSize: 18,
        fontWeight: FontWeight.w800,
      ),
      iconTheme: IconThemeData(color: Colors.white),
    ),
    dropdownMenuTheme: DropdownMenuThemeData(
      textStyle: TextStyle(
        fontFamily: 'Tajawal',
        color: palette.ink,
        fontSize: 13.5,
        fontWeight: FontWeight.w600,
      ),
      menuStyle: MenuStyle(
        backgroundColor: WidgetStatePropertyAll(palette.surface),
        surfaceTintColor: const WidgetStatePropertyAll(Colors.transparent),
        shape: WidgetStatePropertyAll(
          RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        ),
      ),
    ),
    popupMenuTheme: PopupMenuThemeData(
      color: palette.surface,
      surfaceTintColor: Colors.transparent,
      elevation: 8,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      textStyle: TextStyle(
        fontFamily: 'Tajawal',
        color: palette.ink,
        fontSize: 13.5,
        fontWeight: FontWeight.w600,
      ),
    ),
    listTileTheme: ListTileThemeData(
      textColor: palette.ink,
      iconColor: palette.muted,
      titleTextStyle: TextStyle(
        fontFamily: 'Tajawal',
        color: palette.ink,
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: palette.inputFill,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
      hintStyle: TextStyle(
        fontFamily: 'Tajawal',
        color: palette.faint,
        fontSize: 13,
        fontWeight: FontWeight.w500,
      ),
      labelStyle: TextStyle(
        fontFamily: 'Tajawal',
        color: palette.muted,
        fontSize: 13,
        fontWeight: FontWeight.w600,
      ),
      floatingLabelStyle: TextStyle(
        fontFamily: 'Tajawal',
        color: palette.accent,
        fontSize: 12.5,
        fontWeight: FontWeight.w700,
      ),
      prefixIconColor: palette.muted,
      suffixIconColor: palette.muted,
      helperStyle: TextStyle(
        fontFamily: 'Tajawal',
        color: palette.faint,
        fontSize: 10.5,
      ),
      errorStyle: TextStyle(
        fontFamily: 'Tajawal',
        color: dark ? const Color(0xFFFF8A80) : appDanger,
        fontSize: 11,
      ),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: BorderSide(color: palette.line),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: BorderSide(color: palette.line),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: BorderSide(
          color: dark ? appTealLight : appTeal,
          width: 1.6,
        ),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: BorderSide(
          color: dark ? const Color(0xFFFF8A80) : appDanger,
        ),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: BorderSide(
          color: dark ? const Color(0xFFFF8A80) : appDanger,
          width: 1.6,
        ),
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: dark ? appTealLight : appTeal,
        foregroundColor: dark ? const Color(0xFF04231F) : Colors.white,
        disabledBackgroundColor: palette.mintStrong,
        disabledForegroundColor: palette.faint,
        minimumSize: const Size.fromHeight(54),
        padding: const EdgeInsets.symmetric(horizontal: 20),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        textStyle: const TextStyle(
          fontFamily: 'Tajawal',
          fontSize: 15,
          fontWeight: FontWeight.w800,
        ),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: palette.accent,
        side: BorderSide(color: palette.mintBorder),
        minimumSize: const Size.fromHeight(50),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        textStyle: const TextStyle(
          fontFamily: 'Tajawal',
          fontSize: 14,
          fontWeight: FontWeight.w700,
        ),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(
        foregroundColor: palette.accent,
        textStyle: const TextStyle(
          fontFamily: 'Tajawal',
          fontSize: 13,
          fontWeight: FontWeight.w700,
        ),
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      height: 70,
      backgroundColor: palette.surface,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      indicatorColor: palette.mint,
      labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
      labelTextStyle: WidgetStateProperty.resolveWith((states) {
        final selected = states.contains(WidgetState.selected);
        return TextStyle(
          fontFamily: 'Tajawal',
          fontSize: 11.5,
          fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
          color: selected ? palette.accent : palette.faint,
        );
      }),
      iconTheme: WidgetStateProperty.resolveWith((states) {
        final selected = states.contains(WidgetState.selected);
        return IconThemeData(
          size: 24,
          color: selected ? palette.accent : palette.faint,
        );
      }),
    ),
    snackBarTheme: SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      backgroundColor: dark ? const Color(0xFF1D302B) : const Color(0xFF102A26),
      elevation: 6,
      insetPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      contentTextStyle: const TextStyle(
        fontFamily: 'Tajawal',
        color: Colors.white,
        fontSize: 13,
        fontWeight: FontWeight.w600,
      ),
    ),
    dividerTheme: DividerThemeData(space: 1, color: palette.line),
    progressIndicatorTheme: ProgressIndicatorThemeData(
      color: dark ? appTealLight : appTeal,
      linearTrackColor: palette.mintStrong,
      circularTrackColor: palette.mintStrong,
    ),
    dialogTheme: DialogThemeData(
      backgroundColor: palette.surface,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(26)),
      titleTextStyle: TextStyle(
        fontFamily: 'Tajawal',
        color: palette.ink,
        fontSize: 17,
        fontWeight: FontWeight.w800,
      ),
      contentTextStyle: TextStyle(
        fontFamily: 'Tajawal',
        color: palette.muted,
        fontSize: 13,
        height: 1.7,
      ),
    ),
    bottomSheetTheme: BottomSheetThemeData(
      backgroundColor: palette.surface,
      surfaceTintColor: Colors.transparent,
    ),
    tooltipTheme: TooltipThemeData(
      decoration: BoxDecoration(
        color: dark ? const Color(0xFF1D302B) : const Color(0xFF102A26),
        borderRadius: BorderRadius.circular(10),
      ),
      textStyle: const TextStyle(
        fontFamily: 'Tajawal',
        color: Colors.white,
        fontSize: 12,
      ),
    ),
  );
}
