package com.hanan.layarku.ui.auth.login

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.hanan.layarku.R
import com.hanan.layarku.ui.components.AuthHeader
import com.hanan.layarku.ui.components.FormTextField
import com.hanan.layarku.ui.components.LoadingButton
import com.hanan.layarku.ui.components.PasswordField
import com.hanan.layarku.ui.theme.LayarkuTheme

/** Connects [LoginScreen] to its ViewModel and handles one-off events. */
@Composable
fun LoginRoute(
    onLoggedIn: () -> Unit,
    onNavigateToRegister: () -> Unit,
    snackbarHostState: SnackbarHostState,
    viewModel: LoginViewModel = hiltViewModel(),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()
    val context = LocalContext.current

    LaunchedEffect(viewModel) {
        viewModel.events.collect { event ->
            when (event) {
                LoginEvent.LoggedIn -> onLoggedIn()
                is LoginEvent.ShowMessage -> snackbarHostState.showSnackbar(context.getString(event.message))
            }
        }
    }

    LoginScreen(
        uiState = uiState,
        snackbarHostState = snackbarHostState,
        onEmailChange = viewModel::onEmailChange,
        onPasswordChange = viewModel::onPasswordChange,
        onTogglePasswordVisible = viewModel::onTogglePasswordVisible,
        onSubmit = viewModel::onSubmit,
        onNavigateToRegister = onNavigateToRegister,
    )
}

/** Stateless: everything comes in as parameters, so it can be previewed and tested. */
@Composable
fun LoginScreen(
    uiState: LoginUiState,
    snackbarHostState: SnackbarHostState,
    onEmailChange: (String) -> Unit,
    onPasswordChange: (String) -> Unit,
    onTogglePasswordVisible: () -> Unit,
    onSubmit: () -> Unit,
    onNavigateToRegister: () -> Unit,
) {
    Scaffold(snackbarHost = { SnackbarHost(snackbarHostState) }) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .imePadding()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 24.dp, vertical = 32.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Spacer(Modifier.height(24.dp))
            AuthHeader(title = R.string.login_title, subtitle = R.string.login_subtitle)
            Spacer(Modifier.height(16.dp))

            FormTextField(
                value = uiState.email,
                onValueChange = onEmailChange,
                label = R.string.field_email,
                error = uiState.emailError,
                keyboardType = KeyboardType.Email,
                imeAction = ImeAction.Next,
                enabled = !uiState.isSubmitting,
            )
            PasswordField(
                value = uiState.password,
                onValueChange = onPasswordChange,
                visible = uiState.passwordVisible,
                onToggleVisible = onTogglePasswordVisible,
                error = uiState.passwordError,
                imeAction = ImeAction.Done,
                enabled = !uiState.isSubmitting,
            )
            Spacer(Modifier.height(8.dp))
            LoadingButton(text = R.string.login_button, loading = uiState.isSubmitting, onClick = onSubmit)

            Row(
                modifier = Modifier.align(Alignment.CenterHorizontally),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    stringResource(R.string.login_no_account),
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                TextButton(onClick = onNavigateToRegister) { Text(stringResource(R.string.login_go_to_register)) }
            }
        }
    }
}

@Preview(showBackground = true)
@Composable
private fun LoginScreenPreview() {
    LayarkuTheme {
        LoginScreen(
            uiState = LoginUiState(email = "customer@layarku.test", passwordError = R.string.error_required),
            snackbarHostState = remember { SnackbarHostState() },
            onEmailChange = {}, onPasswordChange = {}, onTogglePasswordVisible = {}, onSubmit = {}, onNavigateToRegister = {},
        )
    }
}
