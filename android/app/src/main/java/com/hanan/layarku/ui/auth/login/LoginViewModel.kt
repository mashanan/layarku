package com.hanan.layarku.ui.auth.login

import androidx.annotation.StringRes
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.hanan.layarku.domain.model.AppResult
import com.hanan.layarku.domain.model.DataError
import com.hanan.layarku.domain.usecase.LoginUseCase
import com.hanan.layarku.domain.validation.AuthField
import com.hanan.layarku.domain.validation.AuthValidator
import com.hanan.layarku.ui.util.messageRes
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.channels.Channel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.receiveAsFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class LoginUiState(
    val email: String = "",
    val password: String = "",
    val passwordVisible: Boolean = false,
    @StringRes val emailError: Int? = null,
    @StringRes val passwordError: Int? = null,
    val isSubmitting: Boolean = false,
)

/** One-off things that must happen exactly once, not on every recomposition. */
sealed interface LoginEvent {
    data object LoggedIn : LoginEvent
    data class ShowMessage(@StringRes val message: Int) : LoginEvent
}

@HiltViewModel
class LoginViewModel @Inject constructor(
    private val login: LoginUseCase,
) : ViewModel() {

    private val _uiState = MutableStateFlow(LoginUiState())
    val uiState: StateFlow<LoginUiState> = _uiState.asStateFlow()

    // A Channel (not a SharedFlow) so an event sent while the screen is in the
    // background is delivered when it comes back, and only once.
    private val _events = Channel<LoginEvent>(Channel.BUFFERED)
    val events = _events.receiveAsFlow()

    fun onEmailChange(value: String) = _uiState.update { it.copy(email = value, emailError = null) }

    fun onPasswordChange(value: String) = _uiState.update { it.copy(password = value, passwordError = null) }

    fun onTogglePasswordVisible() = _uiState.update { it.copy(passwordVisible = !it.passwordVisible) }

    fun onSubmit() {
        val state = _uiState.value
        if (state.isSubmitting) return

        val errors = AuthValidator.validateLogin(state.email, state.password)
        if (errors.isNotEmpty()) {
            _uiState.update {
                it.copy(
                    emailError = errors[AuthField.Email]?.messageRes(),
                    passwordError = errors[AuthField.Password]?.messageRes(),
                )
            }
            return
        }

        _uiState.update { it.copy(isSubmitting = true) }
        viewModelScope.launch {
            val result = login(state.email, state.password)
            _uiState.update { it.copy(isSubmitting = false) }
            when (result) {
                is AppResult.Success -> _events.send(LoginEvent.LoggedIn)
                is AppResult.Failure -> handleFailure(result.error)
            }
        }
    }

    private suspend fun handleFailure(error: DataError) {
        if (error == DataError.InvalidCredentials) {
            // Keep the email, clear the password: the usual pattern on login forms.
            _uiState.update { it.copy(password = "") }
        }
        _events.send(LoginEvent.ShowMessage(error.messageRes()))
    }
}
