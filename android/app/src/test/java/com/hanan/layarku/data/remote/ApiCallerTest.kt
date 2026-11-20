package com.hanan.layarku.data.remote

import app.cash.turbine.test
import com.hanan.layarku.data.local.StoredSession
import com.hanan.layarku.data.local.StoredUser
import com.hanan.layarku.data.remote.dto.LoginRequestDto
import com.hanan.layarku.domain.model.AppResult
import com.hanan.layarku.domain.model.DataError
import com.hanan.layarku.testutil.FakeSessionStorage
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.Json
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory

/**
 * Real Retrofit + OkHttp against a local MockWebServer, so these tests cover the
 * whole chain: interceptor -> HTTP -> JSON -> ErrorMapper -> AppResult.
 */
class ApiCallerTest {

    private val server = MockWebServer()
    private val json = Json { ignoreUnknownKeys = true; explicitNulls = false }
    private val session = StoredSession("token-123", StoredUser(1, "Sari", "sari@example.com", null, "customer"))
    private lateinit var storage: FakeSessionStorage
    private lateinit var api: LayarkuApi

    @Before
    fun setUp() {
        server.start()
        storage = FakeSessionStorage(session)
        val client = OkHttpClient.Builder().addInterceptor(AuthInterceptor(storage)).build()
        api = Retrofit.Builder()
            .baseUrl(server.url("/api/"))
            .client(client)
            .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
            .build()
            .create(LayarkuApi::class.java)
    }

    @After
    fun tearDown() = server.close()

    private fun caller(dispatcher: kotlinx.coroutines.CoroutineDispatcher) =
        ApiCaller(ErrorMapper(json), storage, dispatcher)

    private fun respond(status: Int, body: String) {
        server.enqueue(MockResponse.Builder().code(status).addHeader("Content-Type", "application/json").body(body).build())
    }

    private fun error(code: String, details: String = "{}") =
        """{"error":{"code":"$code","message":"English text we never show","details":$details}}"""

    @Test
    fun `success returns data and sends the bearer token`() = runTest {
        respond(200, """{"data":{"id":1,"name":"Sari","email":"sari@example.com","phone":null,"role":"customer","extra":"ignored"}}""")

        val result = caller(StandardTestDispatcher(testScheduler)).call { api.me().data }

        assertEquals("Sari", (result as AppResult.Success).data.name)
        val request = server.takeRequest()
        assertEquals("Bearer token-123", request.headers["Authorization"])
        assertEquals("application/json", request.headers["Accept"])
    }

    @Test
    fun `validation errors keep the field names`() = runTest {
        respond(422, error("validation_failed", """{"fields":{"email":["The email has already been taken."]}}"""))

        val result = caller(StandardTestDispatcher(testScheduler)).call { api.me() }

        val err = (result as AppResult.Failure).error as DataError.Validation
        assertEquals(setOf("email"), err.fields.keys)
    }

    @Test
    fun `seat_taken carries the conflicting seat ids`() = runTest {
        respond(409, error("seat_taken", """{"seat_ids":[12,13]}"""))

        val result = caller(StandardTestDispatcher(testScheduler)).call { api.me() }

        assertEquals(DataError.SeatTaken(listOf(12L, 13L)), (result as AppResult.Failure).error)
    }

    @Test
    fun `codes map to their own errors`() = runTest {
        val cases = listOf(
            Triple(401, error("invalid_credentials"), DataError.InvalidCredentials),
            Triple(429, error("too_many_requests"), DataError.TooManyRequests),
            Triple(404, error("not_found"), DataError.NotFound),
            Triple(403, error("forbidden"), DataError.Forbidden),
            Triple(422, error("booking_closed"), DataError.BusinessRule("booking_closed")),
            Triple(500, error("server_error"), DataError.Server),
            // Not our JSON at all (e.g. a proxy error page): fall back to the status.
            Triple(502, "<html>Bad gateway</html>", DataError.Server),
        )
        val apiCaller = caller(StandardTestDispatcher(testScheduler))
        for ((status, body, expected) in cases) {
            respond(status, body)
            assertEquals("HTTP $status", expected, (apiCaller.call { api.me() } as AppResult.Failure).error)
        }
    }

    @Test
    fun `wrong password does not end the session`() = runTest {
        respond(401, error("invalid_credentials"))

        caller(StandardTestDispatcher(testScheduler)).call { api.login(LoginRequestDto("a@b.c", "x", "test")) }

        assertEquals("token-123", storage.currentToken())
    }

    @Test
    fun `a rejected token clears the session and signals it once`() = runTest {
        respond(401, error("unauthenticated"))
        val apiCaller = caller(StandardTestDispatcher(testScheduler))

        apiCaller.sessionExpired.test {
            val result = apiCaller.call { api.me() }
            assertEquals(DataError.Unauthenticated, (result as AppResult.Failure).error)
            awaitItem()
            assertNull(storage.currentToken())
            expectNoEvents()
        }
    }

    @Test
    fun `no server means no internet`() = runTest {
        server.close() // nothing listening on that port any more

        val result = caller(StandardTestDispatcher(testScheduler)).call { api.me() }

        assertEquals(DataError.NoInternet, (result as AppResult.Failure).error)
        assertTrue(storage.currentToken() != null)
    }
}
