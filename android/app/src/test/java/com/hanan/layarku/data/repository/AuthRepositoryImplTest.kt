package com.hanan.layarku.data.repository

import app.cash.turbine.test
import com.hanan.layarku.data.local.StoredSession
import com.hanan.layarku.data.local.StoredUser
import com.hanan.layarku.data.remote.ApiCaller
import com.hanan.layarku.data.remote.ErrorMapper
import com.hanan.layarku.data.remote.LayarkuApi
import com.hanan.layarku.data.remote.dto.AuthDataDto
import com.hanan.layarku.data.remote.dto.DataEnvelope
import com.hanan.layarku.data.remote.dto.LoginRequestDto
import com.hanan.layarku.data.remote.dto.UserDto
import com.hanan.layarku.domain.model.AppResult
import com.hanan.layarku.domain.model.DataError
import com.hanan.layarku.domain.model.UserRole
import com.hanan.layarku.testutil.FakeSessionStorage
import io.mockk.coEvery
import io.mockk.coVerify
import io.mockk.mockk
import java.io.IOException
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.TestScope
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.Json
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class AuthRepositoryImplTest {

    private val api = mockk<LayarkuApi>()
    private val storage = FakeSessionStorage()
    private val userDto = UserDto(7, "Budi", "budi@example.com", "0812", "staff")

    private fun TestScope.repository() = AuthRepositoryImpl(
        api = api,
        apiCaller = ApiCaller(ErrorMapper(Json), storage, StandardTestDispatcher(testScheduler)),
        sessionStorage = storage,
        deviceName = "Pixel 6",
    )

    @Test
    fun `login stores the token and exposes the user as the session`() = runTest {
        coEvery { api.login(any()) } returns DataEnvelope(AuthDataDto("tok", "Bearer", userDto))
        val repository = repository()

        val result = repository.login("budi@example.com", "secret-password")

        assertEquals(UserRole.Staff, (result as AppResult.Success).data.role)
        assertEquals("tok", storage.currentToken())
        coVerify { api.login(LoginRequestDto("budi@example.com", "secret-password", "Pixel 6")) }
        repository.session.test { assertEquals("Budi", awaitItem()?.name) }
    }

    @Test
    fun `failed login stores nothing`() = runTest {
        coEvery { api.login(any()) } throws IOException("offline")

        val result = repository().login("budi@example.com", "x")

        assertEquals(DataError.NoInternet, (result as AppResult.Failure).error)
        assertNull(storage.currentToken())
    }

    @Test
    fun `logout clears the session even when the server is unreachable`() = runTest {
        storage.save(StoredSession("tok", StoredUser(7, "Budi", "b@e.com", null, "customer")))
        coEvery { api.logout() } throws IOException("offline")

        repository().logout()

        assertNull(storage.currentToken())
    }

    @Test
    fun `refreshing the profile updates the stored user`() = runTest {
        storage.save(StoredSession("tok", StoredUser(7, "Old name", "b@e.com", null, "customer")))
        coEvery { api.me() } returns DataEnvelope(userDto)
        val repository = repository()

        repository.refreshProfile()

        repository.session.test { assertEquals("Budi", awaitItem()?.name) }
        assertEquals("tok", storage.currentToken())
    }
}
