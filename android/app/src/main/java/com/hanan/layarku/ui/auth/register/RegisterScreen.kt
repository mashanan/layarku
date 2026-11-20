package com.hanan.layarku.ui.auth.register

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
import com.hanan.layarku.domain.validation.AuthField
import com.hanan.layarku.ui.components.AuthHeader
import com.hanan.layarku.ui.components.FormTextField
import com.hanan.layarku.ui.components.LoadingButton
import com.hanan.layarku.ui.components.PasswordField
import com.hanan.layarku.ui.theme.LayarkuTheme

@Composable
fun RegisterRoute(
    onRegistered: () -> Unit,
    onNavigateToLogin: () -> Unit,
    snackbarHostState: SnackbarHostState,
    viewModel: RegisterViewModel = hiltViewModel(),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()
    val context = LocalContext.current

    LaunchedEffect(viewModel) {
        viewModel.events.collect { event ->
            when (event) {
                RegisterEvent.Registered -> onRegistered()
                is RegisterEvent.ShowMessage -> snackbarHostState.showSnackbar(context.getString(event.message))
            }
        }
    }

    RegisterScreen(
        uiState = uiState,
        snackbarHostState = snackbarHostState,
        onNameChange = viewModel::onNameChange,
        onEmailChange = viewModel::onEmailChange,
        onPhoneChange = viewModel::onPhoneChange,
        onPasswordChange = viewModel::onPasswordChange,
        onTogglePasswordVisible = viewModel::onTogglePasswordVisible,
        onSubmit = viewModel::onSubmit,
        onNavigateToLogin = onNavigateToLogin,
    )
}

@Composable
fun RegisterScreen(
    uiState: RegisterUiState,
    snackbarHostState: SnackbarHostState,
    onNameChange: (String) -> Unit,
    onEmailChange: (String) -> Unit,
    onPhoneChange: (String) -> Unit,
    onPasswordChange: (String) -> Unit,
    onTogglePasswordVisible: () -> Unit,
    onSubmit: () -> Unit,
    onNavigateToLogin: () -> Unit,
) {
    val enabled = !uiState.isSubmitting
    Scaffold(snackbarHost = { SnackbarHost(snackbarHostState) }) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .imePadding()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 24.dp, vertical = 32.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            AuthHeader(title = R.string.register_title, subtitle = R.string.register_subtitle)
            Spacer(Modifier.height(12.dp))

            FormTextField(
                value = uiState.name, onValueChange = onNameChange, label = R.string.field_name,
                error = uiState.fieldErrors[AuthField.Name], keyboardType = KeyboardType.Text,
                imeAction = ImeAction.Next, enabled = enabled,
            )
            FormTextField(
                value = uiState.email, onValueChange = onEmailChange, label = R.string.field_email,
                error = uiState.fieldErrors[AuthField.Email], keyboardType = KeyboardType.Email,
                imeAction = ImeAction.Next, enabled = enabled,
            )
            FormTextField(
                value = uiState.phone, onValueChange = onPhoneChange, label = R.string.field_phone,
                error = uiState.fieldErrors[AuthField.Phone], keyboardType = KeyboardType.Phone,
                imeAction = ImeAction.Next, enabled = enabled,
            )
            PasswordField(
                value = uiState.password, onValueChange = onPasswordChange,
                visible = uiState.passwordVisible, onToggleVisible = onTogglePasswordVisible,
                error = uiState.fieldErrors[AuthField.Password], supportingHint = R.string.register_password_hint,
                imeAction = ImeAction.Done, enabled = enabled,
            )
            Spacer(Modifier.height(8.dp))
            LoadingButton(text = R.string.register_button, loading = uiState.isSubmitting, onClick = onSubmit)

            Row(
                modifier = Modifier.align(Alignment.CenterHorizontally),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    stringResource(R.string.register_have_account),
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                TextButton(onClick = onNavigateToLogin) { Text(stringResource(R.string.register_go_to_login)) }
            }
        }
    }
}

@Preview(showBackground = true)
@Composable
private fun RegisterScreenPreview() {
    LayarkuTheme {
        RegisterScreen(
            uiState = RegisterUiState(
                name = "Sari",
                email = "sari@",
                fieldErrors = mapOf(AuthField.Email to R.string.error_email_invalid),
            ),
            snackbarHostState = remember { SnackbarHostState() },
            onNameChange = {}, onEmailChange = {}, onPhoneChange = {}, onPasswordChange = {},
            onTogglePasswordVisible = {}, onSubmit = {}, onNavigateToLogin = {},
        )
    }
}
