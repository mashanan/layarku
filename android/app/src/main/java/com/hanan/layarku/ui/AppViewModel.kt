package com.hanan.layarku.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.hanan.layarku.domain.usecase.ObserveSessionExpiredUseCase
import com.hanan.layarku.domain.usecase.ObserveSessionUseCase
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.stateIn

sealed interface SessionState {
    /** Reading the stored session at startup; show nothing yet to avoid a login flash. */
    data object Loading : SessionState
    data object SignedIn : SessionState
    data object SignedOut : SessionState
}

/** App-wide state owned by MainActivity: are we signed in, and did the session expire? */
@HiltViewModel
class AppViewModel @Inject constructor(
    observeSession: ObserveSessionUseCase,
    observeSessionExpired: ObserveSessionExpiredUseCase,
) : ViewModel() {

    val sessionState: StateFlow<SessionState> = observeSession()
        .map { user -> if (user != null) SessionState.SignedIn else SessionState.SignedOut }
        .stateIn(viewModelScope, SharingStarted.Eagerly, SessionState.Loading)

    val sessionExpired: Flow<Unit> = observeSessionExpired()
}
