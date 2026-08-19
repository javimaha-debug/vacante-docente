import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../lib/api';

const TYPE_LABELS = {
    video: { label: 'Videos', icon: '🎬' },
    article: { label: 'Artículos', icon: '📰' },
    podcast: { label: 'Podcasts', icon: '🎙️' },
    reference: { label: 'Referencias', icon: '📖' },
    interactive: { label: 'Interactivo', icon: '🎮' },
};

function ResourceCard({ resource, onToggleFavorite }) {
    const info = TYPE_LABELS[resource.type] ?? { label: resource.type, icon: '🔗' };
    return (
        <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 space-y-2">
            <div className="flex items-start justify-between gap-2">
                <div className="flex items-center gap-2 flex-1 min-w-0">
                    <span className="text-xl flex-shrink-0">{resource.icon_emoji ?? info.icon}</span>
                    <div className="min-w-0">
                        <p className="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{resource.title}</p>
                        <p className="text-xs text-gray-500 dark:text-gray-400">{info.label} · {resource.source}</p>
                    </div>
                </div>
                <button
                    onClick={() => onToggleFavorite(resource.id)}
                    className={`flex-shrink-0 text-lg transition-transform hover:scale-110 ${resource.is_favorite ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600 hover:text-amber-300'}`}
                    title={resource.is_favorite ? 'Quitar favorito' : 'Añadir favorito'}
                >
                    ★
                </button>
            </div>
            <p className="text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{resource.description}</p>
            <div className="flex items-center justify-between">
                {resource.duration_minutes && (
                    <span className="text-xs text-gray-400 dark:text-gray-500">⏱ {resource.duration_minutes} min</span>
                )}
                <a
                    href={resource.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-xs font-semibold text-brand-600 dark:text-brand-400 hover:underline ml-auto"
                >
                    Abrir →
                </a>
            </div>
        </div>
    );
}

export default function ResourcesModal({ onClose }) {
    const [activeType, setActiveType] = useState('all');
    const queryClient = useQueryClient();

    const { data, isLoading } = useQuery({
        queryKey: ['b2-resources', activeType],
        queryFn: async () => {
            const params = activeType !== 'all' ? { type: activeType } : {};
            const res = await api.get('/b2/resources', { params });
            return res.data;
        },
        staleTime: 30000,
    });

    const favoriteMutation = useMutation({
        mutationFn: async (resourceId) => {
            const res = await api.post(`/b2/resources/${resourceId}/favorite`);
            return { resourceId, isFavorite: res.data.is_favorite };
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['b2-resources'] });
        },
    });

    const resources = data?.resources ?? [];
    const types = ['all', ...(data?.types ?? [])];

    return (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
            <div className="bg-white dark:bg-gray-900 w-full sm:max-w-lg sm:rounded-2xl flex flex-col max-h-[90vh] shadow-2xl overflow-hidden">
                {/* Header */}
                <div className="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 className="text-base font-bold text-gray-900 dark:text-gray-100">🌍 Recursos B2</h2>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Type filter */}
                <div className="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex gap-2 overflow-x-auto">
                    {types.map(type => (
                        <button
                            key={type}
                            onClick={() => setActiveType(type)}
                            className={`flex-shrink-0 text-xs px-3 py-1.5 rounded-full font-medium transition-colors ${
                                activeType === type
                                    ? 'bg-brand-600 text-white'
                                    : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'
                            }`}
                        >
                            {type === 'all' ? 'Todos' : (TYPE_LABELS[type]?.label ?? type)}
                        </button>
                    ))}
                </div>

                {/* List */}
                <div className="flex-1 overflow-y-auto p-5 space-y-3">
                    {isLoading ? (
                        <div className="space-y-3 animate-pulse">
                            {[1,2,3].map(i => <div key={i} className="h-24 bg-gray-100 dark:bg-gray-700 rounded-xl" />)}
                        </div>
                    ) : resources.length === 0 ? (
                        <div className="text-center py-10">
                            <p className="text-3xl mb-2">📚</p>
                            <p className="text-sm text-gray-500 dark:text-gray-400">No hay recursos disponibles.</p>
                        </div>
                    ) : (
                        resources.map(resource => (
                            <ResourceCard
                                key={resource.id}
                                resource={resource}
                                onToggleFavorite={(id) => favoriteMutation.mutate(id)}
                            />
                        ))
                    )}
                </div>
            </div>
        </div>
    );
}
