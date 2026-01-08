import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:student_app/screens/attendance_screen.dart';
import 'package:student_app/screens/student_portal_webview.dart';
import 'package:student_app/theme/app_theme.dart';

class MainNavigationScreen extends StatefulWidget {
  const MainNavigationScreen({super.key});

  @override
  State<MainNavigationScreen> createState() => _MainNavigationScreenState();
}

class _MainNavigationScreenState extends State<MainNavigationScreen> {
  int _selectedIndex = 0;
  late PageController _pageController;

  final List<_NavItem> _items = const [
    _NavItem(
      icon: Icons.fingerprint,
      label: 'Attendance',
      screen: AttendanceScreen(),
    ),
    _NavItem(
      icon: Icons.school_outlined,
      label: 'Portal',
      screen: StudentPortalWebView(),
    ),
  ];

  @override
  void initState() {
    super.initState();
    _pageController = PageController(initialPage: _selectedIndex);
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  void _onItemTapped(int index) {
    if (_selectedIndex == index) return;

    HapticFeedback.selectionClick();
    setState(() => _selectedIndex = index);
    _pageController.animateToPage(
      index,
      duration: const Duration(milliseconds: 300),
      curve: Curves.easeInOutCubic,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: PageView(
        controller: _pageController,
        physics: const NeverScrollableScrollPhysics(),
        children: _items.map((item) => item.screen).toList(),
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: TrueSignTheme.shadowMD,
        ),
        child: SafeArea(
          child: Container(
            height: 80,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: List.generate(_items.length, (index) {
                final isSelected = index == _selectedIndex;
                return Expanded(
                  child: GestureDetector(
                    onTap: () => _onItemTapped(index),
                    behavior: HitTestBehavior.opaque,
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 200),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 6,
                      ),
                      decoration: BoxDecoration(
                        color: isSelected
                            ? TrueSignTheme.primaryBlue.withValues(alpha: 0.1)
                            : Colors.transparent,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            _items[index].icon,
                            size: 24,
                            color: isSelected
                                ? TrueSignTheme.primaryBlue
                                : TrueSignTheme.textSecondary,
                          ),
                          const SizedBox(height: 4),
                          Text(
                            _items[index].label,
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: isSelected
                                  ? FontWeight.w600
                                  : FontWeight.w400,
                              color: isSelected
                                  ? TrueSignTheme.primaryBlue
                                  : TrueSignTheme.textSecondary,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              }),
            ),
          ),
        ),
      ),
    );
  }
}

class _NavItem {
  final IconData icon;
  final String label;
  final Widget screen;

  const _NavItem({
    required this.icon,
    required this.label,
    required this.screen,
  });
}
