package com.hanan.layarku.testutil

import com.hanan.layarku.data.local.SessionStorage
import com.hanan.layarku.data.local.StoredSession
import com.hanan.layarku.data.local.StoredUser
import com.hanan.layarku.domain.model.AppResult
import com.hanan.layarku.domain.model.RegistrationForm
import com.hanan.layarku.domain.model.User
import com.hanan.layarku.domain.model.UserRole
import com.hanan.layarku.domain.repository.AuthRepository
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow

val sampleUser = User(id = 1, name = "Sari", email = "sari@example.com", phone = null, role = UserRole.Customer)

/** In-memory SessionStorage, used instead of DataStore in JVM tests. */
class FakeSessionStorage(initial: StoredSession? = null) : SessionStorage {
    private val state = MutableStateFlow(initial)
    override val session: Flow<StoredSession?> = state
    override fun currentToken(): String? = state.value?.token
    override suspend fun save(session: StoredSession) { state.value = session }
    override suspend fun updateUser(user: StoredUser) { state.value = state.value?.copy(user = user) }
    override suspend fun clear() { state.value = null }
}

/** Scriptable AuthRepository for ViewModel tests. */
class FakeAuthRepository : AuthRepository {
    val sessionFlow = MutableStateFlow<User?>(null)
    val expiredFlow = MutableSharedFlow<Unit>()
    var loginResult: AppResult<User> = AppResult.Success(sampleUser)
    var registerResult: AppResult<User> = AppResult.Success(sampleUser)

    /** When set, login/register suspend until it completes (to observe the loading state). */
    var gate: CompletableDeferred<Unit>? = null

    val loginCalls = mutableListOf<Pair<String, String>>()
    val registerCalls = mutableListOf<RegistrationForm>()

    override val session: Flow<User?> = sessionFlow
    override val sessionExpired: Flow<Unit> = expiredFlow

    override suspend fun login(email: String, password: String): AppResult<User> {
        loginCalls += email to password
        gate?.await()
        return loginResult
    }

    override suspend fun register(form: RegistrationForm): AppResult<User> {
        registerCalls += form
        gate?.await()
        return registerResult
    }

    override suspend fun refreshProfile(): AppResult<User> = AppResult.Success(sampleUser)

    override suspend fun logout() { sessionFlow.value = null }
}
