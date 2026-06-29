import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../lib/api';

const CATEGORIES = [
    { value: 'oficial', label: 'Oficial' },
    { value: 'sindicato', label: 'Sindicato' },
    { value: 'otro', label: 'Otro' },
];

const EMPTY = { title: '', description: '', url: '', category: 'oficial', icon: '🔗', position: 0, active: true };

export default function AdminRecursos() {
    const qc = useQueryClient();
    const [editing, setEditing] = useState(null); // null = list, 'new' = form, object = edit existing
    const [form, setForm] = useState(EMPTY);

    const { data, isLoading } = useQuery({
        queryKey: ['admin-recursos'],
        queryFn: async () => (await api.get('/superadmin/recursos')).data,
    });

    const links = data?.data ?? (Array.isArray(data) ? data : []);

    const saveMut = useMutation({
        mutationFn: async (d) => {
            if (editing === 'new') return (await api.post('/superadmin/recursos', d)).data;
            return (await api.put(`/superadmin/recursos/${editing.id}`, d)).data;
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['admin-recursos'] });
            setEditing(null);
        },
    });

    const deleteMut = useMutation({
        mutationFn: async (id) => (await api.delete(`/superadmin/recursos/${id}`)).data,
        onSuccess: () => qc.invalidateQueries({ queryKey: ['admin-recursos'] }),
    });

    const openNew = () => { setForm(EMPTY); setEditing('new'); };
    const openEdit = (l) => { setForm({ ...l, active: Boolean(l.active) }); setEditing(l); };
    const f = (k, v) => setForm((prev) => ({ ...prev, [k]: v }));

    if (editing) {
        return (
            <div className="max-w-lg space-y-4">
                <div className="flex items-center justify-between">
                    <h2 className="font-bold text-white">{editing === 'new' ? 'Nuevo enlace' : 'Editar enlace'}</h2>
                    <button onClick={() => setEditing(null)} className="text-slate-400 hover:text-white text-xl leading-none">✕</button>
                </div>
                <div className="space-y-3">
                    {[
                        ['title', 'Título', 'text'],
                        ['url', 'URL', 'url'],
                        ['description', 'Descripción', 'text'],
                        ['icon', 'Icono (emoji)', 'text'],
                    ].map(([key, label, type]) => (
                        <div key={key}>
                            <label className="block text-xs font-medium text-slate-400 mb-1">{label}</label>
                            <input
                                type={type}
                                value={form[key] ?? ''}
                                onChange={(e) => f(key, e.target.value)}
                                className="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-white focus:border-brand-400 focus:outline-none"
                            />
                        </div>
                    ))}
                    <div>
                        <label className="block text-xs font-medium text-slate-400 mb-1">Categoría</label>
                        <select
                            value={form.category}
                            onChange={(e) => f('category', e.target.value)}
                            className="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-white"
                        >
                            {CATEGORIES.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-slate-400 mb-1">Posición (orden)</label>
                        <input
                            type="number"
                            value={form.position}
                            onChange={(e) => f('position', Number(e.target.value))}
                            className="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-white"
                        />
                    </div>
                    <label className="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={Boolean(form.active)}
                            onChange={(e) => f('active', e.target.checked)}
                            className="rounded border-slate-500 text-brand-600"
                        />
                        Activo (visible en la app)
                    </label>
                </div>
                <div className="flex gap-2">
                    <button
                        onClick={() => saveMut.mutate(form)}
                        disabled={saveMut.isPending}
                        className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60"
                    >
                        {saveMut.isPending ? 'Guardando…' : 'Guardar'}
                    </button>
                    <button
                        onClick={() => setEditing(null)}
                        className="rounded-lg px-4 py-2 text-sm text-slate-400 hover:bg-slate-700"
                    >
                        Cancelar
                    </button>
                </div>
                {saveMut.isError && (
                    <p className="text-sm text-rose-400">{saveMut.error?.message ?? 'Error al guardar.'}</p>
                )}
            </div>
        );
    }

    return (
        <div>
            <div className="flex items-center justify-between mb-4">
                <h2 className="font-bold text-white">Recursos ({links.length})</h2>
                <button
                    onClick={openNew}
                    className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700"
                >
                    + Añadir
                </button>
            </div>

            {isLoading ? (
                <p className="text-slate-400 text-sm">Cargando…</p>
            ) : links.length === 0 ? (
                <p className="text-slate-400 text-sm">No hay recursos aún. Ejecuta el seeder o añade uno.</p>
            ) : (
                <div className="space-y-2">
                    {links.map((l) => (
                        <div key={l.id} className="flex items-center gap-3 rounded-lg bg-slate-800 px-3 py-2.5">
                            <span className="text-lg shrink-0">{l.icon}</span>
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-medium text-white truncate">{l.title}</p>
                                <p className="text-xs text-slate-400">
                                    {l.category} · pos {l.position} · {l.active ? '✓ activo' : '✗ inactivo'}
                                </p>
                            </div>
                            <button
                                onClick={() => openEdit(l)}
                                className="text-xs text-slate-400 hover:text-white px-2 py-1 rounded hover:bg-slate-700 shrink-0"
                            >
                                Editar
                            </button>
                            <button
                                onClick={() => { if (window.confirm('¿Eliminar este recurso?')) deleteMut.mutate(l.id); }}
                                className="text-xs text-rose-400 hover:text-rose-300 px-2 py-1 rounded hover:bg-slate-700 shrink-0"
                            >
                                ✕
                            </button>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
