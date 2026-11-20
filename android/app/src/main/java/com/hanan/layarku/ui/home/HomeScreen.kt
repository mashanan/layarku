package com.hanan.layarku.ui.home

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.hanan.layarku.R
import com.hanan.layarku.domain.model.User
import com.hanan.layarku.domain.model.UserRole
import com.hanan.layarku.ui.theme.LayarkuTheme

@Composable
fun HomeRoute(viewModel: HomeViewModel = hiltViewModel()) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()
    HomeScreen(uiState = uiState, onLogout = viewModel::onLogout)
}

@Composable
fun HomeScreen(uiState: HomeUiState, onLogout: () -> Unit) {
    Scaffold { padding ->
        Column(
            modifier = Modifier.fillMaxSize().padding(padding).padding(24.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            val user = uiState.user ?: return@Column
            Text(
                text = stringResource(R.string.brand_name).uppercase(),
                style = MaterialTheme.typography.labelLarge,
                color = MaterialTheme.colorScheme.secondary,
            )
            Text(stringResource(R.string.home_greeting, user.name), style = MaterialTheme.typography.headlineMedium)
            val role = stringResource(
                if (user.role == UserRole.Staff) R.string.home_role_staff else R.string.home_role_customer,
            )
            Text(
                stringResource(R.string.home_placeholder, role),
                style = MaterialTheme.typography.bodyLarge,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            OutlinedButton(onClick = onLogout, enabled = !uiState.isLoggingOut) {
                Text(stringResource(R.string.logout_button))
            }
        }
    }
}

@Preview(showBackground = true)
@Composable
private fun HomeScreenPreview() {
    LayarkuTheme {
        HomeScreen(
            uiState = HomeUiState(User(1, "Sari", "sari@example.com", null, UserRole.Customer)),
            onLogout = {},
        )
    }
}
