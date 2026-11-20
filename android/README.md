# Layarku Android

Kotlin + Jetpack Compose client (package `com.hanan.layarku`, minSdk 26, targetSdk 37).

## Run against the local backend

1. Start the backend (`php artisan serve` in `backend/`, see [backend/README.md](../backend/README.md)).
2. Choose how the app reaches it:

| Device | Setup |
|---|---|
| Emulator | Nothing to do: debug builds use `http://10.0.2.2:8000/api/` (the host machine as seen from the emulator). |
| Real phone over USB | Add `layarku.apiBaseUrl=http://127.0.0.1:8000/api/` to `android/local.properties` (git-ignored), then run `adb reverse tcp:8000 tcp:8000` after plugging in the phone. The phone's port 8000 is forwarded to the laptop over the cable, so no shared Wi-Fi or firewall change is needed. Run `adb reverse` again after reconnecting. |

3. Build and install: `.\gradlew.bat installDebug` (set `JAVA_HOME` to Android Studio's `jbr` first).

Cleartext HTTP is allowed **only in debug builds** and only for `10.0.2.2`, `localhost` and `127.0.0.1` (`src/debug/res/xml/network_security_config.xml`). Release builds require HTTPS.

Demo accounts: see [backend/README.md](../backend/README.md#demo-accounts).

## Commands (Windows, from `android/`)

| Task | Command |
|---|---|
| Build debug APK | `.\gradlew.bat assembleDebug` |
| Unit tests | `.\gradlew.bat testDebugUnitTest` |
| Lint | `.\gradlew.bat lintDebug` |

## Architecture

```
com.hanan.layarku
├─ domain/   pure Kotlin: models, AppResult/DataError, AuthRepository interface,
│            use cases, AuthValidator (same rules as the backend)
├─ data/     Retrofit API + DTOs, AuthInterceptor, ErrorMapper, ApiCaller,
│            DataStore session storage, repository implementation, Hilt modules
└─ ui/       theme, navigation (type-safe routes), auth (login/register), home,
             components, util (error -> string resource)
```

Dependency rule: `ui` → `domain` ← `data`. `domain` imports no Android or `data` classes.

Key decisions:

- **One error type.** `ApiCaller` runs every request and turns failures into `DataError` using the backend's stable `error.code`. `ui/util/ErrorMessages.kt` is the only place that picks the text the user sees, so raw exception text is never shown.
- **Session expiry in one place.** If the server answers `401 unauthenticated` while we had a token, `ApiCaller` clears the stored session and emits `sessionExpired`. The app returns to the login screen and explains why.
- **Navigation.** Signing in navigates on the screen's success event; signing out or an expired token navigates because the session became null. Both clear the back stack.
- **ViewModels hold string resource ids, not strings,** so they are unit-testable on the JVM and text follows the device language (English default, Bahasa Indonesia in `values-in`).
- **Token storage.** Preferences DataStore in app-private storage, with an in-memory copy for the OkHttp interceptor.
- **Logging.** Debug builds log HTTP at `BASIC` level only (method, URL, status), never bodies, which would contain passwords and tokens.

## Tests

`src/test` (JVM): `AuthValidatorTest`, `ApiCallerTest` (real Retrofit/OkHttp against MockWebServer), `AuthRepositoryImplTest`, `LoginViewModelTest`, `RegisterViewModelTest`.
