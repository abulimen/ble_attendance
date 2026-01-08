import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppTextStyles {
  // Display Styles (Largest)
  static final TextStyle displayLarge = GoogleFonts.roboto(
    fontSize: 36,
    fontWeight: FontWeight.w900,
    letterSpacing: -0.5,
    height: 1.1,
  );
  
  static final TextStyle displayMedium = GoogleFonts.roboto(
    fontSize: 32,
    fontWeight: FontWeight.bold,
    letterSpacing: -0.25,
    height: 1.2,
  );
  
  static final TextStyle displaySmall = GoogleFonts.roboto(
    fontSize: 28,
    fontWeight: FontWeight.w600,
    letterSpacing: 0,
    height: 1.2,
  );

  // Headline Styles
  static final TextStyle headlineLarge = GoogleFonts.roboto(
    fontSize: 24,
    fontWeight: FontWeight.w600,
    letterSpacing: -0.25,
    height: 1.3,
  );
  
  static final TextStyle headlineMedium = GoogleFonts.roboto(
    fontSize: 20,
    fontWeight: FontWeight.w600,
    letterSpacing: 0,
    height: 1.3,
  );
  
  static final TextStyle headlineSmall = GoogleFonts.roboto(
    fontSize: 18,
    fontWeight: FontWeight.w500,
    letterSpacing: 0.15,
    height: 1.4,
  );

  // Title Styles
  static final TextStyle titleLarge = GoogleFonts.roboto(
    fontSize: 16,
    fontWeight: FontWeight.w600,
    letterSpacing: 0.15,
    height: 1.5,
  );
  
  static final TextStyle titleMedium = GoogleFonts.roboto(
    fontSize: 14,
    fontWeight: FontWeight.w500,
    letterSpacing: 0.1,
    height: 1.4,
  );
  
  static final TextStyle titleSmall = GoogleFonts.roboto(
    fontSize: 12,
    fontWeight: FontWeight.w500,
    letterSpacing: 0.1,
    height: 1.3,
  );

  // Body Styles
  static final TextStyle bodyLarge = GoogleFonts.roboto(
    fontSize: 16,
    fontWeight: FontWeight.normal,
    letterSpacing: 0.5,
    height: 1.5,
  );
  
  static final TextStyle bodyMedium = GoogleFonts.roboto(
    fontSize: 14,
    fontWeight: FontWeight.normal,
    letterSpacing: 0.25,
    height: 1.4,
  );
  
  static final TextStyle bodySmall = GoogleFonts.roboto(
    fontSize: 12,
    fontWeight: FontWeight.normal,
    letterSpacing: 0.4,
    height: 1.3,
  );

  // Label Styles
  static final TextStyle labelLarge = GoogleFonts.roboto(
    fontSize: 14,
    fontWeight: FontWeight.w600,
    letterSpacing: 1.25,
    height: 1.4,
  );
  
  static final TextStyle labelMedium = GoogleFonts.roboto(
    fontSize: 12,
    fontWeight: FontWeight.w500,
    letterSpacing: 0.5,
    height: 1.3,
  );
  
  static final TextStyle labelSmall = GoogleFonts.roboto(
    fontSize: 10,
    fontWeight: FontWeight.w500,
    letterSpacing: 1.5,
    height: 1.2,
  );

  // Special Purpose Styles
  static final TextStyle button = GoogleFonts.roboto(
    fontSize: 16,
    fontWeight: FontWeight.w600,
    letterSpacing: 1.25,
    height: 1.2,
  );
  
  static final TextStyle caption = GoogleFonts.roboto(
    fontSize: 11,
    fontWeight: FontWeight.normal,
    letterSpacing: 0.4,
    height: 1.3,
  );
  
  static final TextStyle overline = GoogleFonts.roboto(
    fontSize: 10,
    fontWeight: FontWeight.w500,
    letterSpacing: 1.5,
    height: 1.6,
  );
  
  static final TextStyle monospace = GoogleFonts.robotoMono(
    fontSize: 14,
    fontWeight: FontWeight.normal,
    letterSpacing: 0,
    height: 1.4,
  );

  // Backward Compatibility
  static final TextStyle headline1 = displayMedium;
  static final TextStyle headline2 = headlineLarge;
  static final TextStyle bodyText1 = bodyLarge;
}
