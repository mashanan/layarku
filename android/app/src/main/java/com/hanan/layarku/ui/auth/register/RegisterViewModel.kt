package com.hanan.layarku.ui.auth.register

import androidx.annotation.StringRes
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.hanan.layarku.R
import com.hanan.layarku.domain.model.AppResult
import com.hanan.layarku.domain.model.DataError
import com.hanan.layarku.domain.model.RegistrationForm
import com.hanan.layarku.domain.usecase.RegisterUseCase
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

data class RegisterUiState(
    val name: String = "",
    val email: String = "",
    val phone: String = "",
    val password: String = "",
    val passwordVisible: Boolean = false,
    /** Field -> string resource id of its error message. */
    val fieldErrors: Map<AuthField, Int> = emptyMap(),
    val isSubmitting: Boolean = false,
)

sealed interface RegisterEvent {
    data object Registered : RegisterEvent
    data class ShowMessage(@StringRes val message: Int) : RegisterEvent
}

@HiltViewModel
class RegisterViewModel @Inject constructor(
    private val register: RegisterUseCase,
) : ViewModel() {

    private val _uiState = MutableStateFlow(RegisterUiState())
    val uiState: StateFlow<RegisterUiState> = _uiState.asStateFlow()

    private val _events = Channel<RegisterEvent>(Channel.BUFFERED)
    val events = _events.receiveAsFlow()

    fun onNameChange(value: String) = edit(AuthField.Name) { copy(name = value) }
    fun onEmailChange(value: String) = edit(AuthField.Email) { copy(email = value) }
    fun onPhoneChange(value: String) = edit(AuthField.Phone) { copy(phone = value) }
    fun onPasswordChange(value: String) = edit(AuthField.Password) { copy(password = value) }
    fun onTogglePasswordVisible() = _uiState.update { it.copy(passwordVisible = !it.passwordVisible) }

    fun onSubmit() {
        val state = _uiState.value
        if (state.isSubmitting) return

        val form = RegistrationForm(
            name = state.name,
            email = state.email,
            phone = state.phone.ifBlank { null },
            password = state.password,
        )
        val errors = AuthValidator.validateRegistration(form)
        if (errors.isNotEmpty()) {
            _uiState.update { it.copy(fieldErrors = errors.mapValues { (_, e) -> e.messageRes() }) }
            return
        }

        _uiState.update { it.copy(isSubmitting = true) }
        viewModelScope.launch {
            val result = register(form)
            _uiState.update { it.copy(isSubmitting = false) }
            when (result) {
                is AppResult.Success -> _events.send(RegisterEvent.Registered)
                is AppResult.Failure -> handleFailure(result.error)
            }
        }
    }

    private suspend fun handleFailure(error: DataError) {
        if (error is DataError.Validation && error.fields.isNotEmpty()) {
            // The client already checked formats, so a server rejection is almost
            // always "email already registered". We map field names to our own
            // translated text instead of showing the server's English messages.
            _uiState.update { it.copy(fieldErrors = serverFieldErrors(error.fields.keys)) }
        } else {
            _events.send(RegisterEvent.ShowMessage(error.messageRes()))
        }
    }

    private fun serverFieldErrors(fields: Set<String>): Map<AuthField, Int> = buildMap {
        for (field in fields) {
            when (field) {
                "email" -> put(AuthField.Email, R.string.error_email_unavailable)
                "name" -> put(AuthField.Name, R.string.error_field_rejected)
                "phone" -> put(AuthField.Phone, R.string.error_field_rejected)
                "password" -> put(AuthField.Password, R.string.error_field_rejected)
            }
        }
    }

    /** Apply an edit and clear that field's error, since the user is fixing it. */
    private fun edit(field: AuthField, change: RegisterUiState.() -> RegisterUiState) =
        _uiState.update { it.change().copy(fieldErrors = it.fieldErrors - field) }
}
