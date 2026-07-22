{{--
    Reusable export dropdown (F12).
    Wajib kirim: $route (nama route export, mis. 'export.students').
    Menyertakan filter aktif dari query string saat ini.
--}}
@php $params = request()->query(); @endphp
<div class="dropdown d-inline-block">
    <button class="btn btn-sm btn-outline-primary shadow-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-download me-1"></i> Export
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li>
            <a class="dropdown-item" href="{{ route($route, array_merge($params, ['format' => 'csv'])) }}">
                <i class="bi bi-file-earmark-spreadsheet text-success me-2"></i> Excel (CSV)
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="{{ route($route, array_merge($params, ['format' => 'pdf'])) }}" target="_blank">
                <i class="bi bi-file-earmark-pdf text-danger me-2"></i> PDF
            </a>
        </li>
    </ul>
</div>
