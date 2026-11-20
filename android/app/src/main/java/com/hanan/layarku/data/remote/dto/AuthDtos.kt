package com.hanan.layarku.data.remote.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonObject

/** Laravel API Resources wrap every successful payload in `{ "data": ... }`. */
@Serializable
data class DataEnvelope<T>(val data: T)

@Serializable
data class UserDto(
    val id: Long,
    val name: String,
    val email: String,
    val phone: String? = null,
    val role: String,
)

@Serializable
data class AuthDataDto(
    val token: String,
    @SerialName("token_type") val tokenType: String,
    val user: UserDto,
)

@Serializable
data class LoginRequestDto(
    val email: String,
    val password: String,
    @SerialName("device_name") val deviceName: String,
)

@Serializable
data class RegisterRequestDto(
    val name: String,
    val email: String,
    val phone: String?,
    val password: String,
    @SerialName("device_name") val deviceName: String,
)

/** `{ "error": { "code": "...", "message": "...", "details": { ... } } }` */
@Serializable
data class ApiErrorEnvelopeDto(val error: ApiErrorDto)

@Serializable
data class ApiErrorDto(
    val code: String,
    val message: String = "",
    val details: JsonObject = JsonObject(emptyMap()),
)
