package com.hanan.layarku.domain.validation

import com.hanan.layarku.domain.model.RegistrationForm
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class AuthValidatorTest {

    private fun form(
        name: String = "Sari",
        email: String = "sari@example.com",
        phone: String? = null,
        password: String = "secret-password",
    ) = RegistrationForm(name, email, phone, password)

    @Test
    fun `valid registration has no errors`() {
        assertTrue(AuthValidator.validateRegistration(form(phone = "081234567890")).isEmpty())
    }

    @Test
    fun `name is required and limited to 100 characters`() {
        assertEquals(FieldError.Required, AuthValidator.validateRegistration(form(name = "   "))[AuthField.Name])
        assertEquals(FieldError.TooLong, AuthValidator.validateRegistration(form(name = "a".repeat(101)))[AuthField.Name])
    }

    @Test
    fun `email must look like an email`() {
        assertEquals(FieldError.Required, AuthValidator.validateRegistration(form(email = ""))[AuthField.Email])
        assertEquals(FieldError.InvalidEmail, AuthValidator.validateRegistration(form(email = "sari@"))[AuthField.Email])
        assertEquals(FieldError.InvalidEmail, AuthValidator.validateRegistration(form(email = "sari example.com"))[AuthField.Email])
        assertTrue(AuthField.Email !in AuthValidator.validateRegistration(form(email = "  sari@example.com ")))
    }

    @Test
    fun `password needs at least 8 characters`() {
        assertEquals(FieldError.Required, AuthValidator.validateRegistration(form(password = ""))[AuthField.Password])
        assertEquals(FieldError.PasswordTooShort, AuthValidator.validateRegistration(form(password = "1234567"))[AuthField.Password])
        assertTrue(AuthField.Password !in AuthValidator.validateRegistration(form(password = "12345678")))
    }

    @Test
    fun `phone is optional but must match the backend rule`() {
        assertTrue(AuthField.Phone !in AuthValidator.validateRegistration(form(phone = null)))
        assertTrue(AuthField.Phone !in AuthValidator.validateRegistration(form(phone = "")))
        assertTrue(AuthField.Phone !in AuthValidator.validateRegistration(form(phone = "+62 812-3456-7890")))
        assertEquals(FieldError.InvalidPhone, AuthValidator.validateRegistration(form(phone = "1234567"))[AuthField.Phone])
        assertEquals(FieldError.InvalidPhone, AuthValidator.validateRegistration(form(phone = "08abc"))[AuthField.Phone])
    }

    @Test
    fun `phone normalisation removes spaces and dashes`() {
        assertEquals("+6281234567890", AuthValidator.normalizePhone("+62 812-3456-7890"))
    }

    @Test
    fun `login needs a valid email and a password`() {
        val errors = AuthValidator.validateLogin(email = "nope", password = "")
        assertEquals(FieldError.InvalidEmail, errors[AuthField.Email])
        assertEquals(FieldError.Required, errors[AuthField.Password])
        assertTrue(AuthValidator.validateLogin("sari@example.com", "x").isEmpty())
    }
}
