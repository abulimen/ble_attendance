# Keep WebView classes to prevent issues in release builds
-keep class * extends android.webkit.WebView { *; }
-keepclassmembers class * extends android.webkit.WebView {
    public *;
}

# Keep WebViewClient and WebChromeClient
-keep class * extends android.webkit.WebViewClient { *; }
-keep class * extends android.webkit.WebChromeClient { *; }

# Keep JavaScript interfaces
-keepclassmembers class * {
    @android.webkit.JavascriptInterface <methods>;
}

# Keep WebView package
-keeppackagenames android.webkit.**

# Keep Flutter WebView plugin classes
-keep class io.flutter.plugins.webviewflutter.** { *; }
