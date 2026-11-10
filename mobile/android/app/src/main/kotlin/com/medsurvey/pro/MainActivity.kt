package com.medsurvey.pro

import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.view.WindowManager
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    private val securityChannelName = "com.medsurvey.pro/screen_security"
    private val updaterChannelName = "com.medsurvey.pro/updater"

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // Secure by default: the survey form shows patient names, phone
        // numbers and free-text answers, so the content must never reach the
        // gallery or the recent-apps preview. The Dart side clears the flag
        // only when the facility opts out in the account tab.
        window.addFlags(WindowManager.LayoutParams.FLAG_SECURE)
    }

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, securityChannelName)
            .setMethodCallHandler { call, result ->
                when (call.method) {
                    "setSecure" -> {
                        val enabled = call.argument<Boolean>("enabled") ?: true
                        if (enabled) {
                            window.addFlags(WindowManager.LayoutParams.FLAG_SECURE)
                        } else {
                            window.clearFlags(WindowManager.LayoutParams.FLAG_SECURE)
                        }
                        result.success(null)
                    }
                    else -> result.notImplemented()
                }
            }

        // Opens the release download page in the browser. Installing an APK
        // stays Android's job: this app never requests the "install unknown
        // apps" permission, and the phone still confirms before installing.
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, updaterChannelName)
            .setMethodCallHandler { call, result ->
                when (call.method) {
                    "openUrl" -> {
                        val url = call.argument<String>("url")?.trim().orEmpty()
                        if (url.isEmpty()) {
                            result.success(false)
                            return@setMethodCallHandler
                        }

                        try {
                            val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url))
                            startActivity(intent)
                            result.success(true)
                        } catch (error: Exception) {
                            // No browser installed or the URL was blocked.
                            result.success(false)
                        }
                    }
                    else -> result.notImplemented()
                }
            }
    }
}
