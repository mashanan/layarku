package com.hanan.layarku.data.remote

import com.hanan.layarku.data.local.SessionStorage
import javax.inject.Inject
import okhttp3.Interceptor
import okhttp3.Response

/** Adds `Authorization: Bearer <token>` to every request when signed in. */
class AuthInterceptor @Inject constructor(private val sessionStorage: SessionStorage) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val token = sessionStorage.currentToken()
        val request = chain.request().newBuilder()
            .header("Accept", "application/json")
            .apply { if (token != null) header("Authorization", "Bearer $token") }
            .build()
        return chain.proceed(request)
    }
}
