package com.hanan.layarku.ui.components

import androidx.annotation.StringRes
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import com.hanan.layarku.R

/** Brand header shared by the login and register screens. */
@Composable
fun AuthHeader(@StringRes title: Int, @StringRes subtitle: Int, modifier: Modifier = Modifier) {
    Column(modifier = modifier, verticalArrangement = Arrangement.spacedBy(4.dp)) {
        Text(
            text = stringResource(R.string.brand_name).uppercase(),
            style = MaterialTheme.typography.labelLarge,
            color = MaterialTheme.colorScheme.secondary,
        )
        Text(text = stringResource(title), style = MaterialTheme.typography.headlineMedium)
        Text(
            text = stringResource(subtitle),
            style = MaterialTheme.typography.bodyLarge,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}

/** Text field that shows its error (a string resource) below the input. */
@Composable
fun FormTextField(
    value: String,
    onValueChange: (String) -> Unit,
    @StringRes label: Int,
    @StringRes error: Int?,
    keyboardType: KeyboardType,
    imeAction: ImeAction,
    modifier: Modifier = Modifier,
    @StringRes supportingHint: Int? = null,
    enabled: Boolean = true,
) {
    OutlinedTextField(
        value = value,
        onValueChange = onValueChange,
        label = { Text(stringResource(label)) },
        isError = error != null,
        supportingText = (error ?: supportingHint)?.let { { Text(stringResource(it)) } },
        singleLine = true,
        enabled = enabled,
        keyboardOptions = KeyboardOptions(keyboardType = keyboardType, imeAction = imeAction),
        modifier = modifier.fillMaxWidth(),
    )
}

@Composable
fun PasswordField(
    value: String,
    onValueChange: (String) -> Unit,
    visible: Boolean,
    onToggleVisible: () -> Unit,
    @StringRes error: Int?,
    imeAction: ImeAction,
    modifier: Modifier = Modifier,
    @StringRes supportingHint: Int? = null,
    enabled: Boolean = true,
) {
    val toggleDescription = stringResource(if (visible) R.string.cd_hide_password else R.string.cd_show_password)
    OutlinedTextField(
        value = value,
        onValueChange = onValueChange,
        label = { Text(stringResource(R.string.field_password)) },
        isError = error != null,
        supportingText = (error ?: supportingHint)?.let { { Text(stringResource(it)) } },
        singleLine = true,
        enabled = enabled,
        visualTransformation = if (visible) VisualTransformation.None else PasswordVisualTransformation(),
        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password, imeAction = imeAction),
        trailingIcon = {
            // A text toggle instead of an eye icon avoids pulling in the large icons library.
            TextButton(onClick = onToggleVisible, modifier = Modifier.semantics { contentDescription = toggleDescription }) {
                Text(stringResource(if (visible) R.string.password_hide else R.string.password_show))
            }
        },
        modifier = modifier.fillMaxWidth(),
    )
}

/** Primary button that shows a spinner and ignores taps while [loading]. */
@Composable
fun LoadingButton(
    @StringRes text: Int,
    loading: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Button(
        onClick = onClick,
        enabled = !loading,
        modifier = modifier.fillMaxWidth().heightIn(min = 52.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            if (loading) {
                CircularProgressIndicator(
                    modifier = Modifier.size(20.dp),
                    strokeWidth = 2.dp,
                    color = MaterialTheme.colorScheme.onPrimary,
                )
            }
            Text(stringResource(text))
        }
    }
}
