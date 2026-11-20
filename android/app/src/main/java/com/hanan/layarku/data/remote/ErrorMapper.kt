package com.hanan.layarku.data.remote

import com.hanan.layarku.data.remote.dto.ApiErrorDto
import com.hanan.layarku.data.remote.dto.ApiErrorEnvelopeDto
import com.hanan.layarku.domain.model.DataError
import java.io.IOException
import javax.inject.Inject
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.longOrNull
import retrofit2.HttpException

/**
 * Turns anything a Retrofit call can throw into a [DataError].
 *
 * The backend's stable `error.code` decides the outcome. The HTTP status is
 * only a fallback, e.g. for a proxy error page that isn't our JSON.
 */
class ErrorMapper @Inject constructor(private val json: Json) {

    fun map(throwable: Throwable): DataError = when (throwable) {
        is HttpException -> fromHttp(throwable.code(), throwable.response()?.errorBody()?.string())
        // Includes no network, DNS failure, refused connection and timeouts.
        is IOException -> DataError.NoInternet
        else -> DataError.Unknown
    }

    fun fromHttp(status: Int, body: String?): DataError {
        val error = body?.let(::parse)
        return error?.let { fromCode(it, status) } ?: fromStatus(status)
    }

    private fun fromCode(error: ApiErrorDto, status: Int): DataError? = when (error.code) {
        "unauthenticated" -> DataError.Unauthenticated
        "invalid_credentials" -> DataError.InvalidCredentials
        "validation_failed" -> DataError.Validation(fieldErrors(error))
        "seat_taken" -> DataError.SeatTaken(seatIds(error))
        "forbidden" -> DataError.Forbidden
        "not_found" -> DataError.NotFound
        "too_many_requests" -> DataError.TooManyRequests
        "server_error" -> DataError.Server
        else -> if (status == 422) DataError.BusinessRule(error.code) else null
    }

    private fun fromStatus(status: Int): DataError = when (status) {
        401 -> DataError.Unauthenticated
        403 -> DataError.Forbidden
        404 -> DataError.NotFound
        429 -> DataError.TooManyRequests
        in 500..599 -> DataError.Server
        else -> DataError.Unknown
    }

    private fun parse(body: String): ApiErrorDto? =
        runCatching { json.decodeFromString<ApiErrorEnvelopeDto>(body).error }.getOrNull()

    private fun fieldErrors(error: ApiErrorDto): Map<String, List<String>> {
        val fields = error.details["fields"] as? kotlinx.serialization.json.JsonObject ?: return emptyMap()
        return fields.mapValues { (_, messages) ->
            (messages as? JsonArray)?.mapNotNull { (it as? JsonPrimitive)?.content }.orEmpty()
        }
    }

    private fun seatIds(error: ApiErrorDto): List<Long> =
        runCatching { error.details.getValue("seat_ids").jsonArray.mapNotNull { (it as JsonPrimitive).longOrNull } }
            .getOrDefault(emptyList())
}
