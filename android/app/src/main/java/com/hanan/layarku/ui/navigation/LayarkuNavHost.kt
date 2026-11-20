package com.hanan.layarku.ui.navigation

import androidx.compose.material3.SnackbarHostState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.navigation.NavDestination.Companion.hasRoute
import androidx.navigation.NavHostController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import com.hanan.layarku.ui.auth.login.LoginRoute
import com.hanan.layarku.ui.auth.register.RegisterRoute
import com.hanan.layarku.ui.home.HomeRoute

/**
 * Who triggers which move, so nothing navigates twice:
 *  - signing in: the login/register screen's success event -> Home;
 *  - signing out or an expired token: the session becomes null -> Login.
 * Both clear the back stack, so "back" never returns to a screen of the other side.
 *
 * @param isSignedIn current session state; also picks the start destination at launch.
 */
@Composable
fun LayarkuNavHost(
    isSignedIn: Boolean,
    snackbarHostState: SnackbarHostState,
    navController: NavHostController = rememberNavController(),
) {
    LaunchedEffect(isSignedIn) {
        // Null before the NavHost's first composition; the start destination covers that case.
        val destination = navController.currentDestination ?: return@LaunchedEffect
        val onAuthScreen = destination.hasRoute<LoginRoute>() || destination.hasRoute<RegisterRoute>()
        if (!isSignedIn && !onAuthScreen) navController.resetTo(LoginRoute)
    }

    // Decided once. If it followed isSignedIn, signing in would swap the start
    // destination, rebuild the graph and create the Home screen (and its
    // ViewModel) twice.
    val startDestination: Any = remember { if (isSignedIn) HomeRoute else LoginRoute }

    NavHost(navController = navController, startDestination = startDestination) {
        composable<LoginRoute> {
            LoginRoute(
                onLoggedIn = { navController.resetTo(HomeRoute) },
                onNavigateToRegister = { navController.navigate(RegisterRoute) { launchSingleTop = true } },
                snackbarHostState = snackbarHostState,
            )
        }
        composable<RegisterRoute> {
            RegisterRoute(
                onRegistered = { navController.resetTo(HomeRoute) },
                onNavigateToLogin = { navController.popBackStack() },
                snackbarHostState = snackbarHostState,
            )
        }
        composable<HomeRoute> {
            HomeRoute()
        }
    }
}

/** Navigate to [route] and drop everything behind it. */
private fun <T : Any> NavHostController.resetTo(route: T) {
    navigate(route) {
        popUpTo(graph.id) { inclusive = true }
        launchSingleTop = true
    }
}
