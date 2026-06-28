import { useAuth } from './useAuth';

/**
 * Plan feature gating. Returns the feature map from the authenticated user
 * (see GET /user/me → `features`) plus a `can(feature)` helper.
 *
 * Usage:
 *   const { features, can } = useFeatures();
 *   if (can('filtros_avanzados')) { ... }
 */
export function useFeatures() {
    const { user } = useAuth();
    const features = user?.features ?? {};

    const can = (feature) => features?.[feature] === true;

    return {
        features,
        can,
        plan: user?.plan ?? 'free',
        planLabel: user?.plan_label ?? 'Gratis',
        isPaid: Boolean(user?.is_paid),
    };
}
