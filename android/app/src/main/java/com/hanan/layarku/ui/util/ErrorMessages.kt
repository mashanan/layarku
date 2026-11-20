package com.hanan.layarku.ui.util

import androidx.annotation.StringRes
import com.hanan.layarku.R
import com.hanan.layarku.domain.model.DataError
import com.hanan.layarku.domain.validation.FieldError

/*
 * The only place that decides which text a user sees for an error. ViewModels
 * keep string resource ids (plain Ints), so they stay unit-testable on the JVM
 * and the text follows the device language. Raw exception or server messages
 * are never shown.
 */

@StringRes
fun DataError.messageRes(): Int = when (this) {
    DataError.NoInternet -> R.string.error_no_internet
    DataError.InvalidCredentials -> R.string.error_invalid_credentials
    DataError.Unauthenticated -> R.string.error_session_expired
    DataError.TooManyRequests -> R.string.error_too_many_requests
    DataError.Server -> R.string.error_server
    DataError.Forbidden -> R.string.error_forbidden
    DataError.NotFound -> R.string.error_not_found
    is DataError.SeatTaken -> R.string.error_seat_taken
    // Field errors are shown next to the fields; this is only a fallback.
    is DataError.Validation -> R.string.error_field_rejected
    is DataError.BusinessRule, DataError.Unknown -> R.string.error_unknown
}

@StringRes
fun FieldError.messageRes(): Int = when (this) {
    FieldError.Required -> R.string.error_required
    FieldError.InvalidEmail -> R.string.error_email_invalid
    FieldError.PasswordTooShort -> R.string.error_password_too_short
    FieldError.InvalidPhone -> R.string.error_phone_invalid
    FieldError.TooLong -> R.string.error_name_too_long
}
