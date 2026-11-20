package com.hanan.layarku.domain.model

/**
 * Every expected failure, independent of HTTP or Retrofit. Mirrors the backend's
 * stable error codes (backend/README.md), so each one can get its own UI message.
 */
sealed interface DataError {
    /** No connection, DNS failure or timeout: nothing reached the server. */
    data object NoInternet : DataError

    /** 401 `unauthenticated`: the stored token is missing, revoked or expired. */
    data object Unauthenticated : DataError

    /** 401 `invalid_credentials`: wrong email or password at login. */
    data object InvalidCredentials : DataError

    /** 422 `validation_failed`: field name -> server messages (not shown verbatim). */
    data class Validation(val fields: Map<String, List<String>>) : DataError

    /** 409 `seat_taken`: these seats were taken by someone else. */
    data class SeatTaken(val seatIds: List<Long>) : DataError

    /** Any other 422 business rule, e.g. `booking_closed`. */
    data class BusinessRule(val code: String) : DataError

    data object Forbidden : DataError
    data object NotFound : DataError
    data object TooManyRequests : DataError

    /** 5xx: the server failed. */
    data object Server : DataError

    /** A response we could not understand. */
    data object Unknown : DataError
}
