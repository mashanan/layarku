package com.hanan.layarku.ui.auth.register

import app.cash.turbine.test
import com.hanan.layarku.R
import com.hanan.layarku.domain.model.AppResult
import com.hanan.layarku.domain.model.DataError
import com.hanan.layarku.domain.usecase.RegisterUseCase
import com.hanan.layarku.domain.validation.AuthField
import com.hanan.layarku.testutil.FakeAuthRepository
import com.hanan.layarku.testutil.MainDispatcherRule
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test

class RegisterViewModelTest {

    @get:Rule val mainDispatcherRule = MainDispatcherRule()

    private val repository = FakeAuthRepository()
    private val viewModel = RegisterViewModel(RegisterUseCase(repository))

    private fun fillValidForm(phone: String = "") {
        viewModel.onNameChange("  Sari  ")
        viewModel.onEmailChange("sari@example.com")
        viewModel.onPhoneChange(phone)
        viewModel.onPasswordChange("secret-password")
    }

    @Test
    fun `invalid fields are reported together without calling the server`() {
        viewModel.onEmailChange("sari@")
        viewModel.onPhoneChange("123")
        viewModel.onPasswordChange("short")
        viewModel.onSubmit()

        assertEquals(
            mapOf(
                AuthField.Name to R.string.error_required,
                AuthField.Email to R.string.error_email_invalid,
                AuthField.Phone to R.string.error_phone_invalid,
                AuthField.Password to R.string.error_password_too_short,
            ),
            viewModel.uiState.value.fieldErrors,
        )
        assertTrue(repository.registerCalls.isEmpty())
    }

    @Test
    fun `sends a cleaned-up form and emits Registered`() = runTest {
        fillValidForm(phone = "0812-3456 7890")

        viewModel.events.test {
            viewModel.onSubmit()
            assertEquals(RegisterEvent.Registered, awaitItem())
        }
        val sent = repository.registerCalls.single()
        assertEquals("Sari", sent.name)
        assertEquals("081234567890", sent.phone)
    }

    @Test
    fun `an empty phone is sent as null`() = runTest {
        fillValidForm(phone = "   ")

        viewModel.onSubmit()

        assertNull(repository.registerCalls.single().phone)
    }

    @Test
    fun `server rejection of the email shows our own message under the field`() = runTest {
        repository.registerResult = AppResult.Failure(
            DataError.Validation(mapOf("email" to listOf("The email has already been taken."))),
        )
        fillValidForm()

        viewModel.onSubmit()

        assertEquals(R.string.error_email_unavailable, viewModel.uiState.value.fieldErrors[AuthField.Email])
    }

    @Test
    fun `editing a field clears only its error`() {
        viewModel.onSubmit()
        viewModel.onNameChange("S")

        val errors = viewModel.uiState.value.fieldErrors
        assertTrue(AuthField.Name !in errors)
        assertTrue(AuthField.Email in errors)
    }

    @Test
    fun `network failures show a snackbar message`() = runTest {
        repository.registerResult = AppResult.Failure(DataError.NoInternet)
        fillValidForm()

        viewModel.events.test {
            viewModel.onSubmit()
            assertEquals(RegisterEvent.ShowMessage(R.string.error_no_internet), awaitItem())
        }
    }
}
