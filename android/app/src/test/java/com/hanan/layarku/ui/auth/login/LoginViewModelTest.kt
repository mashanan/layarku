package com.hanan.layarku.ui.auth.login

import app.cash.turbine.test
import com.hanan.layarku.R
import com.hanan.layarku.domain.model.AppResult
import com.hanan.layarku.domain.model.DataError
import com.hanan.layarku.domain.usecase.LoginUseCase
import com.hanan.layarku.testutil.FakeAuthRepository
import com.hanan.layarku.testutil.MainDispatcherRule
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test

class LoginViewModelTest {

    @get:Rule val mainDispatcherRule = MainDispatcherRule()

    private val repository = FakeAuthRepository()
    private val viewModel = LoginViewModel(LoginUseCase(repository))

    private fun fillValidForm() {
        viewModel.onEmailChange(" sari@example.com ")
        viewModel.onPasswordChange("secret-password")
    }

    @Test
    fun `invalid input shows field errors and does not call the server`() {
        viewModel.onEmailChange("not-an-email")
        viewModel.onSubmit()

        val state = viewModel.uiState.value
        assertEquals(R.string.error_email_invalid, state.emailError)
        assertEquals(R.string.error_required, state.passwordError)
        assertTrue(repository.loginCalls.isEmpty())
    }

    @Test
    fun `typing clears that field's error`() {
        viewModel.onSubmit()
        viewModel.onEmailChange("s")

        assertEquals(null, viewModel.uiState.value.emailError)
        assertEquals(R.string.error_required, viewModel.uiState.value.passwordError)
    }

    @Test
    fun `successful login trims the email and emits LoggedIn`() = runTest {
        fillValidForm()

        viewModel.events.test {
            viewModel.onSubmit()
            assertEquals(LoginEvent.LoggedIn, awaitItem())
        }
        assertEquals("sari@example.com" to "secret-password", repository.loginCalls.single())
    }

    @Test
    fun `shows a loading state and ignores double taps while submitting`() = runTest {
        val gate = CompletableDeferred<Unit>()
        repository.gate = gate
        fillValidForm()

        viewModel.onSubmit()
        viewModel.onSubmit()

        assertTrue(viewModel.uiState.value.isSubmitting)
        assertEquals(1, repository.loginCalls.size)

        gate.complete(Unit)
        assertFalse(viewModel.uiState.value.isSubmitting)
    }

    @Test
    fun `wrong credentials show a message and clear only the password`() = runTest {
        repository.loginResult = AppResult.Failure(DataError.InvalidCredentials)
        fillValidForm()

        viewModel.events.test {
            viewModel.onSubmit()
            assertEquals(LoginEvent.ShowMessage(R.string.error_invalid_credentials), awaitItem())
        }
        assertEquals(" sari@example.com ", viewModel.uiState.value.email)
        assertEquals("", viewModel.uiState.value.password)
    }

    @Test
    fun `no internet maps to its own message`() = runTest {
        repository.loginResult = AppResult.Failure(DataError.NoInternet)
        fillValidForm()

        viewModel.events.test {
            viewModel.onSubmit()
            assertEquals(LoginEvent.ShowMessage(R.string.error_no_internet), awaitItem())
        }
        assertEquals("secret-password", viewModel.uiState.value.password)
    }

    @Test
    fun `too many attempts maps to the rate limit message`() = runTest {
        repository.loginResult = AppResult.Failure(DataError.TooManyRequests)
        fillValidForm()

        viewModel.events.test {
            viewModel.onSubmit()
            assertEquals(LoginEvent.ShowMessage(R.string.error_too_many_requests), awaitItem())
        }
    }
}
