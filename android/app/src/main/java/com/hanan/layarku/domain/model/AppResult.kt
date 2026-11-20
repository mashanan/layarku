package com.hanan.layarku.domain.model

/**
 * Result of any operation that can fail in an expected way (network, server rules).
 * Repositories never throw for these cases; the UI decides how to show [DataError].
 */
sealed interface AppResult<out T> {
    data class Success<T>(val data: T) : AppResult<T>
    data class Failure(val error: DataError) : AppResult<Nothing>
}

inline fun <T, R> AppResult<T>.map(transform: (T) -> R): AppResult<R> = when (this) {
    is AppResult.Success -> AppResult.Success(transform(data))
    is AppResult.Failure -> this
}
