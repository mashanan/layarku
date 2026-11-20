package com.hanan.layarku.domain.model

data class RegistrationForm(
    val name: String,
    val email: String,
    val phone: String?,
    val password: String,
)
