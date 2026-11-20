package com.hanan.layarku.domain.usecase

import com.hanan.layarku.domain.model.AppResult
import com.hanan.layarku.domain.model.RegistrationForm
import com.hanan.layarku.domain.model.User
import com.hanan.layarku.domain.repository.AuthRepository
import com.hanan.layarku.domain.validation.AuthValidator
import javax.inject.Inject
import kotlinx.coroutines.flow.Flow

/*
 * One action per class, invoked like a function: `login(email, password)`.
 * They are thin today; they are where rules shared by several screens go,
 * so ViewModels never talk to repositories directly.
 */

class LoginUseCase @Inject constructor(private val repository: AuthRepository) {
    suspend operator fun invoke(email: String, password: String): AppResult<User> =
        repository.login(email.trim(), password)
}

class RegisterUseCase @Inject constructor(private val repository: AuthRepository) {
    suspend operator fun invoke(form: RegistrationForm): AppResult<User> =
        repository.register(
            form.copy(
                name = form.name.trim(),
                email = form.email.trim(),
                phone = form.phone?.let(AuthValidator::normalizePhone)?.ifEmpty { null },
            ),
        )
}

class LogoutUseCase @Inject constructor(private val repository: AuthRepository) {
    suspend operator fun invoke() = repository.logout()
}

class ObserveSessionUseCase @Inject constructor(private val repository: AuthRepository) {
    operator fun invoke(): Flow<User?> = repository.session
}

class ObserveSessionExpiredUseCase @Inject constructor(private val repository: AuthRepository) {
    operator fun invoke(): Flow<Unit> = repository.sessionExpired
}

class RefreshProfileUseCase @Inject constructor(private val repository: AuthRepository) {
    suspend operator fun invoke(): AppResult<User> = repository.refreshProfile()
}
