package com.hanan.layarku.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

private val LightColors = lightColorScheme(
    primary = LayarNight,
    onPrimary = Color.White,
    primaryContainer = LayarNightSurfaceHigh,
    onPrimaryContainer = Color.White,
    secondary = LayarAmberDeep,
    onSecondary = Color.White,
    secondaryContainer = LayarAmberContainer,
    onSecondaryContainer = LayarInk,
    tertiary = LayarAmber,
    error = LayarRed,
    background = LayarPaper,
    onBackground = LayarInk,
    surface = LayarPaper,
    onSurface = LayarInk,
    onSurfaceVariant = LayarMuted,
    outline = LayarOutline,
)

private val DarkColors = darkColorScheme(
    primary = LayarAmber,
    onPrimary = LayarNight,
    primaryContainer = LayarAmberDeep,
    onPrimaryContainer = Color.White,
    secondary = LayarAmber,
    onSecondary = LayarNight,
    secondaryContainer = LayarNightSurfaceHigh,
    onSecondaryContainer = Color.White,
    tertiary = LayarAmberContainer,
    error = Color(0xFFFFB3B8),
    background = LayarNight,
    onBackground = Color(0xFFE7E6EE),
    surface = LayarNight,
    onSurface = Color(0xFFE7E6EE),
    surfaceVariant = LayarNightSurface,
    onSurfaceVariant = Color(0xFFB9BAC9),
    outline = Color(0xFF4A5277),
)

/**
 * Dynamic (wallpaper) colour is intentionally off: the cinema brand should look
 * the same on every phone.
 */
@Composable
fun LayarkuTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit,
) {
    MaterialTheme(
        colorScheme = if (darkTheme) DarkColors else LightColors,
        typography = Typography,
        content = content,
    )
}
