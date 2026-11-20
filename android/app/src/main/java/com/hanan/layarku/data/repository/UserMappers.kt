package com.hanan.layarku.data.repository

import com.hanan.layarku.data.local.StoredUser
import com.hanan.layarku.data.remote.dto.UserDto
import com.hanan.layarku.domain.model.User
import com.hanan.layarku.domain.model.UserRole

fun UserDto.toStored() = StoredUser(id = id, name = name, email = email, phone = phone, role = role)

fun StoredUser.toDomain() = User(
    id = id,
    name = name,
    email = email,
    phone = phone,
    // Unknown roles fall back to the least privileged one.
    role = if (role == "staff") UserRole.Staff else UserRole.Customer,
)
