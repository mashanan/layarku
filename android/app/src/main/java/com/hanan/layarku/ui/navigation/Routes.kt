package com.hanan.layarku.ui.navigation

import kotlinx.serialization.Serializable

/*
 * Type-safe routes: each destination is a @Serializable type, so arguments
 * (added from phase 4, e.g. MovieDetail(movieId)) are checked by the compiler
 * instead of being glued into route strings.
 */

@Serializable data object LoginRoute

@Serializable data object RegisterRoute

@Serializable data object HomeRoute
