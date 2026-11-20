package com.hanan.layarku.domain.model

data class User(
    val id: Long,
    val name: String,
    val email: String,
    val phone: String?,
    val role: UserRole,
)

enum class UserRole { Customer, Staff }
