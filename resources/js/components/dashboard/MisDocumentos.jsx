import { useRef, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../lib/api';
import { formatBytes, TYPE_ICON, TYPE_LABEL, STATUS_BADGE, fechaCorta } from '../../lib/documents';

const TYPE_FILTERS = [
    { key: '', label: 'Todos' },
    { key: 'pdf', label: 'PDF' },
    { key: 'word', label: 'Word' },
    { key: 'image', label: 'Imágenes' },
];

const SORTS = {
    recent: (a, b) => new Date(b.created_at) - new Date(a.created_at),
    name: (a, b) => a.name.localeCompare(b.name),
    size: (a, b) => (b.size_bytes ?? 0) - (a.size_bytes ?? 0),
};

export default function MisDocumentos() {
    const qc = useQueryClient();
    const [folderId, setFolderId] = useState(null); // null = all; 'root' = sin carpeta
    const [search, setSearch] = useState('');
    const [typeFilter, setTypeFilter] = useState('');
    const [sort, setSort] = useState('recent');
    const [grid, setGrid] = useState(true);
    const [selected, setSelected] = useState(null); // open doc in side panel
    const [dragId, setDragId] = useState(null);

    const docsQuery = useQuery({
        queryKey: ['documents'],
        queryFn: async () => (await api.get('/documents')).data,
        refetchInterval: (q) => (q.state.data?.data ?? []).some((d) => ['pending', 'processing'].includes(d.processing_status)) ? 4000 : false,
    });
    const foldersQuery = useQuery({
        queryKey: ['folders'],
        queryFn: async () => (await api.get('/folders')).data,
    });
    const integrationsQuery = useQuery({
        queryKey: ['integrations-status'],
        queryFn: async () => (await api.get('/integrations/status')).data,
    });

    const allDocs = docsQuery.data?.data ?? [];
    const used = docsQuery.data?.storage_used_bytes ?? 0;
    const limit = docsQuery.data?.storage_limit_bytes ?? 1;
    const pct = Math.min(100, Math.round((used / limit) * 100));

    const move = useMutation({
        mutationFn: ({ id, folder_id }) => api.post(`/documents/${id}/move`, { folder_id }),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['documents'] }),
    });

    const docs = allDocs
        .filter((d) => (folderId === null ? true : folderId === 'root' ? !d.folder_id : d.folder_id === folderId))
        .filter((d) => (typeFilter ? d.type === typeFilter : true))
        .filter((d) => d.name.toLowerCase().includes(search.toLowerCase()))
        .sort(SORTS[sort]);

    const onDropToFolder = (targetFolderId) => {
        if (dragId) {
            move.mutate({ id: dragId, folder_id: targetFolderId });
            setDragId(null);
        }
    };

    return (
        <div className="mx-auto flex max-w-6xl flex-col gap-4 lg:flex-row">
            {/* Sidebar */}
            <aside className="w-full shrink-0 space-y-4 lg:w-60">
                <div className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <h1 className="text-base font-bold text-slate-800">Mis documentos</h1>
                    <p className="mt-1 text-xs text-slate-500">{formatBytes(used)} de {formatBytes(limit)}</p>
                    <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                        <div className={`h-full rounded-full ${pct >= 100 ? 'bg-rose-500' : pct >= 80 ? 'bg-amber-500' : 'bg-brand-500'}`} style={{ width: `${pct}%` }} />
                    </div>
                    {pct >= 80 && (
                        <p className={`mt-1 text-[11px] ${pct >= 100 ? 'text-rose-600' : 'text-amber-600'}`}>
                            {pct >= 100 ? 'Almacenamiento lleno' : 'Te queda poco espacio'}
                        </p>
                    )}
                </div>

                <FolderTree
                    folders={foldersQuery.data?.data ?? []}
                    activeId={folderId}
                    onSelect={setFolderId}
                    onDropDoc={onDropToFolder}
                    onCreated={() => qc.invalidateQueries({ queryKey: ['folders'] })}
                />

                <IntegrationBadges status={integrationsQuery.data} />
            </aside>

            {/* Main */}
            <main className="min-w-0 flex-1 space-y-4">
                <UploadZone
                    onUploaded={() => { docsQuery.refetch(); }}
                    folderId={typeof folderId === 'number' ? folderId : null}
                    full={pct >= 100}
                />

                <div className="flex flex-wrap items-center gap-2">
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar…"
                        className="min-w-[12rem] flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none"
                    />
                    <select value={typeFilter} onChange={(e) => setTypeFilter(e.target.value)} className="rounded-lg border border-slate-200 px-2 py-2 text-sm">
                        {TYPE_FILTERS.map((t) => <option key={t.key} value={t.key}>{t.label}</option>)}
                    </select>
                    <select value={sort} onChange={(e) => setSort(e.target.value)} className="rounded-lg border border-slate-200 px-2 py-2 text-sm">
                        <option value="recent">Recientes</option>
                        <option value="name">Nombre</option>
                        <option value="size">Tamaño</option>
                    </select>
                    <div className="flex overflow-hidden rounded-lg border border-slate-200">
                        <button onClick={() => setGrid(true)} className={`px-3 py-2 text-sm ${grid ? 'bg-brand-600 text-white' : 'text-slate-500'}`}>▦</button>
                        <button onClick={() => setGrid(false)} className={`px-3 py-2 text-sm ${!grid ? 'bg-brand-600 text-white' : 'text-slate-500'}`}>☰</button>
                    </div>
                </div>

                {docsQuery.isLoading ? (
                    <p className="text-sm text-slate-400">Cargando…</p>
                ) : docs.length === 0 ? (
                    <EmptyState hasAny={allDocs.length > 0} />
                ) : grid ? (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        {docs.map((d) => (
                            <DocCard key={d.id} doc={d} onOpen={() => setSelected(d)} onDragStart={() => setDragId(d.id)} />
                        ))}
                    </div>
                ) : (
                    <DocTable docs={docs} onOpen={setSelected} onDragStart={setDragId} />
                )}
            </main>

            {/* Right panel */}
            {selected && (
                <DocPanel
                    docId={selected.id}
                    folders={foldersQuery.data?.data ?? []}
                    onClose={() => setSelected(null)}
                    onChanged={() => { qc.invalidateQueries({ queryKey: ['documents'] }); }}
                    onDeleted={() => { setSelected(null); qc.invalidateQueries({ queryKey: ['documents'] }); }}
                />
            )}
        </div>
    );
}

function FolderTree({ folders, activeId, onSelect, onDropDoc, onCreated }) {
    const [creating, setCreating] = useState(false);
    const [name, setName] = useState('');
    const create = useMutation({
        mutationFn: () => api.post('/folders', { name: name.trim() }),
        onSuccess: () => { setName(''); setCreating(false); onCreated(); },
    });

    const Row = ({ f, depth = 0 }) => (
        <>
            <button
                onClick={() => onSelect(f.id)}
                onDragOver={(e) => e.preventDefault()}
                onDrop={() => onDropDoc(f.id)}
                style={{ paddingLeft: `${8 + depth * 14}px` }}
                className={`flex w-full items-center gap-2 rounded-lg py-1.5 pr-2 text-left text-sm ${activeId === f.id ? 'bg-brand-50 font-semibold text-brand-700' : 'text-slate-600 hover:bg-slate-50'}`}
            >
                <span className="h-2.5 w-2.5 shrink-0 rounded-full" style={{ backgroundColor: f.color || '#94a3b8' }} />
                <span className="flex-1 truncate">{f.name}</span>
                <span className="text-xs text-slate-400">{f.documents_count}</span>
            </button>
            {(f.children ?? []).map((c) => <Row key={c.id} f={c} depth={depth + 1} />)}
        </>
    );

    return (
        <div className="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
            <div className="space-y-0.5">
                <button onClick={() => onSelect(null)} className={`flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm ${activeId === null ? 'bg-brand-50 font-semibold text-brand-700' : 'text-slate-600 hover:bg-slate-50'}`}>📁 Todos</button>
                <button onClick={() => onSelect('root')} onDragOver={(e) => e.preventDefault()} onDrop={() => onDropDoc(null)} className={`flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm ${activeId === 'root' ? 'bg-brand-50 font-semibold text-brand-700' : 'text-slate-600 hover:bg-slate-50'}`}>📂 Sin carpeta</button>
                {folders.map((f) => <Row key={f.id} f={f} />)}
            </div>

            {creating ? (
                <div className="mt-2 flex gap-1">
                    <input autoFocus value={name} onChange={(e) => setName(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && name.trim() && create.mutate()} placeholder="Nombre" className="w-full rounded border border-slate-200 px-2 py-1 text-sm" />
                    <button onClick={() => create.mutate()} disabled={!name.trim()} className="rounded bg-brand-600 px-2 text-sm text-white disabled:opacity-50">✓</button>
                </div>
            ) : (
                <button onClick={() => setCreating(true)} className="mt-2 w-full rounded-lg border border-dashed border-slate-300 py-1.5 text-xs font-medium text-slate-500 hover:bg-slate-50">+ Nueva carpeta</button>
            )}

            <div className="mt-3 border-t border-slate-100 pt-2">
                <p className="px-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Por tema</p>
                <p className="px-2 py-1 text-xs text-slate-400">Disponible al activar tu preparación de oposición.</p>
            </div>
        </div>
    );
}

function IntegrationBadges({ status }) {
    const connect = async (provider) => {
        try {
            const path = provider === 'google_drive' ? '/integrations/google-drive/connect' : '/integrations/microsoft/connect';
            const { data } = await api.get(path);
            if (data?.url) window.location.assign(data.url);
        } catch (e) {
            alert(e?.friendlyMessage ?? 'No se pudo conectar.');
        }
    };

    const Item = ({ provider, label, icon, connected }) => (
        <button onClick={() => !connected && connect(provider)} className="flex w-full items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-left text-sm hover:bg-slate-50">
            <span>{icon}</span>
            <span className="flex-1 text-slate-700">{label}</span>
            <span className={`text-xs font-semibold ${connected ? 'text-emerald-600' : 'text-brand-600'}`}>{connected ? 'Conectado' : 'Conectar'}</span>
        </button>
    );

    return (
        <div className="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
            <p className="mb-2 px-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Importar de</p>
            <div className="space-y-1.5">
                <Item provider="google_drive" label="Google Drive" icon="🟢" connected={status?.google_drive} />
                <Item provider="microsoft_365" label="Microsoft 365" icon="🔵" connected={status?.microsoft_365} />
            </div>
        </div>
    );
}

function UploadZone({ onUploaded, folderId, full }) {
    const inputRef = useRef(null);
    const [progress, setProgress] = useState(null);
    const [error, setError] = useState(null);
    const [dragOver, setDragOver] = useState(false);

    const send = async (files) => {
        if (!files?.length) return;
        if (full) { setError('Has alcanzado el límite de almacenamiento.'); return; }
        setError(null);
        const fd = new FormData();
        [...files].forEach((f) => fd.append('files[]', f));
        if (folderId) fd.append('folder_id', folderId);
        try {
            await api.post('/documents/upload', fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: (e) => setProgress(e.total ? Math.round((e.loaded / e.total) * 100) : null),
            });
            setProgress(null);
            onUploaded();
        } catch (e) {
            setProgress(null);
            setError(e?.friendlyMessage ?? 'No se pudo subir.');
        }
    };

    return (
        <div
            onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
            onDragLeave={() => setDragOver(false)}
            onDrop={(e) => { e.preventDefault(); setDragOver(false); send(e.dataTransfer.files); }}
            className={`rounded-2xl border-2 border-dashed p-4 text-center transition ${dragOver ? 'border-brand-400 bg-brand-50' : 'border-slate-300 bg-white'}`}
        >
            <input ref={inputRef} type="file" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" className="hidden" onChange={(e) => send(e.target.files)} />
            <p className="text-sm text-slate-500">
                Arrastra archivos aquí o{' '}
                <button onClick={() => inputRef.current?.click()} className="font-semibold text-brand-600 hover:underline">subir archivos</button>
            </p>
            <p className="mt-0.5 text-[11px] text-slate-400">PDF, Word o imágenes · máx. 50 MB</p>
            {progress !== null && (
                <div className="mx-auto mt-2 h-1.5 w-2/3 overflow-hidden rounded-full bg-slate-100">
                    <div className="h-full bg-brand-500" style={{ width: `${progress}%` }} />
                </div>
            )}
            {error && <p className="mt-1 text-xs text-rose-600">{error}</p>}
        </div>
    );
}

function DocCard({ doc, onOpen, onDragStart }) {
    const badge = STATUS_BADGE[doc.processing_status];
    return (
        <button
            draggable
            onDragStart={onDragStart}
            onClick={onOpen}
            className="group flex flex-col rounded-xl border border-slate-200 bg-white p-3 text-left shadow-sm transition hover:border-brand-300 hover:shadow"
        >
            <div className="flex h-20 items-center justify-center rounded-lg bg-slate-50 text-3xl">
                {doc.has_thumbnail ? <img src={doc.thumbnail_url} alt="" className="h-full w-full rounded-lg object-cover" /> : TYPE_ICON[doc.type]}
            </div>
            <p className="mt-2 line-clamp-2 text-sm font-medium text-slate-700">{doc.name}</p>
            <div className="mt-1 flex items-center justify-between">
                <span className="text-[11px] text-slate-400">{formatBytes(doc.size_bytes)}</span>
                {badge && doc.processing_status !== 'ready' && (
                    <span className={`rounded-full px-1.5 py-0.5 text-[10px] font-semibold ${badge.cls}`}>{badge.label}</span>
                )}
            </div>
        </button>
    );
}

function DocTable({ docs, onOpen, onDragStart }) {
    return (
        <div className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <table className="w-full text-sm">
                <thead className="bg-slate-50 text-left text-xs text-slate-400">
                    <tr><th className="px-3 py-2">Nombre</th><th className="px-3 py-2">Tipo</th><th className="px-3 py-2">Tamaño</th><th className="px-3 py-2">Fecha</th></tr>
                </thead>
                <tbody>
                    {docs.map((d) => (
                        <tr key={d.id} draggable onDragStart={() => onDragStart(d.id)} onClick={() => onOpen(d)} className="cursor-pointer border-t border-slate-100 hover:bg-slate-50">
                            <td className="px-3 py-2"><span className="mr-1">{TYPE_ICON[d.type]}</span>{d.name}</td>
                            <td className="px-3 py-2 text-slate-500">{TYPE_LABEL[d.type]}</td>
                            <td className="px-3 py-2 text-slate-500">{formatBytes(d.size_bytes)}</td>
                            <td className="px-3 py-2 text-slate-500">{fechaCorta(d.created_at)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function DocPanel({ docId, folders, onClose, onChanged, onDeleted }) {
    const qc = useQueryClient();
    const { data: doc, isLoading } = useQuery({
        queryKey: ['document', docId],
        queryFn: async () => (await api.get(`/documents/${docId}`)).data,
    });
    const [name, setName] = useState(null);
    const [notes, setNotes] = useState(null);

    const patch = useMutation({
        mutationFn: (payload) => api.patch(`/documents/${docId}`, payload),
        onSuccess: () => { onChanged(); qc.invalidateQueries({ queryKey: ['document', docId] }); },
    });
    const del = useMutation({
        mutationFn: () => api.delete(`/documents/${docId}`),
        onSuccess: onDeleted,
    });

    const flatFolders = (list, depth = 0, acc = []) => {
        for (const f of list) { acc.push({ id: f.id, name: `${'— '.repeat(depth)}${f.name}` }); flatFolders(f.children ?? [], depth + 1, acc); }
        return acc;
    };

    return (
        <aside className="w-full shrink-0 space-y-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 lg:w-72">
            <div className="flex items-start justify-between">
                <h2 className="text-sm font-bold text-slate-700">Documento</h2>
                <button onClick={onClose} className="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            {isLoading || !doc ? (
                <p className="text-sm text-slate-400">Cargando…</p>
            ) : (
                <>
                    <input
                        value={name ?? doc.name}
                        onChange={(e) => setName(e.target.value)}
                        onBlur={() => name && name !== doc.name && patch.mutate({ name })}
                        className="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm font-medium focus:border-brand-400 focus:outline-none"
                    />
                    <p className="text-xs text-slate-400">{TYPE_LABEL[doc.type]} · {formatBytes(doc.size_bytes)} · {fechaCorta(doc.created_at)}</p>

                    <a href={doc.view_url} target="_blank" rel="noreferrer" className="block rounded-lg bg-brand-600 px-3 py-2 text-center text-sm font-semibold text-white hover:bg-brand-700">Ver documento</a>

                    <label className="block">
                        <span className="text-xs font-medium text-slate-400">Carpeta</span>
                        <select value={doc.folder_id ?? ''} onChange={(e) => patch.mutate({ folder_id: e.target.value ? Number(e.target.value) : null })} className="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                            <option value="">Sin carpeta</option>
                            {flatFolders(folders).map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
                        </select>
                    </label>

                    <label className="block">
                        <span className="text-xs font-medium text-slate-400">Notas</span>
                        <textarea
                            value={notes ?? doc.notes ?? ''}
                            onChange={(e) => setNotes(e.target.value)}
                            onBlur={() => notes !== null && patch.mutate({ notes })}
                            rows={3}
                            className="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm focus:border-brand-400 focus:outline-none"
                        />
                    </label>

                    <DocTags doc={doc} onChanged={() => qc.invalidateQueries({ queryKey: ['document', docId] })} />

                    <button
                        onClick={() => confirm('¿Eliminar este documento?') && del.mutate()}
                        className="w-full rounded-lg border border-rose-200 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50"
                    >
                        Eliminar
                    </button>
                </>
            )}
        </aside>
    );
}

function DocTags({ doc, onChanged }) {
    const qc = useQueryClient();
    const [adding, setAdding] = useState('');
    const { data: tags } = useQuery({ queryKey: ['document-tags'], queryFn: async () => (await api.get('/document-tags')).data });
    const all = tags?.data ?? [];
    const current = doc.tags ?? [];

    const setTags = useMutation({
        mutationFn: (ids) => api.patch(`/documents/${doc.id}`, { tag_ids: ids }),
        onSuccess: onChanged,
    });
    const createTag = useMutation({
        mutationFn: (name) => api.post('/document-tags', { name }),
        onSuccess: (res) => {
            qc.invalidateQueries({ queryKey: ['document-tags'] });
            setTags.mutate([...current.map((t) => t.id), res.data.id]);
            setAdding('');
        },
    });

    const toggle = (id) => {
        const ids = current.some((t) => t.id === id) ? current.filter((t) => t.id !== id).map((t) => t.id) : [...current.map((t) => t.id), id];
        setTags.mutate(ids);
    };

    return (
        <div>
            <span className="text-xs font-medium text-slate-400">Etiquetas</span>
            <div className="mt-1 flex flex-wrap gap-1">
                {current.map((t) => (
                    <button key={t.id} onClick={() => toggle(t.id)} className="rounded-full bg-brand-100 px-2 py-0.5 text-xs font-medium text-brand-700">{t.name} ✕</button>
                ))}
            </div>
            <div className="mt-1 flex flex-wrap gap-1">
                {all.filter((t) => !current.some((c) => c.id === t.id)).map((t) => (
                    <button key={t.id} onClick={() => toggle(t.id)} className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500 hover:bg-slate-200">+ {t.name}</button>
                ))}
            </div>
            <div className="mt-1 flex gap-1">
                <input value={adding} onChange={(e) => setAdding(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && adding.trim() && createTag.mutate(adding.trim())} placeholder="Nueva etiqueta" className="w-full rounded border border-slate-200 px-2 py-1 text-xs" />
            </div>
        </div>
    );
}

function EmptyState({ hasAny }) {
    return (
        <div className="rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-200">
            <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-brand-100 text-3xl">📂</div>
            <h2 className="mt-3 text-lg font-bold text-slate-800">{hasAny ? 'Nada en esta vista' : 'Aún no tienes documentos'}</h2>
            <p className="mt-1 text-sm text-slate-500">{hasAny ? 'Prueba a quitar filtros o elegir otra carpeta.' : 'Arrastra y suelta tus apuntes arriba para empezar.'}</p>
            {!hasAny && <p className="mt-1 text-xs text-slate-400">O impórtalos desde Google Drive / Microsoft 365.</p>}
        </div>
    );
}
