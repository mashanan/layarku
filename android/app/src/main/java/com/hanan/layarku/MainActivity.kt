package com.hanan.layarku

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.viewModels
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.hanan.layarku.ui.AppViewModel
import com.hanan.layarku.ui.SessionState
import com.hanan.layarku.ui.navigation.LayarkuNavHost
import com.hanan.layarku.ui.theme.LayarkuTheme
import dagger.hilt.android.AndroidEntryPoint

@AndroidEntryPoint
class MainActivity : ComponentActivity() {

    private val appViewModel: AppViewModel by viewModels()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            LayarkuTheme {
                Surface(modifier = Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
                    LayarkuApp(appViewModel)
                }
            }
        }
    }
}

@Composable
private fun LayarkuApp(appViewModel: AppViewModel) {
    val sessionState by appViewModel.sessionState.collectAsStateWithLifecycle()
    // One snackbar host for the whole app, so a message survives navigation
    // (e.g. "session ended" shown on the login screen after a 401).
    val snackbarHostState = remember { SnackbarHostState() }
    val context = LocalContext.current

    LaunchedEffect(appViewModel) {
        appViewModel.sessionExpired.collect {
            snackbarHostState.showSnackbar(context.getString(R.string.error_session_expired))
        }
    }

    when (sessionState) {
        // DataStore read takes a few milliseconds; a blank frame beats flashing the login screen.
        SessionState.Loading -> Box(Modifier.fillMaxSize())
        SessionState.SignedIn, SessionState.SignedOut -> LayarkuNavHost(
            isSignedIn = sessionState == SessionState.SignedIn,
            snackbarHostState = snackbarHostState,
        )
    }
}
