package com.hanan.layarku.data.di

import javax.inject.Qualifier

/** Dispatcher for network and disk work. Injected so tests can swap in a test dispatcher. */
@Qualifier
@Retention(AnnotationRetention.BINARY)
annotation class IoDispatcher

/** Human-readable device name sent at login, shown in the server's token list. */
@Qualifier
@Retention(AnnotationRetention.BINARY)
annotation class DeviceName
