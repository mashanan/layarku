package com.hanan.layarku.domain.repository

import com.hanan.layarku.domain.model.AppResult
import com.hanan.layarku.domain.model.RegistrationForm
import com.hanan.layarku.domain.model.User
import kotlinx.coroutines.flow.Flow

interface AuthRepository {
    /** The signed-in user, or null when signed out. Survives app restarts. */
    val session: Flow<User?>

    /**
     * Emits once each time the server rejects our token (401). The session has
     * already been cleared when this fires; the UI only needs to explain it.
     */
    val sessionExpired: Flow<Unit>

    suspend fun login(email: String, password: String): AppResult<User>

    suspend fun register(form: RegistrationForm): AppResult<User>

    /** Fetches the latest profile from the server and updates the session. */
    suspend fun refreshProfile(): AppResult<User>

    /** Always signs out locally, even if the server can't be reached. */
    suspend fun logout()
}
