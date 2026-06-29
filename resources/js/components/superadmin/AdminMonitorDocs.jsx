import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../lib/api';
import { SkeletonRows, ErrorState, Badge } from './ui';

const TYPE_LABELS = {
    listado_provisional: 'Provisional',
    listado_definitivo: 'Definitivo',
    vacantes: 'Vacantes',
    resolucion: 'Resolución',
    convocatoria: 'Convocatoria',
    otro: 'Otro',
};

const TYPE_TONE = {
    listado_provisional: 'blue',
    listado_definitivo: 'green',
    vacantes: 'amber',
    resolucion: 'slate',
    convocatoria: 'blue',
    otro: 'slate',
};

function fecha(d) {
    if (!d) return '—';
    try { return new Date(d).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' }); }
    catch { return d; }
}

const TABS = [
    { key: 'pendientes', label: 'Pendientes' },
    { key: 'publicados', label: 'Publicados' },
    { key: 'fuentes', label: 'Fuentes' },
    { key: 'subida', label: 'Subida manual' },
];

export default function AdminMonitorDocs() {
    const [tab, setTab] = useState('pendientes');

    return (
        <div className="space-y-5">
            <div>
                <h1 className="text-xl font-bold text-white">Monitor de documentos</h1>
                <p className="text-sm text-slate-400">Documentos detectados en fuentes oficiales y sindicatos.</p>
            </div>

            <div className="flex flex-wrap gap-1 border-b border-slate-800">
                {TABS.map((t) => (
                    <button
                        key={t.key}
                        onClick={() => setTab(t.key)}
                        className={`-mb-px border-b-2 px-3 py-2 text-sm font-medium transition ${
                            tab === t.key
                                ? 'border-sky-500 text-white'
                                : 'border-transparent text-slate-400 hover:text-slate-200'
                        }`}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {tab === 'pendientes' && <PendientesTab />}
            {tab === 'publicados' && <PublicadosTab />}
            {tab === 'fuentes' && <FuentesTab />}
            {tab === 'subida' && <SubidaTab onDone={() => setTab('pendientes')} />}
        </div>
    );
}

function DocCard({ doc, children }) {
    return (
        <div className="rounded-xl border border-slate-700/60 bg-slate-800/50 p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="font-semibold text-slate-100">{doc.title}</p>
                    <p className="mt-0.5 text-xs text-slate-400">
                        {doc.source?.name ?? 'Subida manual'} · detectado {fecha(doc.detected_at)}
                    </p>
                </div>
                <Badge tone={TYPE_TONE[doc.document_type]}>{TYPE_LABELS[doc.document_type] ?? doc.document_type}</Badge>
            </div>
            <div className="mt-2 flex flex-wrap gap-3 text-xs">
                {doc.source_url && (
                    <a href={doc.source_url} target="_blank" rel="noreferrer" className="text-sky-400 hover:underline">↗ Fuente</a>
                )}
                {(doc.pdf_url || doc.pdf_path) && (
                    <a href={`/api/v1/superadmin/documents/${doc.id}/pdf`} target="_blank" rel="noreferrer" className="text-sky-400 hover:underline">📄 PDF</a>
                )}
            </div>
            {doc.superadmin_notes && <p className="mt-2 rounded bg-slate-900/60 px-2 py-1 text-xs text-slate-400">{doc.superadmin_notes}</p>}
            {children}
        </div>
    );
}

function NotesModal({ title, confirmLabel, tone = 'sky', onConfirm, onClose, extra }) {
    const [notes, setNotes] = useState('');
    const [opts, setOpts] = useState({ confirm_event: false, event_visibility: 'public' });
    const toneCls = tone === 'rose' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-sky-600 hover:bg-sky-700';

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" onClick={onClose}>
            <div className="w-full max-w-md rounded-xl border border-slate-700 bg-slate-900 p-5" onClick={(e) => e.stopPropagation()}>
                <h3 className="font-semibold text-white">{title}</h3>
                <textarea
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    rows={3}
                    placeholder="Notas (opcional)…"
                    className="mt-3 w-full rounded-lg border border-slate-700 bg-slate-800 p-2 text-sm text-slate-100 focus:border-sky-500 focus:outline-none"
                />
                {extra === 'publish' && (
                    <label className="mt-3 flex items-center gap-2 text-sm text-slate-300">
                        <input
                            type="checkbox"
                            checked={opts.confirm_event}
                            onChange={(e) => setOpts((o) => ({ ...o, confirm_event: e.target.checked }))}
                        />
                        Confirmar el evento de calendario vinculado
                    </label>
                )}
                <div className="mt-4 flex justify-end gap-2">
                    <button onClick={onClose} className="rounded-lg px-3 py-1.5 text-sm text-slate-400 hover:bg-slate-800">Cancelar</button>
                    <button
                        onClick={() => onConfirm({ notes, ...opts })}
                        className={`rounded-lg px-4 py-1.5 text-sm font-semibold text-white ${toneCls}`}
                    >
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}

function PendientesTab() {
    const qc = useQueryClient();
    const [modal, setModal] = useState(null); // {doc, action}
    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['admin', 'documents', 'pendientes'],
        queryFn: async () => (await api.get('/superadmin/documents', { params: { status: 'pending' } })).data,
    });
    // Validated-but-not-published documents also need the publish action here.
    const { data: validated } = useQuery({
        queryKey: ['admin', 'documents', 'validated'],
        queryFn: async () => (await api.get('/superadmin/documents', { params: { status: 'validated' } })).data,
    });

    const act = useMutation({
        mutationFn: ({ id, action, payload }) => api.post(`/superadmin/documents/${id}/${action}`, payload),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['admin', 'documents'] });
            setModal(null);
        },
    });

    if (isLoading) return <SkeletonRows rows={3} className="h-24" />;
    if (isError) return <ErrorState error={error} onRetry={refetch} />;

    const pending = data?.data ?? [];
    const toPublish = validated?.data ?? [];

    return (
        <div className="space-y-4">
            {pending.length === 0 && toPublish.length === 0 && (
                <p className="rounded-xl border border-slate-800 bg-slate-800/30 p-6 text-center text-sm text-slate-400">
                    No hay documentos pendientes. 🎉
                </p>
            )}

            {pending.map((doc) => (
                <DocCard key={doc.id} doc={doc}>
                    <div className="mt-3 flex gap-2">
                        <button onClick={() => setModal({ doc, action: 'validate' })} className="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700">✅ Validar</button>
                        <button onClick={() => setModal({ doc, action: 'reject' })} className="rounded-lg bg-rose-600/80 px-3 py-1.5 text-sm font-semibold text-white hover:bg-rose-700">❌ Rechazar</button>
                    </div>
                </DocCard>
            ))}

            {toPublish.length > 0 && (
                <>
                    <h2 className="pt-2 text-sm font-semibold uppercase tracking-wide text-slate-500">Validados — listos para publicar</h2>
                    {toPublish.map((doc) => (
                        <DocCard key={doc.id} doc={doc}>
                            <div className="mt-3 flex gap-2">
                                <button onClick={() => setModal({ doc, action: 'publish' })} className="rounded-lg bg-sky-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-sky-700">🚀 Publicar</button>
                                <button onClick={() => setModal({ doc, action: 'reject' })} className="rounded-lg px-3 py-1.5 text-sm font-medium text-slate-400 hover:bg-slate-800">Rechazar</button>
                            </div>
                        </DocCard>
                    ))}
                </>
            )}

            {modal && (
                <NotesModal
                    title={modal.action === 'validate' ? 'Validar documento' : modal.action === 'reject' ? 'Rechazar documento' : 'Publicar documento'}
                    confirmLabel={modal.action === 'validate' ? 'Validar' : modal.action === 'reject' ? 'Rechazar' : 'Publicar'}
                    tone={modal.action === 'reject' ? 'rose' : 'sky'}
                    extra={modal.action === 'publish' ? 'publish' : null}
                    onClose={() => setModal(null)}
                    onConfirm={(payload) => act.mutate({ id: modal.doc.id, action: modal.action, payload })}
                />
            )}
        </div>
    );
}

function PublicadosTab() {
    const [q, setQ] = useState('');
    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['admin', 'documents', 'publicados'],
        queryFn: async () => (await api.get('/superadmin/documents', { params: { status: 'published' } })).data,
    });

    if (isLoading) return <SkeletonRows rows={4} />;
    if (isError) return <ErrorState error={error} onRetry={refetch} />;

    const docs = (data?.data ?? []).filter((d) => d.title.toLowerCase().includes(q.toLowerCase()));

    return (
        <div className="space-y-3">
            <input
                value={q}
                onChange={(e) => setQ(e.target.value)}
                placeholder="Buscar…"
                className="w-full max-w-sm rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-sky-500 focus:outline-none"
            />
            {docs.length === 0 && <p className="text-sm text-slate-400">Sin documentos publicados.</p>}
            <div className="overflow-hidden rounded-xl border border-slate-700/60">
                {docs.map((doc) => (
                    <div key={doc.id} className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800 bg-slate-800/40 px-4 py-2.5 last:border-0">
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium text-slate-100">{doc.title}</p>
                            <p className="text-xs text-slate-500">
                                Publicado {fecha(doc.published_at)} · {doc.validator?.name ?? '—'}
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <Badge tone={TYPE_TONE[doc.document_type]}>{TYPE_LABELS[doc.document_type]}</Badge>
                            {(doc.pdf_url || doc.pdf_path) && (
                                <a href={`/api/v1/superadmin/documents/${doc.id}/pdf`} target="_blank" rel="noreferrer" className="text-xs text-sky-400 hover:underline">📄 PDF</a>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function FuentesTab() {
    const qc = useQueryClient();
    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['admin', 'sources'],
        queryFn: async () => (await api.get('/superadmin/sources')).data,
    });

    const toggle = useMutation({
        mutationFn: ({ id, active }) => api.patch(`/superadmin/sources/${id}`, { active }),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['admin', 'sources'] }),
    });
    const check = useMutation({
        mutationFn: (id) => api.post(`/superadmin/sources/${id}/check`),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['admin', 'sources'] });
            qc.invalidateQueries({ queryKey: ['admin', 'documents'] });
        },
    });

    if (isLoading) return <SkeletonRows rows={4} />;
    if (isError) return <ErrorState error={error} onRetry={refetch} />;

    return (
        <div className="space-y-2">
            {(data?.data ?? []).map((s) => (
                <div key={s.id} className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-700/60 bg-slate-800/50 p-3">
                    <div className="min-w-0">
                        <p className="flex items-center gap-2 text-sm font-medium text-slate-100">
                            {s.name}
                            <Badge tone={s.type === 'gva' ? 'blue' : s.type === 'dogv' ? 'amber' : 'slate'}>{s.type}</Badge>
                        </p>
                        <p className="truncate text-xs text-slate-500">{s.url}</p>
                        <p className="text-xs text-slate-500">
                            {s.documents_count ?? 0} docs · {s.last_checked_at ? `comprobado ${fecha(s.last_checked_at)}` : 'nunca comprobado'}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => check.mutate(s.id)}
                            disabled={check.isPending}
                            className="rounded-lg bg-sky-600/80 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700 disabled:opacity-50"
                        >
                            {check.isPending && check.variables === s.id ? 'Comprobando…' : 'Comprobar ahora'}
                        </button>
                        <button
                            onClick={() => toggle.mutate({ id: s.id, active: !s.active })}
                            className={`rounded-lg px-3 py-1.5 text-xs font-semibold ${s.active ? 'bg-emerald-600/20 text-emerald-300' : 'bg-slate-700 text-slate-300'}`}
                        >
                            {s.active ? 'Activa' : 'Inactiva'}
                        </button>
                    </div>
                </div>
            ))}
        </div>
    );
}

function SubidaTab({ onDone }) {
    const qc = useQueryClient();
    const [form, setForm] = useState({ document_type: 'listado_provisional', source_id: '', title: '', notes: '', publish_now: false });
    const [file, setFile] = useState(null);
    const { data: sources } = useQuery({
        queryKey: ['admin', 'sources'],
        queryFn: async () => (await api.get('/superadmin/sources')).data,
    });

    const upload = useMutation({
        mutationFn: () => {
            const fd = new FormData();
            fd.append('document_type', form.document_type);
            fd.append('title', form.title);
            if (form.source_id) fd.append('source_id', form.source_id);
            if (form.notes) fd.append('notes', form.notes);
            if (form.publish_now) fd.append('publish_now', '1');
            fd.append('pdf', file);
            return api.post('/superadmin/documents/upload', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['admin', 'documents'] });
            onDone?.();
        },
    });

    const canSubmit = form.title.trim() && file;

    return (
        <div className="max-w-lg space-y-3">
            <Field label="Tipo de documento">
                <select value={form.document_type} onChange={(e) => setForm((f) => ({ ...f, document_type: e.target.value }))} className={inputCls}>
                    {Object.entries(TYPE_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                </select>
            </Field>
            <Field label="Fuente (opcional)">
                <select value={form.source_id} onChange={(e) => setForm((f) => ({ ...f, source_id: e.target.value }))} className={inputCls}>
                    <option value="">—</option>
                    {(sources?.data ?? []).map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                </select>
            </Field>
            <Field label="Título">
                <input value={form.title} onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))} className={inputCls} />
            </Field>
            <Field label="Notas (opcional)">
                <textarea value={form.notes} onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))} rows={2} className={inputCls} />
            </Field>
            <Field label="PDF">
                <input type="file" accept="application/pdf" onChange={(e) => setFile(e.target.files?.[0] ?? null)} className="text-sm text-slate-300" />
            </Field>
            <label className="flex items-center gap-2 text-sm text-slate-300">
                <input type="checkbox" checked={form.publish_now} onChange={(e) => setForm((f) => ({ ...f, publish_now: e.target.checked }))} />
                Publicar inmediatamente
            </label>
            <div className="flex items-center gap-3">
                <button
                    onClick={() => upload.mutate()}
                    disabled={!canSubmit || upload.isPending}
                    className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50"
                >
                    {upload.isPending ? 'Subiendo…' : 'Subir documento'}
                </button>
                {upload.isError && <span className="text-sm text-rose-400">{upload.error?.friendlyMessage}</span>}
                {upload.isSuccess && <span className="text-sm text-emerald-400">Subido ✓</span>}
            </div>
        </div>
    );
}

const inputCls = 'w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-sky-500 focus:outline-none';

function Field({ label, children }) {
    return (
        <label className="block">
            <span className="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-400">{label}</span>
            {children}
        </label>
    );
}
