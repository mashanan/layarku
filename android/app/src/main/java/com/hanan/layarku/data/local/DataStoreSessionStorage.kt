package com.hanan.layarku.data.local

import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.runBlocking
import kotlinx.serialization.json.Json

/**
 * Session stored as one JSON string in Preferences DataStore, inside the app's
 * private storage. Other apps can't read it. A rooted device or a backup could,
 * which is acceptable for this demo; a revoked token is useless anyway because
 * the server checks it on every request.
 */
@Singleton
class DataStoreSessionStorage @Inject constructor(
    private val dataStore: DataStore<Preferences>,
    private val json: Json,
) : SessionStorage {

    private val sessionKey = stringPreferencesKey("session")

    // The interceptor needs the token synchronously on every request, so we keep
    // a copy in memory instead of reading the file each time.
    @Volatile private var cachedToken: String? = null
    @Volatile private var cacheLoaded = false

    override val session: Flow<StoredSession?> = dataStore.data.map { prefs ->
        prefs[sessionKey]?.let { runCatching { json.decodeFromString<StoredSession>(it) }.getOrNull() }
    }

    override fun currentToken(): String? {
        if (!cacheLoaded) {
            // First request after app start: OkHttp threads are background
            // threads, so a one-off blocking read here is safe.
            cachedToken = runBlocking { session.first()?.token }
            cacheLoaded = true
        }
        return cachedToken
    }

    override suspend fun save(session: StoredSession) {
        dataStore.edit { it[sessionKey] = json.encodeToString(StoredSession.serializer(), session) }
        cachedToken = session.token
        cacheLoaded = true
    }

    override suspend fun updateUser(user: StoredUser) {
        val current = session.first() ?: return
        save(current.copy(user = user))
    }

    override suspend fun clear() {
        dataStore.edit { it.remove(sessionKey) }
        cachedToken = null
        cacheLoaded = true
    }
}
