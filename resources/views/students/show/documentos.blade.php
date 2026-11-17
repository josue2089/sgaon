{{-- Ficha del alumno: pestaña "documentos". Separada de students/show en #454. --}}
<section class="portal-tab-panel student-panel" id="panel-documentos" data-tab-panel="documentos" role="tabpanel" aria-labelledby="tab-documentos">
<div class="card table-card">
    <div class="section-head">
        <h2 class="section-title section-title-md">Adjuntos del alumno</h2>
        <div class="entity-sub">{{ $student->attachments->count() }} archivo(s)</div>
    </div>
    <form method="POST" action="{{ route('students.attachments.store', $student) }}" enctype="multipart/form-data" class="fi-filter-bar">
        @csrf
        <input type="text" name="title" placeholder="Título del documento" required>
        <select class="max-w-[220px]" name="category">
            <option value="general">General</option>
            <option value="identity">Cédula</option>
            <option value="medical">Médico</option>
            <option value="contract">Contrato</option>
            <option value="payment">Pago</option>
        </select>
        <input type="file" name="file" required>
        <button class="btn" type="submit">Cargar adjunto</button>
    </form>
    @if($student->attachments->count() > 0)
        <div class="table-wrap" style="margin-top: 1rem;">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Título</th>
                    <th>Categoría</th>
                    <th>Archivo</th>
                    <th>Tamaño</th>
                    <th>Fecha</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($student->attachments as $attachment)
                    <tr>
                        <td class="table-title">{{ $attachment->title }}</td>
                        <td>{{ \App\Support\StatusLabel::category($attachment->category) }}</td>
                        <td>{{ $attachment->original_name }}</td>
                        <td>{{ $attachment->file_size_label }}</td>
                        <td>{{ $attachment->created_at?->format('d/m/Y') ?? 'N/D' }}</td>
                        <td class="table-actions">
                            <a href="{{ route('students.attachments.download', [$student, $attachment]) }}">Descargar</a>
                            <form method="POST" action="{{ route('students.attachments.destroy', [$student, $attachment]) }}" data-confirm="¿Eliminar este adjunto?" data-confirm-danger>
                                @csrf
                                @method('DELETE')
                                <button class="btn-link-danger" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="empty-state">No hay adjuntos cargados.</div>
    @endif
</div>
</section>
