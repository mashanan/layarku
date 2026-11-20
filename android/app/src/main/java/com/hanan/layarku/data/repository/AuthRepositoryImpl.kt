package com.hanan.layarku.data.repository

import com.hanan.layarku.data.di.DeviceName
import com.hanan.layarku.data.local.SessionStorage
import com.hanan.layarku.data.local.StoredSession
import com.hanan.layarku.data.remote.ApiCaller
import com.hanan.layarku.data.remote.LayarkuApi
import com.hanan.layarku.data.remote.dto.AuthDataDto
import com.hanan.layarku.data.remote.dto.LoginRequestDto
import com.hanan.layarku.data.remote.dto.RegisterRequestDto
import com.hanan.layarku.domain.model.AppResult
import com.hanan.layarku.domain.model.RegistrationForm
import com.hanan.layarku.domain.model.User
import com.hanan.layarku.domain.repository.AuthRepository
import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.map

@Singleton
class AuthRepositoryImpl @Inject constructor(
    private val api: LayarkuApi,
    private val apiCaller: ApiCaller,
    private val sessionStorage: SessionStorage,
    @param:DeviceName private val deviceName: String,
) : AuthRepository {

    override val session: Flow<User?> = sessionStorage.session
        .map { it?.user?.toDomain() }
        .distinctUntilChanged()

    override val sessionExpired: Flow<Unit> = apiCaller.sessionExpired

    override suspend fun login(email: String, password: String): AppResult<User> =
        authenticate { api.login(LoginRequestDto(email, password, deviceName)).data }

    override suspend fun register(form: RegistrationForm): AppResult<User> =
        authenticate {
            api.register(RegisterRequestDto(form.name, form.email, form.phone, form.password, deviceName)).data
        }

    override suspend fun refreshProfile(): AppResult<User> =
        when (val result = apiCaller.call { api.me().data }) {
            is AppResult.Success -> {
                val stored = result.data.toStored()
                sessionStorage.updateUser(stored)
                AppResult.Success(stored.toDomain())
            }
            is AppResult.Failure -> result
        }

    override suspend fun logout() {
        // Revoke the token on the server if we can, but never keep the user
        // signed in just because the network is down.
        apiCaller.call { api.logout() }
        sessionStorage.clear()
    }

    private suspend fun authenticate(request: suspend () -> AuthDataDto): AppResult<User> =
        when (val result = apiCaller.call(request)) {
            is AppResult.Success -> {
                val session = StoredSession(token = result.data.token, user = result.data.user.toStored())
                sessionStorage.save(session)
                AppResult.Success(session.user.toDomain())
            }
            is AppResult.Failure -> result
        }
}
