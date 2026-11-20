package com.hanan.layarku.ui.home

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.hanan.layarku.domain.model.User
import com.hanan.layarku.domain.usecase.LogoutUseCase
import com.hanan.layarku.domain.usecase.ObserveSessionUseCase
import com.hanan.layarku.domain.usecase.RefreshProfileUseCase
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch

data class HomeUiState(val user: User? = null, val isLoggingOut: Boolean = false)

/**
 * Temporary home screen for phase 3. It proves an authenticated request works
 * (GET /me) and gives a way to sign out. Phase 4 replaces it with the movie list.
 */
@HiltViewModel
class HomeViewModel @Inject constructor(
    observeSession: ObserveSessionUseCase,
    private val refreshProfile: RefreshProfileUseCase,
    private val logout: LogoutUseCase,
) : ViewModel() {

    private val isLoggingOut = MutableStateFlow(false)

    val uiState: StateFlow<HomeUiState> = combine(observeSession(), isLoggingOut) { user, loggingOut ->
        HomeUiState(user = user, isLoggingOut = loggingOut)
    }.stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), HomeUiState())

    init {
        // Refresh in the background; the cached profile is shown meanwhile. A 401
        // here signs the user out through ApiCaller's session-expired rule.
        viewModelScope.launch { refreshProfile() }
    }

    fun onLogout() {
        if (isLoggingOut.value) return
        isLoggingOut.value = true
        // Navigation back to login reacts to the session becoming null.
        viewModelScope.launch { logout() }
    }
}
