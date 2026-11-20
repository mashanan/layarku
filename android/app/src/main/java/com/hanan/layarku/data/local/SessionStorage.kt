package com.hanan.layarku.data.local

import kotlinx.coroutines.flow.Flow
import kotlinx.serialization.Serializable

/** What we keep on the device between launches. */
@Serializable
data class StoredSession(
    val token: String,
    val user: StoredUser,
)

@Serializable
data class StoredUser(
    val id: Long,
    val name: String,
    val email: String,
    val phone: String? = null,
    val role: String,
)

/**
 * Persists the auth token and the signed-in user. An interface so repository
 * tests can use an in-memory fake instead of DataStore.
 */
interface SessionStorage {
    val session: Flow<StoredSession?>

    /** Synchronous read for the OkHttp interceptor, which runs on a background thread. */
    fun currentToken(): String?

    suspend fun save(session: StoredSession)

    suspend fun updateUser(user: StoredUser)

    suspend fun clear()
}
