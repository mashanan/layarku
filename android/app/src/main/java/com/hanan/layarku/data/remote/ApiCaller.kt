package com.hanan.layarku.data.remote

import com.hanan.layarku.data.di.IoDispatcher
import com.hanan.layarku.data.local.SessionStorage
import com.hanan.layarku.domain.model.AppResult
import com.hanan.layarku.domain.model.DataError
import javax.inject.Inject
import javax.inject.Singleton
import kotlin.coroutines.cancellation.CancellationException
import kotlinx.coroutines.CoroutineDispatcher
import kotlinx.coroutines.channels.BufferOverflow
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.asSharedFlow
import kotlinx.coroutines.withContext

/**
 * Runs every API call the same way: on the IO dispatcher, with exceptions
 * turned into [AppResult.Failure].
 *
 * It also owns the "session expired" rule in one place: if the server says our
 * token is no longer valid (401 `unauthenticated`) while we had one, the local
 * session is cleared and [sessionExpired] fires, wherever the call came from.
 */
@Singleton
class ApiCaller @Inject constructor(
    private val errorMapper: ErrorMapper,
    private val sessionStorage: SessionStorage,
    @param:IoDispatcher private val ioDispatcher: CoroutineDispatcher,
) {
    private val _sessionExpired = MutableSharedFlow<Unit>(
        extraBufferCapacity = 1,
        onBufferOverflow = BufferOverflow.DROP_OLDEST,
    )
    val sessionExpired: SharedFlow<Unit> = _sessionExpired.asSharedFlow()

    suspend fun <T> call(block: suspend () -> T): AppResult<T> = withContext(ioDispatcher) {
        try {
            AppResult.Success(block())
        } catch (e: CancellationException) {
            throw e // never swallow coroutine cancellation
        } catch (e: Throwable) {
            val error = errorMapper.map(e)
            if (error == DataError.Unauthenticated && sessionStorage.currentToken() != null) {
                sessionStorage.clear()
                _sessionExpired.tryEmit(Unit)
            }
            AppResult.Failure(error)
        }
    }
}
