import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:provider/provider.dart';
import 'package:student_app/providers/auth_provider.dart';
import 'package:student_app/theme/app_theme.dart';
import 'package:student_app/widgets/animated_button.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();

  bool _obscurePassword = true;

  @override
  void dispose() {
    _usernameController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    final success = await authProvider.login(
      _usernameController.text.trim(),
      _passwordController.text.trim(),
    );

    if (!success && mounted) {
      _showErrorSnackBar(
          authProvider.errorMessage ?? 'Login failed. Please try again.');
    }
  }

  void _showErrorSnackBar(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(
          children: [
            const Icon(Icons.error_outline, color: Colors.white, size: 20),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                message,
                style: const TextStyle(color: Colors.white),
              ),
            ),
          ],
        ),
        backgroundColor: TrueSignTheme.error,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        margin: const EdgeInsets.all(16),
        duration: const Duration(seconds: 4),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);

    return Scaffold(
      backgroundColor: TrueSignTheme.background,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Container(
              constraints: const BoxConstraints(maxWidth: 440),
              child: Form(
                key: _formKey,
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _buildHeader()
                        .animate()
                        .fadeIn(duration: 600.ms)
                        .slideY(begin: -0.2, end: 0, duration: 600.ms),
                    const SizedBox(height: 48),
                    _buildUsernameField()
                        .animate()
                        .fadeIn(delay: 200.ms, duration: 500.ms)
                        .slideX(begin: -0.1, end: 0),
                    const SizedBox(height: 20),
                    _buildPasswordField()
                        .animate()
                        .fadeIn(delay: 300.ms, duration: 500.ms)
                        .slideX(begin: -0.1, end: 0),
                    const SizedBox(height: 32),
                    AnimatedButton(
                      text: 'Sign In',
                      onPressed: _submit,
                      isLoading: authProvider.isLoading,
                    )
                        .animate()
                        .fadeIn(delay: 400.ms, duration: 500.ms)
                        .slideY(begin: 0.2, end: 0),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Column(
      children: [
        Container(
          width: 100,
          height: 100,
          decoration: BoxDecoration(
            color: TrueSignTheme.primaryBlue.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(24),
          ),
          child: const Icon(
            Icons.fingerprint,
            size: 56,
            color: TrueSignTheme.primaryBlue,
          ),
        ),
        const SizedBox(height: 24),
        const Text(
          'TrueSign',
          style: TextStyle(
            fontSize: 32,
            fontWeight: FontWeight.bold,
            color: TrueSignTheme.textPrimary,
            letterSpacing: -0.5,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          'Sign in to continue',
          style: TextStyle(
            fontSize: 16,
            color: TrueSignTheme.textSecondary,
          ),
        ),
      ],
    );
  }

  Widget _buildUsernameField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Student ID',
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: TrueSignTheme.textPrimary,
          ),
        ),
        const SizedBox(height: 8),
        TextFormField(
          controller: _usernameController,
          keyboardType: TextInputType.text,
          textInputAction: TextInputAction.next,
          decoration: const InputDecoration(
            hintText: 'Enter your Matric No. or Application ID',
            prefixIcon: Icon(Icons.person_outline, size: 22),
          ),
          validator: (value) {
            if (value == null || value.trim().isEmpty) {
              return 'Please enter your Student ID';
            }
            if (value.trim().length < 3) {
              return 'ID must be at least 3 characters';
            }
            return null;
          },
        ),
      ],
    );
  }

  Widget _buildPasswordField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Password',
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: TrueSignTheme.textPrimary,
          ),
        ),
        const SizedBox(height: 8),
        TextFormField(
          controller: _passwordController,
          obscureText: _obscurePassword,
          textInputAction: TextInputAction.done,
          onFieldSubmitted: (_) => _submit(),
          decoration: InputDecoration(
            hintText: 'Enter your password',
            prefixIcon: const Icon(Icons.lock_outline, size: 22),
            suffixIcon: IconButton(
              icon: Icon(
                _obscurePassword ? Icons.visibility_off : Icons.visibility,
                size: 22,
              ),
              onPressed: () {
                setState(() {
                  _obscurePassword = !_obscurePassword;
                });
              },
            ),
          ),
          validator: (value) {
            if (value == null || value.isEmpty) {
              return 'Please enter your password';
            }
            if (value.length < 6) {
              return 'Password must be at least 6 characters';
            }
            return null;
          },
        ),
      ],
    );
  }
}
