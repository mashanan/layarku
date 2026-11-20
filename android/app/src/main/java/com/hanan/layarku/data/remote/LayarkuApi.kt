package com.hanan.layarku.data.remote

import com.hanan.layarku.data.remote.dto.AuthDataDto
import com.hanan.layarku.data.remote.dto.DataEnvelope
import com.hanan.layarku.data.remote.dto.LoginRequestDto
import com.hanan.layarku.data.remote.dto.RegisterRequestDto
import com.hanan.layarku.data.remote.dto.UserDto
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST

/** The Layarku REST API (see backend/README.md). Paths are relative to BuildConfig.API_BASE_URL. */
interface LayarkuApi {
    @POST("auth/register")
    suspend fun register(@Body body: RegisterRequestDto): DataEnvelope<AuthDataDto>

    @POST("auth/login")
    suspend fun login(@Body body: LoginRequestDto): DataEnvelope<AuthDataDto>

    /** 204 No Content. */
    @POST("auth/logout")
    suspend fun logout()

    @GET("me")
    suspend fun me(): DataEnvelope<UserDto>
}
