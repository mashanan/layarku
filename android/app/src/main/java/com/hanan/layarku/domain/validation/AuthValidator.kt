package com.hanan.layarku.domain.validation

import com.hanan.layarku.domain.model.RegistrationForm

/** Why a form field was rejected on the device, before any network call. */
enum class FieldError { Required, InvalidEmail, PasswordTooShort, InvalidPhone, TooLong }

enum class AuthField { Name, Email, Phone, Password }

/**
 * Client-side checks that mirror the backend's Form Requests, so the user gets
 * instant feedback. The server still validates everything; this only saves a
 * round trip.
 */
object AuthValidator {
    const val MIN_PASSWORD_LENGTH = 8
    const val MAX_NAME_LENGTH = 100

    // Deliberately simple: the server does the strict RFC check.
    private val emailRegex = Regex("^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$")

    // Same rule as the backend: optional +, then 8 to 15 digits.
    private val phoneRegex = Regex("^\\+?[0-9]{8,15}$")

    fun validateLogin(email: String, password: String): Map<AuthField, FieldError> = buildMap {
        emailError(email)?.let { put(AuthField.Email, it) }
        if (password.isEmpty()) put(AuthField.Password, FieldError.Required)
    }

    fun validateRegistration(form: RegistrationForm): Map<AuthField, FieldError> = buildMap {
        val name = form.name.trim()
        when {
            name.isEmpty() -> put(AuthField.Name, FieldError.Required)
            name.length > MAX_NAME_LENGTH -> put(AuthField.Name, FieldError.TooLong)
        }
        emailError(form.email)?.let { put(AuthField.Email, it) }
        val phone = form.phone?.let(::normalizePhone)
        if (!phone.isNullOrEmpty() && !phoneRegex.matches(phone)) put(AuthField.Phone, FieldError.InvalidPhone)
        when {
            form.password.isEmpty() -> put(AuthField.Password, FieldError.Required)
            form.password.length < MIN_PASSWORD_LENGTH -> put(AuthField.Password, FieldError.PasswordTooShort)
        }
    }

    /** People type "0812-3456 7890"; the API wants digits only. */
    fun normalizePhone(raw: String): String = raw.filterNot { it == ' ' || it == '-' }

    private fun emailError(email: String): FieldError? {
        val trimmed = email.trim()
        return when {
            trimmed.isEmpty() -> FieldError.Required
            !emailRegex.matches(trimmed) -> FieldError.InvalidEmail
            else -> null
        }
    }
}
